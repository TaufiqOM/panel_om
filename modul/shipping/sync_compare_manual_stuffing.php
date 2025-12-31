<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

session_start();
require __DIR__ . '/../../inc/config.php';
require __DIR__ . '/../../inc/config_odoo.php';

$username = $_SESSION['username'] ?? '';

// Ambil data dari POST
$shipping_id = isset($_POST['shipping_id']) ? intval($_POST['shipping_id']) : 0;

if (!$shipping_id) {
    echo json_encode(['success' => false, 'message' => 'Shipping ID tidak lengkap']);
    exit;
}

try {
    error_log("=== START SYNC COMPARE MANUAL STUFFING untuk shipping_id: $shipping_id ===");
    
    // Ambil detail shipping dari local database
    $sql = "SELECT id, name, sheduled_date FROM shipping WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $shipping_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        error_log("Shipping tidak ditemukan untuk shipping_id: $shipping_id");
        echo json_encode(['success' => false, 'message' => 'Shipping tidak ditemukan']);
        exit;
    }
    
    $shipping = $result->fetch_assoc();
    $stmt->close();
    error_log("Shipping ditemukan: " . $shipping['name']);
    
    // Ambil batch dari Odoo
    $batch_name = $shipping['name'];
    $batches = callOdooRead($username, "stock.picking.batch", [["name", "=", $batch_name]], ["picking_ids"]);
    
    if (!$batches || count($batches) == 0) {
        echo json_encode(['success' => false, 'message' => 'Data batch tidak ditemukan di Odoo']);
        exit;
    }
    
    $batch = $batches[0];
    $picking_ids = $batch['picking_ids'] ?? [];
    
    if (empty($picking_ids)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada picking untuk batch ini']);
        exit;
    }
    
    // Ambil pickings dari Odoo
    $pickings = callOdooRead($username, "stock.picking", [["id", "in", $picking_ids]], ["id", "name", "sale_id"]);
    
    if (!$pickings || !is_array($pickings)) {
        echo json_encode(['success' => false, 'message' => 'Data picking tidak ditemukan di Odoo']);
        exit;
    }
    
    $total_updated = 0;
    $total_inserted = 0;
    $errors = [];
    
    // Track quantity per move_id untuk update quantity
    // Key: move_id, Value: jumlah barcode yang berhasil di-insert
    $move_quantity_map = [];
    
    // Track move_id yang perlu di-uncheck picked setelah create move line
    // Key: move_id, Value: true jika perlu di-uncheck
    $moves_to_uncheck_picked = [];
    
    // Track barcode yang sudah di-insert untuk menghindari duplikasi
    // Key: barcode, Value: true jika sudah di-insert
    $inserted_barcodes_tracker = [];
    
    // STEP 0: Kumpulkan semua move_id yang akan diproses dan reset quantity ke 0
    $all_move_ids_to_reset = [];
    
    // Loop per picking untuk mengumpulkan move_ids
    foreach ($pickings as $picking) {
        $picking_id = $picking['id'];
        $sale_id = is_array($picking['sale_id']) ? $picking['sale_id'][0] : ($picking['sale_id'] ?? 0);
        
        if (!$sale_id) {
            continue;
        }
        
        // Ambil move_ids
        $picking_full = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_ids']);
        $move_ids = $picking_full[0]['move_ids'] ?? [];
        
        if (!empty($move_ids)) {
            $all_move_ids_to_reset = array_merge($all_move_ids_to_reset, $move_ids);
        }
    }
    
    // Reset quantity stock.move ke 0 untuk semua move_id yang akan diproses
    if (!empty($all_move_ids_to_reset)) {
        // Hapus duplikat move_id
        $all_move_ids_to_reset = array_unique($all_move_ids_to_reset);
        
        error_log("Reset quantity stock.move ke 0 untuk " . count($all_move_ids_to_reset) . " move_id");
        
        // Update quantity ke 0 untuk semua move (product_uom_qty tidak diubah)
        foreach ($all_move_ids_to_reset as $move_id) {
            $reset_result = callOdooWrite($username, 'stock.move', [$move_id], [
                'quantity' => 0
            ]);
            
            if ($reset_result === false) {
                $errors[] = "Gagal reset quantity untuk move_id: $move_id";
                error_log("Gagal reset quantity untuk move_id: $move_id");
            } else {
                error_log("Berhasil reset quantity ke 0 untuk move_id: $move_id");
            }
        }
    }
    
    // Loop per picking untuk sinkronisasi
    foreach ($pickings as $picking) {
        $picking_id = $picking['id'];
        $picking_name = $picking['name'] ?? '';
        $sale_id = is_array($picking['sale_id']) ? $picking['sale_id'][0] : ($picking['sale_id'] ?? 0);
        
        if (!$sale_id) {
            continue;
        }
        
        // Ambil move_ids
        $picking_full = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_ids']);
        $move_ids = $picking_full[0]['move_ids'] ?? [];
        
        if (empty($move_ids)) {
            continue;
        }
        
        // Ambil moves dengan sale_line_id dan location info
        $moves = callOdooRead($username, 'stock.move', [['id', 'in', $move_ids]], ['id', 'product_id', 'product_uom_qty', 'sale_line_id', 'move_line_ids', 'product_uom', 'location_id', 'location_dest_id']);
        
        if ($moves && is_array($moves)) {
            foreach ($moves as $move) {
                $move_id = $move['id'];
                $product_id = is_array($move['product_id']) ? $move['product_id'][0] : null;
                $sale_line_id = is_array($move['sale_line_id']) ? $move['sale_line_id'][0] : null;
                $move_line_ids = $move['move_line_ids'] ?? [];
                $product_uom_id = is_array($move['product_uom']) ? $move['product_uom'][0] : 1;
                $location_id = is_array($move['location_id']) ? $move['location_id'][0] : ($move['location_id'] ?? null);
                $location_dest_id = is_array($move['location_dest_id']) ? $move['location_dest_id'][0] : ($move['location_dest_id'] ?? null);
                
                if (!$product_id || !$sale_line_id || !$location_id || !$location_dest_id) {
                    if (!$location_id || !$location_dest_id) {
                        $errors[] = "Location tidak lengkap untuk move_id: $move_id";
                        error_log("Location tidak lengkap untuk move_id: $move_id");
                    }
                    continue;
                }
                
                // Ambil barcode yang sudah ada di Odoo untuk move ini
                $existing_barcodes = [];
                if (!empty($move_line_ids)) {
                    $existing_move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id']);
                    if ($existing_move_lines && is_array($existing_move_lines)) {
                        foreach ($existing_move_lines as $move_line) {
                            $lot_id = is_array($move_line['lot_id']) ? $move_line['lot_id'][0] : $move_line['lot_id'];
                            if ($lot_id) {
                                // Ambil nama lot (barcode) dari Odoo
                                $lot_info = callOdooRead($username, 'stock.lot', [['id', '=', $lot_id]], ['name']);
                                if ($lot_info && !empty($lot_info)) {
                                    $existing_barcodes[] = $lot_info[0]['name'];
                                }
                            }
                        }
                    }
                }
                
                // Ambil barcode dari shipping_manual_stuffing
                $scanned_barcodes = [];
                
                // Method 1: Cari dari production_lots_strg
                $sql_strg = "SELECT DISTINCT sms.production_code
                            FROM shipping_manual_stuffing sms
                            INNER JOIN production_lots_strg pls ON pls.production_code = sms.production_code
                            WHERE sms.id_shipping = ?
                            AND pls.sale_order_id = ?
                            AND pls.product_code = ?
                            AND pls.sale_order_line_id = ?
                            ORDER BY sms.production_code";
                
                $stmt_strg = $conn->prepare($sql_strg);
                if ($stmt_strg) {
                    $stmt_strg->bind_param("iiii", $shipping_id, $sale_id, $product_id, $sale_line_id);
                    $stmt_strg->execute();
                    $result_strg = $stmt_strg->get_result();
                    
                    while ($row = $result_strg->fetch_assoc()) {
                        $scanned_barcodes[] = $row['production_code'];
                    }
                    $stmt_strg->close();
                }
                
                // Method 2: Jika tidak ada di strg, cari dari barcode_item
                if (empty($scanned_barcodes)) {
                    $sql_bi = "SELECT DISTINCT sms.production_code
                              FROM shipping_manual_stuffing sms
                              INNER JOIN barcode_item bi ON bi.barcode = sms.production_code
                              WHERE sms.id_shipping = ?
                              AND bi.sale_order_id = ?
                              AND bi.product_id = ?
                              AND bi.sale_order_line_id = ?
                              ORDER BY sms.production_code";
                    
                    $stmt_bi = $conn->prepare($sql_bi);
                    if ($stmt_bi) {
                        $stmt_bi->bind_param("iiii", $shipping_id, $sale_id, $product_id, $sale_line_id);
                        $stmt_bi->execute();
                        $result_bi = $stmt_bi->get_result();
                        
                        while ($row = $result_bi->fetch_assoc()) {
                            $scanned_barcodes[] = $row['production_code'];
                        }
                        $stmt_bi->close();
                    }
                }
                
                // Filter barcode yang belum ada di Odoo (untuk di-insert)
                $barcodes_to_insert = [];
                foreach ($scanned_barcodes as $barcode) {
                    // Skip jika barcode ini sudah di-insert sebelumnya (hindari duplikasi)
                    if (isset($inserted_barcodes_tracker[$barcode])) {
                        continue;
                    }
                    // Hanya insert yang belum ada di Odoo
                    if (!in_array($barcode, $existing_barcodes)) {
                        $barcodes_to_insert[] = $barcode;
                    }
                }
                
                // Insert barcode yang belum ada ke Odoo sebagai move_line
                $move_line_count_for_this_move = count($existing_barcodes); // Hitung dari yang sudah ada
                foreach ($barcodes_to_insert as $barcode) {
                    // Skip jika barcode ini sudah di-insert sebelumnya (hindari duplikasi)
                    if (isset($inserted_barcodes_tracker[$barcode])) {
                        error_log("Barcode $barcode sudah di-insert sebelumnya, skip untuk menghindari duplikasi");
                        continue;
                    }
                    
                    // Ambil lot_id dari Odoo berdasarkan barcode
                    $lot_data = callOdooRead($username, 'stock.lot', [['name', '=', $barcode]], ['id']);
                    if (!$lot_data || empty($lot_data)) {
                        $errors[] = "Lot tidak ditemukan di Odoo untuk barcode: $barcode";
                        error_log("Lot tidak ditemukan di Odoo untuk barcode: $barcode");
                        continue;
                    }
                    
                    $lot_id = $lot_data[0]['id'];
                    
                    // Create move line data
                    $move_line_data = [
                        'move_id' => $move_id,
                        'picking_id' => $picking_id,
                        'product_id' => $product_id,
                        'lot_id' => $lot_id,
                        'qty_done' => 1,
                        'product_uom_id' => $product_uom_id,
                        'location_id' => $location_id,
                        'location_dest_id' => $location_dest_id
                    ];
                    
                    $create_result = callOdooCreate($username, 'stock.move.line', $move_line_data);
                    
                    if ($create_result !== false && $create_result > 0) {
                        $total_inserted++;
                        $move_line_count_for_this_move++;
                        // Mark barcode sebagai sudah di-insert
                        $inserted_barcodes_tracker[$barcode] = true;
                        // Mark move_id untuk di-uncheck picked
                        $moves_to_uncheck_picked[$move_id] = true;
                        error_log("Berhasil create move line untuk barcode: $barcode (picking: $picking_id, move: $move_id)");
                    } else {
                        $error_msg = "Gagal create move line untuk barcode: $barcode (picking: $picking_id, move: $move_id)";
                        $errors[] = $error_msg;
                        error_log($error_msg);
                    }
                }
                
                // Simpan quantity berdasarkan jumlah barcode (existing + yang berhasil di-insert)
                if ($move_line_count_for_this_move > 0) {
                    $move_quantity_map[$move_id] = $move_line_count_for_this_move;
                }
            }
        }
    }
    
    // STEP 2: Uncheck picked untuk semua move_id yang sudah di-create move line-nya
    // Ini untuk mencegah picked otomatis terceklis oleh Odoo
    if (!empty($moves_to_uncheck_picked)) {
        $move_ids_to_uncheck = array_keys($moves_to_uncheck_picked);
        error_log("Unchecking picked untuk " . count($move_ids_to_uncheck) . " move_id: " . implode(', ', $move_ids_to_uncheck));
        
        $uncheck_result = callOdooWrite($username, 'stock.move', $move_ids_to_uncheck, ['picked' => false]);
        
        if ($uncheck_result !== false) {
            error_log("Berhasil uncheck picked untuk " . count($move_ids_to_uncheck) . " move_id");
        } else {
            $errors[] = "Gagal uncheck picked untuk beberapa move_id";
            error_log("Gagal uncheck picked untuk move_ids: " . implode(', ', $move_ids_to_uncheck));
        }
    }
    
    // STEP 3: Update quantity stock.move berdasarkan jumlah barcode (existing + yang berhasil di-insert)
    if (!empty($move_quantity_map)) {
        error_log("Update quantity stock.move untuk " . count($move_quantity_map) . " move_id berdasarkan barcode manual stuffing");
        
        foreach ($move_quantity_map as $move_id => $quantity) {
            $update_result = callOdooWrite($username, 'stock.move', [$move_id], [
                'quantity' => $quantity
            ]);
            
            if ($update_result === false) {
                $errors[] = "Gagal update quantity untuk move_id: $move_id";
                error_log("Gagal update quantity untuk move_id: $move_id (quantity: $quantity)");
            } else {
                $total_updated++;
                error_log("Berhasil update quantity untuk move_id: $move_id (quantity: $quantity)");
            }
        }
    }
    
    // STEP 4: Update scheduled_date di semua picking sesuai scheduled_date dari shipping
    $scheduled_date_updated = 0;
    if (!empty($picking_ids) && !empty($shipping['sheduled_date'])) {
        $shipping_scheduled_date = $shipping['sheduled_date'];
        // Format tanggal untuk Odoo (format: YYYY-MM-DD HH:MM:SS atau YYYY-MM-DD)
        // Konversi ke format yang sesuai untuk Odoo
        $scheduled_date_formatted = date('Y-m-d H:i:s', strtotime($shipping_scheduled_date));
        
        error_log("Update scheduled_date di " . count($picking_ids) . " picking menjadi: $scheduled_date_formatted");
        
        foreach ($picking_ids as $picking_id) {
            $update_picking_result = callOdooWrite($username, 'stock.picking', [$picking_id], [
                'scheduled_date' => $scheduled_date_formatted
            ]);
            
            if ($update_picking_result === false) {
                $errors[] = "Gagal update scheduled_date untuk picking_id: $picking_id";
                error_log("Gagal update scheduled_date untuk picking_id: $picking_id");
            } else {
                $scheduled_date_updated++;
                error_log("Berhasil update scheduled_date untuk picking_id: $picking_id menjadi: $scheduled_date_formatted");
            }
        }
    }
    
    $message = "Sinkronisasi selesai. Diinsert: $total_inserted barcode, Diupdate: $total_updated move_id";
    if ($scheduled_date_updated > 0) {
        $message .= ", $scheduled_date_updated picking";
    }
    if (!empty($errors)) {
        $message .= ". Error: " . count($errors) . " item";
    }
    
    error_log("=== END SYNC COMPARE MANUAL STUFFING ===");
    error_log("Total inserted: $total_inserted barcode");
    error_log("Total updated: $total_updated move_id");
    if ($scheduled_date_updated > 0) {
        error_log("Total picking updated: $scheduled_date_updated picking");
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'inserted' => $total_inserted,
        'updated' => $total_updated,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    error_log("Error in sync_compare_manual_stuffing: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}



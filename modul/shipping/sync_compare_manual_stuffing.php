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
    
    $total_deleted = 0;
    $total_created = 0;
    $errors = [];
    
    // Track barcode yang sudah di-insert untuk menghindari duplikasi
    // Key: barcode, Value: true jika sudah di-insert
    $inserted_barcodes_tracker = [];
    
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
                
                // Ambil barcode dari Odoo (stock.move.line dengan lot_name)
                $odoo_barcodes = [];
                $move_line_ids_for_delete = [];
                if (!empty($move_line_ids)) {
                    $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['id', 'lot_id', 'lot_name']);
                    
                    if ($move_lines && is_array($move_lines)) {
                        foreach ($move_lines as $line) {
                            $lot_name = null;
                            
                            if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                                $lot_name = $line['lot_id'][1];
                            } else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                                $lot_name = $line['lot_name'];
                            }
                            
                            if ($lot_name && !empty($lot_name)) {
                                $odoo_barcodes[] = $lot_name;
                                $move_line_ids_for_delete[$line['id']] = $lot_name;
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
                
                // Bandingkan barcode
                $matched_barcodes = array_intersect($odoo_barcodes, $scanned_barcodes); // Barcode yang cocok di kedua sisi
                $odoo_only = array_diff($odoo_barcodes, $scanned_barcodes); // Hanya ada di Odoo (akan dihapus)
                $scanned_only = array_diff($scanned_barcodes, $odoo_barcodes); // Hanya ada di scan (akan ditambahkan)
                
                // STEP 1: Hapus move_line yang lot_name-nya ada di odoo_only
                // PENTING: Barcode yang matched TIDAK akan dihapus karena tidak ada di odoo_only
                $move_line_ids_to_unlink = [];
                foreach ($move_line_ids_for_delete as $move_line_id => $lot_name) {
                    // Hanya hapus yang ada di odoo_only, skip yang matched
                    if (in_array($lot_name, $odoo_only)) {
                        $move_line_ids_to_unlink[] = $move_line_id;
                    }
                }
                
                // Unlink (delete) move lines secara batch
                if (!empty($move_line_ids_to_unlink)) {
                    $connInfo = odooConnectionInfo($username);
                    if ($connInfo) {
                        $delete_params = [
                            "jsonrpc" => "2.0",
                            "method" => "call",
                            "params" => [
                                "service" => "object",
                                "method" => "execute_kw",
                                "args" => [
                                    $connInfo['db'],
                                    $connInfo['uid'],
                                    $connInfo['password'],
                                    'stock.move.line',
                                    'unlink',
                                    [$move_line_ids_to_unlink]
                                ]
                            ],
                            "id" => 1
                        ];
                        
                        $curl = curl_init($connInfo['url']);
                        curl_setopt_array($curl, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode($delete_params),
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false
                        ]);
                        
                        $response = curl_exec($curl);
                        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                        curl_close($curl);
                        
                        $decoded = json_decode($response, true);
                        if ($decoded && isset($decoded['result']) && $decoded['result'] === true) {
                            $total_deleted += count($move_line_ids_to_unlink);
                            error_log("Berhasil hapus " . count($move_line_ids_to_unlink) . " move_line untuk move_id: $move_id");
                        } else {
                            $errors[] = "Gagal hapus move_line untuk move_id: $move_id";
                            error_log("Gagal hapus move_line untuk move_id: $move_id. Response: " . substr($response, 0, 500));
                        }
                    }
                }
                
                // STEP 2: Tambah move_line baru untuk barcode yang ada di scanned_only
                // PENTING: Barcode yang matched TIDAK akan ditambahkan karena tidak ada di scanned_only
                foreach ($scanned_only as $barcode) {
                    // Skip jika barcode ini sudah di-insert sebelumnya (hindari duplikasi dari shipping_manual_stuffing)
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
                        $total_created++;
                        // Mark barcode sebagai sudah di-insert
                        $inserted_barcodes_tracker[$barcode] = true;
                        error_log("Berhasil create move line untuk barcode: $barcode (picking: $picking_id, move: $move_id)");
                    } else {
                        $error_msg = "Gagal create move line untuk barcode: $barcode (picking: $picking_id, move: $move_id)";
                        $errors[] = $error_msg;
                        error_log($error_msg);
                    }
                }
            }
        }
    }
    
    $message = "Sinkronisasi selesai. Dihapus: $total_deleted, Ditambahkan: $total_created";
    if (!empty($errors)) {
        $message .= ". Error: " . count($errors) . " item";
    }
    
    error_log("=== END SYNC COMPARE MANUAL STUFFING ===");
    error_log("Total deleted: $total_deleted, Total created: $total_created");
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted' => $total_deleted,
        'created' => $total_created,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    error_log("Error in sync_compare_manual_stuffing: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}



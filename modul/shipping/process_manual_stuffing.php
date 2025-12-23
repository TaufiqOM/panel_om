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
    error_log("=== START PROCESS MANUAL STUFFING untuk shipping_id: $shipping_id ===");
    
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
    
    $total_processed = 0;
    $errors = [];
    
    error_log("Jumlah picking ditemukan: " . count($pickings));
    
    // Loop per picking
    foreach ($pickings as $picking) {
        error_log("Memproses picking_id: " . $picking['id']);
        $picking_id = $picking['id'];
        $sale_id = is_array($picking['sale_id']) ? $picking['sale_id'][0] : ($picking['sale_id'] ?? 0);
        
        if (!$sale_id) {
            continue;
        }
        
        // Ambil sale.order untuk mendapatkan client_order_ref
        $sale_order = callOdooRead($username, 'sale.order', [['id', '=', $sale_id]], ['client_order_ref', 'name']);
        if (!$sale_order || empty($sale_order)) {
            continue;
        }
        
        $sale_order_data = $sale_order[0];
        $client_order_ref = $sale_order_data['client_order_ref'] ?? '';
        $so_name = $sale_order_data['name'] ?? '';
        
        if (empty($client_order_ref)) {
            continue;
        }
        
        // Ambil move_ids untuk picking ini
        $picking_full = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_ids']);
        $move_ids = [];
        if ($picking_full && !empty($picking_full) && isset($picking_full[0]['move_ids'])) {
            $move_ids = $picking_full[0]['move_ids'];
        }
        
        if (empty($move_ids)) {
            continue;
        }
        
        // Step 1: Reset quantity_done di stock.move menjadi 0
        $moves = callOdooRead($username, 'stock.move', [['id', 'in', $move_ids]], ['id', 'product_id', 'product_uom_qty', 'sale_line_id']);
        
        if ($moves && is_array($moves)) {
            // Reset quantity_done menjadi 0
            foreach ($moves as $move) {
                $move_id = $move['id'];
                $reset_result = callOdooWrite($username, 'stock.move', [$move_id], ['quantity_done' => 0]);
                if ($reset_result === false) {
                    error_log("Failed to reset move_id: $move_id");
                }
            }
            
            // Step 2: Delete existing move lines
            $move_line_ids_to_delete = [];
            foreach ($moves as $move) {
                $move_id = $move['id'];
                $move_full = callOdooRead($username, 'stock.move', [['id', '=', $move_id]], ['move_line_ids']);
                if ($move_full && !empty($move_full) && isset($move_full[0]['move_line_ids'])) {
                    $move_line_ids_to_delete = array_merge($move_line_ids_to_delete, $move_full[0]['move_line_ids']);
                }
            }
            
            if (!empty($move_line_ids_to_delete)) {
                // Delete move lines menggunakan unlink method
                $delete_result = callOdoo($username, 'stock.move.line', 'unlink', [$move_line_ids_to_delete]);
                if ($delete_result === false) {
                    error_log("Failed to delete move lines");
                }
            }
            
            // Step 3: Insert barcode dari production_lots_strg ke stock.move.line
            foreach ($moves as $move) {
                $move_id = $move['id'];
                $product_id = is_array($move['product_id']) ? $move['product_id'][0] : null;
                $product_uom_qty = floatval($move['product_uom_qty'] ?? 0);
                $sale_line_id = is_array($move['sale_line_id']) ? $move['sale_line_id'][0] : null;
                
                if (!$product_id || $product_uom_qty <= 0 || !$sale_line_id) {
                    continue;
                }
                
                // Ambil barcode dari production_lots_strg
                $sql_strg = "SELECT pls.production_code, pls.id
                             FROM production_lots_strg pls
                             LEFT JOIN shipping_manual_stuffing sms ON sms.production_code = pls.production_code AND sms.id_shipping = ?
                             WHERE pls.sale_order_id = ?
                             AND pls.product_code = ?
                             AND pls.sale_order_line_id = ?
                             AND sms.production_code IS NULL
                             ORDER BY pls.id DESC
                             LIMIT ?";
                
                $stmt_strg = $conn->prepare($sql_strg);
                if (!$stmt_strg) {
                    $errors[] = "Gagal prepare query untuk product_id: $product_id, error: " . $conn->error;
                    continue;
                }
                
                $limit_qty = intval($product_uom_qty);
                $stmt_strg->bind_param("iiiii", $shipping_id, $sale_id, $product_id, $sale_line_id, $limit_qty);
                
                if (!$stmt_strg->execute()) {
                    $errors[] = "Gagal execute query untuk product_id: $product_id, error: " . $stmt_strg->error;
                    $stmt_strg->close();
                    continue;
                }
                
                $result_strg = $stmt_strg->get_result();
                
                $barcodes_to_insert = [];
                while ($row = $result_strg->fetch_assoc()) {
                    $barcodes_to_insert[] = $row['production_code'];
                }
                $stmt_strg->close();
                
                if (empty($barcodes_to_insert)) {
                    $errors[] = "Tidak ada barcode tersedia untuk product_id: $product_id (Qty SO: $product_uom_qty, Sale Line ID: $sale_line_id)";
                    continue;
                }
                
                // Get location_id dan location_dest_id dari move (ambil sekali saja)
                $move_full = callOdooRead($username, 'stock.move', [['id', '=', $move_id]], ['location_id', 'location_dest_id', 'product_uom']);
                if (!$move_full || empty($move_full)) {
                    $errors[] = "Data move tidak ditemukan untuk move_id: $move_id";
                    continue;
                }
                
                $location_id = is_array($move_full[0]['location_id']) ? $move_full[0]['location_id'][0] : $move_full[0]['location_id'];
                $location_dest_id = is_array($move_full[0]['location_dest_id']) ? $move_full[0]['location_dest_id'][0] : $move_full[0]['location_dest_id'];
                $product_uom_id = is_array($move_full[0]['product_uom']) ? $move_full[0]['product_uom'][0] : ($move_full[0]['product_uom'] ?? 1);
                
                if (!$location_id || !$location_dest_id) {
                    $errors[] = "Location tidak valid untuk move_id: $move_id";
                    continue;
                }
                
                // Insert barcode ke Odoo stock.move.line
                $success_count = 0;
                foreach ($barcodes_to_insert as $barcode) {
                    // Ambil lot_id dari Odoo berdasarkan barcode
                    $lot_data = callOdooRead($username, 'stock.lot', [['name', '=', $barcode]], ['id']);
                    if (!$lot_data || empty($lot_data)) {
                        $errors[] = "Lot tidak ditemukan di Odoo untuk barcode: $barcode";
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
                        $total_processed++;
                        $success_count++;
                        error_log("Berhasil create move line untuk barcode: $barcode (picking: $picking_id, move: $move_id)");
                    } else {
                        $error_msg = "Gagal create move line untuk barcode: $barcode (picking: $picking_id, move: $move_id)";
                        $errors[] = $error_msg;
                        error_log($error_msg);
                    }
                }
                
                // Note: quantity_done di stock.move biasanya computed field dari move lines,
                // jadi tidak perlu update manual. Odoo akan menghitung otomatis dari qty_done di move lines.
                if ($success_count > 0) {
                    error_log("Berhasil insert $success_count barcode untuk move_id: $move_id - quantity_done akan dihitung otomatis oleh Odoo");
                }
            }
        }
    }
    
    error_log("=== FINISH PROCESS MANUAL STUFFING ===");
    error_log("Total processed: $total_processed");
    error_log("Total errors: " . count($errors));
    
    $response_message = '';
    if ($total_processed > 0) {
        $response_message = "Berhasil memproses $total_processed barcode ke Odoo";
        if (!empty($errors)) {
            $response_message .= ". Terdapat " . count($errors) . " error: " . implode('; ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $response_message .= ' dan ' . (count($errors) - 3) . ' error lainnya';
            }
        }
        echo json_encode([
            'success' => true,
            'message' => $response_message,
            'processed' => $total_processed,
            'errors' => $errors
        ]);
    } else {
        $error_message = 'Tidak ada barcode yang diproses';
        if (!empty($errors)) {
            $error_message .= '. ' . implode('; ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $error_message .= ' dan ' . (count($errors) - 5) . ' error lainnya';
            }
        }
        echo json_encode([
            'success' => false,
            'message' => $error_message,
            'processed' => 0,
            'errors' => $errors
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>


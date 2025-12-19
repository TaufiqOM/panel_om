<?php
session_start();
require __DIR__ . '/../../inc/config.php';
require __DIR__ . '/../../inc/config_odoo.php';

header('Content-Type: application/json');

$username = $_SESSION['username'] ?? '';

// Ambil data id dari POST
$shipping_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if (!$shipping_id) {
    echo json_encode(['success' => false, 'message' => 'Shipping ID tidak valid']);
    exit;
}

try {
    // Ambil data pickings untuk shipping ini dengan sale_id
    $sql_pickings = "SELECT id, name, sale_id, client_order_ref FROM shipping_detail WHERE id_shipping = ? ORDER BY sale_id, name";
    $stmt_pickings = $conn->prepare($sql_pickings);
    $stmt_pickings->bind_param("i", $shipping_id);
    $stmt_pickings->execute();
    $result_pickings = $stmt_pickings->get_result();
    
    // Ambil semua picking data
    $all_pickings = [];
    while ($picking = $result_pickings->fetch_assoc()) {
        $all_pickings[] = $picking;
    }
    $stmt_pickings->close();
    
    // Ambil semua production_code dari shipping_manual_stuffing untuk shipping ini
    $sql_manual = "SELECT DISTINCT production_code FROM shipping_manual_stuffing WHERE id_shipping = ? ORDER BY production_code";
    $stmt_manual = $conn->prepare($sql_manual);
    $stmt_manual->bind_param("i", $shipping_id);
    $stmt_manual->execute();
    $result_manual = $stmt_manual->get_result();
    
    $all_manual_codes = [];
    while ($manual = $result_manual->fetch_assoc()) {
        $all_manual_codes[] = $manual['production_code'];
    }
    $stmt_manual->close();
    
    $comparison_data = [];
    
    // Loop per picking (bukan per sale_id)
    foreach ($all_pickings as $picking_data) {
        $picking_id = $picking_data['id'];
        $picking_name = $picking_data['name'];
        $sale_id = $picking_data['sale_id'] ?? 0;
        $client_order_ref = $picking_data['client_order_ref'] ?? '';
        
        // Ambil lot_ids langsung dari Odoo (realtime) untuk picking ini
        $picking_odoo = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_line_ids_without_package']);
        
        $picking_lots = [];
        
        if ($picking_odoo && !empty($picking_odoo)) {
            $move_line_ids = $picking_odoo[0]['move_line_ids_without_package'] ?? [];
            
            if (!empty($move_line_ids)) {
                // Ambil data move_line langsung dari Odoo
                $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name', 'product_id', 'qty_done']);
                
                if ($move_lines && is_array($move_lines)) {
                    foreach ($move_lines as $line) {
                        $lot_name = null;
                        $product_name = null;
                        $qty_done = $line['qty_done'] ?? 0;
                        
                        // Try to get lot info from lot_id field
                        if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                            $lot_name = $line['lot_id'][1];
                        }
                        // Fallback: try lot_name field
                        else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                            $lot_name = $line['lot_name'];
                        }
                        
                        // Extract product_name
                        if (isset($line['product_id']) && is_array($line['product_id']) && count($line['product_id']) >= 2) {
                            $product_name = $line['product_id'][1];
                        }
                        
                        if ($lot_name !== null && $lot_name !== '') {
                            // Extract product_id
                            $product_id = null;
                            if (isset($line['product_id']) && is_array($line['product_id']) && count($line['product_id']) >= 2) {
                                $product_id = $line['product_id'][0];
                            }
                            
                            $picking_lots[] = [
                                'lot_name' => $lot_name,
                                'product_id' => $product_id,
                                'product_name' => $product_name,
                                'qty_done' => $qty_done
                            ];
                        }
                    }
                }
            }
        }
        
        // Get sale order name from Odoo if sale_id exists
        $sale_order_name = '';
        if ($sale_id && $sale_id > 0) {
            $sale_order = callOdooRead($username, 'sale.order', [['id', '=', $sale_id]], ['name']);
            if ($sale_order && !empty($sale_order)) {
                $sale_order_name = $sale_order[0]['name'] ?? '';
            }
        }
        
        // Group lots per product_id dalam picking ini
        $products_data = [];
        $picking_lot_names = [];
        
        foreach ($picking_lots as $lot) {
            $product_id = $lot['product_id'] ?? 0;
            $lot_name = $lot['lot_name'];
            $picking_lot_names[] = $lot_name;
            
            // Initialize product data jika belum ada
            if (!isset($products_data[$product_id])) {
                $products_data[$product_id] = [
                    'product_id' => $product_id,
                    'product_name' => $lot['product_name'] ?? '-',
                    'matched' => [],
                    'odoo_only' => [],
                    'manual_only' => [],
                    'total_matched' => 0,
                    'total_odoo_only' => 0,
                    'total_manual_only' => 0
                ];
            }
            
            // Cek apakah lot_name ada di manual codes
            if (in_array($lot_name, $all_manual_codes)) {
                $products_data[$product_id]['matched'][] = [
                    'code' => $lot_name,
                    'product_id' => $product_id,
                    'product' => $lot['product_name'] ?? '-',
                    'qty' => $lot['qty_done'],
                    'source' => 'both'
                ];
                $products_data[$product_id]['total_matched']++;
            } else {
                $products_data[$product_id]['odoo_only'][] = [
                    'code' => $lot_name,
                    'product_id' => $product_id,
                    'product' => $lot['product_name'] ?? '-',
                    'qty' => $lot['qty_done'],
                    'source' => 'odoo'
                ];
                $products_data[$product_id]['total_odoo_only']++;
            }
        }
        
        // Untuk manual_only: hanya code yang seharusnya ada di picking ini (berdasarkan product_id yang ada di picking)
        // Cek manual codes yang tidak ada di picking ini tapi memiliki product_id yang sama dengan product di picking ini
        $product_ids_in_picking = array_unique(array_column($picking_lots, 'product_id'));
        
        foreach ($all_manual_codes as $code) {
            if (!in_array($code, $picking_lot_names)) {
                // Coba cari product_id dari barcode_item untuk melihat apakah code ini terkait dengan product di picking ini
                $product_id_for_manual = null;
                $sql_barcode = "SELECT product_id, sale_order_id FROM barcode_item WHERE barcode = ? LIMIT 1";
                $stmt_barcode = $conn->prepare($sql_barcode);
                if ($stmt_barcode) {
                    $stmt_barcode->bind_param("s", $code);
                    $stmt_barcode->execute();
                    $result_barcode = $stmt_barcode->get_result();
                    if ($result_barcode->num_rows > 0) {
                        $barcode_data = $result_barcode->fetch_assoc();
                        $product_id_for_manual = $barcode_data['product_id'];
                        $sale_order_id_from_barcode = $barcode_data['sale_order_id'] ?? null;
                        
                        // Hanya tambahkan manual_only jika product_id cocok dengan product di picking ini
                        // atau jika sale_order_id cocok dengan sale_id picking ini
                        if ($product_id_for_manual && in_array($product_id_for_manual, $product_ids_in_picking)) {
                            // Code ini terkait dengan product yang ada di picking ini
                            if (!isset($products_data[$product_id_for_manual])) {
                                $products_data[$product_id_for_manual] = [
                                    'product_id' => $product_id_for_manual,
                                    'product_name' => '-',
                                    'matched' => [],
                                    'odoo_only' => [],
                                    'manual_only' => [],
                                    'total_matched' => 0,
                                    'total_odoo_only' => 0,
                                    'total_manual_only' => 0
                                ];
                            }
                            
                            $products_data[$product_id_for_manual]['manual_only'][] = [
                                'code' => $code,
                                'product_id' => $product_id_for_manual,
                                'product' => '-',
                                'qty' => '-',
                                'source' => 'manual'
                            ];
                            $products_data[$product_id_for_manual]['total_manual_only']++;
                        } else if ($sale_order_id_from_barcode && $sale_order_id_from_barcode == $sale_id) {
                            // Code ini terkait dengan sale order yang sama tapi product_id tidak cocok atau tidak ada
                            // Kelompokkan ke product_id 0 untuk manual-only yang terkait sale order tapi product tidak jelas
                            if (!isset($products_data[0])) {
                                $products_data[0] = [
                                    'product_id' => 0,
                                    'product_name' => '-',
                                    'matched' => [],
                                    'odoo_only' => [],
                                    'manual_only' => [],
                                    'total_matched' => 0,
                                    'total_odoo_only' => 0,
                                    'total_manual_only' => 0
                                ];
                            }
                            
                            $products_data[0]['manual_only'][] = [
                                'code' => $code,
                                'product_id' => 0,
                                'product' => '-',
                                'qty' => '-',
                                'source' => 'manual'
                            ];
                            $products_data[0]['total_manual_only']++;
                        }
                    }
                    $stmt_barcode->close();
                }
            }
        }
        
        // Tambahkan product_name ke setiap product jika masih kosong
        foreach ($products_data as &$prod) {
            if (empty($prod['product_name']) || $prod['product_name'] === '-') {
                // Coba ambil dari matched/odoo_only
                if (!empty($prod['matched'])) {
                    $prod['product_name'] = $prod['matched'][0]['product'] ?? '-';
                } else if (!empty($prod['odoo_only'])) {
                    $prod['product_name'] = $prod['odoo_only'][0]['product'] ?? '-';
                }
            }
        }
        unset($prod);
        
        // Convert to indexed array
        $products_list = array_values($products_data);
        
        // Hitung total untuk picking ini
        $total_matched_picking = 0;
        $total_odoo_only_picking = 0;
        $total_manual_only_picking = 0;
        foreach ($products_list as $prod) {
            $total_matched_picking += $prod['total_matched'];
            $total_odoo_only_picking += $prod['total_odoo_only'];
            $total_manual_only_picking += $prod['total_manual_only'];
        }
        
        $comparison_data[] = [
            'picking_id' => $picking_id,
            'picking_name' => $picking_name,
            'sale_id' => $sale_id,
            'sale_order_name' => $sale_order_name,
            'client_order_ref' => $client_order_ref,
            'products' => $products_list,
            'total_matched' => $total_matched_picking,
            'total_odoo_only' => $total_odoo_only_picking,
            'total_manual_only' => $total_manual_only_picking,
            'total_odoo' => count($picking_lots),
            'has_matched' => $total_matched_picking > 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $comparison_data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>

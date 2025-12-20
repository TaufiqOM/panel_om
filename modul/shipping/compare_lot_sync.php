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
        
        // Struktur baru: Perbandingan 2 sisi (Odoo vs DB) per product
        $products_data = [];
        
        // Sisi Odoo: Group lots per product_id dalam picking ini
        $odoo_barcodes_by_product = [];
        foreach ($picking_lots as $lot) {
            $product_id = $lot['product_id'] ?? 0;
            $lot_name = $lot['lot_name'];
            
            if (!isset($odoo_barcodes_by_product[$product_id])) {
                $odoo_barcodes_by_product[$product_id] = [
                    'product_id' => $product_id,
                    'product_name' => $lot['product_name'] ?? '-',
                    'barcodes' => []
                ];
            }
            
            $odoo_barcodes_by_product[$product_id]['barcodes'][] = [
                'code' => $lot_name,
                'qty' => $lot['qty_done']
            ];
        }
        
        // Sisi DB: Ambil barcode dari manual stuffing untuk picking ini (berdasarkan sale_id dan product_id)
        $db_barcodes_by_product = [];
        if ($sale_id && $sale_id > 0) {
            // Ambil semua barcode dari shipping_manual_stuffing yang terkait dengan sale_id ini
            $sql_db_barcodes = "SELECT DISTINCT sms.production_code, bi.product_id
                               FROM shipping_manual_stuffing sms
                               INNER JOIN barcode_item bi ON bi.barcode = sms.production_code
                               WHERE sms.id_shipping = ? AND bi.sale_order_id = ?
                               ORDER BY bi.product_id, sms.production_code";
            $stmt_db_barcodes = $conn->prepare($sql_db_barcodes);
            if ($stmt_db_barcodes) {
                $stmt_db_barcodes->bind_param("ii", $shipping_id, $sale_id);
                $stmt_db_barcodes->execute();
                $result_db_barcodes = $stmt_db_barcodes->get_result();
                
                while ($row = $result_db_barcodes->fetch_assoc()) {
                    $product_id = $row['product_id'] ?? 0;
                    $barcode = $row['production_code'];
                    
                    if (!isset($db_barcodes_by_product[$product_id])) {
                        $db_barcodes_by_product[$product_id] = [
                            'product_id' => $product_id,
                            'barcodes' => []
                        ];
                    }
                    
                    $db_barcodes_by_product[$product_id]['barcodes'][] = [
                        'code' => $barcode,
                        'qty' => '-' // DB tidak punya qty
                    ];
                }
                $stmt_db_barcodes->close();
            }
        }
        
        // Gabungkan semua product_id dari kedua sisi
        $all_product_ids = array_unique(array_merge(
            array_keys($odoo_barcodes_by_product),
            array_keys($db_barcodes_by_product)
        ));
        
        // Buat perbandingan per product
        foreach ($all_product_ids as $product_id) {
            $odoo_product = $odoo_barcodes_by_product[$product_id] ?? null;
            $db_product = $db_barcodes_by_product[$product_id] ?? null;
            
            $odoo_barcodes = $odoo_product ? $odoo_product['barcodes'] : [];
            $db_barcodes = $db_product ? $db_product['barcodes'] : [];
            
            $product_name = $odoo_product['product_name'] ?? '-';
            
            // Buat array barcode untuk perbandingan
            $odoo_barcode_list = array_column($odoo_barcodes, 'code');
            $db_barcode_list = array_column($db_barcodes, 'code');
            
            // Matched: barcode yang ada di kedua sisi
            $matched_barcodes = array_intersect($odoo_barcode_list, $db_barcode_list);
            
            // Odoo only: barcode yang hanya ada di Odoo
            $odoo_only_barcodes = array_diff($odoo_barcode_list, $db_barcode_list);
            
            // DB only: barcode yang hanya ada di DB
            $db_only_barcodes = array_diff($db_barcode_list, $odoo_barcode_list);
            
            // Buat detail untuk matched
            $matched_detail = [];
            foreach ($matched_barcodes as $code) {
                $odoo_item = null;
                foreach ($odoo_barcodes as $item) {
                    if ($item['code'] === $code) {
                        $odoo_item = $item;
                        break;
                    }
                }
                $matched_detail[] = [
                    'code' => $code,
                    'qty_odoo' => $odoo_item ? $odoo_item['qty'] : '-',
                    'qty_db' => '-' // DB tidak punya qty
                ];
            }
            
            // Buat detail untuk odoo_only
            $odoo_only_detail = [];
            foreach ($odoo_only_barcodes as $code) {
                $odoo_item = null;
                foreach ($odoo_barcodes as $item) {
                    if ($item['code'] === $code) {
                        $odoo_item = $item;
                        break;
                    }
                }
                $odoo_only_detail[] = [
                    'code' => $code,
                    'qty' => $odoo_item ? $odoo_item['qty'] : '-'
                ];
            }
            
            // Buat detail untuk db_only
            $db_only_detail = [];
            foreach ($db_only_barcodes as $code) {
                $db_only_detail[] = [
                    'code' => $code,
                    'qty' => '-'
                ];
            }
            
            $products_data[] = [
                'product_id' => $product_id,
                'product_name' => $product_name,
                'odoo_side' => [
                    'barcodes' => $odoo_barcodes,
                    'count' => count($odoo_barcodes)
                ],
                'db_side' => [
                    'barcodes' => $db_barcodes,
                    'count' => count($db_barcodes)
                ],
                'matched' => [
                    'barcodes' => $matched_detail,
                    'count' => count($matched_detail)
                ],
                'odoo_only' => [
                    'barcodes' => $odoo_only_detail,
                    'count' => count($odoo_only_detail)
                ],
                'db_only' => [
                    'barcodes' => $db_only_detail,
                    'count' => count($db_only_detail)
                ]
            ];
        }
        
        // Hitung total untuk picking ini
        $total_matched_picking = 0;
        $total_odoo_only_picking = 0;
        $total_db_only_picking = 0;
        $total_odoo_picking = 0;
        $total_db_picking = 0;
        
        foreach ($products_data as $prod) {
            $total_matched_picking += $prod['matched']['count'];
            $total_odoo_only_picking += $prod['odoo_only']['count'];
            $total_db_only_picking += $prod['db_only']['count'];
            $total_odoo_picking += $prod['odoo_side']['count'];
            $total_db_picking += $prod['db_side']['count'];
        }
        
        $comparison_data[] = [
            'picking_id' => $picking_id,
            'picking_name' => $picking_name,
            'sale_id' => $sale_id,
            'sale_order_name' => $sale_order_name,
            'client_order_ref' => $client_order_ref,
            'products' => $products_data,
            'total_matched' => $total_matched_picking,
            'total_odoo_only' => $total_odoo_only_picking,
            'total_db_only' => $total_db_only_picking,
            'total_odoo' => $total_odoo_picking,
            'total_db' => $total_db_picking,
            'has_matched' => $total_matched_picking > 0
        ];
    }
    
    // Cari barcode yang ada di db tapi picking nya tidak ada di picking odoo
    $barcodes_without_picking = [];
    
    // Ambil semua sale_id dari pickings yang ada
    $existing_sale_ids = [];
    foreach ($all_pickings as $picking) {
        if (!empty($picking['sale_id']) && $picking['sale_id'] > 0) {
            $existing_sale_ids[] = $picking['sale_id'];
        }
    }
    $existing_sale_ids = array_unique($existing_sale_ids);
    
    // Kumpulkan semua barcode yang sudah ada di picking manapun (untuk skip)
    $barcodes_in_pickings = [];
    foreach ($comparison_data as $picking_group) {
        foreach ($picking_group['products'] as $product) {
            foreach ($product['matched'] as $matched_item) {
                $barcodes_in_pickings[$matched_item['code']] = true;
            }
            foreach ($product['odoo_only'] as $odoo_item) {
                $barcodes_in_pickings[$odoo_item['code']] = true;
            }
            foreach ($product['manual_only'] as $manual_item) {
                $barcodes_in_pickings[$manual_item['code']] = true;
            }
        }
    }
    
    // Loop semua manual codes untuk cek apakah picking nya ada
    foreach ($all_manual_codes as $code) {
        // Skip jika barcode ini sudah ada di picking manapun
        if (isset($barcodes_in_pickings[$code])) {
            continue;
        }
        
        // Ambil sale_order_id dari barcode_item
        $sql_barcode_check = "SELECT sale_order_id, product_id FROM barcode_item WHERE barcode = ? LIMIT 1";
        $stmt_barcode_check = $conn->prepare($sql_barcode_check);
        if ($stmt_barcode_check) {
            $stmt_barcode_check->bind_param("s", $code);
            $stmt_barcode_check->execute();
            $result_barcode_check = $stmt_barcode_check->get_result();
            
            if ($result_barcode_check->num_rows > 0) {
                $barcode_info = $result_barcode_check->fetch_assoc();
                $sale_order_id = $barcode_info['sale_order_id'] ?? null;
                $product_id = $barcode_info['product_id'] ?? null;
                
                // Cek apakah sale_order_id ini ada di existing_sale_ids
                // Jika tidak ada, berarti picking nya tidak ada di Odoo
                if ($sale_order_id && !in_array($sale_order_id, $existing_sale_ids)) {
                    // Barcode ini tidak punya picking di Odoo
                    // Cari atau buat grup berdasarkan sale_order_id
                    $group_key = $sale_order_id ?? 'unknown';
                    
                    if (!isset($barcodes_without_picking[$group_key])) {
                        // Ambil sale order name dari Odoo jika ada
                        $sale_order_name_for_group = '';
                        if ($sale_order_id && $sale_order_id > 0) {
                            $sale_order_check = callOdooRead($username, 'sale.order', [['id', '=', $sale_order_id]], ['name']);
                            if ($sale_order_check && !empty($sale_order_check)) {
                                $sale_order_name_for_group = $sale_order_check[0]['name'] ?? '';
                            }
                        }
                        
                        $barcodes_without_picking[$group_key] = [
                            'sale_order_id' => $sale_order_id,
                            'sale_order_name' => $sale_order_name_for_group,
                            'barcodes' => []
                        ];
                    }
                    
                    $barcodes_without_picking[$group_key]['barcodes'][] = [
                        'barcode' => $code,
                        'product_id' => $product_id
                    ];
                }
            }
            $stmt_barcode_check->close();
        }
    }
    
    // Hitung jumlah barcode di Odoo dan manual stuffing untuk setiap grup
    foreach ($barcodes_without_picking as $group_key => &$group) {
        $sale_order_id = $group['sale_order_id'];
        
        // Hitung jumlah barcode di manual stuffing untuk sale_order_id ini
        $count_manual = 0;
        if ($sale_order_id) {
            // Ambil semua barcode dari shipping_manual_stuffing yang terkait dengan sale_order_id ini
            $sql_count_manual = "SELECT COUNT(DISTINCT sms.production_code) as count 
                                FROM shipping_manual_stuffing sms
                                INNER JOIN barcode_item bi ON bi.barcode = sms.production_code
                                WHERE sms.id_shipping = ? AND bi.sale_order_id = ?";
            $stmt_count_manual = $conn->prepare($sql_count_manual);
            if ($stmt_count_manual) {
                $stmt_count_manual->bind_param("ii", $shipping_id, $sale_order_id);
                $stmt_count_manual->execute();
                $result_count_manual = $stmt_count_manual->get_result();
                if ($result_count_manual->num_rows > 0) {
                    $count_data = $result_count_manual->fetch_assoc();
                    $count_manual = intval($count_data['count']);
                }
                $stmt_count_manual->close();
            }
        }
        
        // Hitung jumlah barcode di Odoo untuk sale_order_id ini
        // Cari semua picking yang terkait dengan sale_order_id ini (meskipun tidak ada di shipping ini)
        $count_odoo = 0;
        if ($sale_order_id) {
            // Cari picking di Odoo yang punya sale_id ini
            $pickings_for_sale = callOdooRead($username, 'stock.picking', [['sale_id', '=', $sale_order_id]], ['move_line_ids_without_package']);
            
            if ($pickings_for_sale && is_array($pickings_for_sale)) {
                $all_lot_names = [];
                foreach ($pickings_for_sale as $picking) {
                    $move_line_ids = $picking['move_line_ids_without_package'] ?? [];
                    if (!empty($move_line_ids)) {
                        $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name']);
                        if ($move_lines && is_array($move_lines)) {
                            foreach ($move_lines as $line) {
                                $lot_name = null;
                                if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                                    $lot_name = $line['lot_id'][1];
                                } else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                                    $lot_name = $line['lot_name'];
                                }
                                if ($lot_name !== null && $lot_name !== '') {
                                    $all_lot_names[$lot_name] = true;
                                }
                            }
                        }
                    }
                }
                $count_odoo = count($all_lot_names);
            }
        }
        
        $group['count_odoo'] = $count_odoo;
        $group['count_manual'] = $count_manual;
    }
    unset($group);
    
    // Convert to indexed array dan urutkan
    $barcodes_without_picking_list = [];
    foreach ($barcodes_without_picking as $group) {
        $barcodes_without_picking_list[] = $group;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $comparison_data,
        'barcodes_without_picking' => $barcodes_without_picking_list
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>

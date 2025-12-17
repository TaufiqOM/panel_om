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
    
    // Group pickings berdasarkan sale_id
    $pickings_by_sale = [];
    while ($picking = $result_pickings->fetch_assoc()) {
        $sale_id = $picking['sale_id'] ?? 0;
        if (!isset($pickings_by_sale[$sale_id])) {
            $pickings_by_sale[$sale_id] = [];
        }
        $pickings_by_sale[$sale_id][] = $picking;
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
    
    // Loop per sale_id
    foreach ($pickings_by_sale as $sale_id => $pickings) {
        // Kumpulkan semua lot_ids dari semua picking dengan sale_id yang sama
        $all_odoo_lots = [];
        $picking_names = [];
        $po_refs = [];
        
        foreach ($pickings as $picking) {
            $picking_id = $picking['id'];
            $picking_name = $picking['name'];
            $picking_names[] = $picking_name;
            if (!empty($picking['client_order_ref'])) {
                $po_refs[] = $picking['client_order_ref'];
            }
            
            // Ambil lot_ids langsung dari Odoo (realtime) untuk picking ini
            // Get picking data dari Odoo untuk ambil move_line_ids
            $picking_odoo = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_line_ids_without_package']);
            
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
                                
                                // Check if lot already exists (avoid duplicates) - check by lot_name AND product_id
                                $exists = false;
                                foreach ($all_odoo_lots as $existing_lot) {
                                    if ($existing_lot['lot_name'] === $lot_name && $existing_lot['product_id'] == $product_id) {
                                        $exists = true;
                                        break;
                                    }
                                }
                                if (!$exists) {
                                    $all_odoo_lots[] = [
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
            }
        }
        
        // Bandingkan lot_names dengan production_codes untuk sale_id ini
        // Perhatikan: manual stuffing hanya punya production_code (lot_name), tidak punya product_id
        // Jadi kita perlu memastikan lot_name cocok (product_id sudah tersimpan di all_odoo_lots)
        $matched = [];
        $odoo_only = [];
        $manual_only = [];
        
        // Convert to arrays for comparison
        $odoo_lot_names = array_column($all_odoo_lots, 'lot_name');
        
        // Find matches: lot_name harus ada di manual (product_id sudah tersimpan di lot data)
        foreach ($all_odoo_lots as $lot) {
            if (in_array($lot['lot_name'], $all_manual_codes)) {
                $matched[] = [
                    'code' => $lot['lot_name'],
                    'product_id' => $lot['product_id'],
                    'product' => $lot['product_name'],
                    'qty' => $lot['qty_done'],
                    'source' => 'both'
                ];
            } else {
                $odoo_only[] = [
                    'code' => $lot['lot_name'],
                    'product_id' => $lot['product_id'],
                    'product' => $lot['product_name'],
                    'qty' => $lot['qty_done'],
                    'source' => 'odoo'
                ];
            }
        }
        
        // Find manual-only codes (yang ada di manual tapi tidak ada di lot_ids sale_id ini)
        foreach ($all_manual_codes as $code) {
            if (!in_array($code, $odoo_lot_names)) {
                $manual_only[] = [
                    'code' => $code,
                    'product_id' => null,
                    'product' => '-',
                    'qty' => '-',
                    'source' => 'manual'
                ];
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
        
        // Group data per product_id untuk detail yang lebih rinci
        $products_data = [];
        
        // Group matched, odoo_only, manual_only per product_id
        foreach ($matched as $item) {
            $prod_id = $item['product_id'] ?? 0;
            if (!isset($products_data[$prod_id])) {
                $products_data[$prod_id] = [
                    'product_id' => $prod_id,
                    'product_name' => $item['product'] ?? '-',
                    'matched' => [],
                    'odoo_only' => [],
                    'manual_only' => [],
                    'total_matched' => 0,
                    'total_odoo_only' => 0,
                    'total_manual_only' => 0
                ];
            }
            $products_data[$prod_id]['matched'][] = $item;
            $products_data[$prod_id]['total_matched']++;
        }
        
        foreach ($odoo_only as $item) {
            $prod_id = $item['product_id'] ?? 0;
            if (!isset($products_data[$prod_id])) {
                $products_data[$prod_id] = [
                    'product_id' => $prod_id,
                    'product_name' => $item['product'] ?? '-',
                    'matched' => [],
                    'odoo_only' => [],
                    'manual_only' => [],
                    'total_matched' => 0,
                    'total_odoo_only' => 0,
                    'total_manual_only' => 0
                ];
            }
            $products_data[$prod_id]['odoo_only'][] = $item;
            $products_data[$prod_id]['total_odoo_only']++;
        }
        
        foreach ($manual_only as $item) {
            // Manual only tidak punya product_id, jadi kita perlu cari dari Odoo atau set ke 0
            $prod_id = $item['product_id'] ?? 0;
            if (!isset($products_data[$prod_id])) {
                $products_data[$prod_id] = [
                    'product_id' => $prod_id,
                    'product_name' => '-',
                    'matched' => [],
                    'odoo_only' => [],
                    'manual_only' => [],
                    'total_matched' => 0,
                    'total_odoo_only' => 0,
                    'total_manual_only' => 0
                ];
            }
            $products_data[$prod_id]['manual_only'][] = $item;
            $products_data[$prod_id]['total_manual_only']++;
        }
        
        // Convert to indexed array
        $products_list = array_values($products_data);
        
        // Get picking details per product (untuk detail per picking)
        $picking_details = [];
        foreach ($pickings as $picking) {
            $picking_id = $picking['id'];
            $picking_name = $picking['name'];
            
            // Ambil lot_ids untuk picking ini
            $picking_odoo = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_line_ids_without_package']);
            $picking_lots = [];
            
            if ($picking_odoo && !empty($picking_odoo)) {
                $move_line_ids = $picking_odoo[0]['move_line_ids_without_package'] ?? [];
                if (!empty($move_line_ids)) {
                    $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name', 'product_id', 'qty_done']);
                    
                    if ($move_lines && is_array($move_lines)) {
                        foreach ($move_lines as $line) {
                            $lot_name = null;
                            $product_id = null;
                            
                            if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                                $lot_name = $line['lot_id'][1];
                            } else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                                $lot_name = $line['lot_name'];
                            }
                            
                            if (isset($line['product_id']) && is_array($line['product_id']) && count($line['product_id']) >= 2) {
                                $product_id = $line['product_id'][0];
                            }
                            
                            if ($lot_name !== null && $lot_name !== '') {
                                $picking_lots[] = [
                                    'lot_name' => $lot_name,
                                    'product_id' => $product_id,
                                    'matched' => in_array($lot_name, $all_manual_codes)
                                ];
                            }
                        }
                    }
                }
            }
            
            $picking_details[] = [
                'picking_id' => $picking_id,
                'picking_name' => $picking_name,
                'lots' => $picking_lots
            ];
        }
        
        // Tambahkan product_name ke setiap product untuk display
        foreach ($products_list as &$prod) {
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
        
        // Deteksi manual stuffing yang tidak ada di picking manapun
        $orphan_codes = [];
        $all_odoo_lot_names = array_column($all_odoo_lots, 'lot_name');
        
        foreach ($all_manual_codes as $manual_code) {
            if (!in_array($manual_code, $all_odoo_lot_names)) {
                // Code ini tidak ada di picking manapun
                // Coba identifikasi picking mana yang seharusnya memiliki code ini
                $suggested_pickings = [];
                
                // Strategy 1: Cek apakah ada lot dengan pattern serupa di picking
                // Strategy 2: Cek berdasarkan product_id jika bisa diidentifikasi dari lot_name
                // Strategy 3: Jika tidak bisa diidentifikasi, suggest semua picking
                
                // Untuk sekarang, kita akan suggest semua picking dalam sale_id ini
                // karena tidak ada informasi product_id di manual stuffing
                foreach ($pickings as $picking) {
                    $suggested_pickings[] = [
                        'picking_id' => $picking['id'],
                        'picking_name' => $picking['name']
                    ];
                }
                
                // Cek di barcode_item untuk mendapatkan product_id dan sale_order_id
                $product_id_suggestion = null;
                $sale_order_id_suggestion = null;
                
                // Query barcode_item untuk mendapatkan product_id dan sale_order_id
                $sql_barcode = "SELECT product_id, sale_order_id FROM barcode_item WHERE barcode = ? LIMIT 1";
                $stmt_barcode = $conn->prepare($sql_barcode);
                if ($stmt_barcode) {
                    $stmt_barcode->bind_param("s", $manual_code);
                    $stmt_barcode->execute();
                    $result_barcode = $stmt_barcode->get_result();
                    if ($result_barcode->num_rows > 0) {
                        $barcode_data = $result_barcode->fetch_assoc();
                        $product_id_suggestion = $barcode_data['product_id'];
                        $sale_order_id_suggestion = $barcode_data['sale_order_id'];
                    }
                    $stmt_barcode->close();
                }
                
                $suggested_pickings = [];
                $suggested_pickings_other_shipping = [];
                
                // Strategy 1: Cari berdasarkan sale_order_id (prioritas tertinggi)
                if ($sale_order_id_suggestion && $sale_order_id_suggestion > 0) {
                    // Cari picking di database lokal berdasarkan sale_order_id
                    $sql_pickings_by_sale = "SELECT sd.id, sd.name, sd.id_shipping, s.name as shipping_name 
                                             FROM shipping_detail sd 
                                             INNER JOIN shipping s ON sd.id_shipping = s.id 
                                             WHERE sd.sale_id = ? 
                                             ORDER BY sd.id_shipping, sd.name";
                    $stmt_pickings_by_sale = $conn->prepare($sql_pickings_by_sale);
                    if ($stmt_pickings_by_sale) {
                        $stmt_pickings_by_sale->bind_param("i", $sale_order_id_suggestion);
                        $stmt_pickings_by_sale->execute();
                        $result_pickings_by_sale = $stmt_pickings_by_sale->get_result();
                        
                        while ($row = $result_pickings_by_sale->fetch_assoc()) {
                            if ($row['id_shipping'] == $shipping_id) {
                                // Picking di shipping saat ini
                                $suggested_pickings[] = [
                                    'picking_id' => $row['id'],
                                    'picking_name' => $row['name'],
                                    'shipping_id' => $row['id_shipping'],
                                    'shipping_name' => $row['shipping_name'],
                                    'reason' => 'Berdasarkan Sale Order ID: ' . $sale_order_id_suggestion . ' (di shipping ini)'
                                ];
                            } else {
                                // Picking di shipping lain
                                $suggested_pickings_other_shipping[] = [
                                    'picking_id' => $row['id'],
                                    'picking_name' => $row['name'],
                                    'shipping_id' => $row['id_shipping'],
                                    'shipping_name' => $row['shipping_name'],
                                    'reason' => 'Berdasarkan Sale Order ID: ' . $sale_order_id_suggestion . ' (di shipping lain)'
                                ];
                            }
                        }
                        $stmt_pickings_by_sale->close();
                    }
                    
                    // Juga cari di Odoo untuk memastikan
                    $odoo_pickings = callOdooRead($username, 'stock.picking', [['sale_id', '=', $sale_order_id_suggestion]], ['id', 'name', 'batch_id']);
                    if ($odoo_pickings && is_array($odoo_pickings)) {
                        foreach ($odoo_pickings as $op) {
                            $op_id = $op['id'];
                            $op_name = $op['name'];
                            
                            // Cek apakah picking ini sudah ada di suggested_pickings
                            $exists = false;
                            foreach ($suggested_pickings as $sp) {
                                if ($sp['picking_id'] == $op_id) {
                                    $exists = true;
                                    break;
                                }
                            }
                            foreach ($suggested_pickings_other_shipping as $sp) {
                                if ($sp['picking_id'] == $op_id) {
                                    $exists = true;
                                    break;
                                }
                            }
                            
                            if (!$exists) {
                                // Cek apakah picking ini ada di shipping saat ini
                                $in_current_shipping = false;
                                foreach ($pickings as $p) {
                                    if ($p['id'] == $op_id) {
                                        $in_current_shipping = true;
                                        break;
                                    }
                                }
                                
                                if ($in_current_shipping) {
                                    $suggested_pickings[] = [
                                        'picking_id' => $op_id,
                                        'picking_name' => $op_name,
                                        'shipping_id' => $shipping_id,
                                        'shipping_name' => 'Shipping saat ini',
                                        'reason' => 'Berdasarkan Sale Order ID: ' . $sale_order_id_suggestion . ' (ditemukan di Odoo)'
                                    ];
                                } else {
                                    $suggested_pickings_other_shipping[] = [
                                        'picking_id' => $op_id,
                                        'picking_name' => $op_name,
                                        'shipping_id' => null,
                                        'shipping_name' => 'Shipping lain (Odoo)',
                                        'reason' => 'Berdasarkan Sale Order ID: ' . $sale_order_id_suggestion . ' (di shipping lain)'
                                    ];
                                }
                            }
                        }
                    }
                }
                
                // Strategy 2: Jika tidak ada berdasarkan sale_order_id, cari berdasarkan product_id
                if (empty($suggested_pickings) && empty($suggested_pickings_other_shipping) && $product_id_suggestion) {
                    // Cari di picking saat ini yang memiliki product_id tersebut
                    foreach ($picking_details as $p_detail) {
                        $has_product = false;
                        foreach ($p_detail['lots'] as $lot) {
                            if ($lot['product_id'] == $product_id_suggestion) {
                                $has_product = true;
                                break;
                            }
                        }
                        if ($has_product) {
                            $suggested_pickings[] = [
                                'picking_id' => $p_detail['picking_id'],
                                'picking_name' => $p_detail['picking_name'],
                                'shipping_id' => $shipping_id,
                                'shipping_name' => 'Shipping saat ini',
                                'reason' => 'Memiliki product_id: ' . $product_id_suggestion
                            ];
                        }
                    }
                    
                    // Cari di shipping lain yang memiliki product_id tersebut
                    $sql_pickings_by_product = "SELECT DISTINCT sd.id, sd.name, sd.id_shipping, s.name as shipping_name 
                                                 FROM shipping_detail sd 
                                                 INNER JOIN shipping s ON sd.id_shipping = s.id 
                                                 WHERE sd.id_shipping != ? 
                                                 AND sd.sale_id = ?
                                                 ORDER BY sd.id_shipping, sd.name";
                    $stmt_pickings_by_product = $conn->prepare($sql_pickings_by_product);
                    if ($stmt_pickings_by_product && $sale_id) {
                        $stmt_pickings_by_product->bind_param("ii", $shipping_id, $sale_id);
                        $stmt_pickings_by_product->execute();
                        $result_pickings_by_product = $stmt_pickings_by_product->get_result();
                        
                        while ($row = $result_pickings_by_product->fetch_assoc()) {
                            // Cek di Odoo apakah picking ini memiliki product_id tersebut
                            $op_check = callOdooRead($username, 'stock.picking', [['id', '=', $row['id']]], ['move_line_ids_without_package']);
                            if ($op_check && !empty($op_check)) {
                                $move_line_ids = $op_check[0]['move_line_ids_without_package'] ?? [];
                                if (!empty($move_line_ids)) {
                                    $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['product_id']);
                                    $has_product = false;
                                    foreach ($move_lines as $ml) {
                                        if (isset($ml['product_id']) && is_array($ml['product_id']) && $ml['product_id'][0] == $product_id_suggestion) {
                                            $has_product = true;
                                            break;
                                        }
                                    }
                                    if ($has_product) {
                                        $suggested_pickings_other_shipping[] = [
                                            'picking_id' => $row['id'],
                                            'picking_name' => $row['name'],
                                            'shipping_id' => $row['id_shipping'],
                                            'shipping_name' => $row['shipping_name'],
                                            'reason' => 'Memiliki product_id: ' . $product_id_suggestion . ' (di shipping lain)'
                                        ];
                                    }
                                }
                            }
                        }
                        $stmt_pickings_by_product->close();
                    }
                }
                
                // Strategy 3: Jika masih tidak ada, suggest semua picking di sale_id ini
                if (empty($suggested_pickings) && empty($suggested_pickings_other_shipping)) {
                    foreach ($pickings as $picking) {
                        $suggested_pickings[] = [
                            'picking_id' => $picking['id'],
                            'picking_name' => $picking['name'],
                            'shipping_id' => $shipping_id,
                            'shipping_name' => 'Shipping saat ini',
                            'reason' => 'Tidak dapat diidentifikasi, suggest semua picking di sale order ini'
                        ];
                    }
                }
                
                // Cek apakah sale_order_id berbeda dengan sale_id saat ini
                $wrong_sale_order = false;
                if ($sale_order_id_suggestion && $sale_order_id_suggestion != $sale_id) {
                    $wrong_sale_order = true;
                }
                
                $orphan_codes[] = [
                    'code' => $manual_code,
                    'product_id' => $product_id_suggestion,
                    'sale_order_id' => $sale_order_id_suggestion,
                    'suggested_pickings' => $suggested_pickings,
                    'suggested_pickings_other_shipping' => $suggested_pickings_other_shipping,
                    'reason' => 'Tidak ditemukan di picking manapun di sale_id ini',
                    'wrong_sale_order' => $wrong_sale_order,
                    'current_sale_id' => $sale_id
                ];
            }
        }
        
        $comparison_data[] = [
            'sale_id' => $sale_id,
            'sale_order_name' => $sale_order_name,
            'picking_names' => $picking_names,
            'po' => implode(', ', array_unique($po_refs)),
            'matched' => $matched,
            'odoo_only' => $odoo_only,
            'manual_only' => $manual_only,
            'total_odoo' => count($all_odoo_lots),
            'total_manual' => count($all_manual_codes),
            'total_matched' => count($matched),
            'has_matched' => count($matched) > 0,
            'products' => $products_list,
            'picking_details' => $picking_details,
            'orphan_codes' => $orphan_codes,
            'total_orphan' => count($orphan_codes)
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

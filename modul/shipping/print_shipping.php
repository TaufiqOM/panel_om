<?php

session_start();

// File Conf
require __DIR__ . '/../../inc/config.php';
require __DIR__ . '/../../inc/config_odoo.php';

// Set timezone ke WIB (UTC+7)
date_default_timezone_set('Asia/Jakarta');

// Start Session untuk Odoo
$username = $_SESSION['username'] ?? '';

// Ambil data id dari GET atau POST
$shipping_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
if (!$shipping_id) {
    echo "Shipping ID tidak valid.";
    exit;
}

// Ambil detail shipping dari local database
$sql = "SELECT id, name, sheduled_date, description, ship_to FROM shipping WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $shipping_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Shipping tidak ditemukan.";
    exit;
}

$shipping = $result->fetch_assoc();
$stmt->close();

// Ambil data pickings untuk shipping ini
$sql_pickings = "SELECT id, name, sale_id, client_order_ref FROM shipping_detail WHERE id_shipping = ? ORDER BY sale_id, name";
$stmt_pickings = $conn->prepare($sql_pickings);
$stmt_pickings->bind_param("i", $shipping_id);
$stmt_pickings->execute();
$result_pickings = $stmt_pickings->get_result();

$all_pickings = [];
while ($picking = $result_pickings->fetch_assoc()) {
    $all_pickings[] = $picking;
}
$stmt_pickings->close();

// Ambil semua production_code dari shipping_manual_stuffing untuk mapping
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

// Struktur baru: picking -> sale_order -> product
$structured_data = [];

// Loop per picking untuk mengumpulkan data
foreach ($all_pickings as $picking_data) {
    $picking_id = $picking_data['id'];
    $picking_name = $picking_data['name'];
    $sale_id = $picking_data['sale_id'] ?? 0;
    $client_order_ref = $picking_data['client_order_ref'] ?? '';
    
    // Ambil lots dari Odoo untuk picking ini
    $picking_odoo = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_line_ids_without_package']);
    
    $picking_lots = [];
    if ($picking_odoo && !empty($picking_odoo)) {
        $move_line_ids = $picking_odoo[0]['move_line_ids_without_package'] ?? [];
        
        if (!empty($move_line_ids)) {
            $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name', 'product_id', 'qty_done']);
            
            if ($move_lines && is_array($move_lines)) {
                foreach ($move_lines as $line) {
                    $lot_name = null;
                    
                    if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                        $lot_name = $line['lot_id'][1];
                    } else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                        $lot_name = $line['lot_name'];
                    }
                    
                    if ($lot_name && in_array($lot_name, $all_manual_codes)) {
                        // Ambil product info
                        $product_id = null;
                        $product_name = null;
                        if (isset($line['product_id']) && is_array($line['product_id']) && count($line['product_id']) >= 2) {
                            $product_id = $line['product_id'][0];
                            $product_name = $line['product_id'][1];
                        }
                        
                        $picking_lots[] = [
                            'lot_name' => $lot_name,
                            'product_id' => $product_id,
                            'product_name' => $product_name,
                            'qty_done' => $line['qty_done'] ?? 0
                        ];
                    }
                }
            }
        }
    }
    
    // Jika tidak ada lots dari Odoo, ambil dari manual stuffing saja
    if (empty($picking_lots)) {
        // Ambil production codes dari manual stuffing untuk picking ini
        $sql_manual_picking = "SELECT DISTINCT sms.production_code 
            FROM shipping_manual_stuffing sms
            WHERE sms.id_shipping = ?
            ORDER BY sms.production_code";
        $stmt_manual_picking = $conn->prepare($sql_manual_picking);
        $stmt_manual_picking->bind_param("i", $shipping_id);
        $stmt_manual_picking->execute();
        $result_manual_picking = $stmt_manual_picking->get_result();
        
        while ($manual_row = $result_manual_picking->fetch_assoc()) {
            $lot_name = $manual_row['production_code'];
            
            // Cek apakah lot ini ada di all_manual_codes
            if (in_array($lot_name, $all_manual_codes)) {
                $picking_lots[] = [
                    'lot_name' => $lot_name,
                    'product_id' => 0, // Tidak ada product_id dari Odoo
                    'product_name' => null, // Akan diambil dari local DB
                    'qty_done' => 1
                ];
            }
        }
        $stmt_manual_picking->close();
        
        // Jika masih tidak ada data, skip picking ini
        if (empty($picking_lots)) {
            continue;
        }
    }
    
    // Gunakan client_order_ref dari DB lokal sebagai sale order info
    // Karena sale_order_name tidak ada di DB, kita gunakan client_order_ref (PO) atau sale_id
    $sale_order_name = $client_order_ref ?: ($sale_id ? "SO-$sale_id" : '');
    
    // Group lots per product_id dalam picking ini
    $products_in_picking = [];
    
    foreach ($picking_lots as $lot) {
        $product_id = $lot['product_id'] ?? 0;
        $lot_name = $lot['lot_name'];
        
        if (!isset($products_in_picking[$product_id])) {
            $products_in_picking[$product_id] = [
                'product_id' => $product_id,
                'product_name' => $lot['product_name'] ?? '-',
                'items' => []
            ];
        }
        
        // Ambil data manual stuffing untuk lot ini, termasuk nama produk dari local DB
        $sql_manual_detail = "SELECT 
            sms.production_code,
            pls.sale_order_ref AS client_order_ref,
            COALESCE(bl.product_ref, bi_lot.product_ref) AS product_ref,
            COALESCE(bl.finishing, bi_lot.finishing) AS finishing,
            COALESCE(bl.product_name, bi_lot.product_name) AS local_product_name,
            TIME(sms.created_at) AS created_time
        FROM shipping_manual_stuffing sms
        LEFT JOIN production_lots_strg pls ON pls.production_code = sms.production_code
        LEFT JOIN barcode_item bi ON bi.barcode = sms.production_code
        LEFT JOIN barcode_lot bi_lot ON bi_lot.id = bi.lot_id
        LEFT JOIN barcode_lot bl ON bl.sale_order_ref = pls.sale_order_ref AND bl.product_id = pls.product_code
        WHERE sms.id_shipping = ? AND sms.production_code = ?
        LIMIT 1";
        
        $stmt_detail = $conn->prepare($sql_manual_detail);
        $stmt_detail->bind_param("is", $shipping_id, $lot_name);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        $manual_data = $result_detail->fetch_assoc();
        $stmt_detail->close();
        
        // Gunakan nama produk dari Odoo jika tersedia (prioritas), fallback ke local DB
        // Fix: Gunakan local_product_name jika tersedia, product_ref hanya sebagai fallback terakhir
        $final_product_name = $lot['product_name'] ?? $manual_data['local_product_name'] ?? 'Unknown';
        
        // Update product_name di products_in_picking jika belum ada atau masih default
        if (empty($products_in_picking[$product_id]['product_name']) || 
            $products_in_picking[$product_id]['product_name'] === '-' || 
            $products_in_picking[$product_id]['product_name'] === 'Unknown') {
            $products_in_picking[$product_id]['product_name'] = $final_product_name;
        }
        
        $products_in_picking[$product_id]['items'][] = [
            'production_code' => $lot_name,
            'client_order_ref' => $manual_data['client_order_ref'] ?? $client_order_ref,
            'product_ref' => $manual_data['product_ref'] ?? null,
            'finishing' => $manual_data['finishing'] ?? null,
            'created_time' => $manual_data['created_time'] ?? '-',
            'qty_done' => $lot['qty_done'],
            'local_product_name' => $final_product_name
        ];
    }
    
    // Tambahkan ke structured_data
    if (!empty($products_in_picking)) {
        $structured_data[] = [
            'picking_id' => $picking_id,
            'picking_name' => $picking_name,
            'sale_id' => $sale_id,
            'sale_order_name' => $sale_order_name,
            'client_order_ref' => $client_order_ref,
            'products' => array_values($products_in_picking)
        ];
    }
}

// Ambil nama SO dari Odoo untuk semua sale_id yang ada
$sale_id_to_name = [];
$unique_sale_ids = [];
foreach ($structured_data as $picking_group) {
    $sale_id = $picking_group['sale_id'] ?? 0;
    if ($sale_id > 0 && !in_array($sale_id, $unique_sale_ids)) {
        $unique_sale_ids[] = $sale_id;
    }
}

// Ambil nama SO dari Odoo
if (!empty($unique_sale_ids) && !empty($username)) {
    $sale_orders = callOdooRead($username, 'sale.order', [['id', 'in', $unique_sale_ids]], ['id', 'name']);
    if ($sale_orders && is_array($sale_orders)) {
        foreach ($sale_orders as $so) {
            $so_id = $so['id'] ?? 0;
            $so_name = $so['name'] ?? '';
            if ($so_id > 0 && !empty($so_name)) {
                $sale_id_to_name[$so_id] = $so_name;
            }
        }
    }
}

    // Buat mapping production_code -> picking/so info untuk efisiensi
$code_to_picking_map = [];
foreach ($structured_data as $picking_group) {
    $picking_name = $picking_group['picking_name'];
    $sale_id = $picking_group['sale_id'] ?? 0;
    
    // Gunakan client_order_ref dari DB lokal, fallback ke nama SO dari Odoo, atau sale_order_name jika ada
    $so_info = $picking_group['client_order_ref'] ?: 
               ($sale_id > 0 && isset($sale_id_to_name[$sale_id]) ? $sale_id_to_name[$sale_id] : '') ?: 
               $picking_group['sale_order_name'] ?: 
               ($sale_id ? "SO-{$sale_id}" : '');
    
    foreach ($picking_group['products'] as $product) {
        foreach ($product['items'] as $item) {
            $production_code = $item['production_code'] ?? '';
            if ($production_code) {
                $code_to_picking_map[$production_code] = [
                    'picking_name' => $picking_name,
                    'so_info' => $so_info
                ];
            }
        }
    }
}

// Flatten untuk kompatibilitas dengan kode M3 yang sudah ada
// Convert structured_data menjadi grouped_data format lama untuk M3 calculation
$grouped_data = [];
$used_codes = [];

foreach ($structured_data as $picking_group) {
    foreach ($picking_group['products'] as $product) {
        $product_id = $product['product_id'] ?? 0;
        $group_key = $product_id ? "product_{$product_id}" : "name_" . md5($product['product_name'] ?? 'Unknown');
        
        if (!isset($grouped_data[$group_key])) {
            $grouped_data[$group_key] = [
                'items' => [],
                'product_id' => $product_id,
                'product_name' => $product['product_name'] ?? 'Unknown',
                'product_ref' => null,
                'finishing' => null,
                'qty' => 0,
                'tot_part' => 0,
                'default_code' => null // Akan di-assign nanti dari Odoo jika ada
            ];
        }
        
        // Tambahkan items dari product ini
        foreach ($product['items'] as $item) {
            $production_code = $item['production_code'] ?? '';
            
            // Skip jika sudah digunakan
            if (empty($production_code) || isset($used_codes[$production_code])) {
                continue;
            }
            
            // Gunakan product_name dari Odoo jika tersedia (prioritas), fallback ke local
            // Ini sesuai dengan print_manual_stuffing.php
            $item_product_name = $product['product_name'] ?? $item['local_product_name'] ?? 'Unknown';
            
            $grouped_data[$group_key]['items'][] = [
                'production_code' => $production_code,
                'client_order_ref' => $item['client_order_ref'] ?? '',
                'product_id' => $product_id,
                'product_name' => $item_product_name,
                'product_ref' => $item['product_ref'] ?? null,
                'finishing' => $item['finishing'] ?? null,
                'created_time' => $item['created_time'] ?? '-'
            ];
            
            $grouped_data[$group_key]['qty']++;
            $grouped_data[$group_key]['tot_part']++;
            $used_codes[$production_code] = true;
            
            // Update product_name dengan local name jika tersedia
            if (!empty($item_product_name) && $item_product_name !== 'Unknown') {
                $grouped_data[$group_key]['product_name'] = $item_product_name;
            }
            
            // Update finishing dan product_ref jika belum ada
            if (empty($grouped_data[$group_key]['finishing']) && !empty($item['finishing'])) {
                $grouped_data[$group_key]['finishing'] = $item['finishing'];
            }
            if (empty($grouped_data[$group_key]['product_ref']) && !empty($item['product_ref'])) {
                $grouped_data[$group_key]['product_ref'] = $item['product_ref'];
            }
        }
    }
}

// Tambahkan production code yang belum ada di Odoo (tidak ada di structured_data)
// Ambil semua production code dari shipping_manual_stuffing yang belum masuk ke used_codes
$stmt_missing = $conn->prepare("SELECT DISTINCT sms.production_code
FROM shipping_manual_stuffing sms
WHERE sms.id_shipping = ?");
$stmt_missing->bind_param("i", $shipping_id);
$stmt_missing->execute();
$result_missing = $stmt_missing->get_result();

$missing_codes = [];
while ($row = $result_missing->fetch_assoc()) {
    $code = $row['production_code'];
    if (!isset($used_codes[$code])) {
        $missing_codes[] = $code;
    }
}
$stmt_missing->close();

// Tambahkan missing codes ke grouped_data
if (!empty($missing_codes)) {
    foreach ($missing_codes as $production_code) {
        // Ambil data manual stuffing untuk production code ini, termasuk nama produk
        $sql_manual_detail = "SELECT 
            sms.production_code,
            pls.sale_order_ref AS client_order_ref,
            COALESCE(bl.product_ref, bi_lot.product_ref) AS product_ref,
            COALESCE(bl.finishing, bi_lot.finishing) AS finishing,
            COALESCE(bl.product_name, bi_lot.product_name) AS local_product_name,
            TIME(sms.created_at) AS created_time
        FROM shipping_manual_stuffing sms
        LEFT JOIN production_lots_strg pls ON pls.production_code = sms.production_code
        LEFT JOIN barcode_item bi ON bi.barcode = sms.production_code
        LEFT JOIN barcode_lot bi_lot ON bi_lot.id = bi.lot_id
        LEFT JOIN barcode_lot bl ON bl.sale_order_ref = pls.sale_order_ref AND bl.product_id = pls.product_code
        WHERE sms.id_shipping = ? AND sms.production_code = ?
        LIMIT 1";
        
        $stmt_detail = $conn->prepare($sql_manual_detail);
        $stmt_detail->bind_param("is", $shipping_id, $production_code);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        $manual_data = $result_detail->fetch_assoc();
        $stmt_detail->close();
        
        // Buat group key untuk produk yang tidak diketahui
        // Prioritas: product_ref > local_product_name > 'Unknown Product'
        // Untuk missing codes (tidak ada di Odoo), gunakan product_ref atau local name
        $product_ref = $manual_data['product_ref'] ?? null;
        $local_product_name = $manual_data['local_product_name'] ?? null;
        // Fix: Nama produk harusnya Nama, bukan Ref/Code. Prioritaskan local_product_name.
        $product_name = $local_product_name ?: ($product_ref ?: 'Unknown Product');
        
        // Group berdasarkan product_ref atau product_name
        if ($product_ref) {
            $group_key = "missing_product_" . md5($product_ref);
        } else if ($local_product_name) {
            $group_key = "missing_product_" . md5($local_product_name);
        } else {
            $group_key = "missing_" . md5($production_code);
        }
        
        if (!isset($grouped_data[$group_key])) {
            $grouped_data[$group_key] = [
                'items' => [],
                'product_id' => 0,
                'product_name' => $product_name,
                'product_ref' => $product_ref,
                'finishing' => $manual_data['finishing'] ?? null,
                'qty' => 0,
                'tot_part' => 0,
                'default_code' => null // Missing codes tidak punya product_id, jadi tidak bisa ambil default_code dari Odoo
            ];
        }
        
        $grouped_data[$group_key]['items'][] = [
            'production_code' => $production_code,
            'client_order_ref' => $manual_data['client_order_ref'] ?? '',
            'product_id' => 0,
            'product_name' => $product_name,
            'product_ref' => $product_ref,
            'finishing' => $manual_data['finishing'] ?? null,
            'created_time' => $manual_data['created_time'] ?? '-'
        ];
        
        $grouped_data[$group_key]['qty']++;
        $grouped_data[$group_key]['tot_part']++;
        $used_codes[$production_code] = true;
        
        // Update finishing dan product_ref jika belum ada
        if (empty($grouped_data[$group_key]['finishing']) && !empty($manual_data['finishing'])) {
            $grouped_data[$group_key]['finishing'] = $manual_data['finishing'];
        }
        if (empty($grouped_data[$group_key]['product_ref']) && !empty($product_ref)) {
            $grouped_data[$group_key]['product_ref'] = $product_ref;
        }
    }
}

// Sort grouped_data berdasarkan product_name untuk display
uasort($grouped_data, function($a, $b) {
    return strcmp($a['product_name'] ?? '', $b['product_name'] ?? '');
});

// Ambil M3 dari Odoo untuk setiap product
// Mengikuti cara di shipping_plan.xml: sum(p.cbm for p in move.product_id.packaging_ids)
// Kumpulkan semua product_id yang unik
error_log("=== M3 PREPARE: Mulai kumpulkan product_ids dari grouped_data ===");
$product_ids_for_m3 = [];
foreach ($grouped_data as $group_key => $product_data) {
    $product_id = $product_data['product_id'];
    $product_name = $product_data['product_name'] ?? 'N/A';
    error_log("=== M3 PREPARE: Checking group_key: $group_key, product_id: " . ($product_id ?: 'NULL') . ", product_name: $product_name ===");
    if ($product_id && !in_array($product_id, $product_ids_for_m3)) {
        $product_ids_for_m3[] = $product_id;
        error_log("=== M3 PREPARE: Added product_id: $product_id ($product_name) ===");
    } else {
        if (!$product_id) {
            error_log("=== M3 PREPARE: SKIP - product_id kosong untuk group_key: $group_key ===");
        } else {
            error_log("=== M3 PREPARE: SKIP - product_id $product_id sudah ada di list ===");
        }
    }
}
error_log("=== M3 PREPARE: Total product_ids untuk M3 fetch: " . count($product_ids_for_m3) . " ===");
error_log("=== M3 PREPARE: product_ids list: " . json_encode($product_ids_for_m3) . " ===");

// Ambil data M3 dari Odoo langsung dari product.packaging berdasarkan product_id atau product_tmpl_id
$m3_data = [];
$product_id_to_default_code = []; // Mapping product_id -> default_code (inisialisasi di luar scope)
$product_id_to_name = []; // Mapping product_id -> name (inisialisasi di luar scope)

if (!empty($product_ids_for_m3)) {
    if (empty($username)) {
        error_log("=== M3 FETCH: Warning: Username tidak ditemukan di session, skip M3 fetch ===");
    } else {
        error_log("=== M3 FETCH: Mulai fetch M3 untuk product_ids: " . json_encode($product_ids_for_m3) . " ===");
        
        // Ambil product.product untuk mendapatkan product_tmpl_id dan default_code
        $products = callOdooRead($username, 'product.product', [['id', 'in', $product_ids_for_m3]], ['id', 'name', 'product_tmpl_id', 'default_code']);
        
        $product_tmpl_ids = [];
        $product_id_to_tmpl = [];
        
        if ($products && is_array($products)) {
            foreach ($products as $product) {
                $prod_id = $product['id'] ?? null;
                $prod_name = $product['name'] ?? 'N/A';
                
                if (!$prod_id) continue;
                
                // Ambil default_code
                $default_code = $product['default_code'] ?? null;
                if ($default_code) {
                    $product_id_to_default_code[$prod_id] = $default_code;
                    error_log("=== DEFAULT_CODE FETCH: Product ID: $prod_id, Default Code: $default_code ===");
                }
                
                // Simpan real product name dari Odoo
                if ($prod_name && $prod_name !== 'N/A') {
                    $product_id_to_name[$prod_id] = $prod_name;
                }
                
                // Ambil product_tmpl_id
                $tmpl_id = null;
                if (isset($product['product_tmpl_id']) && is_array($product['product_tmpl_id']) && count($product['product_tmpl_id']) >= 1) {
                    $tmpl_id = $product['product_tmpl_id'][0];
                } else if (isset($product['product_tmpl_id']) && !is_array($product['product_tmpl_id'])) {
                    $tmpl_id = $product['product_tmpl_id'];
                }
                
                if ($tmpl_id) {
                    if (!in_array($tmpl_id, $product_tmpl_ids)) {
                        $product_tmpl_ids[] = $tmpl_id;
                    }
                    $product_id_to_tmpl[$prod_id] = $tmpl_id;
                }
                
                error_log("=== M3 FETCH: Product ID: $prod_id, Name: $prod_name, Template ID: " . ($tmpl_id ?? 'NULL') . " ===");
            }
        }
        
        // Ambil packaging berdasarkan product_id langsung
        error_log("=== M3 FETCH: Query packaging by product_id: " . json_encode($product_ids_for_m3) . " ===");
        $packagings_by_product = callOdooRead($username, 'product.packaging', [['product_id', 'in', $product_ids_for_m3]], ['id', 'product_id', 'cbm']);
        
        error_log("=== M3 FETCH: Packaging by product_id result count: " . (is_array($packagings_by_product) ? count($packagings_by_product) : 0) . " ===");
        
        // Ambil packaging berdasarkan product_tmpl_id
        $packagings_by_template = [];
        if (!empty($product_tmpl_ids)) {
            error_log("=== M3 FETCH: Query packaging by product_tmpl_id: " . json_encode($product_tmpl_ids) . " ===");
            $packagings_by_template = callOdooRead($username, 'product.packaging', [['product_tmpl_id', 'in', $product_tmpl_ids]], ['id', 'product_tmpl_id', 'cbm']);
            error_log("=== M3 FETCH: Packaging by product_tmpl_id result count: " . (is_array($packagings_by_template) ? count($packagings_by_template) : 0) . " ===");
        }
        
        // Process packaging by product_id
        if ($packagings_by_product && is_array($packagings_by_product)) {
            foreach ($packagings_by_product as $pkg) {
                $pkg_product_id = null;
                if (isset($pkg['product_id']) && is_array($pkg['product_id']) && count($pkg['product_id']) >= 1) {
                    $pkg_product_id = $pkg['product_id'][0];
                } else if (isset($pkg['product_id']) && !is_array($pkg['product_id'])) {
                    $pkg_product_id = $pkg['product_id'];
                }
                
                if ($pkg_product_id) {
                    $cmb = 0;
                    if (isset($pkg['cbm'])) {
                        $cmb_val = $pkg['cbm'];
                        if (is_numeric($cmb_val) && $cmb_val > 0) {
                            $cmb = floatval($cmb_val);
                            error_log("=== M3 FETCH: Product ID $pkg_product_id - CMB FOUND from packaging: $cmb ===");
                        }
                    }
                    
                    if ($cmb > 0) {
                        if (!isset($m3_data[$pkg_product_id])) {
                            $m3_data[$pkg_product_id] = 0;
                        }
                        $m3_data[$pkg_product_id] += $cmb;
                    }
                }
            }
        }
        
        // Process packaging by product_tmpl_id
        if ($packagings_by_template && is_array($packagings_by_template)) {
            foreach ($packagings_by_template as $pkg) {
                $pkg_tmpl_id = null;
                if (isset($pkg['product_tmpl_id']) && is_array($pkg['product_tmpl_id']) && count($pkg['product_tmpl_id']) >= 1) {
                    $pkg_tmpl_id = $pkg['product_tmpl_id'][0];
                } else if (isset($pkg['product_tmpl_id']) && !is_array($pkg['product_tmpl_id'])) {
                    $pkg_tmpl_id = $pkg['product_tmpl_id'];
                }
                
                if ($pkg_tmpl_id) {
                    $cmb = 0;
                    if (isset($pkg['cbm'])) {
                        $cmb_val = $pkg['cbm'];
                        if (is_numeric($cmb_val) && $cmb_val > 0) {
                            $cmb = floatval($cmb_val);
                            error_log("=== M3 FETCH: Template ID $pkg_tmpl_id - CMB FOUND from packaging: $cmb ===");
                        }
                    }
                    
                    if ($cmb > 0) {
                        // Assign ke semua product_id yang memiliki template ini
                        foreach ($product_id_to_tmpl as $prod_id => $tmpl_id) {
                            if ($tmpl_id == $pkg_tmpl_id) {
                                if (!isset($m3_data[$prod_id])) {
                                    $m3_data[$prod_id] = 0;
                                }
                                $m3_data[$prod_id] += $cmb;
                                error_log("=== M3 FETCH: Product ID $prod_id - Added CMB $cmb from template $tmpl_id ===");
                            }
                        }
                    }
                }
            }
        }
        
        error_log("=== M3 FETCH: Final M3 data: " . json_encode($m3_data) . " ===");
    }
} else {
    error_log("=== M3 FETCH: No product_ids to fetch ===");
}

// Assign M3 ke setiap product di grouped_data
error_log("=== M3 ASSIGN: Mulai assign M3 ke grouped_data ===");
error_log("=== M3 ASSIGN: Jumlah grouped_data: " . count($grouped_data) . " ===");
error_log("=== M3 ASSIGN: m3_data keys: " . json_encode(array_keys($m3_data)) . " ===");

foreach ($grouped_data as $group_key => &$product_data) {
    $product_id = $product_data['product_id'];
    $product_name = $product_data['product_name'] ?? 'N/A';
    $qty = $product_data['qty'];
    
    error_log("=== M3 ASSIGN: Processing group_key: $group_key, product_id: $product_id, product_name: $product_name, qty: $qty ===");
    
    if ($product_id) {
        if (isset($m3_data[$product_id])) {
            // M3 per unit (sum dari semua packaging cmb)
            $product_data['m3'] = $m3_data[$product_id];
            // Total M3 = M3 per unit × Qty
            $product_data['tot_m3'] = $m3_data[$product_id] * $qty;
            error_log("=== M3 ASSIGN: SUCCESS - Product ID $product_id ($product_name): m3={$product_data['m3']}, tot_m3={$product_data['tot_m3']}, qty=$qty ===");
        } else {
            $product_data['m3'] = 0;
            $product_data['tot_m3'] = 0;
            error_log("=== M3 ASSIGN: FAILED - Product ID $product_id ($product_name) tidak ada di m3_data. Available keys: " . json_encode(array_keys($m3_data)) . " ===");
        }
        
        // Update product_name dengan nama dari Odoo (jika ada) - Prioritas lebih tinggi daripada nama dari stock.move
        if (isset($product_id_to_name[$product_id]) && !empty($product_id_to_name[$product_id])) {
             $product_data['product_name'] = $product_id_to_name[$product_id];
             error_log("=== PRODUCT_NAME UPDATE: Product ID $product_id - Name updated to: {$product_data['product_name']} ===");
        }
        
    } else {
        $product_data['m3'] = 0;
        $product_data['tot_m3'] = 0;
        error_log("=== M3 ASSIGN: SKIP - Product ID kosong untuk group_key: $group_key ===");
    }
    
    // Assign default_code jika ada
    if ($product_id && isset($product_id_to_default_code[$product_id])) {
        $product_data['default_code'] = $product_id_to_default_code[$product_id];
        error_log("=== DEFAULT_CODE ASSIGN: Product ID $product_id - Default Code: {$product_data['default_code']} ===");
    } else {
        $product_data['default_code'] = null;
    }
}
unset($product_data);

error_log("=== M3 ASSIGN: Selesai assign M3 ===");

// Query untuk pickings tidak diperlukan karena langsung ke Product List

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Product - <?= htmlspecialchars($shipping['name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
            padding: 10mm 15mm;
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
        }
        
        .print-header {
            margin-bottom: 15px;
            padding-bottom: 8px;
        }
        
        .print-header table {
            border: none !important;
        }
        
        .print-header table tr {
            border: none !important;
        }
        
        .print-header table td {
            border: none !important;
        }
        
        .print-header h3 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 3px;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .print-header h5 {
            font-size: 11pt;
            font-weight: bold;
            color: #000;
            margin-top: 5px;
        }
        
        .info-section {
            margin-bottom: 10px;
            display: table;
            width: 100%;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 30%;
            padding: 3px 10px 3px 0;
            vertical-align: top;
            color: #000;
            font-size: 9pt;
        }
        
        .info-value {
            display: table-cell;
            padding: 3px 0;
            color: #000;
            font-size: 9pt;
        }
        
        .table-container {
            margin-top: 10px;
            page-break-inside: avoid;
        }
        
        .table-title {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        
        /* Kurangi jarak antara detail-row dan product-row berikutnya */
        .detail-row {
            margin-bottom: 0;
        }
        
        .product-row {
            margin-top: 0;
        }
        
        /* Kurangi spacing pada table rows */
        table.main-table tbody tr {
            margin: 0;
        }
        
        table.main-table tbody tr.detail-row {
            margin-top: 0;
            margin-bottom: 0;
        }
        
        table.main-table tbody tr.product-row {
            margin-top: 0;
            margin-bottom: 0;
        }
        
        table.main-table {
            border: 1px solid #000;
        }
        
        .product-row {
            background-color: white;
        }
        
        .product-row td {
            font-weight: bold;
            padding: 2px 3px;
        }
        
        .detail-row {
            background-color: #fafafa;
            margin: 0;
        }
        
        .detail-table {
            width: 100%;
            border: none;
            margin: 0;
        }
        
        .detail-table td {
            border: none;
            border-bottom: 1px solid #ddd;
            padding: 1px 3px;
            font-size: 7pt;
        }
        
        .detail-table tr:last-child td {
            border-bottom: none;
        }
        
        .detail-table th {
            background-color: #808080;
            color: #000;
            border: none;
            border-bottom: 1px solid #ccc;
            padding: 2px 3px;
            font-size: 7pt;
            text-align: left;
        }
        
        .warna-label {
            font-weight: bold;
            margin: 0;
            padding: 1px 3px;
            background-color: #f5f5f5;
            font-size: 8pt;
            color: #000;
            border-left: none;
            border-right: none;
        }
        
        thead {
            background-color: #000;
            color: #fff;
        }
        
        th {
            border: 1px solid #000;
            padding: 4px 3px;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
            background-color: #000;
            color: #fff;
        }
        
        td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 8pt;
            color: #000;
            vertical-align: top;
        }
        
        tbody tr {
            background-color: #fff;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-right {
            text-align: right;
        }
        
        .no-data {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        .footer-row {
            background-color: #f0f0f0;
        }
        
        .footer-row td {
            font-weight: bold;
            padding: 6px;
            font-size: 9pt;
        }
        
        .print-footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #000;
            text-align: right;
            font-size: 7pt;
            color: #000;
        }
        
        .summary-section {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-weight: bold;
        }
        
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            @page {
                size: A4 portrait;
                margin: 10mm 15mm;
                width: 210mm;
                height: 297mm;
            }
            
            body {
                width: 100% !important;
                min-height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
                line-height: 1.3 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .a4-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                min-height: auto !important;
                background: transparent !important;
            }
            
            .print-header {
                page-break-after: avoid;
                page-break-inside: avoid;
                margin-bottom: 5px;
            }
            
            .print-header h2 {
                font-size: 20pt !important;
            }
            
            .print-header h3 {
                font-size: 14pt !important;
            }
            
            .info-section {
                page-break-after: avoid;
                page-break-inside: avoid;
                margin-bottom: 3px;
            }
            
            .table-container {
                page-break-inside: auto;
                margin-top: 3px;
            }
            
            table.main-table {
                page-break-inside: auto;
                border-collapse: collapse;
            }
            
            table.main-table thead {
                display: table-header-group;
            }
            
            table.main-table tbody {
                display: table-row-group;
            }
            
            /* Pastikan setiap row tidak terpotong */
            table.main-table tbody tr {
                page-break-inside: avoid !important;
                page-break-after: auto;
            }
            
            /* Pastikan product-row dan detail-row selalu bersama - tidak terpisah */
            .product-row {
                page-break-after: avoid !important;
            }
            
            .product-row + .detail-row {
                page-break-before: avoid !important;
            }
            
            /* Jika detail-row terlalu panjang, boleh pindah halaman tapi tetap bersama product-row */
            .detail-row {
                orphans: 2;
                widows: 2;
            }
            
            /* Kurangi space kosong saat pagebreak */
            table.main-table tbody tr {
                margin: 0;
                padding: 0;
            }
            
            thead {
                display: table-header-group;
                background-color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            th {
                background-color: #000 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .product-row td {
                font-weight: bold !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .detail-row {
                background-color: #fafafa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .detail-table {
                page-break-inside: auto;
            }
            
            .detail-table tr {
                page-break-inside: avoid;
            }
            
            .detail-table th {
                background-color: #808080 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            /* Pastikan footer tidak terpotong */
            .footer-row {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                page-break-inside: avoid !important;
                page-break-before: auto;
            }
            
            .warna-label {
                background-color: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            /* Pastikan cell tidak terpotong */
            table.main-table td {
                page-break-inside: avoid !important;
                vertical-align: top;
            }
            
            table.main-table th {
                page-break-inside: avoid !important;
            }
            
            /* Pastikan border tidak terpotong */
            table.main-table {
                border-spacing: 0;
            }
            
            
            tfoot {
                display: table-footer-group;
            }
        }
        /* Preview A4 Container */
        .a4-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 15mm;
        }
        
        @media print {
            .a4-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                min-height: auto !important;
                background: transparent !important;
            }
            
            body {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="a4-container">
    <!-- Header dengan Logo dan Info seperti shipping_plan.xml -->
    <div class="print-header" style="margin-bottom: 15px;">
        <table style="width: 100%; border-collapse: collapse; border: none !important;">
            <tr style="border: none !important;">
                <td style="vertical-align: top; border: none !important; padding: 0;">
                    <h2 style="font-weight: bold; margin-bottom: -3px; font-size: 16pt; color: #000;">
                        SHIPPING PRODUCT
                    </h2>
                    <?php if (!empty($shipping['description'])): ?>
                    <h3 style="font-weight: bold; margin-top: 5px; font-size: 11pt; color: #000;">
                        <?= htmlspecialchars($shipping['description']) ?>
                    </h3>
                    <?php endif; ?>
                </td>
                <td style="width: 20%; vertical-align: top; text-align: right; padding-left: 15px; border: none !important; padding-top: 0;">
                    <?php
                    // Ambil logo dari Odoo res.company
                    // Ambil company yang sesuai dengan shipping/batch
                    $company_logo = '';
                    if (!empty($username)) {
                        // Coba ambil company dari batch/shipping terlebih dahulu
                        $batch_name = $shipping['name'];
                        $batches = callOdooRead($username, 'stock.picking.batch', [['name', '=', $batch_name]], ['company_id']);
                        
                        error_log("=== LOGO FETCH: Batch name: $batch_name ===");
                        error_log("=== LOGO FETCH: Batches result: " . json_encode($batches) . " ===");
                        
                        $company_id = null;
                        if ($batches && is_array($batches) && !empty($batches) && isset($batches[0]['company_id'])) {
                            $company_id_field = $batches[0]['company_id'];
                            error_log("=== LOGO FETCH: company_id field: " . json_encode($company_id_field) . " ===");
                            
                            if (is_array($company_id_field) && count($company_id_field) >= 1) {
                                $company_id = $company_id_field[0];
                            } else if (!is_array($company_id_field) && $company_id_field) {
                                $company_id = $company_id_field;
                            }
                            error_log("=== LOGO FETCH: Extracted company_id: " . ($company_id ?? 'NULL') . " ===");
                        }
                        
                        // Jika tidak ada company_id dari batch, ambil company aktif
                        if ($company_id) {
                            error_log("=== LOGO FETCH: Querying company by ID: $company_id ===");
                            $companies = callOdooRead($username, 'res.company', [['id', '=', $company_id]], ['id', 'name', 'logo']);
                        } else {
                            error_log("=== LOGO FETCH: No company_id from batch, querying active company ===");
                            // Ambil company aktif (biasanya company_id = 1 atau yang memiliki parent_id = false)
                            $companies = callOdooRead($username, 'res.company', [['parent_id', '=', false]], ['id', 'name', 'logo']);
                            
                            // Jika masih tidak ada, ambil semua company
                            if (!$companies || empty($companies)) {
                                error_log("=== LOGO FETCH: No company with parent_id=false, trying all companies ===");
                                $companies = callOdooRead($username, 'res.company', [], ['id', 'name', 'logo']);
                            }
                        }
                        
                        error_log("=== LOGO FETCH: Companies result count: " . (is_array($companies) ? count($companies) : 0) . " ===");
                        
                        if ($companies && is_array($companies) && !empty($companies)) {
                            // Loop semua company untuk mencari yang punya logo
                            foreach ($companies as $company) {
                                error_log("=== LOGO FETCH: Checking company - ID: " . ($company['id'] ?? 'N/A') . ", Name: " . ($company['name'] ?? 'N/A') . " ===");
                                
                                if (isset($company['logo']) && !empty($company['logo']) && $company['logo'] !== false) {
                                    // Logo dari Odoo adalah base64 string, langsung gunakan
                                    $company_logo = $company['logo'];
                                    error_log("=== LOGO FETCH: Logo found for company ID " . ($company['id'] ?? 'N/A') . ", length: " . strlen($company_logo) . " ===");
                                    break; // Gunakan company pertama yang punya logo
                                } else {
                                    error_log("=== LOGO FETCH: Company ID " . ($company['id'] ?? 'N/A') . " - Logo field: " . json_encode($company['logo'] ?? 'not set') . " ===");
                                }
                            }
                            
                            if (empty($company_logo)) {
                                error_log("=== LOGO FETCH: No company with logo found ===");
                            }
                        } else {
                            error_log("=== LOGO FETCH: No companies returned from Odoo ===");
                        }
                    } else {
                        error_log("=== LOGO FETCH: Username is empty ===");
                    }
                    ?>
                    <div style="text-align: right;">
                        <?php 
                        // Debug: cek apakah logo ada
                        $logo_exists = !empty($company_logo) && $company_logo !== false && $company_logo !== 'False';
                        error_log("=== LOGO DISPLAY: company_logo exists: " . ($logo_exists ? 'YES' : 'NO') . ", length: " . strlen($company_logo ?? '') . " ===");
                        
                        if ($logo_exists): 
                            // Pastikan logo adalah base64 yang valid
                            $logo_data = $company_logo;
                            // Hapus prefix data:image jika ada
                            if (strpos($logo_data, 'data:image') === 0) {
                                $logo_data = substr($logo_data, strpos($logo_data, ',') + 1);
                            }
                        ?>
                            <img src="data:image/png;base64,<?= htmlspecialchars($logo_data) ?>" alt="Company Logo" style="max-width: 150px; max-height: 120px; object-fit: contain; margin-bottom: 1px; margin-top: -25px; display: block; margin-left: auto;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="width: 150px; height: 120px; display: none; align-items: center; justify-content: center; background: transparent; margin-left: auto; margin-bottom: 1px;">
                                <small style="color: #999;">LOGO</small>
                            </div>
                        <?php else: ?>
                            <div style="width: 150px; height: 120px; display: flex; align-items: center; justify-content: center; background: transparent; margin-left: auto; margin-bottom: 1px;">
                                <small style="color: #999;">LOGO</small>
                            </div>
                        <?php endif; ?>
                        <div style="font-size: 9pt; color: #000; margin-top: -20px; margin-bottom: -25px; white-space: nowrap; line-height: 1;">
                            Printed on : <?= date('d M Y H:i:s') ?> (UTC+7)
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- DEBUG INFO - Hapus setelah selesai -->
    <?php if (isset($_GET['debug']) || isset($_POST['debug'])): ?>
    <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc; font-size: 10pt;">
        <h3>DEBUG M3 Info:</h3>
        <p><strong>Product IDs untuk M3:</strong> <?= json_encode($product_ids_for_m3 ?? []) ?></p>
        <p><strong>M3 Data dari Odoo:</strong> <?= json_encode($m3_data ?? []) ?></p>
        <p><strong>Jumlah Grouped Data:</strong> <?= count($grouped_data ?? []) ?></p>
        <p><strong>Username:</strong> <?= htmlspecialchars($username ?? 'NOT SET') ?></p>
        <hr>
        <h4>Grouped Data dengan M3:</h4>
        <pre style="font-size: 9pt; max-height: 300px; overflow: auto;"><?php 
        foreach ($grouped_data ?? [] as $key => $data) {
            echo "Key: $key\n";
            echo "  Product ID: " . ($data['product_id'] ?? 'NULL') . "\n";
            echo "  Product Name: " . ($data['product_name'] ?? 'NULL') . "\n";
            echo "  M3: " . ($data['m3'] ?? 'NOT SET') . "\n";
            echo "  Tot M3: " . ($data['tot_m3'] ?? 'NOT SET') . "\n";
            echo "  Qty: " . ($data['qty'] ?? 'NOT SET') . "\n";
            echo "\n";
        }
        ?></pre>
    </div>
    <?php endif; ?>
    
    <div class="table-container">
        <div class="table-title">Product List</div>
        
        <?php if (count($grouped_data) > 0): ?>
            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width: 3%;">No</th>
                        <th style="width: 10%;">ID Produk</th>
                        <th style="width: 30%;">Nama Produk</th>
                        <th style="width: 7%;">M3</th>
                        <th style="width: 7%;">Qty</th>
                        <th style="width: 10%;">Tot. Part</th>
                        <th style="width: 10%;">Tot. M3</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $product_no = 1;
                    $total_items = 0;
                    $total_m3_all = 0;
                    foreach ($grouped_data as $group_key => $product_data): 
                        $items = $product_data['items'];
                        $total_items += count($items);
                        // Hitung total M3
                        $tot_m3 = $product_data['tot_m3'] ?? 0;
                        $total_m3_all += $tot_m3;
                        $detail_no = 1;
                        $product_id = $product_data['product_id'];
                        $product_ref = $product_data['product_ref'];
                        $product_name = $product_data['product_name'];
                        $default_code = $product_data['default_code'] ?? null;
                        $finishing = $product_data['finishing'];
                        $qty = $product_data['qty'];
                        $tot_part = $product_data['tot_part'];
                        $m3 = $product_data['m3'] ?? 0;
                        $tot_m3 = $product_data['tot_m3'] ?? 0;
                        
                        // Tentukan ID Produk dengan prioritas: default_code > ekstrak dari nama produk > product_ref > product_id
                        $display_product_id = '-';
                        if (!empty($default_code)) {
                            $display_product_id = $default_code;
                        } else if (!empty($product_name)) {
                            // Ekstrak ID dari nama produk yang berbentuk [20999] Part of Goods
                            if (preg_match('/^\[([^\]]+)\]/', $product_name, $matches)) {
                                $display_product_id = $matches[1];
                            }
                        }
                        
                        // Fallback ke product_ref atau product_id jika masih kosong
                        if ($display_product_id === '-') {
                            $display_product_id = $product_ref ?: ($product_id ?: '-');
                        }
                        
                        // Log untuk debugging display
                        error_log("=== M3 DISPLAY: Product ID: $product_id, Default Code: " . ($default_code ?? 'NULL') . ", Display ID: $display_product_id, Name: $product_name, m3: $m3, tot_m3: $tot_m3, qty: $qty ===");
                    ?>
                        <!-- Product Row -->
                        <tr class="product-row">
                            <td class="text-center"><?= $product_no++ ?></td>
                            <td class="text-left"><?= htmlspecialchars($display_product_id) ?></td>
                            <td class="text-left"><?= htmlspecialchars($product_name ?: 'Unknown') ?></td>
                            <td class="text-right"><?= $m3 > 0 ? number_format($m3, 3) : '-' ?></td>
                            <td class="text-right"><?= $qty ?></td>
                            <td class="text-right"><?= $tot_part ?></td>
                            <td class="text-right"><?= $tot_m3 > 0 ? number_format($tot_m3, 3) : '-' ?></td>
                        </tr>
                        <!-- Detail Row -->
                        <tr class="detail-row">
                            <td colspan="7" style="padding: 0; border-left: none; border-right: none; margin: 0;">
                                <div class="warna-label" style="margin: 0; padding: 1px 3px;">Warna : <?= htmlspecialchars($finishing ?: '-') ?></div>
                                <table class="detail-table" style="margin: 0;">
                                    <thead>
                                        <tr>
                                            <th style="width: 6%;">No</th>
                                            <th style="width: 25%;">No Produk</th>
                                            <th style="width: 18%;">Jam Proses</th>
                                            <th style="width: 25%;">ID Order</th>
                                            <th style="width: 26%;">Picking / SO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item_data): 
                                            $item_production_code = $item_data['production_code'] ?? '';
                                            $picking_info = '-';
                                            $so_info = '-';
                                            
                                            // Ambil info dari mapping
                                            if (isset($code_to_picking_map[$item_production_code])) {
                                                $picking_info = $code_to_picking_map[$item_production_code]['picking_name'];
                                                $so_info = $code_to_picking_map[$item_production_code]['so_info'];
                                            }
                                            
                                            // Tampilkan picking/SO, jika tidak diketahui beri penjelasan
                                            $display_picking_so = '';
                                            if (empty($picking_info) || $picking_info === '-') {
                                                $display_picking_so = 'Picking/SO nya Belum ada di odoo';
                                            } else {
                                                $display_picking_so = htmlspecialchars($picking_info);
                                                if (!empty($so_info) && $so_info !== '-') {
                                                    $display_picking_so .= '<br><span style="color: #666;">' . htmlspecialchars($so_info) . '</span>';
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td class="text-center"><?= $detail_no++ ?></td>
                                                <td class="text-left"><?= htmlspecialchars($item_production_code) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($item_data['created_time'] ?? '-') ?></td>
                                                <td class="text-left"><?= htmlspecialchars($item_data['client_order_ref'] ?? '-') ?></td>
                                                <td class="text-left" style="font-size: 8pt;">
                                                    <?= $display_picking_so ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <!-- Spacer row untuk mengurangi jarak (opsional, bisa dihapus jika terlalu rapat) -->
                    <?php endforeach; ?>
                    
                    <!-- Footer Row -->
                    <tr class="footer-row">
                        <td colspan="5">
                            <strong>Jumlah Barang : <?= $total_items ?></strong>
                        </td>
                        <td class="text-right">
                            <strong>Total :</strong>
                        </td>
                        <td class="text-right">
                            <strong><?= number_format($total_m3_all, 3) ?></strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width: 3%;">No</th>
                        <th style="width: 10%;">ID Produk</th>
                        <th style="width: 30%;">Nama Produk</th>
                        <th style="width: 7%;">M3</th>
                        <th style="width: 7%;">Qty</th>
                        <th style="width: 10%;">Tot. Part</th>
                        <th style="width: 10%;">Tot. M3</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="no-data">Tidak ada data ditemukan</td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    </div> <!-- End a4-container -->
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
<?php
// Tutup koneksi
mysqli_close($conn);
?>


<?php
// Enable error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Query untuk mengambil data dari shipping_manual_stuffing, production_lots_strg, barcode_lot, dan barcode_item
// GROUP BY production_code untuk memastikan tidak ada duplikasi di level query
$query = "SELECT 
    sms.production_code,
    MAX(pls.sale_order_ref) AS client_order_ref,
    MAX(COALESCE(bl.product_name, bi_lot.product_name)) AS product_name,
    MAX(COALESCE(bl.product_id, bi.product_id, bi_lot.product_id)) AS product_id,
    MAX(COALESCE(bl.product_ref, bi_lot.product_ref)) AS product_ref,
    MAX(COALESCE(bl.finishing, bi_lot.finishing)) AS finishing,
    TIME(MIN(sms.created_at)) AS created_time
FROM shipping_manual_stuffing sms
LEFT JOIN production_lots_strg pls ON pls.production_code = sms.production_code
LEFT JOIN barcode_item bi ON bi.barcode = sms.production_code
LEFT JOIN barcode_lot bi_lot ON bi_lot.id = bi.lot_id
LEFT JOIN barcode_lot bl ON bl.sale_order_ref = pls.sale_order_ref
WHERE sms.id_shipping = ?
GROUP BY sms.production_code
ORDER BY MAX(COALESCE(bl.product_name, bi_lot.product_name)), sms.production_code";

$stmt_data = $conn->prepare($query);
$stmt_data->bind_param("i", $shipping_id);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
$raw_data = [];
while ($row = $result_data->fetch_assoc()) {
    $raw_data[] = $row;
}
$stmt_data->close();

// Kelompokkan data berdasarkan product_id (bukan product_name atau reference) untuk menghindari duplikasi
// Track production_code yang sudah digunakan untuk memastikan tidak ada duplikasi
$used_codes = [];
$grouped_data = [];

foreach ($raw_data as $row) {
    $production_code = $row['production_code'] ?? '';
    
    // Skip jika production_code sudah digunakan (menghindari duplikasi)
    if (empty($production_code) || isset($used_codes[$production_code])) {
        continue;
    }
    
    // Gunakan product_id sebagai key utama, jika tidak ada gunakan product_name
    $product_id = $row['product_id'] ?? null;
    $product_name = $row['product_name'] ?? 'Unknown';
    
    // Buat key unik berdasarkan product_id atau product_name
    $group_key = $product_id ? "product_{$product_id}" : "name_" . md5($product_name);
    
    if (!isset($grouped_data[$group_key])) {
        $grouped_data[$group_key] = [
            'items' => [],
            'product_id' => $product_id,
            'product_name' => $product_name,
            'product_ref' => $row['product_ref'] ?? null,
            'finishing' => $row['finishing'] ?? null,
            'qty' => 0,
            'tot_part' => 0
        ];
    }
    
    // Tambahkan item dan mark code sebagai used
    $grouped_data[$group_key]['items'][] = $row;
    $grouped_data[$group_key]['qty']++;
    $grouped_data[$group_key]['tot_part']++;
    $used_codes[$production_code] = true;
    
    // Update finishing jika belum ada dan row ini punya finishing
    if (empty($grouped_data[$group_key]['finishing']) && !empty($row['finishing'])) {
        $grouped_data[$group_key]['finishing'] = $row['finishing'];
    }
    
    // Update product_ref jika belum ada
    if (empty($grouped_data[$group_key]['product_ref']) && !empty($row['product_ref'])) {
        $grouped_data[$group_key]['product_ref'] = $row['product_ref'];
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
if (!empty($product_ids_for_m3)) {
    if (empty($username)) {
        error_log("=== M3 FETCH: Warning: Username tidak ditemukan di session, skip M3 fetch ===");
    } else {
        error_log("=== M3 FETCH: Mulai fetch M3 untuk product_ids: " . json_encode($product_ids_for_m3) . " ===");
        
        // Ambil product.product untuk mendapatkan product_tmpl_id
        $products = callOdooRead($username, 'product.product', [['id', 'in', $product_ids_for_m3]], ['id', 'name', 'product_tmpl_id']);
        
        $product_tmpl_ids = [];
        $product_id_to_tmpl = [];
        
        if ($products && is_array($products)) {
            foreach ($products as $product) {
                $prod_id = $product['id'] ?? null;
                $prod_name = $product['name'] ?? 'N/A';
                
                if (!$prod_id) continue;
                
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
    } else {
        $product_data['m3'] = 0;
        $product_data['tot_m3'] = 0;
        error_log("=== M3 ASSIGN: SKIP - Product ID kosong untuk group_key: $group_key ===");
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
            line-height: 1.4;
            color: #000;
            padding: 15mm;
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
            margin-top: 10px;
        }
        
        table.main-table {
            border: 1px solid #000;
        }
        
        .product-row {
            background-color: white;
        }
        
        .product-row td {
            font-weight: bold;
            padding: 4px 3px;
        }
        
        .detail-row {
            background-color: #fafafa;
        }
        
        .detail-table {
            width: 100%;
            border: none;
            margin: 0;
        }
        
        .detail-table td {
            border: none;
            border-bottom: 1px solid #ddd;
            padding: 2px 3px;
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
            padding: 3px;
            font-size: 7pt;
            text-align: left;
        }
        
        .warna-label {
            font-weight: bold;
            margin: 0;
            padding: 2px 3px;
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
                margin: 15mm;
            }
            
            body {
                width: 100% !important;
                min-height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
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
                margin-bottom: 8px;
            }
            
            .print-header h2 {
                font-size: 20pt !important;
            }
            
            .print-header h3 {
                font-size: 14pt !important;
            }
            
            .info-section {
                page-break-after: avoid;
                margin-bottom: 5px;
            }
            
            .table-container {
                page-break-inside: auto;
                margin-top: 5px;
            }
            
            table.main-table {
                page-break-inside: auto;
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
            
            .product-row {
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            
            .product-row td {
                font-weight: bold !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .detail-row {
                page-break-before: avoid;
                page-break-inside: auto;
                background-color: #fafafa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .detail-table th {
                background-color: #808080 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .footer-row {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                page-break-inside: avoid;
            }
            
            .warna-label {
                background-color: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
                        
                        $company_id = null;
                        if ($batches && is_array($batches) && !empty($batches) && isset($batches[0]['company_id'])) {
                            $company_id_field = $batches[0]['company_id'];
                            if (is_array($company_id_field) && count($company_id_field) >= 1) {
                                $company_id = $company_id_field[0];
                            } else if (!is_array($company_id_field)) {
                                $company_id = $company_id_field;
                            }
                        }
                        
                        // Jika tidak ada company_id dari batch, ambil company aktif
                        if ($company_id) {
                            $companies = callOdooRead($username, 'res.company', [['id', '=', $company_id]], ['id', 'name', 'logo']);
                        } else {
                            // Ambil company aktif (biasanya company_id = 1 atau yang memiliki parent_id = false)
                            $companies = callOdooRead($username, 'res.company', [['parent_id', '=', false]], ['id', 'name', 'logo']);
                        }
                        
                        if ($companies && is_array($companies) && !empty($companies)) {
                            // Ambil company pertama
                            $company = $companies[0];
                            if (isset($company['logo']) && !empty($company['logo'])) {
                                // Logo dari Odoo adalah base64 string, langsung gunakan
                                $company_logo = $company['logo'];
                            }
                        }
                    }
                    ?>
                    <div style="text-align: right;">
                        <?php if (!empty($company_logo)): ?>
                            <img src="data:image/png;base64,<?= $company_logo ?>" alt="Company Logo" style="max-width: 150px; max-height: 120px; object-fit: contain; margin-bottom: 1px; margin-top: -25px; display: block; margin-left: auto;">
                        <?php else: ?>
                            <div style="width: 250px; height: 120px; display: flex; align-items: center; justify-content: center; background: transparent; margin-left: auto; margin-bottom: 1px;">
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
                        $finishing = $product_data['finishing'];
                        $qty = $product_data['qty'];
                        $tot_part = $product_data['tot_part'];
                        $m3 = $product_data['m3'] ?? 0;
                        $tot_m3 = $product_data['tot_m3'] ?? 0;
                        
                        // Log untuk debugging display
                        error_log("=== M3 DISPLAY: Product ID: $product_id, Name: $product_name, m3: $m3, tot_m3: $tot_m3, qty: $qty ===");
                    ?>
                        <!-- Product Row -->
                        <tr class="product-row">
                            <td class="text-center"><?= $product_no++ ?></td>
                            <td class="text-left"><?= htmlspecialchars($product_ref ?: ($product_id ?: '-')) ?></td>
                            <td class="text-left"><?= htmlspecialchars($product_name ?: 'Unknown') ?></td>
                            <td class="text-right"><?= $m3 > 0 ? number_format($m3, 3) : '-' ?></td>
                            <td class="text-right"><?= $qty ?></td>
                            <td class="text-right"><?= $tot_part ?></td>
                            <td class="text-right"><?= $tot_m3 > 0 ? number_format($tot_m3, 3) : '-' ?></td>
                        </tr>
                        <!-- Detail Row -->
                        <tr class="detail-row">
                            <td colspan="7" style="padding: 0; border-left: none; border-right: none;">
                                <div class="warna-label">Warna : <?= htmlspecialchars($finishing ?: '-') ?></div>
                                <table class="detail-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 8%;">No</th>
                                            <th style="width: 30%;">No Produk</th>
                                            <th style="width: 20%;">Jam Proses</th>
                                            <th style="width: 42%;">ID Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $data): ?>
                                            <tr>
                                                <td class="text-center"><?= $detail_no++ ?></td>
                                                <td class="text-left"><?= htmlspecialchars($data['production_code'] ?? '-') ?></td>
                                                <td class="text-center"><?= htmlspecialchars($data['created_time'] ?? '-') ?></td>
                                                <td class="text-left"><?= htmlspecialchars($data['client_order_ref'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
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
    
    <div class="print-footer">
        <div>Printed on: <?= date('d M Y H:i:s') ?></div>
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


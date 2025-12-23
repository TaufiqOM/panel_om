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

// Ambil batch dari Odoo berdasarkan name shipping
$batch_name = $shipping['name'];
$batches = callOdooRead($username, "stock.picking.batch", [["name", "=", $batch_name]], ["name", "scheduled_date", "description", "picking_ids"]);

if (!$batches || count($batches) == 0) {
    echo "Data batch tidak ditemukan di Odoo.";
    exit;
}

$batch = $batches[0];
$picking_ids = $batch['picking_ids'] ?? [];

if (empty($picking_ids)) {
    echo "Tidak ada picking untuk batch ini.";
    exit;
}

// Ambil pickings dari Odoo dengan sale_id
$pickings = callOdooRead($username, "stock.picking", [["id", "in", $picking_ids]], ["id", "name", "sale_id"]);

if (!$pickings || !is_array($pickings)) {
    echo "Data picking tidak ditemukan di Odoo.";
    exit;
}

// Struktur data baru: picking_id -> array of products (setiap stock.move sebagai entry terpisah)
$order_groups = []; // picking_id => array of product entries
$sale_order_cache = []; // Cache untuk sale.order data
$product_cache = []; // Cache untuk product data
$sale_line_cache = []; // Cache untuk sale.order.line data

// Loop per picking untuk mengumpulkan data
foreach ($pickings as $picking) {
    $picking_id = $picking['id'];
    $picking_name = $picking['name'] ?? '';
    $sale_id = is_array($picking['sale_id']) ? $picking['sale_id'][0] : ($picking['sale_id'] ?? 0);
    
    if (!$sale_id) {
        continue;
    }
    
    // Ambil sale.order untuk mendapatkan client_order_ref, partner_id, name (so_name)
    if (!isset($sale_order_cache[$sale_id])) {
        $sale_order = callOdooRead($username, 'sale.order', [['id', '=', $sale_id]], ['client_order_ref', 'partner_id', 'due_date_order', 'name']);
        if ($sale_order && !empty($sale_order)) {
            $sale_order_cache[$sale_id] = $sale_order[0];
        } else {
            continue;
        }
    }
    
    $sale_order_data = $sale_order_cache[$sale_id];
    $client_order_ref = $sale_order_data['client_order_ref'] ?? '';
    $so_name = $sale_order_data['name'] ?? '';
    $customer_name = is_array($sale_order_data['partner_id']) ? $sale_order_data['partner_id'][1] : '';
    // Split customer_name by '/' dan ambil bagian pertama
    if (strpos($customer_name, '/') !== false) {
        $customer_name = trim(explode('/', $customer_name)[0]);
    }
    
    if (empty($client_order_ref)) {
        continue;
    }
    
    // Ambil sale.order.line untuk mendapatkan product_uom_qty per product
    $sale_order_lines = callOdooRead($username, 'sale.order.line', [
        ['order_id', '=', $sale_id]
    ], ['id', 'product_id', 'product_uom_qty', 'info_to_production', 'name']);
    
    if (!$sale_order_lines || !is_array($sale_order_lines)) {
        continue;
    }
    
    // Ambil move_ids untuk picking ini
    $picking_full = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_ids']);
    $move_ids = [];
    if ($picking_full && !empty($picking_full) && isset($picking_full[0]['move_ids'])) {
        $move_ids = $picking_full[0]['move_ids'];
    }
    
    // Inisialisasi array untuk picking ini
    if (!isset($order_groups[$picking_id])) {
        $order_groups[$picking_id] = [
            'picking_id' => $picking_id,
            'picking_name' => $picking_name,
            'sale_id' => $sale_id,
            'client_order_ref' => $client_order_ref,
            'so_name' => $so_name,
            'customer_name' => $customer_name,
            'due_date' => $sale_order_data['due_date_order'] ?? null,
            'products' => [] // Array berurutan untuk setiap stock.move
        ];
    }
    
    // PENTING: Loop per stock.move (TIDAK menggabungkan product yang sama)
    // Setiap stock.move menjadi entry terpisah untuk mempertahankan urutan
    if (!empty($move_ids)) {
        $moves = callOdooRead($username, 'stock.move', [['id', 'in', $move_ids]], ['id', 'product_id', 'product_uom_qty', 'sale_line_id']);
        
        if ($moves && is_array($moves)) {
            foreach ($moves as $move) {
                $move_id = $move['id'];
                $product_id = is_array($move['product_id']) ? $move['product_id'][0] : null;
                $product_name = is_array($move['product_id']) ? $move['product_id'][1] : 'N/A';
                $product_uom_qty = floatval($move['product_uom_qty'] ?? 0);
                $sale_line_id = is_array($move['sale_line_id']) ? $move['sale_line_id'][0] : null;
                
                if (!$product_id || $product_uom_qty <= 0) {
                    continue;
                }
        
                // Ambil info_to_production dari sale.order.line jika ada sale_line_id
                $info_to_production = '##';
                if ($sale_line_id) {
                    foreach ($sale_order_lines as $sale_line) {
                        if ($sale_line['id'] == $sale_line_id) {
                            $info_to_production = $sale_line['info_to_production'] ?? '##';
                            break;
                        }
                    }
                }
                
                // PENTING: Ambil production_code langsung dari production_lots_strg
                // Filter berdasarkan: customer_name, so_name, sale_order_id, sale_order_ref, product_code, sale_order_line_id
                // Exclude production_code yang sudah ada di shipping_manual_stuffing untuk shipping_id ini
                // Ambil sebanyak product_uom_qty dari stock.move
                $valid_barcodes = [];
                
                if ($product_uom_qty > 0 && $sale_line_id) {
                    // Escape untuk keamanan
                    $escaped_customer_name = $conn->real_escape_string($customer_name);
                    $escaped_so_name = $conn->real_escape_string($so_name);
                    $escaped_client_order_ref = $conn->real_escape_string($client_order_ref);
                    
                    // Query production_lots_strg dengan filter lengkap
                    $sql_strg = "SELECT pls.production_code 
                                 FROM production_lots_strg pls
                                 LEFT JOIN shipping_manual_stuffing sms ON sms.production_code = pls.production_code AND sms.id_shipping = ?
                                 WHERE pls.customer_name = ?
                                 AND pls.so_name = ?
                                 AND pls.sale_order_id = ?
                                 AND pls.sale_order_ref = ?
                                 AND pls.product_code = ?
                                 AND pls.sale_order_line_id = ?
                                 AND sms.production_code IS NULL
                                 ORDER BY pls.id DESC
                                 LIMIT ?";
                    
                    $stmt_strg = $conn->prepare($sql_strg);
                    if ($stmt_strg) {
                        $limit_qty = intval($product_uom_qty);
                        $stmt_strg->bind_param("issisiii", $shipping_id, $escaped_customer_name, $escaped_so_name, $sale_id, $escaped_client_order_ref, $product_id, $sale_line_id, $limit_qty);
                        $stmt_strg->execute();
                        $result_strg = $stmt_strg->get_result();
                        
                        while ($row = $result_strg->fetch_assoc()) {
                            $valid_barcodes[] = $row['production_code'];
                        }
                        $stmt_strg->close();
                        
                        // Balik urutan karena kita menggunakan ORDER BY id DESC untuk mengambil terakhir
                        $valid_barcodes = array_reverse($valid_barcodes);
                    }
                }
                
                // Ambil product default_code
                $default_code = '##';
                if (!isset($product_cache[$product_id])) {
                    $product_data_odoo = callOdooRead($username, 'product.product', [['id', '=', $product_id]], ['default_code', 'name']);
                    if ($product_data_odoo && !empty($product_data_odoo)) {
                        $default_code = $product_data_odoo[0]['default_code'] ?? '##';
                        if (empty($product_name) || $product_name == 'N/A') {
                            $product_name = $product_data_odoo[0]['name'] ?? $product_name;
                        }
                    }
                    $product_cache[$product_id] = [
                        'name' => $product_name,
                        'default_code' => $default_code
                    ];
                } else {
                    $default_code = $product_cache[$product_id]['default_code'];
                    $product_name = $product_cache[$product_id]['name'];
                }
                
                // PENTING: Simpan setiap stock.move sebagai entry terpisah (tidak menggabungkan product yang sama)
                // Struktur: order_groups[picking_id]['products'][] = array of product entries
                $order_groups[$picking_id]['products'][] = [
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'qty' => intval($product_uom_qty),
                    'product_uom_qty' => intval($product_uom_qty),
                    'default_code' => $default_code,
                    'barcodes' => $valid_barcodes,
                    'info_to_production' => $info_to_production,
                    'sale_line_id' => $sale_line_id
                ];
            }
        }
    }
}

// Ambil buyer name (customer name) dari sale.order pertama yang ditemukan
$buyer_name = '';
if (!empty($pickings) && !empty($username)) {
    foreach ($pickings as $picking) {
        $sale_id = is_array($picking['sale_id']) ? $picking['sale_id'][0] : ($picking['sale_id'] ?? 0);
        if ($sale_id && isset($sale_order_cache[$sale_id])) {
            $sale_order_data = $sale_order_cache[$sale_id];
            if (isset($sale_order_data['partner_id']) && is_array($sale_order_data['partner_id'])) {
                $buyer_name = $sale_order_data['partner_id'][1] ?? '';
                // Tampilkan full customer name (tidak split)
                if (!empty($buyer_name)) {
                    break;
                }
            }
        }
    }
}

// Format tanggal (d M Y - contoh: 20 Dec 2025)
$scheduled_date = $shipping['sheduled_date'] ?? '';
$formatted_scheduled_date = '';
if ($scheduled_date) {
    $date_obj = DateTime::createFromFormat('Y-m-d', $scheduled_date);
    if ($date_obj) {
        $formatted_scheduled_date = $date_obj->format('d M Y');
    }
}

// Ambil logo company dari Odoo
$company_logo = '';
if (!empty($username)) {
    $batch_name = $shipping['name'];
    $batches = callOdooRead($username, 'stock.picking.batch', [['name', '=', $batch_name]], ['company_id']);
    
    $company_id = null;
    if ($batches && is_array($batches) && !empty($batches) && isset($batches[0]['company_id'])) {
        $company_id_field = $batches[0]['company_id'];
        if (is_array($company_id_field) && count($company_id_field) >= 1) {
            $company_id = $company_id_field[0];
        } else if (!is_array($company_id_field) && $company_id_field) {
            $company_id = $company_id_field;
        }
    }
    
    if ($company_id) {
        $companies = callOdooRead($username, 'res.company', [['id', '=', $company_id]], ['id', 'name', 'logo']);
    } else {
        $companies = callOdooRead($username, 'res.company', [['parent_id', '=', false]], ['id', 'name', 'logo']);
        if (!$companies || empty($companies)) {
            $companies = callOdooRead($username, 'res.company', [], ['id', 'name', 'logo']);
        }
    }
    
    if ($companies && is_array($companies) && !empty($companies)) {
        foreach ($companies as $company) {
            if (isset($company['logo']) && !empty($company['logo']) && $company['logo'] !== false) {
                $company_logo = $company['logo'];
                break;
            }
        }
    }
}

// Generate QR code dari description menggunakan API eksternal
$qr_code_url = '';
if (!empty($shipping['description'])) {
    $qr_size = '80x80'; // Ukuran lebih kecil
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $qr_size . '&data=' . urlencode($shipping['description']);
}

$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$printed_date = $now->format('l, d M Y H:i:s');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Stuffing Checklist - <?= htmlspecialchars($shipping['description']) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1;
            color: #000;
            background: #fff;
            width: 210mm;
            margin: 0 auto;
            padding: 15mm;
        }

        .page {
            width: 100%;
        }

        h2 {
            font-weight: bold;
            margin-bottom: -3px;
            font-size: 18px;
        }

        h5 {
            font-weight: bold;
            margin-bottom: -3px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial;
            letter-spacing: 0.05em;
            table-layout: fixed;
            margin-top: 10px;
        }

        table.main-table {
            border: 1px solid black;
        }

        table.main-table th {
            background-color: #a0a0a0;
            color: black;
            border: 1px solid black;
            font-size: 16px;
            font-weight: 900;
            padding: 2px 4px;
            text-align: center;
            vertical-align: middle;
        }

        table.main-table td {
            border: 1px solid black;
            padding: 2px 4px;
            font-size: 16px;
            vertical-align: middle;
        }

        .header-row {
            background-color: #a0a0a0;
            color: black;
            border: 1px solid black;
            font-size: 16px;
            font-weight: 900;
            line-height: 1;
        }

        .product-row {
            background-color: #cfcfcf;
            color: black;
            border: 1px solid black;
            font-size: 16px;
            line-height: 1;
        }

        .info-row {
            border: 1px solid black;
            font-size: 16px;
            line-height: 1;
        }

        .barcode-header {
            border: 1px solid black;
            font-size: 16px;
            text-align: center;
            line-height: 1;
        }

        .barcode-row {
            font-size: 16px;
            vertical-align: middle;
            line-height: 1;
        }

        .barcode-cell {
            text-align: center;
            font-size: 17px;
        }

        .checkbox {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid black;
            vertical-align: middle;
        }

        .total-row {
            line-height: 1;
            vertical-align: middle;
        }

        .total-label {
            text-align: right;
            padding: 5px 5px;
            font-size: 17px;
            font-weight: 900;
        }

        .total-cell {
            border: 1px solid black;
            padding: 5px 5px;
            text-align: center;
        }

        .extra-item-table {
            margin-top: 20px;
        }

        .extra-item-table th {
            height: 25px;
        }

        .extra-item-table td {
            height: 25px;
        }

        .approval-table {
            width: 100%;
            margin-top: 40px;
            border: none;
        }

        .approval-cell {
            text-align: center;
            width: 33.33%;
            padding: 10px;
        }

        .approval-line {
            height: 60px;
            border-bottom: 1px solid black;
            margin-top: 20px;
        }

        .spacer {
            height: 15px;
        }

        .spacer-small {
            height: 5px;
        }

        .border-bottom-thick {
            border-bottom: 2px solid black;
        }
        .btn-process {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 0;
        }
        
        .btn-process:hover {
            background-color: #45a049;
        }
        
        .btn-process:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Tombol Proses (no-print) -->
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button class="btn-process" onclick="processManualStuffing()">Insert Barcode ke Odoo</button>
            <div id="process-message" style="margin-top: 10px; font-weight: bold;"></div>
        </div>
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div style="flex: 1;">
                <h2 style="font-weight: bold; margin-bottom: -3px;">Manual Stuffing Checklist PRE STUFFING</h2>
                <h5 style="font-weight: bold; margin-bottom: -3px;">Customer : <?= htmlspecialchars($buyer_name) ?></h5>
            </div>
            <div style="width: 20%; text-align: right;">
                <?php if (!empty($company_logo) && $company_logo !== false && $company_logo !== 'False'): 
                    // Pastikan logo adalah base64 yang valid
                    $logo_data = $company_logo;
                    // Hapus prefix data:image jika ada
                    if (strpos($logo_data, 'data:image') === 0) {
                        $logo_data = substr($logo_data, strpos($logo_data, ',') + 1);
                    }
                ?>
                    <img src="data:image/png;base64,<?= htmlspecialchars($logo_data) ?>" alt="Company Logo" style="max-height: 120px; object-fit: contain; margin-top: -23px;" onerror="this.style.display='none';">
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: -23px;">
            <div style="flex: 1;">
                <h5 style="font-weight: bold; margin-bottom: -3px;"><?= htmlspecialchars($shipping['description']) ?>, <?= htmlspecialchars($formatted_scheduled_date) ?></h5>
                <?php if (!empty($qr_code_url)): ?>
                    <div style="display: inline-block; border: 2px solid black; border-radius: 5px; padding: 5px; margin-bottom: -3px; margin-top: 5px;">
                        <img src="<?= htmlspecialchars($qr_code_url) ?>" alt="QR Code" style="max-height: 80px; height: auto; display: block;" onerror="this.parentElement.style.display='none';">
                    </div>
                <?php endif; ?>
            </div>
            <div style="font-size: 11px; text-align: right; flex: 1;">
                <span>Printed on : <?= htmlspecialchars($printed_date) ?> (UTC+7)</span>
                <table style="font-size: 16px; font-weight: bold; width: 100%; margin-top: 5px; border: none;">
                    <tr>
                        <td style="width: 20%; text-align: left; border: none; padding: 0;">Start</td>
                        <td style="width: 80%; border-bottom: 1px solid black; text-align: left; border-top: none; border-left: none; border-right: none; padding: 0;">:</td>
                    </tr>
                    <tr>
                        <td style="width: 20%; text-align: left; border: none; padding: 0;">Finish</td>
                        <td style="width: 80%; border-bottom: 1px solid black; text-align: left; border-top: none; border-left: none; border-right: none; padding: 0;">:</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Main Table -->
        <table class="main-table">
            <colgroup>
                <col style="width: 13%"/>
                <col style="width: 25%"/>
                <col style="width: 6%"/>
                <col style="width: 16%"/>
                <col style="width: 34%"/>
                <col style="width: 8%"/>
                <col style="width: 8%"/>
            </colgroup>
            <thead>
                <tr class="header-row">
                    <th>ID Produk</th>
                    <th>Nama Produk</th>
                    <th>Qty</th>
                    <th>PR/Urgent</th>
                    <th colspan="3">DUE DATE</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7"></td>
                </tr>
                <?php 
                // Hitung total keseluruhan dari semua product
                $total_all_qty = 0;
                foreach ($order_groups as $picking_id => $picking_data) {
                    if (isset($picking_data['products']) && is_array($picking_data['products'])) {
                        foreach ($picking_data['products'] as $product_data) {
                            $qty_val = 0;
                            if (isset($product_data['product_uom_qty'])) {
                                $qty_val = intval($product_data['product_uom_qty']);
                            } elseif (isset($product_data['qty'])) {
                                $qty_val = intval($product_data['qty']);
                            }
                            $total_all_qty += $qty_val;
                        }
                    }
                }
                
                // Loop per picking_id (grouping by picking)
                foreach ($order_groups as $picking_id => $picking_data):
                    $client_ref = $picking_data['client_order_ref'] ?? '';
                    $picking_name = $picking_data['picking_name'] ?? '';
                    $due_date = $picking_data['due_date'] ?? null;
                    
                    // Format due_date
                    $due_date_str = '##';
                    if ($due_date && $due_date != '##') {
                        try {
                            $due_date_obj = new DateTime($due_date);
                            $due_date_str = $due_date_obj->format('d F Y');
                        } catch (Exception $e) {
                            $due_date_str = '##';
                        }
                    }
                    
                    // Loop per product dalam picking ini (setiap stock.move sebagai entry terpisah)
                    if (!isset($picking_data['products']) || !is_array($picking_data['products']) || empty($picking_data['products'])) {
                        continue;
                    }
                ?>
                    <!-- Picking Name dan Client Order Ref Header (sekali per picking) -->
                    <tr class="header-row">
                        <td colspan="2"><?= htmlspecialchars($picking_name) ?> - <?= htmlspecialchars($client_ref) ?></td>
                        <td class="text-center" colspan="5" style="text-align: center;"><?= htmlspecialchars($due_date_str) ?></td>
                    </tr>
                    
                    <?php 
                    // Loop per product dalam picking ini
                    foreach ($picking_data['products'] as $product_data):
                        // Ambil product_name dari product_data
                        $display_product_name = $product_data['product_name'] ?? '';
                        ?>
                        
                        <!-- Product Row -->
                        <?php 
                        // PENTING: Ambil qty LANGSUNG dari product_uom_qty yang sudah diambil dari Odoo stock.move
                        // Setiap stock.move menjadi entry terpisah (tidak digabungkan meskipun product sama)
                        $qty_value = 0;
                        if (isset($product_data['product_uom_qty'])) {
                            $qty_value = intval($product_data['product_uom_qty']);
                        } elseif (isset($product_data['qty'])) {
                            // Fallback ke qty jika product_uom_qty tidak ada (seharusnya selalu ada)
                            $qty_value = intval($product_data['qty']);
                        }
                        ?>
                        <tr class="product-row">
                            <td colspan="2"><?= htmlspecialchars($product_data['default_code']) ?></td>
                            <td style="text-align: center;"><?= $qty_value ?></td>
                            <td></td>
                            <td colspan="3"><?= htmlspecialchars($display_product_name) ?></td>
                        </tr>
                        
                        <!-- Info to Production Row -->
                        <tr class="info-row">
                            <td colspan="7"><?= htmlspecialchars($product_data['info_to_production']) ?></td>
                        </tr>
                        
                        <!-- Barcode Header -->
                        <tr class="barcode-header">
                            <td>No</td>
                            <td style="text-align: center">Keterangan</td>
                            <td colspan="2" style="text-align: center"></td>
                            <td style="text-align: center">Nomor Barcode</td>
                            <td style="text-align: center">IN</td>
                            <td style="text-align: center">OUT</td>
                        </tr>
                        
                        <!-- Barcode Rows -->
                        <?php 
                        $counter = 1;
                        // Sort barcodes (jika ada)
                        $barcodes_array = isset($product_data['barcodes']) && is_array($product_data['barcodes']) ? $product_data['barcodes'] : [];
                        if (!empty($barcodes_array)) {
                            sort($barcodes_array);
                        }
                        // PENTING: Qty HARUS menggunakan product_uom_qty dari Odoo (bukan jumlah barcode)
                        // Meskipun ada banyak barcode, qty tetap mengikuti product_uom_qty
                        // Jika tidak ada barcode, akan ditampilkan "##" untuk setiap row
                        $barcodes_count = count($barcodes_array);
                        // Gunakan product_uom_qty langsung dari Odoo (yang sama dengan qty_value di atas)
                        $qty = $qty_value; // Gunakan qty_value yang sudah diambil dari product_uom_qty
                        for ($i = 0; $i < $qty; $i++): 
                            // Show barcode[i] if it exists, otherwise '##' (matching XML logic)
                            $barcode = ($i < $barcodes_count) ? $barcodes_array[$i] : '##';
                        ?>
                            <tr class="barcode-row">
                                <td><?= $counter ?></td>
                                <td style="border-bottom: 1px solid black; text-align: center"></td>
                                <td colspan="2" style="text-align: center"></td>
                                <td class="barcode-cell"><?= htmlspecialchars($barcode) ?></td>
                                <td style="text-align: center;">
                                    <div class="checkbox"></div>
                                </td>
                                <td style="text-align: center;">
                                    <div class="checkbox"></div>
                                </td>
                            </tr>
                        <?php 
                            $counter++;
                        endfor; ?>
                        
                        <tr>
                            <td colspan="7" class="spacer"></td>
                        </tr>
                        
                        <!-- Total Produk In -->
                        <tr class="total-row">
                            <td colspan="4"></td>
                            <td class="total-label">Total Produk In</td>
                            <td class="total-cell"></td>
                            <td></td>
                        </tr>
                        
                        <tr>
                            <td colspan="7" class="spacer"></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                
                <tr>
                    <td colspan="7" class="spacer border-bottom-thick"></td>
                </tr>
                <tr>
                    <td colspan="7" class="spacer"></td>
                </tr>
                
                <!-- Total All Produk IN -->
                <tr class="total-row">
                    <td colspan="4"></td>
                    <td class="total-label">Total All Produk IN:</td>
                    <td class="total-cell" style="text-align: center; font-weight: bold;"><?= $total_all_qty ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <!-- Extra Item Section -->
        <h5 style="font-weight: bold; margin-top: 20px; page-break-before: always;">Extra Item :</h5>
        <table class="main-table extra-item-table">
            <colgroup>
                <col style="width: 13%"/>
                <col style="width: 47%"/>
                <col style="width: 34%"/>
                <col style="width: 8%"/>
                <col style="width: 8%"/>
            </colgroup>
            <thead>
                <tr class="header-row">
                    <th>No</th>
                    <th>Nomor Barcode</th>
                    <th>ID Produk</th>
                    <th>IN</th>
                    <th>OUT</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= 20; $i++): ?>
                    <tr class="barcode-row">
                        <td style="text-align: center; height: 25px; vertical-align: middle"><?= $i ?></td>
                        <td style="height: 25px;"></td>
                        <td style="height: 25px;"></td>
                        <td style="height: 25px;"></td>
                        <td style="height: 25px;"></td>
                    </tr>
                <?php endfor; ?>
                
                <tr>
                    <td colspan="5" class="spacer"></td>
                </tr>
                
                <!-- Total Produk In -->
                <tr class="total-row">
                    <td colspan="2"></td>
                    <td class="total-label">Total Produk In :</td>
                    <td class="total-cell" colspan="2"></td>
                </tr>
                
                <tr>
                    <td colspan="5" class="spacer-small border-bottom-thick"></td>
                </tr>
                <tr>
                    <td colspan="5" class="spacer-small"></td>
                </tr>
                
                <!-- Grand Total In -->
                <tr class="total-row">
                    <td colspan="2"></td>
                    <td class="total-label" style="font-size: 25px;">Grand Total In :</td>
                    <td class="total-cell" colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <!-- Approval Section -->
        <table class="approval-table">
            <tr>
                <td class="approval-cell">
                    <p>Approved By</p>
                    <div class="approval-line"></div>
                    <p>Opr. Manual</p>
                </td>
                <td class="approval-cell">
                    <p>Approved By</p>
                    <div class="approval-line"></div>
                    <p>Opr. Scan</p>
                </td>
                <td class="approval-cell">
                    <p>Approved By</p>
                    <div class="approval-line"></div>
                    <p>Opr. Final Check</p>
                </td>
            </tr>
        </table>
    </div>
    
    <script>
    function processManualStuffing() {
        const btn = document.querySelector('.btn-process');
        const messageDiv = document.getElementById('process-message');
        
        if (!confirm('Apakah Anda yakin ingin insert barcode ke Odoo? Proses ini akan:\n1. Reset quantity_done menjadi 0\n2. Insert barcode sesuai production_lots_strg')) {
            return;
        }
        
        btn.disabled = true;
        btn.textContent = 'Processing...';
        messageDiv.textContent = 'Sedang memproses...';
        messageDiv.style.color = 'blue';
        
        const shipping_id = <?= $shipping_id ?>;
        
        fetch('process_manual_stuffing.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'shipping_id=' + shipping_id
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Insert Barcode ke Odoo';
            
            if (data.success) {
                messageDiv.textContent = data.message;
                messageDiv.style.color = 'green';
                
                // Reload halaman setelah 2 detik
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                messageDiv.textContent = 'Error: ' + data.message;
                messageDiv.style.color = 'red';
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.textContent = 'Insert Barcode ke Odoo';
            messageDiv.textContent = 'Error: ' + error.message;
            messageDiv.style.color = 'red';
        });
    }
    </script>
</body>
</html>
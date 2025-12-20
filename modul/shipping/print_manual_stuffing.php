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

// Struktur data: client_order_ref -> products -> barcodes
$order_groups = [];
$sale_order_cache = []; // Cache untuk sale.order data
$product_cache = []; // Cache untuk product data
$sale_line_cache = []; // Cache untuk sale.order.line data

// Loop per picking untuk mengumpulkan data
foreach ($pickings as $picking) {
    $picking_id = $picking['id'];
    $sale_id = is_array($picking['sale_id']) ? $picking['sale_id'][0] : ($picking['sale_id'] ?? 0);
    
    if (!$sale_id) {
        continue;
    }
    
    // Ambil sale.order untuk mendapatkan client_order_ref dan partner_id
    if (!isset($sale_order_cache[$sale_id])) {
        $sale_order = callOdooRead($username, 'sale.order', [['id', '=', $sale_id]], ['client_order_ref', 'partner_id', 'due_date_order']);
        if ($sale_order && !empty($sale_order)) {
            $sale_order_cache[$sale_id] = $sale_order[0];
        } else {
            continue;
        }
    }
    
    $sale_order_data = $sale_order_cache[$sale_id];
    $client_order_ref = $sale_order_data['client_order_ref'] ?? '';
    
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
    
    // Ambil moves dengan quantity dan product_id
    $moves = callOdooRead($username, 'stock.move', [['id', 'in', $move_ids]], ['id', 'product_id', 'quantity', 'picking_id']);
    
    if (!$moves || !is_array($moves)) {
        continue;
    }
    
    // Loop per move
    foreach ($moves as $move) {
        $move_id = $move['id'];
        $product_id = is_array($move['product_id']) ? $move['product_id'][0] : null;
        $product_name = is_array($move['product_id']) ? $move['product_id'][1] : 'N/A';
        $quantity = floatval($move['quantity'] ?? 0);
        
        if (!$product_id || $quantity <= 0) {
            continue;
        }
        
        // Ambil move_line_ids untuk move ini
        $move_full = callOdooRead($username, 'stock.move', [['id', '=', $move_id]], ['move_line_ids']);
        $move_line_ids = [];
        if ($move_full && !empty($move_full) && isset($move_full[0]['move_line_ids'])) {
            $move_line_ids = $move_full[0]['move_line_ids'];
        }
        
        // Ambil move_lines untuk mendapatkan lot_id.name (barcodes)
        $barcodes = [];
        if (!empty($move_line_ids)) {
            $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id']);
            
            if ($move_lines && is_array($move_lines)) {
                foreach ($move_lines as $line) {
                    if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                        $barcode = $line['lot_id'][1]; // lot_id.name
                        if (!empty($barcode)) {
                            $barcodes[] = $barcode;
                        }
                    }
                }
            }
        }
        
        // Skip jika tidak ada barcode
        if (empty($barcodes)) {
            continue;
        }
        
        // Ambil info_to_production dari sale.order.line
        $info_to_production = '##';
        $cache_key = $sale_id . '_' . $product_id;
        
        if (!isset($sale_line_cache[$cache_key])) {
            // Ambil sale.order.line yang sesuai dengan sale_id dan product_id
            $sale_lines = callOdooRead($username, 'sale.order.line', [
                ['order_id', '=', $sale_id],
                ['product_id', '=', $product_id]
            ], ['info_to_production']);
            
            if ($sale_lines && !empty($sale_lines)) {
                $info_to_production = $sale_lines[0]['info_to_production'] ?? '##';
            }
            $sale_line_cache[$cache_key] = $info_to_production;
        } else {
            $info_to_production = $sale_line_cache[$cache_key];
        }
        
        // Ambil product default_code
        $default_code = '##';
        if (!isset($product_cache[$product_id])) {
            $product_data = callOdooRead($username, 'product.product', [['id', '=', $product_id]], ['default_code']);
            if ($product_data && !empty($product_data)) {
                $default_code = $product_data[0]['default_code'] ?? '##';
            }
            $product_cache[$product_id] = [
                'name' => $product_name,
                'default_code' => $default_code
            ];
        } else {
            $default_code = $product_cache[$product_id]['default_code'];
            $product_name = $product_cache[$product_id]['name'];
        }
        
        // Inisialisasi order group jika belum ada
        if (!isset($order_groups[$client_order_ref])) {
            $order_groups[$client_order_ref] = [];
        }
        
        // Inisialisasi product jika belum ada
        if (!isset($order_groups[$client_order_ref][$product_name])) {
            $due_date = $sale_order_data['due_date_order'] ?? null;
            $order_groups[$client_order_ref][$product_name] = [
                'qty' => 0,
                'default_code' => $default_code,
                'due_date' => $due_date,
                'barcodes' => [],
                'info_to_production' => $info_to_production
            ];
        }
        
        // Tambahkan quantity dan barcodes
        $current = $order_groups[$client_order_ref][$product_name];
        $order_groups[$client_order_ref][$product_name] = [
            'qty' => $current['qty'] + $quantity,
            'default_code' => $current['default_code'],
            'due_date' => $current['due_date'],
            'barcodes' => array_merge($current['barcodes'], $barcodes),
            'info_to_production' => $current['info_to_production']
        ];
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
                // Split by '/' dan ambil bagian pertama seperti di XML
                if (strpos($buyer_name, '/') !== false) {
                    $buyer_name = trim(explode('/', $buyer_name)[0]);
                }
                if (!empty($buyer_name)) {
                    break;
                }
            }
        }
    }
}

// Format tanggal
$scheduled_date = $shipping['sheduled_date'] ?? '';
$formatted_scheduled_date = '';
if ($scheduled_date) {
    $date_obj = DateTime::createFromFormat('Y-m-d', $scheduled_date);
    if ($date_obj) {
        $formatted_scheduled_date = $date_obj->format('d M Y');
    }
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
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div style="margin-bottom: 10px;">
            <h2>Manual Stuffing Checklist PRE STUFFING</h2>
            <h5>Customer : <?= htmlspecialchars($buyer_name) ?></h5>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: -23px;">
            <div style="flex: 1;">
                <h5><?= htmlspecialchars($shipping['description']) ?>, <?= htmlspecialchars($formatted_scheduled_date) ?></h5>
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
                foreach ($order_groups as $client_ref => $products): 
                    // Cek apakah ada barcode
                    $has_barcodes = false;
                    foreach ($products as $product_name => $product_data) {
                        if (!empty($product_data['barcodes'])) {
                            $has_barcodes = true;
                            break;
                        }
                    }
                    
                    if (!$has_barcodes) {
                        continue;
                    }
                    
                    // Ambil due_date dari product pertama
                    $first_product = reset($products);
                    $due_date_str = '##';
                    if ($first_product['due_date'] && $first_product['due_date'] != '##') {
                        try {
                            $due_date_obj = new DateTime($first_product['due_date']);
                            $due_date_str = $due_date_obj->format('d F Y');
                        } catch (Exception $e) {
                            $due_date_str = '##';
                        }
                    }
                ?>
                    <!-- Client Order Ref Header -->
                    <tr class="header-row">
                        <td colspan="2"><?= htmlspecialchars($client_ref) ?></td>
                        <td class="text-center" colspan="5" style="text-align: center;"><?= htmlspecialchars($due_date_str) ?></td>
                    </tr>
                    
                    <?php foreach ($products as $product_name => $product_data): ?>
                        <?php if (empty($product_data['barcodes'])) continue; ?>
                        
                        <!-- Product Row -->
                        <tr class="product-row">
                            <td colspan="2"><?= htmlspecialchars($product_data['default_code']) ?></td>
                            <td></td>
                            <td></td>
                            <td colspan="3"><?= htmlspecialchars($product_name) ?></td>
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
                        // Sort barcodes
                        sort($product_data['barcodes']);
                        $qty = intval($product_data['qty']);
                        $barcodes_array = $product_data['barcodes'];
                        $barcodes_count = count($barcodes_array);
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
                    <td class="total-cell"></td>
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
</body>
</html>
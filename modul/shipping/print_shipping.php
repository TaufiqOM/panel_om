<?php
session_start();

// File Conf
require __DIR__ . '/../../inc/config.php';

// Set timezone ke WIB (UTC+7)
date_default_timezone_set('Asia/Jakarta');

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

// Query untuk mengambil data dari shipping_manual_stuffing, production_lots_strg, dan barcode_lot
$query = "SELECT 
    sms.production_code,
    pls.sale_order_ref AS client_order_ref,
    bl.product_name,
    TIME(sms.created_at) AS created_time
FROM shipping_manual_stuffing sms
LEFT JOIN production_lots_strg pls ON pls.production_code = sms.production_code
LEFT JOIN barcode_lot bl ON bl.sale_order_ref = pls.sale_order_ref
WHERE sms.id_shipping = ?
GROUP BY sms.production_code
ORDER BY bl.product_name, sms.production_code";

$stmt_data = $conn->prepare($query);
$stmt_data->bind_param("i", $shipping_id);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
$raw_data = [];
while ($row = $result_data->fetch_assoc()) {
    $raw_data[] = $row;
}
$stmt_data->close();

// Kelompokkan data berdasarkan product_name
$grouped_data = [];
foreach ($raw_data as $row) {
    $product_name = $row['product_name'] ?? 'Unknown';
    if (!isset($grouped_data[$product_name])) {
        $grouped_data[$product_name] = [];
    }
    $grouped_data[$product_name][] = $row;
}

// Query untuk mengambil data pickings dan lot_ids dari sync Odoo
$query_pickings = "SELECT id, name, sale_id, client_order_ref FROM shipping_detail WHERE id_shipping = ? ORDER BY name";
$stmt_pickings = $conn->prepare($query_pickings);
$stmt_pickings->bind_param("i", $shipping_id);
$stmt_pickings->execute();
$result_pickings = $stmt_pickings->get_result();
$pickings_data = [];
while ($row = $result_pickings->fetch_assoc()) {
    $picking_id = $row['id'];
    
    // Ambil lot_ids untuk picking ini
    $query_lots = "SELECT lot_name, product_name, qty_done FROM shipping_lot_ids WHERE picking_id = ? ORDER BY product_name, lot_name";
    $stmt_lots = $conn->prepare($query_lots);
    $stmt_lots->bind_param("i", $picking_id);
    $stmt_lots->execute();
    $result_lots = $stmt_lots->get_result();
    $lots = [];
    while ($lot_row = $result_lots->fetch_assoc()) {
        $lots[] = $lot_row;
    }
    $stmt_lots->close();
    
    $row['lots'] = $lots;
    $pickings_data[] = $row;
}
$stmt_pickings->close();

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
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }
        
        .print-header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 3px;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .print-header .subtitle {
            font-size: 9pt;
            color: #000;
            font-weight: normal;
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
            
            body {
                padding: 5mm;
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                size: A4 portrait;
                margin: 15mm;
            }
            
            .print-header {
                page-break-after: avoid;
                margin-bottom: 8px;
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
    </style>
</head>
<body>
    <div class="print-header">
        <h1>Shipping Product</h1>
        <div class="subtitle"><?= htmlspecialchars($shipping['name']) ?></div>
    </div>
    
    <div class="info-section">
        <div class="info-row">
            <div class="info-label">Shipping Name:</div>
            <div class="info-value"><?= htmlspecialchars($shipping['name']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Scheduled Date:</div>
            <div class="info-value"><?= htmlspecialchars($shipping['sheduled_date']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Description:</div>
            <div class="info-value"><?= htmlspecialchars($shipping['description'] ?? '-') ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Ship To:</div>
            <div class="info-value"><?= htmlspecialchars($shipping['ship_to']) ?></div>
        </div>
    </div>
    
    <?php if (count($pickings_data) > 0): ?>
    <div class="table-container">
        <div class="table-title">Pickings & Lot/Serial Numbers (From Odoo Sync)</div>
        
        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 20%;">Picking Name</th>
                    <th style="width: 20%;">Client Order Ref (PO)</th>
                    <th style="width: 55%;">Lot/Serial Numbers</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $picking_no = 1;
                foreach ($pickings_data as $picking): 
                ?>
                    <tr>
                        <td class="text-center"><?= $picking_no++ ?></td>
                        <td class="text-left"><?= htmlspecialchars($picking['name']) ?></td>
                        <td class="text-left"><?= htmlspecialchars($picking['client_order_ref'] ?: '-') ?></td>
                        <td class="text-left">
                            <?php if (count($picking['lots']) > 0): ?>
                                <table style="width: 100%; border: none; margin: 0;">
                                    <thead style="background-color: #e0e0e0;">
                                        <tr>
                                            <th style="width: 40%; background-color: #e0e0e0; color: #000; border: 1px solid #ccc; padding: 2px; font-size: 7pt;">Lot/Serial</th>
                                            <th style="width: 45%; background-color: #e0e0e0; color: #000; border: 1px solid #ccc; padding: 2px; font-size: 7pt;">Product</th>
                                            <th style="width: 15%; background-color: #e0e0e0; color: #000; border: 1px solid #ccc; padding: 2px; font-size: 7pt;">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($picking['lots'] as $lot): ?>
                                            <tr>
                                                <td style="border: 1px solid #ddd; padding: 2px; font-size: 7pt;"><?= htmlspecialchars($lot['lot_name']) ?></td>
                                                <td style="border: 1px solid #ddd; padding: 2px; font-size: 7pt;"><?= htmlspecialchars($lot['product_name']) ?></td>
                                                <td style="border: 1px solid #ddd; padding: 2px; font-size: 7pt; text-align: right;"><?= htmlspecialchars($lot['qty_done']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <span class="text-muted">No lot/serial numbers</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
                        <th style="width: 13%;">L. Perm</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $product_no = 1;
                    $total_items = 0;
                    foreach ($grouped_data as $product_name => $items): 
                        $total_items += count($items);
                        $detail_no = 1;
                    ?>
                        <!-- Product Row -->
                        <tr class="product-row">
                            <td class="text-center"><?= $product_no++ ?></td>
                            <td class="text-left">##</td>
                            <td class="text-left"><?= htmlspecialchars($product_name ?: 'Unknown') ?></td>
                            <td class="text-right">##</td>
                            <td class="text-right">##</td>
                            <td class="text-right">##</td>
                            <td class="text-right">##</td>
                            <td class="text-right">##</td>
                        </tr>
                        <!-- Detail Row -->
                        <tr class="detail-row">
                            <td colspan="8" style="padding: 0; border-left: none; border-right: none;">
                                <div class="warna-label">Warna : ##</div>
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
                        <td colspan="8">
                            <strong>Jumlah Barang : <?= $total_items ?></strong>
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
                        <th style="width: 13%;">L. Perm</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="no-data">Tidak ada data ditemukan</td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="print-footer">
        <div>Printed on: <?= date('d M Y H:i:s') ?></div>
    </div>
    
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


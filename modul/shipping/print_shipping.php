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
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
            padding: 15mm;
            background: #fff;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
        }
        
        .print-header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .print-header .subtitle {
            font-size: 12pt;
            color: #000;
            font-weight: normal;
        }
        
        .info-section {
            margin-bottom: 15px;
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
            padding: 5px 10px 5px 0;
            vertical-align: top;
            color: #000;
            font-size: 10pt;
        }
        
        .info-value {
            display: table-cell;
            padding: 5px 0;
            color: #000;
            font-size: 10pt;
        }
        
        .table-container {
            margin-top: 20px;
            page-break-inside: avoid;
        }
        
        .table-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        
        .product-group {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .product-group-header {
            background-color: #333;
            color: #fff;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 10pt;
            border: 1px solid #000;
            margin-bottom: 0;
        }
        
        .product-group-table {
            border-top: none;
            margin-top: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border: 2px solid #000;
        }
        
        thead {
            background-color: #000;
            color: #fff;
        }
        
        th {
            border: 1px solid #000;
            padding: 8px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
            background-color: #000;
            color: #fff;
        }
        
        td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 9pt;
            color: #000;
        }
        
        tbody tr {
            background-color: #fff;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f5f5f5;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-left {
            text-align: left;
        }
        
        .no-data {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        .print-footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #000;
            text-align: right;
            font-size: 8pt;
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
                padding: 10mm;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                size: A4;
                margin: 10mm;
            }
            
            .print-header {
                page-break-after: avoid;
            }
            
            .table-container {
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
            
            .product-group-header {
                background-color: #333 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            tbody tr:nth-child(even) {
                background-color: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            tfoot {
                display: table-footer-group;
            }
            
            tr {
                page-break-inside: avoid;
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
    
    <div class="table-container">
        <div class="table-title">Product List</div>
        
        <?php if (count($grouped_data) > 0): ?>
            <?php 
            $global_no = 1;
            $total_items = 0;
            foreach ($grouped_data as $product_name => $items): 
                $total_items += count($items);
            ?>
                <div class="product-group">
                    <div class="product-group-header">
                        Product: <?= htmlspecialchars($product_name ?: 'Unknown') ?> (<?= count($items) ?> item<?= count($items) > 1 ? 's' : '' ?>)
                    </div>
                    <table class="product-group-table">
                        <thead>
                            <tr>
                                <th style="width: 8%;">No</th>
                                <th style="width: 35%;">Production Code</th>
                                <th style="width: 20%;">Created Time</th>
                                <th style="width: 37%;">Client Order Ref (PO)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $data): ?>
                                <tr>
                                    <td class="text-center"><?= $global_no++ ?></td>
                                    <td class="text-left"><?= htmlspecialchars($data['production_code'] ?? '-') ?></td>
                                    <td class="text-center"><?= htmlspecialchars($data['created_time'] ?? '-') ?></td>
                                    <td class="text-left"><?= htmlspecialchars($data['client_order_ref'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <div style="margin-top: 15px; padding: 10px; background-color: #e0e0e0; border: 1px solid #000; font-weight: bold; text-align: center;">
                Total Items: <?= $total_items ?>
            </div>
        <?php else: ?>
            <table>
                <tbody>
                    <tr>
                        <td colspan="4" class="no-data">Tidak ada data ditemukan</td>
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


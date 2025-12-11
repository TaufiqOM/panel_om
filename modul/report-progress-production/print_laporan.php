<?php
// File Conf
require __DIR__ . '/../../inc/config_odoo.php';
// Start Session
session_start();
$username = $_SESSION['username'] ?? '';

// Ambil data filter dari session
$filter = $_SESSION['print_filter'] ?? [
    'month' => date('m'),
    'year' => date('Y'),
    'supplier' => '',
    'start_date' => date('Y-m-01'),
    'end_date' => date('Y-m-t')
];

$selected_month = $filter['month'];
$selected_year = $filter['year'];
$selected_supplier = $filter['supplier'] ?? '';
$start_date = $filter['start_date'];
$end_date = $filter['end_date'];

// Function untuk mendapatkan gambar base64
function getImageBase64($imageData)
{
    if (empty($imageData)) return null;

    if (base64_encode(base64_decode($imageData, true)) !== $imageData) {
        $imageData = base64_encode($imageData);
    }

    if (strpos($imageData, '/9j/') === 0 || strpos($imageData, 'data:image/jpeg') === 0) {
        $mime = 'image/jpeg';
    } elseif (strpos($imageData, 'iVBOR') === 0 || strpos($imageData, 'data:image/png') === 0) {
        $mime = 'image/png';
    } elseif (strpos($imageData, 'R0lGOD') === 0 || strpos($imageData, 'data:image/gif') === 0) {
        $mime = 'image/gif';
    } else {
        $mime = 'image/jpeg';
    }

    if (strpos($imageData, 'data:image') === 0) {
        return $imageData;
    }

    return "data:$mime;base64,$imageData";
}

// Ambil data supplier dari Odoo untuk mendapatkan nama supplier yang dipilih
$partners = callOdooRead($username, "res.partner", [
    ["category_id", "ilike", "Production"]
], ["id", "name"]);

if (!is_array($partners)) {
    $partners = [];
}

// Cari nama supplier yang dipilih (jika ada filter supplier)
$selected_supplier_name = '';
if (!empty($selected_supplier)) {
    foreach ($partners as $partner) {
        if ($partner['id'] == $selected_supplier) {
            $selected_supplier_name = $partner['name'];
            break;
        }
    }
}

// Siapkan kondisi filter DASAR untuk blanket orders (tanpa filter supplier)
$filters = [
    ["due_date_order", ">=", $start_date],
    ["due_date_order", "<=", $end_date],
    ["state", "=", "sale"] // Menggunakan state "sale" seperti di halaman utama
];

// Ambil SEMUA data blanket orders dengan filter dasar (tanpa filter supplier)
$blanket_orders = callOdooRead($username, "sale.order", $filters, [
    "id",
    "partner_id",
    "client_order_ref",
    "due_date_order",
    "state",
]);

if (!is_array($blanket_orders)) {
    $blanket_orders = [];
}

// Urutkan array berdasarkan due_date_order
usort($blanket_orders, function($a, $b) {
    $dateA = strtotime($a['due_date_order'] ?? '');
    $dateB = strtotime($b['due_date_order'] ?? '');
    return $dateA - $dateB;
});

// Group by partner_id dan proses data
$grouped_orders = [];
foreach ($blanket_orders as $order) {
    $partner_id = $order['partner_id'][0] ?? null;
    if ($partner_id) {
        if (!isset($grouped_orders[$partner_id])) {
            $grouped_orders[$partner_id] = [
                'partner_name' => $order['partner_id'][1] ?? 'Unknown',
                'orders' => []
            ];
        }
        
        // Siapkan filter untuk order lines
        $line_filters = [["order_id", "=", $order['id']]];
        
        // TAMBAHKAN FILTER SUPPLIER DI LEVEL ORDER LINES SAJA
        if (!empty($selected_supplier_name)) {
            $line_filters[] = ["supp_order.name", "ilike", $selected_supplier_name];
        }
        
        // Ambil order lines untuk setiap order - DENGAN FILTER SUPPLIER
        $order_lines = callOdooRead($username, "sale.order.line", $line_filters, [
            "product_id",
            "name",
            "finish",
            "product_uom_qty",
            "product_uom",
            "info_to_production",
            "info_to_buyer",
            "supp_order",
        ]);

        if (!is_array($order_lines)) {
            $order_lines = [];
        }

        // Process each line to get product images
        $processed_lines = [];
        $supp_order_values = [];
        
        foreach ($order_lines as $line) {
            if ($line['product_uom_qty'] <= 0) continue;
            
            $product_id = $line['product_id'][0] ?? null;
            $product_image = null;
            
            if ($product_id) {
                $product_details = callOdooRead($username, "product.product", 
                    [["id", "=", $product_id]], 
                    ["default_code", "image_1920"]
                );
                
                if (is_array($product_details) && count($product_details) > 0) {
                    $image_data = $product_details[0]['image_1920'] ?? null;
                    if ($image_data) {
                        $product_image = getImageBase64($image_data);
                    }
                }
            }
            
            // Kumpulkan nilai supp_order yang unik
            if (!empty($line['supp_order']) && is_array($line['supp_order'])) {
                $supp_name = $line['supp_order'][1] ?? null;
                
                if ($supp_name && !in_array($supp_name, $supp_order_values)) {
                    $supp_order_values[] = $supp_name;
                }
            }
            
            $processed_lines[] = [
                'product_id' => $product_id,
                'product_ref' => $line['product_id'][1] ?? '-',
                'name' => $line['name'] ?? '-',
                'finish' => $line['finish'] ?? '-',
                'product_uom_qty' => $line['product_uom_qty'],
                'product_uom' => $line['product_uom'] ?? '-',
                'info_to_production' => $line['info_to_production'] ?? '-',
                'info_to_buyer' => $line['info_to_buyer'] ?? '-',
                'product_image' => $product_image,
                'supp_order' => $line['supp_order'] ?? ''
            ];
        }

        // Hanya tambahkan order jika memiliki lines setelah difilter
        // Jika tidak ada filter supplier, tampilkan semua order (meskipun lines kosong)
        if (empty($selected_supplier) || !empty($processed_lines)) {
            $order['lines'] = $processed_lines;
            $order['supp_order_values'] = $supp_order_values;
            $grouped_orders[$partner_id]['orders'][] = $order;
        }
    }
}

// Hapus group partner yang tidak memiliki orders setelah filter
$grouped_orders = array_filter($grouped_orders, function($group) {
    return !empty($group['orders']);
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Laporan Progress Order</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: white;
            font-size: 12px;
        }
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        .print-header h1 {
            margin: 0;
            font-size: 18px;
        }
        .print-info {
            text-align: center;
            margin-bottom: 15px;
            font-size: 11px;
            color: #666;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
            font-size: 10px;
        }
        td {
            padding: 6px;
            border: 1px solid #000;
            vertical-align: top;
        }
        .customer-header {
            background-color: #e0e0e0;
            font-size: 16px;
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 3px;
            page-break-before: always;
        }
        .customer-header:first-child {
            page-break-before: avoid;
        }
        .product-image {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }
        .no-image {
            color: #999;
            font-style: italic;
            font-size: 9px;
        }
        .header {
            background-color: #f0f0f0;
            font-weight: 700;
        }
        h2 {
            margin: 5px 0;
            font-size: 15px;
        }
        .supplier-area {
            background-color: #fff3cd;
            padding: 5px;
            border: 1px dashed #ffc107;
            border-radius: 3px;
            margin-top: 5px;
            font-weight: bold;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            .print-action {
                display: none;
            }
            .customer-header {
                page-break-before: always;
            }
            .customer-header:first-child {
                page-break-before: avoid;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
        
        .print-action {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .print-btn {
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-btn:hover {
            background-color: #218838;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="print-action">
        <button class="print-btn" onclick="window.print()">🖨 Cetak Laporan</button>
        <button class="print-btn" onclick="window.close()" style="background-color: #6c757d; margin-left: 10px;">Tutup</button>
    </div>

    <?php if (empty($grouped_orders)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <h3>Tidak ada data order untuk periode yang dipilih.</h3>
        </div>
    <?php else: ?>
        <?php foreach ($grouped_orders as $partner_id => $group): ?>
            <div class="customer-header">
                <b><?= htmlspecialchars($group['partner_name']) ?></b> ORDER LIST DUE DATE 
                <b>[
                <?php 
                // Tentukan status berdasarkan filter
                if (!empty($selected_supplier_name)) {
                    echo htmlspecialchars($selected_supplier_name);
                } else {
                    echo "ALL SUPPLIERS";
                }
                ?>
                ]</b> : <b><?= date("M Y", strtotime($start_date)) ?></b>
            </div>

            <?php foreach ($group['orders'] as $order): ?>
                <table>
                    <tr class="header" align="center">
                        <td width="3%">No</td>
                        <td width="8%">Reference</td>
                        <td width="15%">Nama Produk</td>
                        <td width="7%">Finish</td>
                        <td width="8%">Gambar Produk</td>
                        <td width="14%">Product Extra Info To Factory Production</td>
                        <td width="8%">Supplier</td>
                        <td width="8%">Due Date Order Update</td>
                        <td width="8%">Due Date Item Update</td>
                        <td width="6%">Qty Order</td>
                        <td width="5%">Qty TTB</td>
                        <td width="5%">Tgl Terima Gbr</td>
                    </tr>

                    <tr>
                        <td colspan="3">
                            ID Order :
                            <h2><?= htmlspecialchars($order['client_order_ref'] ?: 'N/A') ?></h2>
                        </td>
                        <td colspan="2">
                            Status :
                            <h2>Production</h2>
                        </td>
                        <td colspan="2">
                            Due Date Shp :
                            <h2><?= date("d M Y", strtotime(htmlspecialchars($order['due_date_order'] ?: ''))) ?></h2>
                        </td>
                        <td colspan="3">
                            <!-- AREA SUPPLIER ORDER DARI sale.order.line -->
                            <div class="supplier-area">
                                Due Date [ 
                                <?php 
                                if (!empty($order['supp_order_values'])) {
                                    // Tampilkan semua nilai supp_order yang unik, dipisahkan koma
                                    echo htmlspecialchars(implode(', ', $order['supp_order_values']));
                                } else {
                                    echo 'N/A';
                                }
                                ?> 
                                ] :
                                <h2>
                                    <?php
                                    if (!empty($order['due_date_order'])) {
                                        // Hitung H-15 dari due_date_order
                                        $due_date_departemen = date("d M Y", strtotime($order['due_date_order'] . ' -15 days'));
                                        echo htmlspecialchars($due_date_departemen);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </h2>
                            </div>
                        </td>
                        <td colspan="2" align="center">
                            <?php
                            $ID_Order = $order['client_order_ref'];
                            $value = $ID_Order;
                            $size = "50x50";
                            $src = "https://api.qrserver.com/v1/create-qr-code/?size={$size}&data=" . urlencode($value);
                            ?>
                            <img src="<?= htmlspecialchars($src) ?>" alt="QR code" width="50" height="50">
                        </td>
                    </tr>

                    <?php if (empty($order['lines'])): ?>
                        <tr>
                            <td colspan="12" align="center" style="color: #999;">
                                Tidak ada item order
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; ?>
                        <?php foreach ($order['lines'] as $line): ?>
                            <tr>
                                <td align="center"><?= $no++ ?></td>
                                <td>
                                    <?php
                                    $product_ref = $line['product_ref'] ?? '';
                                    if (preg_match('/\[(.*?)\]/', $product_ref, $matches)) {
                                        echo htmlspecialchars($matches[1]);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $product_name = $line['name'] ?? '';
                                    if (preg_match('/\[(.*?)\]/', $product_name, $matches)) {
                                        echo htmlspecialchars($matches[1]);
                                    } else {
                                        echo htmlspecialchars($product_name);
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($line['finish']) ?></td>
                                <td align="center">
                                    <?php if ($line['product_image']): ?>
                                        <img src="<?= $line['product_image'] ?>" 
                                             alt="Product Image" 
                                             class="product-image"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <span class="no-image" style="display: none;">No Image</span>
                                    <?php else: ?>
                                        <span class="no-image">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($line['info_to_production']) ?></td>
                                <td>
                                    <?php
                                    if (!empty($line['supp_order']) && is_array($line['supp_order'])) {
                                        echo htmlspecialchars($line['supp_order'][1] ?? 'N/A');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>-</td>
                                <td>-</td>
                                <td align="center"><?= htmlspecialchars($line['product_uom_qty']) ?></td>
                                <td align="center">-</td>
                                <td align="center">-</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="print-action">
        <button class="print-btn" onclick="window.print()">🖨 Cetak Laporan</button>
        <button class="print-btn" onclick="window.close()" style="background-color: #6c757d; margin-left: 10px;">Tutup</button>
    </div>
</body>
</html>
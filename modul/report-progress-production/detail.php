<?php
// File Conf
require __DIR__ . '/../../inc/config_odoo.php';
// Start Session
$username = $_SESSION['username'] ?? '';

// Function untuk mendapatkan gambar base64
function getImageBase64($imageData)
{
    if (empty($imageData)) return null;

    // Jika datanya belum base64 (masih blob biner)
    if (base64_encode(base64_decode($imageData, true)) !== $imageData) {
        $imageData = base64_encode($imageData);
    }

    // Deteksi tipe MIME berdasarkan header base64
    if (strpos($imageData, '/9j/') === 0 || strpos($imageData, 'data:image/jpeg') === 0) {
        $mime = 'image/jpeg';
    } elseif (strpos($imageData, 'iVBOR') === 0 || strpos($imageData, 'data:image/png') === 0) {
        $mime = 'image/png';
    } elseif (strpos($imageData, 'R0lGOD') === 0 || strpos($imageData, 'data:image/gif') === 0) {
        $mime = 'image/gif';
    } else {
        $mime = 'image/jpeg'; // default
    }

    // Jika sudah ada data:image prefix, return langsung
    if (strpos($imageData, 'data:image') === 0) {
        return $imageData;
    }

    return "data:$mime;base64,$imageData";
}

// Handle filter month, year, dan supplier dari form
$selected_month = $_POST['month'] ?? date('m');
$selected_year = $_POST['year'] ?? date('Y');
$selected_supplier = $_POST['supp'] ?? '';

// Konversi month-year ke start_date dan end_date
$start_date = date('Y-m-d', strtotime($selected_year . '-' . $selected_month . '-01'));
$end_date = date('Y-m-t', strtotime($selected_year . '-' . $selected_month . '-01'));

// Simpan data filter ke session untuk halaman print
$_SESSION['print_filter'] = [
    'month' => $selected_month,
    'year' => $selected_year,
    'supplier' => $selected_supplier,
    'start_date' => $start_date,
    'end_date' => $end_date
];

// Ambil data supplier dari Odoo (category_id = 8)
$partners = callOdooRead($username, "res.partner", [
    ["category_id", "ilike", "Production"]
], ["id", "name"]);

if (!is_array($partners)) {
    $partners = [];
}

// Siapkan kondisi filter DASAR untuk blanket orders (tanpa filter supplier)
$filters = [
    ["due_date_order", ">=", $start_date],
    ["due_date_order", "<=", $end_date],
    ["state", "=", "sale"]
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
    return $dateA - $dateB; // Ascending (terlama ke terbaru)
});

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
            "production_av",
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
            
            // Get product image if product_id exists
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
                'production_av' => $line['production_av'] ?? '-',
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
    <title>Laporan Progress Order</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            width: 100%;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .filter-form {
            background-color: #e9ecef;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-form label {
            font-weight: bold;
        }
        .filter-form select, .filter-form input {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .filter-form button {
            padding: 5px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .filter-form button:hover {
            background-color: #0056b3;
        }
        .filter-form .reset-btn {
            background-color: #6c757d;
        }
        .filter-form .reset-btn:hover {
            background-color: #545b62;
        }
        .filter-form .print-btn {
            background-color: #28a745;
        }
        .filter-form .print-btn:hover {
            background-color: #218838;
        }
        .filter-info {
            background-color: #d1ecf1;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border-left: 4px solid #0c5460;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        td {
            padding: 8px;
            border: 1px solid #000;
            vertical-align: top;
        }
        .customer-header {
            background-color: #e0e0e0;
            font-size: 25px;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .product-image {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }
        .no-image {
            color: #999;
            font-style: italic;
        }
        .header {
            background-color: #f0f0f0;
            font-weight: 700;
        }
        .supplier-area {
            background-color: #fff3cd;
            padding: 5px;
            border: 1px dashed #ffc107;
            border-radius: 3px;
            margin-top: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Laporan Progress Order</h1>
        
        <!-- Form Filter -->
        <form method="POST" class="filter-form">
            <label for="month">Bulan:</label>
            <select name="month" id="month">
                <?php
                $months = [
                    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                ];
                foreach ($months as $value => $name) {
                    $selected = ($value == $selected_month) ? 'selected' : '';
                    echo "<option value='$value' $selected>$name</option>";
                }
                ?>
            </select>
            
            <label for="year">Tahun:</label>
            <select name="year" id="year">
                <?php
                $current_year = date('Y');
                for ($year = $current_year - 2; $year <= $current_year + 2; $year++) {
                    $selected = ($year == $selected_year) ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>

            <label for="supp">Supp:</label>
            <select name="supp" id="supp">
                <option value="">-- Semua Supplier --</option>
                <?php foreach ($partners as $partner): ?>
                    <option value="<?= $partner['id'] ?>" 
                        <?= ($selected_supplier == $partner['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($partner['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit">Filter</button>
            <button type="button" class="reset-btn" onclick="resetFilter()">Reset</button>

            <?php if (isset($_POST['month']) || isset($_POST['year']) || isset($_POST['supp'])): ?>
            <button type="button" class="print-btn" onclick="openPrintPage()">🖨️ Buka Halaman Print</button>
            <?php endif; ?>
        </form>
        
        <!-- Info Filter Aktif -->
        <div class="filter-info">
            Menampilkan data untuk periode: <strong><?= date("F Y", strtotime($selected_year . '-' . $selected_month . '-01')) ?></strong>
            (<?= date("d M Y", strtotime($start_date)) ?> - <?= date("d M Y", strtotime($end_date)) ?>)
            <?php if (!empty($selected_supplier)): ?>
                <br>Supplier: <strong>
                <?php 
                $supplier_name = 'Unknown';
                foreach ($partners as $partner) {
                    if ($partner['id'] == $selected_supplier) {
                        $supplier_name = $partner['name'];
                        break;
                    }
                }
                echo htmlspecialchars($supplier_name);
                ?>
                </strong>
            <?php endif; ?>
            <br>
            <em>Hanya menampilkan order dengan status: Done</em>
        </div>

        <?php if (empty($grouped_orders)): ?>
            <div style="text-align: center; padding: 20px; color: #666;">
                <?php if (!empty($selected_supplier)): ?>
                    Tidak ada data order untuk supplier dan periode yang dipilih.
                <?php else: ?>
                    Tidak ada data order untuk periode yang dipilih.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_orders as $partner_id => $group): ?>
                <div class="customer-header">
                    <b><?= htmlspecialchars($group['partner_name']) ?></b> ORDER LIST DUE DATE <b>[ 
                    <?php 
                        // Tentukan status berdasarkan filter
                        if (!empty($selected_supplier)) {
                            echo htmlspecialchars($selected_supplier_name);
                        } else {
                            echo "ALL DEPARTEMEN";
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
                                $value = $ID_Order; // value dari QR
                                $size = "60x60";
                                $src = "https://api.qrserver.com/v1/create-qr-code/?size={$size}&data=" . urlencode($value);
                                ?>
                                <img src="<?= htmlspecialchars($src) ?>" alt="QR code" width="60" height="60">
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
                                        // Cari teks dalam kurung siku [ ]
                                        if (preg_match('/\[(.*?)\]/', $product_ref, $matches)) {
                                            echo htmlspecialchars($matches[1]); // Tampilkan hanya yang di dalam []
                                        } else {
                                            echo '-'; // Jika tidak ada kurung siku, tampilkan -
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $product_name = $line['name'] ?? '';
                                        // Cari teks dalam kurung siku [ ]
                                        if (preg_match('/\[(.*?)\]/', $product_name, $matches)) {
                                            echo htmlspecialchars($matches[1]); // Tampilkan hanya yang di dalam []
                                        } else {
                                            echo htmlspecialchars($product_name); // Jika tidak ada kurung siku, tampilkan semua
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
                                    <td>-</td> <!-- Due Date Order Update -->
                                    <td>-</td> <!-- Due Date Item Update -->
                                    <td align="center"><?= htmlspecialchars($line['product_uom_qty']) ?></td>
                                    <td align="center"><?= htmlspecialchars($line['production_av']) ?></td> <!-- Qty TTB -->
                                    <td align="center">-</td> <!-- Tgl Terima Gbr -->
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function resetFilter() {
            const today = new Date();
            const currentMonth = String(today.getMonth() + 1).padStart(2, '0');
            const currentYear = today.getFullYear();
            
            document.getElementById('month').value = currentMonth;
            document.getElementById('year').value = currentYear;
            document.getElementById('supp').value = '';
            document.forms[0].submit();
        }

        function openPrintPage() {
            window.open('report-progress-production/print_laporan.php', '_blank');
        }
    </script>
</body>
</html>
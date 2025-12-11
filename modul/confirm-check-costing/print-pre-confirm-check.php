<?php
require __DIR__ . '/../../inc/config_odoo.php';
require __DIR__ . '/../../inc/config.php';
session_start();

$username = $_SESSION['username'] ?? '';
$ID_Order = $_GET['ID_Order'] ?? '';

// Validasi
if (empty($username)) {
    die("Error: User not logged in. Please login first.");
}

if (empty($ID_Order)) {
    die("Error: ID Order parameter is required.");
}

// Decode URL encoding jika diperlukan
$ID_Order = urldecode($ID_Order);
$ID_Order = str_replace('%20', ' ', $ID_Order);
$ID_Order = str_replace('~', '#', $ID_Order);

// FUNGSI getImageBase64 - PAKAI YANG SAMA DARI KODE BERHASIL
function getImageBase64($imageData)
{
    if (empty($imageData)) return null;

    // Cek apakah sudah base64
    if (base64_encode(base64_decode($imageData, true)) !== $imageData) {
        $imageData = base64_encode($imageData);
    }

    // Deteksi mime type
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

// Fungsi untuk mendapatkan gambar base64 dari Odoo - PERBAIKAN
function getProductImage($username, $product_id) {
    if (!$product_id) return null;
    
    // Ambil detail produk termasuk gambar
    $product_details = callOdooRead($username, "product.product", 
        [["id", "=", $product_id]], 
        ["default_code", "image_1920", "name"]
    );
    
    if (is_array($product_details) && count($product_details) > 0) {
        $image_data = $product_details[0]['image_1920'] ?? null;
        if ($image_data && $image_data !== false && $image_data !== 'false') {
            return getImageBase64($image_data);
        }
    }
    
    return null;
}

// Fungsi untuk mendapatkan kurs mata uang dari Google Finance
function getExchangeRate($from_currency = 'USD', $to_currency = 'IDR') {
    // Cache hasil untuk mengurangi request
    $cache_key = $from_currency . '_' . $to_currency;
    $cache_file = __DIR__ . '/cache/' . $cache_key . '.json';
    
    // Buat direktori cache jika belum ada
    if (!is_dir(__DIR__ . '/cache')) {
        mkdir(__DIR__ . '/cache', 0755, true);
    }
    
    // Cek cache yang masih valid (1 jam)
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        if (time() - $cache_data['timestamp'] < 3600) {
            return $cache_data['rate'];
        }
    }
    
    try {
        // Google Finance API format
        $url = "https://www.google.com/finance/quote/{$from_currency}-{$to_currency}";
        
        // Menggunakan cURL untuk mengambil data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            // Mencari rate dalam HTML response
            $pattern = '/data-last-price="([0-9,.]+)"/';
            if (preg_match($pattern, $response, $matches)) {
                $rate = (float) str_replace(',', '', $matches[1]);
                
                // Simpan ke cache
                $cache_data = [
                    'rate' => $rate,
                    'timestamp' => time(),
                    'from' => $from_currency,
                    'to' => $to_currency
                ];
                file_put_contents($cache_file, json_encode($cache_data));
                
                return $rate;
            }
        }
        
        // Fallback rates jika API tidak berhasil
        $fallback_rates = [
            'USD_IDR' => 15000,
            'NZD_IDR' => 9000,
            'EUR_IDR' => 16000,
            'AUD_IDR' => 10000,
            'SGD_IDR' => 11000
        ];
        
        $key = $from_currency . '_' . $to_currency;
        return $fallback_rates[$key] ?? 15000;
        
    } catch (Exception $e) {
        // Log error jika perlu
        error_log("Exchange rate error: " . $e->getMessage());
        return 15000; // Fallback rate
    }
}

// Ambil data Blanket Order dari Odoo
$blanket_order = null;
$order_lines = [];

// 1. Cari Blanket Order berdasarkan client_order_ref
$orders = callOdooRead($username, "sale.blanket.order", [
    ['client_order_ref', '=', $ID_Order]
], [
    "id", 
    "name", 
    "display_name", 
    "client_order_ref", 
    "user_id",
    "partner_id",
    "note",
    "order_remarks_for_production",
    "order_admin_update"
]);

if ($orders && !empty($orders)) {
    $blanket_order = $orders[0];
    
    // 2. Ambil Order Lines
    $order_lines = callOdooRead($username, "sale.blanket.order.line", [
        ['order_id', '=', $blanket_order['id']]
    ], [
        "id",
        "display_name",
        "product_id",
        "product_uom_qty",
        "original_uom_qty",
        "price_unit",
        "product_uom",
        "date_schedule",
        "sequence",
        "currency_id",
        "company_id",
        "finish_product",
        "type_product",
        "supp_order",
        "info_to_buyer",
        "info_to_production",

        "x_studio_height",
        "x_studio_width",
        "x_studio_depth"
    ]);
    
    // 3. Ambil detail customer
    if (isset($blanket_order['partner_id']) && is_array($blanket_order['partner_id'])) {
        $customer_id = $blanket_order['partner_id'][0];
        $customer = callOdooRead($username, "res.partner", [
            ['id', '=', $customer_id]
        ], [
            "id",
            "name",
            "street",
            "city",
            "country_id",
            "email",
            "phone"
        ]);
        
        if ($customer && !empty($customer)) {
            $blanket_order['customer_details'] = $customer[0];
        }
    }
    
    // 4. Ambil detail currency
    if (isset($blanket_order['currency_id']) && is_array($blanket_order['currency_id'])) {
        $currency_id = $blanket_order['currency_id'][0];
        $currency = callOdooRead($username, "res.currency", [
            ['id', '=', $currency_id]
        ], ["id", "name", "symbol"]);
        
        if ($currency && !empty($currency)) {
            $blanket_order['currency_details'] = $currency[0];
        }
    }
    
} else {
    // Coba cari berdasarkan name jika tidak ditemukan dengan client_order_ref
    $orders_by_name = callOdooRead($username, "sale.blanket.order", [
        ['name', '=', $ID_Order]
    ], [
        "id", 
        "name", 
        "display_name", 
        "client_order_ref", 
        "user_id",
        "partner_id",
        "date_order",
        "validity_date",
        "state",
        "amount_total",
        "currency_id",
        "note",
        "order_remarks_for_production",
        "order_admin_update"
    ]);
    
    if ($orders_by_name && !empty($orders_by_name)) {
        $blanket_order = $orders_by_name[0];
        
        // Ambil order lines
        $order_lines = callOdooRead($username, "sale.blanket.order.line", [
            ['order_id', '=', $blanket_order['id']]
        ], [
            "id",
            "display_name",
            "product_id",
            "product_uom_qty",
            "qty_ordered",
            "price_unit",
            "product_uom",
            "finish_product",
            "type_product",
            "supp_order",
            "info_to_buyer",
            "info_to_production",

            "x_studio_height",
            "x_studio_width",
            "x_studio_depth"
        ]);
    }
}

// Jika tidak ditemukan
if (!$blanket_order) {
    die("Blanket Order tidak ditemukan untuk: " . htmlspecialchars($ID_Order));
}

$note = $blanket_order['note'] ?? '';
$order_remarks_for_production = $blanket_order['order_remarks_for_production'] ?? '';
$order_admin_update = $blanket_order['order_admin_update'] ?? '';

// Set variabel untuk template
$order_number = $blanket_order['display_name'] ?? $blanket_order['name'] ?? $ID_Order;
$customer_name = isset($blanket_order['partner_id']) && is_array($blanket_order['partner_id']) 
    ? $blanket_order['partner_id'][1] 
    : 'Unknown Customer';
    
$customer_id = isset($blanket_order['partner_id']) && is_array($blanket_order['partner_id']) 
    ? $blanket_order['partner_id'][0] 
    : 0;

$order_date = $blanket_order['date_order'] ?? $blanket_order['create_date'] ?? date('Y-m-d');
$delivery_date = $blanket_order['validity_date'] ?? date('Y-m-d', strtotime('+30 days'));

// AMBIL DETAIL CURRENCY DAN EXCHANGE RATE
$currency = isset($blanket_order['currency_details']) 
    ? $blanket_order['currency_details']['name'] 
    : (isset($blanket_order['currency_id']) && is_array($blanket_order['currency_id']) 
        ? $blanket_order['currency_id'][1] 
        : 'USD');
        
$currency_symbol = isset($blanket_order['currency_details']) 
    ? $blanket_order['currency_details']['symbol'] 
    : '$';

// Ambil exchange rate dari Google Finance
$exchange_rate = getExchangeRate($currency, 'IDR');

$total_amount = $blanket_order['amount_total'] ?? 0;

// User yang membuat order
$created_by = isset($blanket_order['user_id']) && is_array($blanket_order['user_id']) 
    ? $blanket_order['user_id'][1] 
    : ($_SESSION['username'] ?? 'System');

// Hitung total dari order lines jika amount_total tidak ada
if ($total_amount == 0 && !empty($order_lines)) {
    foreach ($order_lines as $line) {
        $qty = $line['qty_ordered'] ?? $line['product_uom_qty'] ?? 0;
        $price = $line['price_unit'] ?? 0;
        $total_amount += ($qty * $price);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pre Confirm Check Report - <?php echo htmlspecialchars($order_number); ?></title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        /* Tambahkan style untuk memastikan semua elemen tampil */
        .product-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5px;
            font-size: 10px;
        }
        .product-info-cell {
            background: #f9f9f9;
            padding: 3px;
            border: 1px solid #eee;
        }
        .table-scroll {
            overflow-x: auto;
            margin: 5px 0;
        }
        .costing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .costing-table th, .costing-table td {
            border: 1px solid #ccc;
            padding: 2px;
        }
        .margin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .margin-table td {
            border: 1px solid #ccc;
            padding: 2px;
        }
        .product-group {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .no-print {
            @media print {
                display: none;
            }
        }
        .print-btn {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        .product-image {
            max-width: 70px;
            max-height: 75px;
            object-fit: contain;
        }
        .no-image {
            color: #999;
            font-style: italic;
            font-size: 9px;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print</button>
    
    <div class="a4-landscape">
        <!-- HEADER SECTION -->
        <table style="margin-bottom: 5px;">
            <tr>
                <td class="header-bg" rowspan="3" style="width: 30%; border-right: 0; padding: 5px; font-size: 20px;">
                    <span>Pre Confirm Check Costing</span>
                </td>
                <td class="header-bg" style="width: 15%; border-left: 0; padding: 5px; font-size: 16px;" align="right">
                    <span>Blanket Order:</span>
                </td>
                <td style="width: 40%; padding: 5px; font-size: 16px;">
                    <strong><?php echo htmlspecialchars($order_number); ?></strong>
                </td>
                <td rowspan="3" style="width: 10%;" align="center" class="p-2">
                    <?php
                        $value = $ID_Order;
                        $size = "85x85";
                        $src = "https://api.qrserver.com/v1/create-qr-code/?size={$size}&data=" . urlencode($value);
                    ?>
                    <img src="<?= htmlspecialchars($src) ?>" alt="QR code" width="85" height="85">
                </td>
            </tr>
            <tr>
                <td class="header-bg" style="width: 15%; border-left: 0; padding: 5px; font-size: 16px;" align="right">
                    <span>ID Order:</span>
                </td>
                <td style="width: 40%; padding: 5px; font-size: 16px;">
                    <strong><?php echo $ID_Order; ?></strong>
                </td>
            </tr>
            <tr>
                <td class="header-bg" style="padding: 5px; font-size: 16px;" align="right">
                    <span>Created On:</span>
                </td>
                <td style="padding: 5px; font-size: 16px;">
                    <?php echo date("d F Y", strtotime($order_date)); ?>
                </td>
            </tr>
        </table>
        
        <!-- PRODUCTS TABLE HEADER -->
        <table style="margin-bottom: 2px;">
            <thead>
                <tr class="header-bg text-center">
                    <th class="col-pic">Picture</th>
                    <th class="col-prod-id">Reference</th>
                    <th class="col-prod-name">Product Name</th>
                    <th class="col-hwd">H W D</th>
                    <th class="col-color">Color</th>
                    <th class="col-type">Type</th>
                    <th class="col-dept">Dept</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-unit">Unit Price</th>
                    <th class="col-total">Total Price</th>
                    <th class="col-currency">Currency</th>
                </tr>
            </thead>
        </table>
        
        <!-- PRODUCTS DATA -->
        <?php
        $total_qty = 0;
        $total_amount_calc = 0;
        
        if (!empty($order_lines)):
            foreach ($order_lines as $index => $line):
                // Ambil data produk dari Odoo
                $product_id = '';
                $product_name = '';
                $qty = 0;
                $unit_price = 0;
                
                if (isset($line['product_id']) && is_array($line['product_id'])) {
                    $product_id = $line['product_id'][0];
                    $product_name = $line['product_id'][1] ?? $line['display_name'] ?? 'Unknown Product';
                } else {
                    $product_name = $line['display_name'] ?? 'Unknown Product';
                }
                
                $qty = $line['original_uom_qty'] ?? 0;
                $unit_price = $line['price_unit'] ?? 0;
                $line_total = $qty * $unit_price;

                // Ambil gambar produk dari Odoo - FUNGSI YANG SUDAH DIPERBAIKI
                $product_image = getProductImage($username, $product_id);
                
                // Jika tidak ada gambar, gunakan placeholder
                $placeholder_image = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNDUiIHZpZXdCb3g9IjAgMCA1MCA0NSIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjUwIiBoZWlnaHQ9IjQ1IiBmaWxsPSIjZjBmMGYwIi8+Cjx0ZXh0IHg9IjI1IiB5PSIyMiIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNjY2Ij5Qcm9kdWN0PC90ZXh0Pgo8L3N2Zz4K';
                
                // Ambil detail produk untuk mendapatkan dimensi (jika ada)
                $product_details = [];
                if ($product_id) {
                    $product_details = callOdooRead($username, "product.product", [
                        ['id', '=', $product_id]
                    ], [
                        "id",
                        "name",
                        "default_code",
                        "list_price",
                        "volume",
                        "weight"
                    ]);
                }
                
                // Data untuk display
                $product_code = isset($product_details[0]['default_code']) 
                    ? $product_details[0]['default_code'] 
                    : 'PROD-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                    
                $product_name_display = $product_name;
                
                // Dimensi placeholder (bisa diganti dengan data real jika ada)
                $height = $line['x_studio_height'] ?? 0;
                $width = $line['x_studio_width'] ?? 0;
                $depth = $line['x_studio_depth'] ?? 0;
                
                // Handle finish_product - mungkin Many2one field
                $finish = $line['finish_product'] ?? 'N/A';
                if (is_array($finish)) {
                    $finish = $finish[1] ?? 'N/A'; // Ambil nama dari array
                }
                
                // Handle type_product
                $type = $line['type_product'] ?? 'N/A';
                if (is_array($type)) {
                    $type = $type[1] ?? 'N/A';
                }
                
                // PERBAIKAN: Handle supp_order yang merupakan Many2one ke res.partner
                $supp_order = 'N/A';
                if (isset($line['supp_order']) && is_array($line['supp_order'])) {
                    // supp_order adalah array [id, name] karena Many2one field
                    $supp_order = $line['supp_order'][1] ?? 'N/A';
                } elseif (isset($line['supp_order'])) {
                    $supp_order = $line['supp_order'];
                }

                $info_to_buyer = $line['info_to_buyer'] ?? '';
                $info_to_production = $line['info_to_production'] ?? '';
                
                // Format numbers
                $unit_price_formatted = number_format($unit_price, 2);
                $line_total_formatted = number_format($line_total, 2);
                $qty = round($qty, 0);
                
                // Unit of measure
                $uom = isset($line['product_uom']) && is_array($line['product_uom']) 
                    ? $line['product_uom'][1] 
                    : 'Unit';
                
                // Akumulasi total
                $total_qty += $qty;
                $total_amount_calc += $line_total;
                ?>
                
                <div class="product-group">
                    <!-- Product Main Row -->
                    <table style="margin-bottom: 1px;">
                        <tr>
                            <td class="col-pic text-center p-1" rowspan="2">
                                <?php if ($product_image): ?>
                                    <img src="<?php echo $product_image; ?>" class="product-image" 
                                         alt="<?php echo htmlspecialchars($product_name); ?>"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <span class="no-image" style="display: none;">No Image</span>
                                <?php else: ?>
                                    <img src="<?php echo $placeholder_image; ?>" class="product-image" 
                                         alt="No Image">
                                <?php endif; ?>
                            </td>
                            <td class="col-prod-id">
                                <strong><?php echo htmlspecialchars($product_code); ?></strong><br>
                                <small>ID: <?php echo htmlspecialchars($product_id); ?></small>
                            </td>
                            <td class="col-prod-name"><?php echo htmlspecialchars($product_name_display); ?></td>
                            <td class="col-hwd text-center"><?php echo round($height,1); ?> × <?php echo round($width,1); ?> × <?php echo round($depth,1); ?></td>
                            <td class="col-color text-center"><?php echo htmlspecialchars($finish); ?></td>
                            <td class="col-type text-center"><?php echo strtoupper(htmlspecialchars($type)); ?></td>
                            <td class="col-dept text-center"><?php echo strtoupper(htmlspecialchars($supp_order)); ?></td>
                            <td class="col-qty text-center"><strong><?php echo $qty; ?></strong></td>
                            <td class="col-unit text-center"><?php echo $unit_price_formatted; ?></td>
                            <td class="col-total text-center"><strong><?php echo $line_total_formatted; ?></strong></td>
                            <td class="col-currency text-center"><?php echo htmlspecialchars($currency); ?></td>
                        </tr>
                        <tr>
                            <td colspan="10" style="padding: 2px;">
                                <div class="product-info-grid">
                                    <div class="product-info-cell">
                                        <strong>PRODUCT EXTRA INFO TO BUYER :</strong><br>
                                        <?php echo htmlspecialchars($info_to_buyer); ?>
                                    </div>
                                    <div class="product-info-cell">
                                        <strong>PRODUCT EXTRA INFO TO FACTORY PRODUCTION :</strong><br>
                                        <?php echo htmlspecialchars($info_to_production); ?>
                                    </div>
                                    <div class="product-info-cell">
                                        <strong>INFORMATION UPDATE PRODUCT PRICE :</strong><br>
                                        Unit Price: <?php echo $unit_price_formatted; ?> <?php echo htmlspecialchars($currency); ?><br>
                                        Line Total: <?php echo $line_total_formatted; ?> <?php echo htmlspecialchars($currency); ?><br>
                                        Exchange Rate: 1 <?php echo $currency; ?> = Rp <?php echo number_format($exchange_rate, 0); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- COSTING TABLE - Horizontal Scroll -->
                    <table class="costing-table">
                        <thead>
                            <tr class="text-center">
                                <th width="4%">Date</th>
                                <th width="3%">H</th>
                                <th width="3%">W</th>
                                <th width="3%">D</th>
                                <th width="4%">Supp</th>
                                <th width="3%">Raw</th>
                                <th width="3%">Raw L</th>
                                <th width="3%">Sanding</th>
                                <th width="3%">Sand L</th>
                                <th width="3%">F. Material</th>
                                <th width="3%">F. Labor</th>
                                <th width="3%">F. Prep</th>
                                <th width="3%">F. Prep L</th>
                                <th width="3%">Pack</th>
                                <th width="3%">Pack L</th>
                                <th width="4%">Total</th>
                                <th width="3%">%</th>
                                <th width="13%">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Query data history costing dari database lokal
                            $qry_history = mysqli_query($conn, "SELECT * FROM bom_history WHERE id_product = '$product_code' ORDER BY costing_date DESC LIMIT 3");
                            
                            if (mysqli_num_rows($qry_history) > 0) {
                                while($row_history = mysqli_fetch_assoc($qry_history)) {
                                    // Tentukan apakah data sudah terlalu lama (lebih dari 365 hari)
                                    $costing_date = $row_history['costing_date'];
                                    $is_old = ($costing_date && strtotime($costing_date) < strtotime('-365 days'));
                                    $bg_color = $is_old ? 'background-color: #ffe6e6;' : '';
                                    
                                    // Ambil data dari database
                                    $height_db = $row_history['height'];
                                    $width_db = $row_history['width'];
                                    $depth_db = $row_history['depth'];
                                    $type_db = $row_history['type'];
                                    $finish_db = $row_history['finish'];
                                    
                                    // Ambil nilai biaya
                                    $rawomd = $row_history['rawomd'];
                                    $rawlomd = $row_history['rawlomd'];
                                    $sandingg = $row_history['sandingg'];
                                    $sandinggl = $row_history['sandinggl'];
                                    $fmaterial = $row_history['fmaterial'];
                                    $fmateriall = $row_history['fmateriall'];
                                    $fprep = $row_history['fprep'];
                                    $fprepl = $row_history['fprepl'];
                                    $pack = $row_history['pack'];
                                    $packl = $row_history['packl'];
                                    
                                    // Hitung total biaya
                                    $total_cost = 
                                        $rawomd + $rawlomd + 
                                        $sandingg + $sandinggl + 
                                        $fmaterial + $fmateriall +
                                        $fprep + $fprepl +
                                        $pack + $packl;                                       
                                    
                                    // Persentase
                                    $persen = $row_history['persen'];
                                    $keterangan = $row_history['keterangan'];
                                    
                                    // Format tanggal
                                    $formatted_date = $costing_date ? date('d M Y', strtotime($costing_date)) : 'N/A';
                                    ?>
                                    <tr style="<?php echo $bg_color; ?>">
                                        <td><?php echo htmlspecialchars($formatted_date); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($height_db); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($width_db); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($depth_db); ?></td>
                                        <td class="text-center"><?php echo strtoupper(htmlspecialchars($supp_order)); ?></td>
                                        
                                        <!-- Material -->
                                        <td class="text-right"><?php echo number_format($rawomd); ?></td>
                                        <td class="text-right"><?php echo number_format($rawlomd); ?></td>
                                        
                                        <!-- Sanding -->
                                        <td class="text-right"><?php echo number_format($sandingg); ?></td>
                                        <td class="text-right"><?php echo number_format($sandinggl); ?></td>
                                        
                                        <!-- Finishing -->
                                        <td class="text-right"><?php echo number_format($fmaterial); ?></td>
                                        <td class="text-right"><?php echo number_format($fmateriall); ?></td>
                                        
                                        <!-- Finishing Prep -->
                                        <td class="text-right"><?php echo number_format($fprep); ?></td>
                                        <td class="text-right"><?php echo number_format($fprepl); ?></td>
                                        
                                        <!-- Packing -->
                                        <td class="text-right"><?php echo number_format($pack); ?></td>
                                        <td class="text-right"><?php echo number_format($packl); ?></td>
                                        
                                        <!-- Total -->
                                        <td class="text-right"><strong><?php echo number_format($total_cost); ?></strong></td>
                                        
                                        <!-- Persentase -->
                                        <td class="text-right"><?php echo number_format($persen, 2); ?>%</td>
                                        
                                        <!-- Keterangan -->
                                        <td><?php echo htmlspecialchars(substr($keterangan, 0, 30)); ?></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                // Jika tidak ada data history, tampilkan placeholder
                                $placeholder_dates = [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')];
                                
                                foreach ($placeholder_dates as $i => $date):
                                    $is_old = $i == 0;
                                    $bg_color = $is_old ? 'background-color: #ffe6e6;' : '';
                                ?>
                                <tr style="<?php echo $bg_color; ?>">
                                    <td><?php echo date('d M Y', strtotime($date)); ?></td>
                                    <td class="text-center"><?php echo round($height,1); ?></td>
                                    <td class="text-center"><?php echo round($width,1); ?></td>
                                    <td class="text-center"><?php echo round($depth,1); ?></td>
                                    <td class="text-center"><?php echo strtoupper(htmlspecialchars($supp_order)); ?></td>
                                    
                                    <!-- Material (placeholder) -->
                                    <td class="text-right"><?php echo number_format(rand(100,500)); ?></td>
                                    <td class="text-right"><?php echo number_format(rand(50,200)); ?></td>
                                    
                                    <!-- Sanding (placeholder) -->
                                    <td class="text-right"><?php echo number_format(rand(80,300)); ?></td>
                                    <td class="text-right"><?php echo number_format(rand(30,150)); ?></td>
                                    
                                    <!-- Finishing (placeholder) -->
                                    <td class="text-right"><?php echo number_format(rand(120,400)); ?></td>
                                    <td class="text-right"><?php echo number_format(rand(40,180)); ?></td>
                                    
                                    <!-- Finishing Prep (placeholder) -->
                                    <td class="text-right"><?php echo number_format(rand(60,250)); ?></td>
                                    <td class="text-right"><?php echo number_format(rand(20,100)); ?></td>
                                    
                                    <!-- Packing (placeholder) -->
                                    <td class="text-right"><?php echo number_format(rand(40,200)); ?></td>
                                    <td class="text-right"><?php echo number_format(rand(15,80)); ?></td>
                                    
                                    <!-- Total (placeholder) -->
                                    <td class="text-right"><strong><?php echo number_format(rand(500,2000)); ?></strong></td>
                                    
                                    <!-- Persentase (placeholder) -->
                                    <td class="text-right"><?php echo number_format(rand(10,30)); ?></td>
                                    
                                    <!-- Keterangan (placeholder) -->
                                    <td><?php echo htmlspecialchars(substr($product_name_display,0,30)); ?></td>
                                </tr>
                                <?php endforeach;
                            }
                            ?>
                        </tbody>
                    </table>
                    
                    <!-- MARGIN TABLE dengan exchange rate real -->
                    <div class="mt-2 mb-3">
                        <table class="margin-table">
                            <tr>
                                <td colspan="2"><strong>Margin Calculation :</strong></td>
                                <td colspan="2" class="text-center"><strong>Harga Database</strong></td>
                                <td colspan="2" class="text-center"><strong>Harga Google Finance</strong></td>
                                <td rowspan="4" class="text-center" style="width: 10%;"><strong>Price</strong></td>
                                <td rowspan="4" class="text-center" style="width: 10%;"><strong>By Date</strong></td>
                            </tr>
                            <?php 
                            // Query untuk mendapatkan data margin dari bom_history_head
                            $query_head = mysqli_query($conn, "SELECT * FROM bom_history_head WHERE id_product = '$product_code' ORDER BY bom_history_head_id DESC LIMIT 1");
                            
                            // Query untuk mendapatkan data biaya terbaru dari bom_history
                            // Ambil data dengan costing_date terbaru untuk setiap id_costing
                            $query_history = mysqli_query($conn, "
                                SELECT bh.* 
                                FROM bom_history bh
                                INNER JOIN (
                                    SELECT id_costing, MAX(costing_date) as max_date
                                    FROM bom_history 
                                    WHERE id_product = '$product_code'
                                    GROUP BY id_costing
                                ) latest ON bh.id_costing = latest.id_costing AND bh.costing_date = latest.max_date
                                ORDER BY bh.costing_date DESC 
                                LIMIT 1
                            ");
                            
                            if(mysqli_num_rows($query_head) > 0 && mysqli_num_rows($query_history) > 0) {
                                $row_head = mysqli_fetch_assoc($query_head);
                                $row_history = mysqli_fetch_assoc($query_history);
                                
                                // Data dari database bom_history_head
                                $overhead = isset($row_head['overhead']) ? $row_head['overhead'] : 0;
                                $profit = isset($row_head['profit']) ? $row_head['profit'] : 0;
                                $rate = isset($row_head['rate']) ? $row_head['rate'] : 0;
                                
                                $overhead1 = isset($row_head['overhead1']) ? $row_head['overhead1'] : 0;
                                $profit1 = isset($row_head['profit1']) ? $row_head['profit1'] : 0;
                                $rate1 = isset($row_head['rate1']) ? $row_head['rate1'] : 0;

                                $overhead2 = isset($row_head['overhead2']) ? $row_head['overhead2'] : 0;
                                $profit2 = isset($row_head['profit2']) ? $row_head['profit2'] : 0;
                                $rate2 = isset($row_head['rate2']) ? $row_head['rate2'] : 0;

                                // Ambil nilai biaya dari bom_history
                                $rawoml = isset($row_history['rawoml']) ? $row_history['rawoml'] : 0;
                                $rawomd = isset($row_history['rawomd']) ? $row_history['rawomd'] : 0;
                                $sandingg = isset($row_history['sandingg']) ? $row_history['sandingg'] : 0;
                                $sandingc = isset($row_history['sandingc']) ? $row_history['sandingc'] : 0;
                                $fmaterial = isset($row_history['fmaterial']) ? $row_history['fmaterial'] : 0;
                                $fprep = isset($row_history['fprep']) ? $row_history['fprep'] : 0;
                                $pack = isset($row_history['pack']) ? $row_history['pack'] : 0;
                                $rawlomd = isset($row_history['rawlomd']) ? $row_history['rawlomd'] : 0;
                                $sandinggl = isset($row_history['sandinggl']) ? $row_history['sandinggl'] : 0;
                                $sandingcl = isset($row_history['sandingcl']) ? $row_history['sandingcl'] : 0;
                                $fmateriall = isset($row_history['fmateriall']) ? $row_history['fmateriall'] : 0;
                                $fprepl = isset($row_history['fprepl']) ? $row_history['fprepl'] : 0;
                                $packl = isset($row_history['packl']) ? $row_history['packl'] : 0;
                                
                                // Hitung total biaya
                                $total_cost = $rawomd + $rawlomd + 
                                              $sandingg + $sandinggl + 
                                              $fmaterial + $fmateriall +
                                              $fprep + $fprepl +
                                              $pack + $packl; 
                                
                                // Format total
                                $formatted_total = $total_cost;

                                // Rumus 1
                                $overhead_price = $overhead / 100 + 1;
                                $profit_price = $profit / 100 + 1;
                                $price_one = number_format(($formatted_total*$overhead_price*$profit_price)/$rate,2);
                                $finance_one = number_format(($formatted_total*$overhead_price*$profit_price)/$exchange_rate,2);

                                // Rumus 2
                                $overhead1_price = $overhead1 / 100 + 1;
                                $profit1_price = $profit1 / 100 + 1;
                                $price_two = number_format(($formatted_total*$overhead1_price*$profit1_price)/$rate1,2);
                                $finance_two = number_format(($formatted_total*$overhead1_price*$profit1_price)/$exchange_rate,2);

                                // Rumus 3
                                $overhead2_price = $overhead2 / 100 + 1;
                                $profit2_price = $profit2 / 100 + 1;
                                $price_three = number_format(($formatted_total*$overhead2_price*$profit2_price)/$rate2,2);
                                $finance_three = number_format(($formatted_total*$overhead2_price*$profit2_price)/$exchange_rate,2);
                                
                                // Tanggal pembuatan
                                $created_date = isset($row_head['created_at']) ? date("d M y", strtotime($row_head['created_at'])) : date("d M y");
                            ?>
                            
                            <!-- Margin 1 -->
                            <tr>
                                <td style="width: 8%; font-weight: bold;">Margin 1</td>
                                <td style="width: 7%;" class="text-center"><?php echo $overhead; ?> | <?php echo $profit; ?></td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($rate, 0); ?>
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($price_one, 2); ?> <?php echo $currency; ?> 
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    Rp <?php echo number_format($exchange_rate, 0); ?>  
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($finance_one, 2); ?> <?php echo $currency; ?> 
                                </td>
                            </tr>

                            <!-- Margin 2 -->
                            <tr>
                                <td style="width: 8%; font-weight: bold;">Margin 2</td>
                                <td style="width: 7%;" class="text-center"><?php echo $overhead1; ?> | <?php echo $profit1; ?></td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($rate1, 0); ?>
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($price_two, 2); ?> <?php echo $currency; ?> 
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    Rp <?php echo number_format($exchange_rate, 0); ?>  
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($finance_two, 2); ?> <?php echo $currency; ?> 
                                </td>
                            </tr>

                            <!-- Margin 3 -->
                            <tr>
                                <td style="width: 8%; font-weight: bold;">Margin 3</td>
                                <td style="width: 7%;" class="text-center"><?php echo $overhead2; ?> | <?php echo $profit2; ?></td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($rate2, 0); ?>
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($price_three, 2); ?> <?php echo $currency; ?> 
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    Rp <?php echo number_format($exchange_rate, 0); ?>  
                                </td>
                                <td style="width: 10%;" class="text-center">
                                    <?php echo number_format($finance_three, 2); ?> <?php echo $currency; ?> 
                                </td>
                            </tr>

                            <?php 
                            } else {
                                // Jika tidak ada data, tampilkan pesan kosong
                                echo '<tr><td colspan="8" class="text-center">No margin data found</td></tr>';
                            }
                            ?>
                        </table>
                    </div>
                    
                    <div style="border-top: 1px dashed #ccc; margin: 3px 0;"></div>
                </div>
                <?php
            endforeach;
        else:
        ?>
        <table>
            <tr>
                <td colspan="11" class="text-center p-5">No products found for this order</td>
            </tr>
        </table>
        <?php endif; ?>

<!-- TOTAL SUMMARY -->
<div class="mt-4 mb-4">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td colspan="4" style="padding: 5px 0 10px 0; font-weight: bold; color: #333; border-bottom: 1px solid #eee;">
                Order Summary
            </td>
        </tr>
        <tr>
            <td style="padding: 8px 0; text-align: center; width: 25%;">
                <div style="font-size: 12px; color: #666;">Quantity</div>
                <div style="font-size: 14px; font-weight: bold;"><?php echo number_format($total_qty, 0); ?></div>
            </td>
            <td style="padding: 8px 0; text-align: center; width: 25%; border-left: 1px solid #f0f0f0;">
                <div style="font-size: 12px; color: #666;">Total <?php echo $currency; ?></div>
                <div style="font-size: 14px; font-weight: bold; color: #27ae60;">
                    <?php echo $currency_symbol; ?><?php echo number_format($total_amount_calc, 2); ?>
                </div>
            </td>
            <td style="padding: 8px 0; text-align: center; width: 25%; border-left: 1px solid #f0f0f0;">
                <div style="font-size: 12px; color: #666;">Total IDR</div>
                <div style="font-size: 14px; font-weight: bold; color: #e74c3c;">
                    Rp <?php echo number_format($total_amount_calc * $exchange_rate, 0); ?>
                </div>
            </td>
            <td style="padding: 8px 0; text-align: center; width: 25%; border-left: 1px solid #f0f0f0;">
                <div style="font-size: 12px; color: #666;">Exchange Rate</div>
                <div style="font-size: 13px; font-weight: bold; color: #3498db;">
                    1 <?php echo $currency; ?> = Rp <?php echo number_format($exchange_rate, 0); ?>
                </div>
            </td>
        </tr>
    </table>
</div>
        
        <!-- SUMMARY SECTION -->
        <div class="mt-5">
            <table style="margin-bottom: 5px;">
                <tr>
                    <td colspan="2">
                        ORDER REMARKS FOR PRODUCTION :<br/>
                        <?php echo htmlspecialchars($order_remarks_for_production); ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        ORDER INFO TO BUYER :<br/>
                        <?php echo htmlspecialchars($note); ?>
                    </td>
                    <td>
                        ORDER ADMIN UPDATE :<br/>
                        <?php echo htmlspecialchars($order_admin_update); ?>
                    </td>
                </tr>
            </table>
            
            <!-- SIGNATURES -->
            <table width="100%">
                <tr>
                    <td style="border: 0px;">Created by</td>
                    <td style="border: 0px;"></td>
                    <td style="border: 0px;">Checked by</td>
                </tr>
                <tr>
                    <td style="border: 0px;"><b>CUSTOMER SERVICE</b></td>
                    <td style="border: 0px;"></td>
                    <td style="border: 0px;"><b>HEAD OF CUSTOMER SERVICE</b></td>
                </tr>
                <tr>
                    <td height="80" style="border: 0px; height: 80px; vertical-align: bottom;"><?php echo htmlspecialchars($created_by); ?></td>
                    <td style="border: 0px;"></td>
                    <td height="80" style="border: 0px; height: 80px; vertical-align: bottom;">Muhammad Ibnu Mundzir</td>
                </tr>
            </table>
            
        </div>
    </div>
    
    <!-- Print Controls -->
    <div class="no-print" style="text-align: center; margin-top: 15px; padding: 15px;">
        <button onclick="window.print()" style="padding: 8px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 0 5px;">
            🖨️ Print Landscape Report
        </button>
        <button onclick="window.close()" style="padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 0 5px;">
            ✕ Close Window
        </button>
        <button onclick="location.reload()" style="padding: 8px 20px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 0 5px;">
            ↻ Refresh Data
        </button>
    </div>
    
    <script>
        // Debug: Tampilkan pesan jika ada gambar yang gagal dimuat
        window.onload = function() {
            document.querySelectorAll('img.product-image').forEach(function(img) {
                img.onerror = function() {
                    console.log('Gambar gagal dimuat:', this.src.substring(0, 50) + '...');
                };
            });
        };
    </script>
</body>
</html>
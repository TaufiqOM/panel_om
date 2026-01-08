<?php
require __DIR__ . '/../../../inc/config.php';

date_default_timezone_set('Asia/Jakarta');

$line_id = isset($_GET['line_id']) ? intval($_GET['line_id']) : 0;

if ($line_id <= 0) {
  die("Line ID tidak valid.");
}

// Query barcode items with additional data
$sql = "SELECT bi.barcode,
               bi.customer_name,
               bi.sequence_no,
               bi.created_at,
               bl.product_id,
               bl.product_ref,
               bl.image_1920,
               bl.finishing,
               bl.order_date,
               bl.order_due_date,
               bl.qty_order,
               bl.info_to_production,
               bl.info_to_buyer,
               bl.product_name,
               bl.country,
               bl.sale_order_ref as so_name
        FROM barcode_item bi
        JOIN barcode_lot bl ON bi.lot_id = bl.id
        WHERE bi.sale_order_line_id = ?
        ORDER BY bi.sequence_no";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $line_id);
$stmt->execute();
$result = $stmt->get_result();
$barcodes = [];
while ($row = $result->fetch_assoc()) {
  $barcodes[] = $row;
}
$stmt->close();

if (empty($barcodes)) {
  die("Tidak ada barcode yang di-generate untuk line ini.");
}

// Get first barcode data for header
$first_barcode = $barcodes[0];
$so_name = $first_barcode['so_name'];
$product_name = $first_barcode['product_name'];
$customer_name = $first_barcode['customer_name'];
$product_id = $first_barcode['product_id'];
$product_ref = $first_barcode['product_ref'] ?? '';
$image_1920 = $first_barcode['image_1920'];
$finishing = $first_barcode['finishing'] ?? '';
$qty_order = $first_barcode['qty_order'];
$info_to_production = $first_barcode['info_to_production'] ?? '-';
$info_to_buyer = $first_barcode['info_to_buyer'] ?? '-';
$order_date = date('d M y', strtotime($first_barcode['order_date'])) ?? '-';
$order_due_date = date('d M y', strtotime($first_barcode['order_due_date'])) ?? '-';
$country = $first_barcode['country'] ?? '-';
$item_description = $first_barcode['item_description'] ?? '';

// Fetch HWD from Odoo (Logic moved from inside loop)
$hwd_info = '';
if (isset($product_id) && $product_id > 0) {
    // Include config_odoo.php
    if (!function_exists('callOdooRead')) {
        $odoo_config_path = __DIR__ . '/../../../inc/config_odoo.php';
        if (file_exists($odoo_config_path)) {
            require_once $odoo_config_path;
        }
    }

    if (function_exists('callOdooRead')) {
        // Helper to format float to avoid unnecessary zeros
        if (!function_exists('formatDim')) {
            function formatDim($val) {
                return (float)$val == (int)$val ? (int)$val : (float)$val;
            }
        }

        $product_data = callOdooRead('admin', 'product.product', [['id', '=', (int)$product_id]], ['height', 'width', 'depth']);

        if ($product_data && !empty($product_data)) {
            $p = $product_data[0];
            $h = isset($p['height']) ? formatDim($p['height']) : 0;
            $w = isset($p['width']) ? formatDim($p['width']) : 0;
            $d = isset($p['depth']) ? formatDim($p['depth']) : 0;

            if ($h > 0 || $w > 0 || $d > 0) {
                $hwd_info = "H:{$h} W:{$w} D:{$d}";
            }
        }
    }
}

// Prepare minimal data for QR generation
$qrData = array_map(function($b) {
  return [
    'barcode' => $b['barcode'],
    'so_name' => $b['so_name']
  ];
}, $barcodes);

function getImageBase64($imageData)
{
  if (empty($imageData)) return null;

  // Batasi maksimal 500KB
  if (strlen($imageData) > 500 * 1024) {
    return '/good/assets/media/img/product-kosong.png'; // fallback
  }

  // Jika datanya belum base64 (masih blob biner)
  if (base64_encode(base64_decode($imageData, true)) !== $imageData) {
    $imageData = base64_encode($imageData);
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->buffer($imageData) ?: 'image/jpeg';

  return "data:$mime;base64,$imageData";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>QR Code Sederhana - Fixed</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

  <!-- Print page size for A6 -->
  <style type="text/css" media="print">
    @page {
      size: A6;
      margin: 0;
    }
  </style>

  <style>
    body {
      background: #eee;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }

    .page {
      background: #fff;
      width: 105mm;
      /* A6 width */
      height: 148mm;
      /* A6 height */
      margin: 0;
      padding: 4mm;
      box-sizing: border-box;
      position: relative;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      page-break-inside: avoid;
    }

    .page-break {
      page-break-after: always;
    }

    img.logo {
      height: 100%;
      max-height: 35px;
      object-fit: contain;
    }

    .qr {
      padding: 3px;
      background-color: white;
      border-radius: 5px;
      margin: auto;
    }

    .qr-center {
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .qr-item-cell {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .info {
      text-align: center;
      margin-top: 5px;
      color: #333;
      font-size: 9px;
    }

    .print-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 999;
      border-radius: 50%;
      padding: 12px 18px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    @media print {
      body {
        background: none !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      .page {
        margin: 0 !important;
        box-shadow: none !important;
        width: 105mm !important;
        height: 148mm !important;
        padding: 4mm !important;
        page-break-inside: avoid !important;
        page-break-after: always !important;
      }

      .print-btn {
        display: none !important;
      }

      /* Ensure all text is visible and properly sized for print */
      * {
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
      }

      /* Optimize QR codes for print */
      .qr canvas {
        max-width: 100% !important;
        height: auto !important;
      }

      /* Ensure images maintain aspect ratio in print */
      img {
        max-width: 100% !important;
        height: auto !important;
      }

      /* Optimize table borders for print */
      .table-bordered {
        border: 1px solid #000 !important;
      }

      .table-bordered td,
      .table-bordered th {
        border: 1px solid #000 !important;
      }
    }

    .fs-small {
      font-size: 9px;
      line-height: 1.1;
    }

    .fs-medium {
      font-size: 10px;
      line-height: 1.1;
    }

    .fs-large {
      font-size: 14px;
      line-height: 1.1;
    }

    .fs-xlarge {
      font-size: 20px;
      line-height: 1.1;
    }

    .table-bordered td,
    .table-bordered th {
      border-color: #333 !important;
    }

    h6 {
      font-size: 13px;
      margin: 5px 0;
      line-height: 1.1;
    }

    .fs-product-name {
      font-size: 11px;
      line-height: 1.1;
    }

    /* Perbaikan untuk header */
    .header-table {
      height: 32px !important;
      max-height: 32px !important;
      table-layout: fixed;
      width: 100%;
      margin-bottom: 1px !important;
    }

    .header-table td {
      height: 32px !important;
      max-height: 32px !important;
      padding: 1px 2px !important;
      vertical-align: middle !important;
      overflow: hidden;
    }

    .header-table .fs-xlarge {
      line-height: 1 !important;
      font-size: 20px;
    }

    .header-table .fs-large {
      line-height: 1 !important;
      font-size: 14px;
    }

    /* Perbaikan khusus untuk kolom kanan */
    .header-table td:last-child {
      font-size: 14px !important;
      font-weight: bold;
      text-align: center;
      padding: 0 !important;
    }

    /* Pastikan logo memiliki tinggi yang konsisten */
    .header-table img.logo {
      max-height: 30px;
      width: auto;
      display: block;
      margin: 0 auto;
    }

    /* Perbaikan untuk text wrap di semua tabel */
    table {
      table-layout: fixed;
      width: 100%;
    }

    td {
      word-wrap: break-word;
      overflow-wrap: break-word;
      hyphens: auto;
      padding: 2px 3px !important;
    }

    /* Penyesuaian untuk konten yang panjang */
    .long-text {
      max-height: 100%;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    /* Flex container untuk konten utama - PERBAIKAN UTAMA */
    .content-container {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      /* Diubah dari space-between */
      gap: 1px;
      /* Kontrol jarak antar elemen untuk A6 */
      height: 100%;
    }

    /* Penyesuaian untuk tabel dengan konten panjang */
    .table-sm td {
      padding: 1px 2px !important;
    }

    /* Penyesuaian khusus untuk bagian catatan */
    .notes-cell {
      max-height: 45px;
      overflow: hidden;
    }

    .mb-1px {
      margin-bottom: 1px !important;
    }

    /* Perbaikan margin untuk semua tabel */
    .table {
      margin-bottom: 2px !important;
    }

    /* Spesifik untuk tabel kedua */
    .table:nth-child(2) {
      margin-bottom: 3px !important;
    }

    /* Perbaikan untuk bagian detail produk */
    .detail-table {
      margin-bottom: 3px !important;
    }

    /* Perbaikan untuk bagian QR item */
    .qr-item-table {
      margin-top: 3px !important;
    }

    .product-img {
      width: 100%;
      /* menyesuaikan lebar kolom */
      max-width: 130px;
      /* batas maksimum lebar */
      max-height: 120px;
      /* batas maksimum tinggi */
      object-fit: contain;
      /* gambar diperkecil agar tidak terpotong */
      border-radius: 8px;
      /* opsional: sudut melengkung */
      background-color: #f9f9f9;
      /* opsional: warna latar belakang */
      padding: 4px;
      /* sedikit jarak biar tidak nempel */
    }
  </style>
</head>

<body>
  <?php
  $image_src = getImageBase64($image_1920);
  ?>
  <?php foreach ($barcodes as $idx => $barcode):  ?>
    <!-- Card Produk 1 -->
    <div class="page page-break">
      <div class="content-container">

        <!-- Header -->
        <table class="table table-bordered border-dark header-table">
          <tr class="align-middle">
            <td class="text-center" style="width: 25%;">
              <img src="../../../good/assets/media/img/logo_c2.jpg" class="logo" alt="Logo">
            </td>
            <td class="fw-bold text-center" style="width: 50%; font-size: 28px !important;"><?php echo htmlspecialchars($product_ref); ?></td>
            <td class="text-center fw-bold" style="width: 25%; font-size: 20px !important;"><?php echo htmlspecialchars(substr($order_due_date, 3, 3) . substr($order_due_date, -2)); ?></td>
          </tr>
        </table>

        <!-- QR Order + Info -->
        <table class="table table-bordered border-dark">
          <tr>
            <td style="width: 75%;">
              <div style="text-align: justify; font-size: 10px; font-weight: bold;"><?php echo htmlspecialchars($info_to_buyer); ?></div>
            </td>
            <td class="text-center fs-small p-0" style="width: 25%;">
              <div class="mb-0">QR Order</div>
              <div id="qr-order-<?php echo $idx + 1 ?>" class="qr-center mb-1"></div>
              <div class="info"><?php echo htmlspecialchars($so_name); ?></div>
            </td>
          </tr>
        </table>

        <!-- Nama Produk -->
        <table class="table" style="height: 40px;">
          <h6 class="fw-bold fs-product-name"><?php echo htmlspecialchars($product_name); ?></h6>
        </table>

        <!-- Detail Produk -->
        <table class="table table-sm border-white align-middle detail-table" style="width: 100%;">
          <tr>
            <td style="width: 65%; vertical-align: top;">
              <table style="width: 100%;">
                <tr>
                  <td style="width: 50%; font-size: 13px;">Order Date:</td>
                  <td style="width: 50%; font-size: 13px;">Due Date:</td>
                </tr>
                <tr>
                  <td style="width: 50%; font-size: 13px;"><b><?php echo htmlspecialchars($order_date); ?></b></td>
                  <td style="width: 50%; font-size: 13px;"><b><?php echo htmlspecialchars($order_due_date); ?></b></td>
                </tr>
                <tr>
                  <td colspan="2" style="font-size: 15px;"><?php echo htmlspecialchars($finishing); ?></td>
                </tr>
                <tr>
                  <td colspan="2" style="font-size: 15px;"><?php echo htmlspecialchars($so_name); ?></td>
                </tr>
                <tr>
                  <td colspan="2" class="text-decoration-underline"
                    style="font-size: 20px; font-weight: bold;"><?php echo htmlspecialchars($customer_name); ?></td>
                </tr>
                <tr>
                  <td class="fs-large" style="width: 50%; font-weight: bold;">FRE</td>
                  <td class="fs-large" style="width: 50%;"><?php echo htmlspecialchars($country); ?></td>
                </tr>
              </table>
            </td>
            <td style="width: 35%; text-align: center; vertical-align: top;">
              <?php if ($image_src): ?>
                <img src="<?php echo $image_src; ?>" class="product-img">
              <?php else: ?>
                <img src="/good/assets/media/img/product-kosong.png" class="product-img">
              <?php endif; ?>
            </td>
          </tr>
        </table>

        <!-- Catatan + QC -->
        <table class="table table-bordered table-sm border-dark">
          <tr>
            <td rowspan="3" style="width: 93%;">
              <div style="text-align: justify; font-size: 10px; font-weight: bold;">
<?php
                echo htmlspecialchars($info_to_production); 
                if ($hwd_info) {
                    // Check if info_to_production is not empty to add a separator
                    if (!empty($info_to_production) && $info_to_production !== '-') {
                         echo " <span style='margin-left:5px; margin-right:5px;'>|</span> ";
                    }
                    echo htmlspecialchars($hwd_info);
                }
                ?>
              </div>
            </td>
            <td class="text-center fs-small" style="width: 7%;">FPS</td>
          </tr>
          <tr>
            <td class="p-0"><br><br></td>
          </tr>
          <tr>
            <td class="text-center fs-small">QC</td>
          </tr>
        </table>

        <!-- QR Item -->
        <table class="table border-white qr-item-table" style="width: 100%;">
          <tr>
            <td class="text-center qr-item-cell"
              style="width: 100%; text-align: center; vertical-align: top;">
              <div style="margin-bottom: 5px; display: flex; justify-content: center; align-items: center; font-size: 12px;">QR
                Item</div>
              <div id="qr-item-left-<?php echo $idx + 1 ?>" class="qr"></div>
            </td>
            <td class="text-center fs-medium" style="width: 50%; text-align: center; vertical-align: top;"
              rowspan="2">
              <table style="width: 100%; height: 100%;">
                <tr>
                  <td class="fs-medium">No Item</td>
                </tr>
                <tr>
                  <td style="font-size: 18px;"><b><?php echo htmlspecialchars($barcode['barcode']) ?></b></td>
                </tr>
                <tr>
                  <td class="fs-medium"><?= date('d F Y'); ?></td>
                </tr>
              </table>
            </td>
            <td class="text-center qr-item-cell"
              style="width: 100%; text-align: center; vertical-align: top;">
              <div style="margin-bottom: 5px; display: flex; justify-content: center; align-items: center; font-size: 12px;">QR
                Item</div>
              <div id="qr-item-right-<?php echo $idx + 1 ?>" class="qr"></div>
            </td>
          </tr>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Floating Print Button -->
  <button onclick="window.print()" class="btn btn-primary print-btn">🖨️</button>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const qrData = <?php echo json_encode($qrData, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;

      qrData.forEach((item, idx) => {
        const i = idx + 1;
        const orderCode = item.so_name || '';
        const itemCode  = item.barcode || '';

        // QR Order
        if (document.getElementById('qr-order-' + i)) {
          new QRCode(document.getElementById('qr-order-' + i), {
            text: orderCode,
            width: 55,
            height: 55,
            correctLevel: QRCode.CorrectLevel.L
          });
        }

        // QR Item (kiri & kanan)
        ['left', 'right'].forEach(side => {
          const el = document.getElementById('qr-item-' + side + '-' + i);
          if (el) {
            new QRCode(el, {
              text: itemCode,
              width: 60,
              height: 60,
              correctLevel: QRCode.CorrectLevel.L
            });
          }
        });
      });
    });
  </script>
</body>

</html>
<?php 
// 1. PEMROSESAN DATA TERPUSAT DI ATAS
include "../inc/config.php";

// Inisialisasi variabel
$pbp_number = $_GET['pbp_number'] ?? '';
$error = '';
$header_data = [];
$items = [];
$total_jenis_item = 0;
$total_kuantitas = 0;

// Set zona waktu dan buat timestamp cetak
date_default_timezone_set('Asia/Jakarta');
$print_timestamp = 'Dicetak pada: ' . date('d M Y H:i:s');

// Validasi awal
if (empty($pbp_number)) {
    $error = "Nomor PBP tidak diberikan.";
} else {
    try {
        // Query untuk mengambil semua data PBP yang relevan
        $sql = "SELECT 
                    pbp.wood_engineered_number, pbp.so_number, pbp.product_code, 
                    pbp.wood_engineered_date, pbp.employee_nik, pbp.wood_engineered_code,
                    pbp.wood_engineered_qty,
                    master.wood_engineered_name,
                    emp.name as employee_name
                FROM wood_engineered_pbp AS pbp
                LEFT JOIN wood_engineered AS master ON pbp.wood_engineered_code = master.wood_engineered_code 
                LEFT JOIN employee AS emp ON pbp.employee_nik = emp.barcode
                WHERE pbp.wood_engineered_number = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Gagal mempersiapkan query: " . $conn->error);
        }
        
        $stmt->bind_param("s", $pbp_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($items)) {
            $error = "Data PBP dengan nomor '" . htmlspecialchars($pbp_number) . "' tidak ditemukan.";
        } else {
            // Lakukan kalkulasi
            $header_data = $items[0]; // Ambil data header umum dari baris pertama
            $total_jenis_item = count($items);
            
            foreach ($items as $item) {
                $total_kuantitas += (float)($item['wood_engineered_qty'] ?? 0);
            }
        }
        $conn->close();

    } catch (Exception $e) {
        $error = "Terjadi kesalahan pada database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Out - PBP Wood Engineered <?= htmlspecialchars($pbp_number) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style type="text/css">
        @page { size: A4; margin: 0; }
        body { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; background-color: #f0f0f0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .a4-page { width: 210mm; min-height: 297mm; background: white; box-shadow: 0 0 20px rgba(0,0,0,0.3); padding: 10mm; box-sizing: border-box; position: relative; }
        h2 { margin: 0; text-align: center; font-weight: 600; font-size: 18px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 15px; font-size: 12px; }
        th, td { padding: 6px; border: 1px solid #000; }
        th { background-color: #f2f2f2; font-weight: 600; text-align: center; }
        td h2 { font-size: 16px; }
        .signature-section { position: absolute; bottom: 10mm; left: 10mm; right: 10mm; }
        .error-box { border: 2px solid #c0392b; background-color: #fdd; padding: 20px; text-align: center; }
        .print-btn { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-family: 'Poppins', sans-serif; z-index: 100; }
        .close-btn { top: 70px; background-color: #c0392b; }
        @media print {
            body { background: white; display: block; }
            .a4-page { box-shadow: none; margin: 0; padding: 10mm; width: auto; height: auto; page-break-after: always; }
            .print-btn, .close-btn { display: none; }
            .signature-section { position: relative; bottom: auto; left: auto; right: auto; margin-top: 30px; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Cetak</button>
    <button class="print-btn close-btn" onclick="location.href='../wood-engineered-pbp/'">Tutup</button>

    <div class="a4-page">
        <?php if ($error): ?>
            <div class="error-box">
                <h2>Terjadi Kesalahan</h2>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>
            <table>
                <tr style="background-color: #f2f2f2;">
                    <td style="font-weight: 600; border-right: none;">PT. OMEGA MAS</td>
                    <td colspan="3" align="right" style="font-size: 18px; font-weight: 800; border-left: none;">PENGAMBILAN WOOD ENGINEERED</td>
                </tr>
                <tr>
                    <td width="20%">Tanggal :<h2><?= date('d M Y', strtotime($header_data['wood_engineered_date'])) ?></h2></td>
                    <td width="25%">Nama :<h2><?= htmlspecialchars($header_data['employee_name']) ?></h2></td>
                    <td width="20%">Total Qty :<h2><?= number_format($total_kuantitas, 2) ?></h2></td>
                    <td width="35%">NO PBP :<h2><?= htmlspecialchars($pbp_number) ?></h2></td>
                </tr>
            </table>

            <table>
                <thead>
                    <tr>
                        <th>Kode Item</th>
                        <th>Nama Item</th>
                        <th>Jumlah</th>
                        <th>No. SO</th>
                        <th>Produk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['wood_engineered_code']) ?></td>
                        <td><?= htmlspecialchars($item['wood_engineered_name'] ?? 'N/A') ?></td>
                        <td align="center"><?= number_format($item['wood_engineered_qty'], 2) ?></td>
                        <td><?= htmlspecialchars($item['so_number'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($item['product_code'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="signature-section">
                <table style="border: none;">
                    <tr>
                        <td width="33%" align="center" style="border: none;"><strong>Operator</strong><br/><br/><br/><?= htmlspecialchars($header_data['employee_name'])."<br/>(".htmlspecialchars($header_data['employee_nik']) ?>)</td>
                        <td width="34%" align="center" style="border: none;"><strong>Mengetahui</strong><br/><br/><br/><br/>(............................)</td>
                        <td width="33%" align="center" style="border: none;"><strong>Menyetujui</strong><br/><br/><br/><br/>(............................)</td>
                    </tr>
                </table>
            </div>

            <table>
                <tr>
                    <td width="35%" style="font-size: 9px; vertical-align: top; white-space: nowrap;">
                        <?= htmlspecialchars($print_timestamp) ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
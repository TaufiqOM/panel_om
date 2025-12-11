<?php
// 1. PEMROSESAN DATA TERPUSAT DI ATAS
include "../inc/config.php";

// Inisialisasi variabel untuk menghindari error
$pbp_number = $_GET['pbp_number'] ?? '';
$error = '';
$header_data = [];
$items = [];
$total_jenis_hardware = 0;
$total_kuantitas = 0;

// Set zona waktu dan buat timestamp cetak
date_default_timezone_set('Asia/Jakarta');
$print_timestamp = 'Dicetak pada: ' . date('d M Y H:i:s');

// Validasi awal
if (empty($pbp_number)) {
    $error = "Nomor PBP tidak diberikan.";
} else {
    try {
        // Query disesuaikan untuk tabel hardware_pbp dan join ke tabel master hardware
        $sql = "SELECT
                    pbp.hardware_pbp_number, pbp.so_number, pbp.product_code,
                    pbp.hardware_pbp_date, pbp.employee_nik, pbp.hardware_code,
                    pbp.hardware_name, pbp.hardware_uom, pbp.hardware_pbp_qty,
                    pbp.picking_name, pbp.picking_name_after_validate, pbp.so_name, e.name
                FROM hardware_pbp AS pbp
                LEFT JOIN hardware AS h ON pbp.hardware_code = h.hardware_code
                LEFT JOIN employee as e ON pbp.employee_nik = e.barcode
                WHERE pbp.hardware_pbp_number = ?";

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
            // Lakukan semua kalkulasi dalam SATU KALI LOOP
            $header_data = $items[0]; // Ambil data header umum (tanggal, NIK)
            $total_jenis_hardware = count($items);

            // Menghitung total kuantitas
            foreach ($items as $item) {
                $total_kuantitas += (int)($item['hardware_pbp_qty'] ?? 0);
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
    <title>Print Out - PBP Hardware <?= htmlspecialchars($pbp_number) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style type="text/css">
        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            padding: 10mm;
            box-sizing: border-box;
            position: relative;
        }

        h2 {
            margin: 0;
            text-align: center;
            font-weight: 600;
            font-size: 18px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 15px;
            font-size: 12px;
        }

        th,
        td {
            padding: 6px;
            border: 1px solid #000;
        }

        th {
            background-color: #f2f2f2;
            font-weight: 600;
            text-align: center;
        }

        td h2 {
            font-size: 16px;
        }

        .signature-section {
            position: absolute;
            bottom: 10mm;
            left: 10mm;
            right: 10mm;
        }

        .error-box {
            border: 2px solid #c0392b;
            background-color: #fdd;
            padding: 20px;
            text-align: center;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            z-index: 100;
        }

        .close-btn {
            top: 120px;
            background-color: #c0392b;
        }

        .sync-btn {
            top: 70px;
            background-color: #28a745;
        }

        @media print {
            body {
                background: white;
                display: block;
            }

            .a4-page {
                box-shadow: none;
                margin: 0;
                padding: 10mm;
                width: auto;
                height: auto;
                page-break-after: always;
            }

            .print-btn,
            .close-btn,
            .sync-btn {
                display: none;
            }

            .signature-section {
                position: relative;
                bottom: auto;
                left: auto;
                right: auto;
                margin-top: 30px;
                page-break-inside: avoid;
            }
        }
        .no-border-right {
            border-right: none !important;
        }

        .no-border-left {
            border-left: none !important;
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">Cetak</button>
    <button class="print-btn sync-btn" id="syncBtn" onclick="syncPicking()">Sync Picking</button>
    <button class="print-btn close-btn" onclick="location.href='../hardware-pbp/'">Tutup</button>

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
                    <td colspan="3" align="right" style="font-size: 18px; font-weight: 800; border-left: none;">PERMINTAAN BAHAN PENOLONG</td>
                </tr>
                <tr>
                    <td width="20%">Tanggal :<h2><?= date('d M Y', strtotime($header_data['hardware_pbp_date'])) ?></h2>
                    </td>
                    <td width="25%">Nama :<h2><?= htmlspecialchars($header_data['name']) ?></h2>
                    </td>
                    <td width="35%" >
                        NO PBP / NO PICKING:
                        <h2 style="text-align:center; margin:0; line-height:1.2;"><?= htmlspecialchars($pbp_number) ?></h2>
                        <h3 style="text-align:center; margin:0; line-height:1.2;">
                            <?= htmlspecialchars($item['so_name'] ?: '-') ?> - <?= htmlspecialchars($item['so_number'] ?: '-') ?>
                        </h3>
                    </td>
                </tr>
            </table>

            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Hardware</th>
                        <th>Jumlah</th>
                        <th>Satuan</th>
                        <th>No. Picking</th>
                        <th>Produk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['hardware_code']) ?></td>
                            <td><?= htmlspecialchars($item['hardware_name'] ?? 'N/A') ?></td>
                            <td align="center"><?= htmlspecialchars($item['hardware_pbp_qty']) ?></td>
                            <td align="center"><?= htmlspecialchars($item['hardware_uom']) ?></td>
                            <td><?= htmlspecialchars($item['picking_name_after_validate'] ?: $item['picking_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($item['product_code'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="2">Jumlah</td>
                        <td align="center" class="no-border-right"><?= $total_kuantitas ?> Pcs</td>
                        <td colspan="3" class="no-border-left"></td>
                    </tr>
                </tbody>
            </table>

            <div class="signature-section">
                <table border="1">
                    <tr>
                        <td width="33%" align="center" style="border: none;"><strong>Pengambil</strong><br /><br /><br /><?= htmlspecialchars($header_data['name']) . "<br/>(" . htmlspecialchars($header_data['employee_nik']) ?>)</td>
                        <td width="34%" align="center" style="border: none;"><strong>Petugas</strong><br /><br /><br /><br />(............................)</td>
                        <td width="33%" align="center" style="border: none;"><strong>Supervisor</strong><br /><br /><br /><br />(............................)</td>
                    </tr>
                </table>
            </div>

            <table>
                <tr>
                    <td><?= htmlspecialchars($print_timestamp) ?></td>
                </tr>
            </table>
        <?php endif; ?>
    </div>

    <script>
        function syncPicking() {
            const syncBtn = document.getElementById('syncBtn');
            const originalText = syncBtn.innerHTML;
            const pbpNumber = '<?= htmlspecialchars($pbp_number) ?>';

            syncBtn.innerHTML = 'Syncing...';
            syncBtn.disabled = true;

            fetch('sync_hardware_pbp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    pbp_number: pbpNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Sync berhasil: ' + data.message);
                    location.reload(); // Reload untuk menampilkan data terbaru
                } else {
                    alert('Sync gagal: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat sync');
            })
            .finally(() => {
                syncBtn.innerHTML = originalText;
                syncBtn.disabled = false;
            });
        }
    </script>
</body>

</html>

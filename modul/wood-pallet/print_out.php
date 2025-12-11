<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Print Out - Pallet</title>
	<style>
		/* Reset dan styling dasar */
		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
			font-family: Arial, sans-serif;
		}
		
		body {
			display: flex;
			justify-content: center;
			align-items: center;
			min-height: 100vh;
			background-color: #f5f5f5;
			padding: 20px;
		}
		
		/* Container untuk kertas A4 */
		.a4-container {
			width: 21cm;
			min-height: 29.7cm;
			background-color: white;
			box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
			padding: 1cm;
			display: flex;
			flex-direction: column;
			align-items: center;
		}

		table {
			margin-bottom: 10px;
			width: 100%;
			border-collapse: collapse;
		}

		table tr td {
			padding: 15px;
			border: 1px solid #000;
			vertical-align: middle;
		}
		
		.qr-code {
			width: 120px;
			height: 120px;
			text-align: center;
		}
		
		.pallet-code {
			font-size: 45px; 
			font-weight: 800;
		}
		
		/* Styling untuk cetak */
		@media print {
			body {
				background-color: white;
				padding: 0;
			}
			
			.a4-container {
				box-shadow: none;
				width: 100%;
				min-height: auto;
				padding: 0;
			}
			
			.no-print {
				display: none;
			}
		}
	</style>
	
	<!-- Memasukkan library QRCode.js -->
	<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
</head>
<body>
	<div class="a4-container">
		<!-- Tombol cetak saja -->
		<div class="no-print" style="margin-bottom: 20px; text-align: center;">
			<button onclick="window.print()">Cetak</button>
		</div>
		
		<?php 
			include "../../inc/config.php";
			$qry = mysqli_query($conn, "SELECT * FROM wood_pallet");
			while($row = mysqli_fetch_assoc($qry)) {
				$palletCode = $row['wood_pallet_code'];
				// Data yang akan dikodekan dalam QR - bisa disesuaikan
				$qrData = $palletCode;
		?>
		<table>
			<tr>
				<td width="25%" rowspan="3">
					<center>
						<div id="qrcode-<?php echo $palletCode; ?>" class="qr-code"></div>
					</center>
				</td>
				<td width="50%" class="pallet-code" align="center"><?php echo $palletCode; ?></td>
				<td width="25%" rowspan="3">
					<center>
						<div id="qrcode2-<?php echo $palletCode; ?>" class="qr-code"></div>
					</center>
				</td>
			</tr>
			<tr>
				<td align="left" style="font-size: 25px;">PO : </td>
			</tr>
			<tr>
				<td align="center">KD : O CARBONIZE : O </td>
			</tr>
		</table>
		
		<script>
			// Fungsi untuk menghasilkan QR Code menggunakan library QRCode.js (offline)
			function generateQRCode(elementId, data) {
				// Hapus konten sebelumnya jika ada
				document.getElementById(elementId).innerHTML = "";
				new QRCode(document.getElementById(elementId), {
					text: data,
					width: 120,
					height: 120
				});
			}
			
			// Menghasilkan QR Code setelah halaman dimuat
			document.addEventListener("DOMContentLoaded", function() {
				generateQRCode("qrcode-<?php echo $palletCode; ?>", "<?php echo $qrData; ?>");
				generateQRCode("qrcode2-<?php echo $palletCode; ?>", "<?php echo $qrData; ?>");
			});
		</script>
		
		<?php } ?>
		
		<div style="margin-top: 20px; text-align: center; font-size: 12px;">
			Dicetak pada: <span id="print-date"></span>
		</div>
	</div>

	<script>
		// Menambahkan tanggal cetak otomatis
		document.getElementById('print-date').textContent = new Date().toLocaleDateString();
	</script>
</body>
</html>
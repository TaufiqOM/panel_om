<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Print Out - Wood Solid</title>
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
			padding: 0.5cm;
			display: flex;
			flex-direction: column;
		}

		/* Tabel utama */
		table {
			width: 100%;
			border-collapse: collapse;
		}
		
		/* Sel barcode */
		.barcode-cell {
			border: 1px solid #c9c9c9;
			width: 50%;
			padding: 2px; /* Lebih kecil */
			height: 38px; /* Dikurangi untuk lebih kompak */
			vertical-align: middle;
		}
		
		/* Tabel dalam sel barcode */
		.barcode-cell table {
			width: 100%;
			height: 100%;
		}
		
		/* Kolom kode depan dan belakang */
		.code-column {
			width: 15%;
			text-align: center;
			font-size: 20px; /* Lebih kecil */
			font-weight: 700;
			vertical-align: middle;
			padding: 0 1px;
		}
		
		/* Kolom barcode */
		.barcode-column {
			width: 70%;
			text-align: center;
			vertical-align: middle;
			padding: 0; /* Menghilangkan padding */
		}
		
		/* Label GRD dan DTG */
		.code-label {
			font-size: 8px; /* Lebih kecil */
			font-weight: 200;
			display: block;
			margin-top: 1px;
		}
		
		/* Container barcode */
		.barcode-container {
			width: 100%;
			height: 40px; /* Dikurangi */
			display: flex;
			justify-content: center;
			align-items: center;
			margin: 0; /* Menghilangkan margin */
			padding: 0; /* Menghilangkan padding */
		}
		
		/* SVG barcode */
		.barcode-svg {
			width: 100% !important;
			height: 40px !important; /* Disesuaikan dengan container */
			margin: 0;
			padding: 0;
			display: block;
		}
		
		/* Kode barcode teks */
		.barcode-text {
			font-size: 12px;
			margin-top: 2px; /* Didekatkan dengan barcode */
			line-height: 1;
			padding: 0;
		}
		
		/* Footer tanggal cetak */
		.print-footer {
			margin-top: 10px;
			text-align: center;
			font-size: 10px;
		}
		
		/* Styling untuk cetak */
		@media print {
			body {
				background-color: white;
				padding: 0;
				margin: 0;
			}
			
			.a4-container {
				box-shadow: none;
				width: 100%;
				min-height: auto;
				padding: 0.3cm;
			}
			
			.no-print {
				display: none;
			}
			
			/* Mengoptimalkan untuk 34 barcode per halaman */
			/*.barcode-cell {
				height: 36px; 
				padding: 1px;
			}
			
			.barcode-container {
				height: 28px;
			}
			
			.barcode-svg {
				height: 28px !important;
			}
			
			.barcode-text {
				font-size: 8px;
				margin-top: -3px;
			}*/
			
			/* Tambahkan page break setelah 17 baris (34 barcode) */
			tr:nth-child(17n) {
				page-break-after: always;
			}
			
			@page {
				margin: 0.3cm; /* Margin lebih kecil */
			}
		}
	</style>
	
	<!-- Memasukkan library JsBarcode -->
	<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
	<div class="a4-container">	
		<table cellspacing="0">
			<?php 
				include "../../inc/config.php";
				
				// Mendapatkan tanggal dari parameter URL
				$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
				
				$qry = mysqli_query($conn, "SELECT * FROM wood_solid WHERE DATE(created_at) = '$date' ORDER BY wood_solid_barcode");
				
				$counter = 0;
				while($row = mysqli_fetch_assoc($qry)) {
					$woodCode = $row['wood_solid_barcode'];

					// Memisahkan berdasarkan tanda -
					$parts = explode("-", $woodCode);

					// Mengambil 4 karakter depan dari bagian pertama
					$depan = substr($parts[0], 0, 4);

					// Mengambil bagian kedua
					$belakang = $parts[1];
					
					// Buat baris baru setiap 2 kolom
					if ($counter % 2 == 0) {
						echo '<tr>';
					}
			?>
				<td class="barcode-cell">
					<table>
						<tr>
							<td class="code-column">
								<?php echo $depan; ?>
								<span class="code-label">GRD</span>
							</td>
							<td class="barcode-column">
								<div class="barcode-container">
									<svg class="barcode-svg" id="barcode-<?php echo $woodCode; ?>"></svg>
								</div>
								<div class="barcode-text"><?php echo $woodCode; ?></div>
							</td>
							<td class="code-column">
								<?php echo $belakang; ?>
								<span class="code-label">DTG</span>
							</td>
						</tr>
					</table>	
				</td>			
			<?php 
					$counter++;
					// Tutup baris setelah 2 kolom
					if ($counter % 2 == 0) {
						echo '</tr>';
					}
				}
				
				// Tutup baris terakhir jika jumlah data ganjil
				if ($counter % 2 != 0) {
					echo '<td class="barcode-cell"></td></tr>';
				}
			?>
		</table>
	</div>

	<script>
		// Menghasilkan barcode setelah halaman dimuat
		document.addEventListener("DOMContentLoaded", function() {
			// Ambil semua elemen dengan kelas barcode-svg
			const barcodes = document.querySelectorAll('.barcode-svg');
			
			barcodes.forEach(barcode => {
				// Ambil ID barcode dan ekstrak wood code
				const woodCode = barcode.id.replace('barcode-', '');
				
				// Generate barcode dengan margin 0 untuk menghilangkan padding putih
				JsBarcode(barcode, woodCode, {
					format: "CODE128",
					lineColor: "#000",
					width: 1.2, // Lebih tipis
					height: 30, // Disesuaikan
					displayValue: false,
					margin: 0, // Menghilangkan margin/padding putih di barcode
					marginTop: 0,
					marginBottom: 0,
					marginLeft: 0,
					marginRight: 0
				});
			});
			
			// Menambahkan tanggal cetak otomatis
			document.getElementById('print-date').textContent = new Date().toLocaleDateString('id-ID', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric'
			});
		});
	</script>
</body>
</html>
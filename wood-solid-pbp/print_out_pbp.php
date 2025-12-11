<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Print Out - PBP</title>
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
			height: 297mm;
			background: white;
			box-shadow: 0 0 20px rgba(0,0,0,0.3);
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
		}
		
		th, td {
			padding: 8px;
			border: 1px solid #000;
		}
		
		th {
			background-color: #f2f2f2;
			font-weight: 600;
		}
		
		.signature-section {
			position: absolute;
			bottom: 30mm;
			left: 20mm;
			right: 20mm;
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
				width: 100%;
				height: auto;
				page-break-after: always;
			}
			
			.print-btn {
				display: none;
			}
			
			.signature-section {
				position: relative;
				bottom: auto;
				left: auto;
				right: auto;
				margin-top: 30px;
			}
		}
	</style>
</head>
<body>
	<button class="print-btn" onclick="window.print()">Cetak</button>
	<a href="../wood-solid-pbp/">
		<button type="button" class="print-btn" style="margin-top: 50px; background-color: #c0392b;">Tutup</button>
	</a>

	<div class="a4-page">
		<?php 
			include "../inc/config.php";
			$pbp_number = $_GET['pbp_number'];
			
			// Ambil data header PBP
			$header_qry = mysqli_query($conn, "SELECT DISTINCT wood_solid_pbp_number, so_number, product_code, wood_solid_pbp_date, employee_nik
											 FROM wood_solid_pbp 
											 WHERE wood_solid_pbp_number = '$pbp_number' 
											 LIMIT 1");
			$header_data = mysqli_fetch_assoc($header_qry);
			
			// Hitung total item dan total volume
			$total_volume = 0;
			$detail_qry = mysqli_query($conn, "SELECT a.*, b.wood_name, b.wood_solid_height, b.wood_solid_width, b.wood_solid_length 
											FROM wood_solid_pbp a 
											LEFT JOIN wood_solid_lpb_detail b ON a.wood_solid_barcode = b.wood_solid_barcode 
											WHERE a.wood_solid_pbp_number = '$pbp_number'");
			
			$total_items = mysqli_num_rows($detail_qry);
			
			// Hitung total volume dalam M3 (dari cm³ ke m³)
			while($row = mysqli_fetch_assoc($detail_qry)) {
				$height = floatval($row['wood_solid_height']);
				$width = floatval($row['wood_solid_width']);
				$length = floatval($row['wood_solid_length']);
				
				// Konversi dari cm³ ke m³ (dibagi 1.000.000 karena 1 m³ = 1.000.000 cm³)
				$volume_m3 = ($height * $width * $length) / 1000000;
				$total_volume += $volume_m3;
			}
			
			// Reset pointer result set untuk digunakan lagi di loop berikutnya
			mysqli_data_seek($detail_qry, 0);
		?>

		<!-- Header Section -->
		<table>
			<tr bgcolor="#c9c9c9">
				<td style="font-weight: 600;">PT. OMEGA MAS</td>
				<td colspan="3" align="right" style="font-size: 18px; font-weight: 800;">PERMINTAAN BAHAN PENOLONG - SOLID WOOD</td>
			</tr>

			<tr>
				<td colspan="2">
					Nomor SO :<br/>
					<h2><?php echo $header_data ? $header_data['so_number'] : 'N/A'; ?></h2>
				</td>
				<td colspan="2">
					Kode Produk :<br/>
					<h2><?php echo $header_data ? $header_data['product_code'] : 'N/A'; ?></h2>
				</td>
			</tr>

			<tr>
				<td width="25%" style="font-size: 14px;">
					Tanggal :<br/> 
					<h2><?php echo $header_data ? date('d M Y', strtotime($header_data['wood_solid_pbp_date'])) : date('d M Y'); ?></h2>
				</td>
				<td width="25%" style="font-size: 14px;">
					Total Papan :<br/> 
					<h2><?php echo $total_items; ?> pcs</h2>
				</td>
				<td width="25%" style="font-size: 14px;">
					Total Vol. (M3) :<br/>
					<h2><?php echo number_format($total_volume, 6); ?></h2>
				</td>
				<td width="25%" style="font-size: 14px;">
					NO PBP :<br/>
					<h2><?php echo $pbp_number; ?></h2>
				</td>
			</tr>
		</table>

		<!-- Detail Items Section -->
		<table>
			<tr>
				<th>Barcode</th>
				<th>Jenis Kayu</th>
				<th>Tinggi (cm)</th>
				<th>Lebar (cm)</th>
				<th>Panjang (cm)</th>
			</tr>
			<?php 
				if(mysqli_num_rows($detail_qry) > 0) {
					while($row = mysqli_fetch_assoc($detail_qry)) {
						$height = floatval($row['wood_solid_height']);
						$width = floatval($row['wood_solid_width']);
						$length = floatval($row['wood_solid_length']);
			?>
			<tr>
				<td><?php echo $row['wood_solid_barcode']; ?></td>
				<td><?php echo $row['wood_name'] ? $row['wood_name'] : 'N/A'; ?></td>
				<td><?php echo $row['wood_solid_height'] ? number_format($row['wood_solid_height'], 1) : 'N/A'; ?></td>
				<td><?php echo $row['wood_solid_width'] ? number_format($row['wood_solid_width'], 1) : 'N/A'; ?></td>
				<td><?php echo $row['wood_solid_length'] ? number_format($row['wood_solid_length'], 1) : 'N/A'; ?></td>
			</tr>
			<?php 
					}
				} else {
			?>
			<tr>
				<td colspan="7" align="center">Tidak ada data material</td>
			</tr>
			<?php } ?>
		</table>

		<!-- Footer Section -->
		<table>
			<tr>
				<td colspan="2">
					Nomor SO :<br/>
					<h2><?php echo $header_data ? $header_data['so_number'] : 'N/A'; ?></h2>
				</td>
				<td colspan="2">
					Kode Produk :<br/>
					<h2><?php echo $header_data ? $header_data['product_code'] : 'N/A'; ?></h2>
				</td>
			</tr>

			<tr>
				<td width="25%" style="font-size: 14px;">
					Tanggal :<br/> 
					<h2><?php echo $header_data ? date('d M Y', strtotime($header_data['wood_solid_pbp_date'])) : date('d M Y'); ?></h2>
				</td>
				<td width="25%" style="font-size: 14px;">
					Total Papan :<br/> 
					<h2><?php echo $total_items; ?> pcs</h2>
				</td>
				<td width="25%" style="font-size: 14px;">
					Total Vol. (M3) :<br/>
					<h2><?php echo number_format($total_volume, 6); ?></h2>
				</td>
				<td width="25%" style="font-size: 14px;">
					NO PBP :<br/>
					<h2><?php echo $pbp_number; ?></h2>
				</td>
			</tr>
		</table>

		<!-- Tanda Tangan Section -->
		<div class="signature-section">
			<table border="0" width="100%">
				<tr>
					<td width="33%" align="center" style="border: none;">
						<br/><br/>
						<strong>Operator</strong><br/>
						(<?php echo $header_data ? $header_data['employee_nik'] : 'N/A'; ?>)
					</td>
					<td width="34%" align="center" style="border: none;">
						<br/><br/>
						<strong>Mengetahui</strong><br/>
						(............................)
					</td>
					<td width="33%" align="center" style="border: none;">
						<br/><br/>
						<strong>Menyetujui</strong><br/>
						(............................)
					</td>
				</tr>
			</table>
		</div>
	</div>
</body>
</html>
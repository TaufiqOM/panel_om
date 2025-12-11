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

		table tr td, th {
			font-size: 13px;
			padding: 5px;
		}
		
		.summary-table {
			margin-top: 20px;
			border: 1px solid #000;
		}
		
		.summary-table th, .summary-table td {
			border: 1px solid #000;
			padding: 8px;
			text-align: center;
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
			
			@page {
				margin: 0.3cm; /* Margin lebih kecil */
			}
		}
	</style>
</head>
<body>
	<div class="a4-container">
		<table>
			<tr>
				<td width="85%">
					<h3 style="font-size: 20px;">LAPORAN DETAIL PENERIMAAN BARANG (LPB)</h3>	
				</td>
				<td width="15%">
					<img src="../../good/assets/media/logos/logo-black.png" width="100%">
				</td>
			</tr>
		</table>
		
		<?php 
			include "../../inc/config.php";
			
			// Mendapatkan nomor LPB dari parameter URL
			$wood_solid_lpb_number = $_GET['number'];
			
			// Query untuk mendapatkan data LPB
			$qry_lpb = mysqli_query($conn, "SELECT * FROM wood_solid_lpb WHERE wood_solid_lpb_number = '$wood_solid_lpb_number'");
			$lpb_data = mysqli_fetch_assoc($qry_lpb);
			
			// Query untuk mendapatkan detail items
			$qry = mysqli_query($conn, "SELECT a.wood_solid_lpb_number, b.* FROM wood_solid_lpb a LEFT JOIN wood_solid_lpb_detail b ON a.wood_solid_lpb_id = b.wood_solid_lpb_id WHERE a.wood_solid_lpb_number = '$wood_solid_lpb_number' ORDER BY b.wood_solid_barcode");
			
			// Hitung total
			$total_pcs = 0;
			$total_volume = 0;
		?>
		
		<!-- Informasi LPB -->
		<table style="margin: 15px 0; width: 100%;">
			<tr>
				<td width="20%"><strong>Nomor LPB</strong></td>
				<td width="30%">: <?php echo $lpb_data['wood_solid_lpb_number']; ?></td>
				<td width="20%"><strong>Tanggal Invoice</strong></td>
				<td width="30%">: <?php echo date('d/m/Y', strtotime($lpb_data['wood_solid_lpb_date_invoice'])); ?></td>
			</tr>
			<tr>
				<td><strong>Nomor PO</strong></td>
				<td>: <?php echo $lpb_data['wood_solid_lpb_po']; ?></td>
				<td><strong>Supplier</strong></td>
				<td>: <?php echo $lpb_data['supplier_name']; ?></td>
			</tr>
			<tr>
				<td><strong>Jenis Kayu</strong></td>
				<td>: <?php echo $lpb_data['wood_name']; ?></td>
				<td><strong>Tanggal Cetak</strong></td>
				<td>: <?php echo date('d/m/Y H:i:s'); ?></td>
			</tr>
		</table>
		
		<!-- Tabel Detail -->
		<table border="1" cellpadding="8" cellspacing="0" style="width: 100%;">
			<tr style="background: #000; color: #fff;">
				<th width="10%" rowspan="2">No</th>
				<th width="20%" rowspan="2">No Pallet</th>
				<th width="20%" rowspan="2">No Kayu</th>
				<th width="30%" colspan="3">Ukuran</th>
				<th width="20%" rowspan="2">M3</th>
			</tr>
			<tr style="background: #000; color: #fff;">
				<th width="10%">T</th>
				<th width="10%">L</th>
				<th width="10%">P</th>
			</tr>
			
			<?php 
				$no = 1;
				// Reset pointer query
				mysqli_data_seek($qry, 0);
				
				while($row = mysqli_fetch_assoc($qry)) {
					// Hitung volume dalam m³
					$volume = ($row['wood_solid_height'] * $row['wood_solid_width'] * $row['wood_solid_length']) / 1000000;
					$total_volume += $volume;
					$total_pcs++;
			?>
			<tr>
				<td align="center"><?php echo $no++; ?></td>
				<td align="center"><?php echo $row['wood_solid_group'] ?? '-'; ?></td>
				<td align="center"><?php echo $row['wood_solid_barcode'] ?? '-'; ?></td>
				<td align="center"><?php echo $row['wood_solid_height'] ?? '0'; ?></td>
				<td align="center"><?php echo $row['wood_solid_width'] ?? '0'; ?></td>
				<td align="center"><?php echo $row['wood_solid_length'] ?? '0'; ?></td>
				<td align="center"><?php echo number_format($volume, 3); ?></td>
			</tr>
			<?php } ?>
			
			<!-- Total Keseluruhan -->
			<tr style="background: #f0f0f0; font-weight: bold;">
				<td colspan="3" align="center">TOTAL</td>
				<td colspan="3" align="center"><?php echo $total_pcs; ?> PCS</td>
				<td align="center"><?php echo number_format($total_volume, 3); ?> m³</td>
			</tr>
		</table>
		
		<!-- Tanda Tangan -->
		<table style="width: 100%; margin-top: 50px;">
			<tr>
				<td width="33%" align="center">
					<br><br>
					<strong>Dibuat Oleh,</strong>
					<br><br><br><br>
					_______________________
				</td>
				<td width="33%" align="center">
					<br><br>
					<strong>Diperiksa Oleh,</strong>
					<br><br><br><br>
					_______________________
				</td>
				<td width="33%" align="center">
					<br><br>
					<strong>Diterima Oleh,</strong>
					<br><br><br><br>
					_______________________
				</td>
			</tr>
		</table>
	</div>
</body>
</html>
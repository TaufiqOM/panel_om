<?php
include '../../inc/config.php';

// Ambil jumlah barcode yang akan dibuat
$count = $_POST['count'];

// Format tanggal saat ini: MMYYDD
$datePart = date('myd');

// Cari nomor urut terakhir untuk hari ini
$sql = "SELECT MAX(wood_solid_barcode) as last_barcode 
        FROM wood_solid 
        WHERE wood_solid_barcode LIKE '$datePart-%'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

// Tentukan nomor urut awal
$startNumber = 1;
if ($row['last_barcode']) {
    $lastNumber = intval(substr($row['last_barcode'], -4));
    $startNumber = $lastNumber + 1;
}

// Siapkan array untuk menyimpan barcode
$barcodes = array();

// Generate barcode
for ($i = 0; $i < $count; $i++) {
    $sequence = str_pad($startNumber + $i, 4, '0', STR_PAD_LEFT);
    $barcode = $datePart . '-' . $sequence;
    $barcodes[] = $barcode;
}

// Simpan ke database
$values = array();
foreach ($barcodes as $barcode) {
    $values[] = "('$barcode', NOW())";
}

$valuesString = implode(',', $values);
$insertSql = "INSERT INTO wood_solid (wood_solid_barcode, created_at) VALUES $valuesString";

if (mysqli_query($conn, $insertSql)) {
    echo json_encode(['success' => true, 'barcodes' => $barcodes]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
}

mysqli_close($conn);
?>
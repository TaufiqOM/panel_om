<?php
// get_material.php
header('Content-Type: application/json');

include '../inc/config.php';

// Periksa koneksi
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Ambil barcode dari POST request
$barcode = isset($_POST['barcode']) ? $_POST['barcode'] : '';

if (empty($barcode)) {
    echo json_encode(['error' => 'Barcode is required']);
    exit;
}

// Cek apakah barcode sudah ada di wood_solid_pbp
$check_pbp_sql = "SELECT wood_solid_pbp_number FROM wood_solid_pbp WHERE wood_solid_barcode = ?";
$check_pbp_stmt = $conn->prepare($check_pbp_sql);
$check_pbp_stmt->bind_param("s", $barcode);
$check_pbp_stmt->execute();
$pbp_result = $check_pbp_stmt->get_result();

if ($pbp_result->num_rows > 0) {
    $pbp_data = $pbp_result->fetch_assoc();
    echo json_encode(['error' => 'Barcode sudah di scan di PBP Number: ' . $pbp_data['wood_solid_pbp_number']]);
    $check_pbp_stmt->close();
    $conn->close();
    exit;
}

$check_pbp_stmt->close();

// Query untuk mengambil data material berdasarkan barcode dengan JOIN
$sql = "SELECT 
            b.wood_name, 
            b.wood_solid_height as thickness, 
            b.wood_solid_width as width, 
            b.wood_solid_length as length 
        FROM wood_solid_dtg a 
        INNER JOIN wood_solid_lpb_detail b ON a.wood_solid_barcode = b.wood_solid_barcode 
        WHERE a.wood_solid_barcode = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $barcode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Ambil data material
    $material = $result->fetch_assoc();
    echo json_encode($material);
} else {
    echo json_encode(['error' => 'Material not found']);
}

$stmt->close();
$conn->close();
?>
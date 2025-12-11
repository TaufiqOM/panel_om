<?php
// Koneksi ke database
include "../inc/config.php";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ambil data dari POST
$employee_nik = $_POST['employee_nik'];

// Query untuk mencari PBP berdasarkan operator
$sql = "SELECT DISTINCT wood_solid_pbp_number, so_number, product_code, wood_solid_pbp_date, wood_solid_pbp_time 
        FROM wood_solid_pbp 
        WHERE employee_nik = ? 
        ORDER BY wood_solid_pbp_date DESC, wood_solid_pbp_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $employee_nik);
$stmt->execute();
$result = $stmt->get_result();

$pbp_list = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $pbp_list[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'pbp_list' => $pbp_list
    ]);
} else {
    echo json_encode([
        'success' => true,
        'pbp_list' => []
    ]);
}

$stmt->close();
$conn->close();
?>
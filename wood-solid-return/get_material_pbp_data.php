<?php
session_start();
require_once '../panel/session_manager.php';

// Cek session
$sessionCheck = SessionManager::checkEmployeeSession();
if (!$sessionCheck['logged_in'] || !SessionManager::checkSessionTimeout()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "siomas_odoo";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim($_POST['barcode']);
    
    if (empty($barcode)) {
        echo json_encode(['success' => false, 'message' => 'Barcode tidak boleh kosong']);
        exit;
    }
    
    // Cek apakah material sudah di-return sebelumnya
    $checkReturnQuery = "SELECT * FROM wood_solid_return
                        WHERE wood_solid_barcode = ?";
    $stmt = $conn->prepare($checkReturnQuery);
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Material dengan barcode ' . $barcode . ' sudah di-return sebelumnya']);
        exit;
    }
    
    // Query untuk mendapatkan data material dari wood_solid_pbp dan wood_solid_lpb_detail
    $query = "
        SELECT 
            pbp.wood_solid_pbp_id,
            pbp.wood_solid_pbp_number,
            pbp.so_number,
            pbp.product_code,
            pbp.wood_solid_pbp_date,
            pbp.wood_solid_pbp_time,
            pbp.wood_solid_barcode,
            pbp.employee_nik,
            lpb.wood_solid_lpb_detail_id,
            lpb.wood_solid_group,
            lpb.wood_name,
            lpb.wood_solid_height as thickness,
            lpb.wood_solid_width as width,
            lpb.wood_solid_length as length
        FROM wood_solid_pbp pbp
        LEFT JOIN wood_solid_lpb_detail lpb ON pbp.wood_solid_barcode = lpb.wood_solid_barcode
        WHERE pbp.wood_solid_barcode = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Barcode tidak ditemukan dalam sistem']);
        exit;
    }
    
    $data = $result->fetch_assoc();
    
    // Format data PBP
    $pbpData = [
        'wood_solid_pbp_number' => $data['wood_solid_pbp_number'],
        'so_number' => $data['so_number'],
        'product_code' => $data['product_code'],
        'wood_solid_pbp_date' => date('d/m/Y', strtotime($data['wood_solid_pbp_date']))
    ];
    
    // Format data material
    $materialData = [
        'wood_solid_pbp_id' => $data['wood_solid_pbp_id'],
        'wood_solid_lpb_detail_id' => $data['wood_solid_lpb_detail_id'],
        'wood_solid_barcode' => $data['wood_solid_barcode'],
        'wood_solid_pbp_number' => $data['wood_solid_pbp_number'],
        'so_number' => $data['so_number'],
        'product_code' => $data['product_code'],
        'wood_name' => $data['wood_name'],
        'thickness' => $data['thickness'],
        'length' => $data['length'],
        'width' => $data['width'],
        'wood_solid_group' => $data['wood_solid_group']
    ];
    
    echo json_encode([
        'success' => true,
        'material' => $materialData,
        'pbp_data' => $pbpData
    ]);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
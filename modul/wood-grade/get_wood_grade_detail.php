<?php
// get_wood_grade_detail.php
include '../../inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $location = $_POST['location'] ?? '';
    
    if (empty($date) || empty($location)) {
        echo json_encode([
            'success' => false,
            'message' => 'Date dan location harus diisi'
        ]);
        exit;
    }
    
    // Query untuk mengambil data detail wood grade
    $sql = "SELECT 
            wood_solid_barcode,
            wood_solid_height,
            wood_solid_width, 
            wood_solid_length
            FROM wood_solid_grade 
            WHERE wood_solid_grade_date = ? AND location_name = ?
            ORDER BY wood_solid_barcode";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $date, $location);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $details = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $details[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    
    echo json_encode([
        'success' => true,
        'details' => $details
    ]);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan'
    ]);
}
?>
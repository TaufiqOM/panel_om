<?php
// koneksi database
include '../../inc/config.php';

header('Content-Type: application/json');

if (isset($_GET['group'])) {
    $group = $_GET['group'];
    
    // Query untuk mengambil detail barcode berdasarkan group
    $query = "SELECT wood_solid_barcode, wood_solid_dtg_time 
              FROM wood_solid_dtg 
              WHERE wood_solid_group = ? 
              ORDER BY wood_solid_dtg_time";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $group);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $barcodes = [];
    while ($row = $result->fetch_assoc()) {
        $barcodes[] = $row;
    }
    
    if (count($barcodes) > 0) {
        echo json_encode([
            'success' => true,
            'barcodes' => $barcodes
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter group tidak ditemukan'
    ]);
}
?>
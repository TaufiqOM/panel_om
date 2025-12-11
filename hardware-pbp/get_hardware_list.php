<?php
session_start();
require_once '../inc/config.php';
header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

try {
    $query = "SELECT hardware_code, hardware_name, hardware_uom FROM hardware WHERE is_active = 1";
    if ($term !== '') {
        $query .= " AND (hardware_code LIKE '%" . mysqli_real_escape_string($conn, $term) . "%' OR hardware_name LIKE '%" . mysqli_real_escape_string($conn, $term) . "%')";
    }
    $result = mysqli_query($conn, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'hardware_code' => $row['hardware_code'],
            'hardware_name' => $row['hardware_name'],
            'hardware_uom' => $row['hardware_uom']
        ];
    }
    mysqli_free_result($result);

    echo json_encode(['success' => true, 'hardware' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

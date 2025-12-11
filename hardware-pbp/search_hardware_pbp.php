<?php
session_start();
require_once '../inc/config.php';
header('Content-Type: application/json');

$employee_nik = $_POST['employee_nik'] ?? '';
if (empty($employee_nik)) {
    echo json_encode(['success' => false, 'message' => 'Employee NIK is required']);
    exit;
}

try {
    $query = "SELECT hardware_pbp_number, hardware_pbp_date, hardware_pbp_time, COUNT(*) as item_count, GROUP_CONCAT(DISTINCT picking_name SEPARATOR ', ') as picking_names
              FROM hardware_pbp
              WHERE employee_nik = ?
              GROUP BY hardware_pbp_number
              ORDER BY hardware_pbp_date DESC, hardware_pbp_time DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $employee_nik);
    $stmt->execute();
    $result = $stmt->get_result();
    $pbp_list = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'pbp_list' => $pbp_list]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

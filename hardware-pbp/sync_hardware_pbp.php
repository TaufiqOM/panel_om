<?php
session_start();
require_once '../inc/config_odoo.php';
require_once '../inc/config.php';
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? 'system';

$input = json_decode(file_get_contents('php://input'), true);
$pbp_number = $input['pbp_number'] ?? '';

if (empty($pbp_number)) {
    echo json_encode(['success' => false, 'message' => 'Nomor PBP tidak diberikan']);
    exit;
}

try {
    // Query untuk mendapatkan semua record dengan pbp_number yang sama
    $sql = "SELECT picking_id FROM hardware_pbp WHERE hardware_pbp_number = ? AND picking_id IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $pbp_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada data picking untuk PBP ini']);
        exit;
    }

    // Collect unique picking_ids
    $picking_ids = array_unique(array_column($rows, 'picking_id'));

    // Query Odoo untuk picking yang state='done'
    $validated_pickings = callOdooRead($username, 'stock.picking', [
        ['id', 'in', $picking_ids],
        ['state', '=', 'done']
    ], ['id', 'name']);

    if (empty($validated_pickings)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada picking yang sudah divalidasi (state=done)']);
        exit;
    }

    // Map picking_id ke name
    $picking_map = [];
    foreach ($validated_pickings as $picking) {
        $picking_map[$picking['id']] = $picking['name'];
    }

    // Update semua record dengan pbp_number yang sama
    $update_sql = "UPDATE hardware_pbp SET picking_name_after_validate = ? WHERE hardware_pbp_number = ? AND picking_id = ?";
    $update_stmt = $conn->prepare($update_sql);

    $updated_count = 0;
    foreach ($picking_map as $picking_id => $picking_name) {
        $update_stmt->bind_param("ssi", $picking_name, $pbp_number, $picking_id);
        if ($update_stmt->execute()) {
            $updated_count++;
        }
    }
    $update_stmt->close();

    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => "Berhasil sync {$updated_count} picking yang sudah divalidasi",
        'updated_count' => $updated_count
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}

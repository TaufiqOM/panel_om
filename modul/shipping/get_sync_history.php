<?php
header('Content-Type: application/json');
session_start();
require __DIR__ . '/../../inc/config.php';

$id_shipping = isset($_GET['id_shipping']) ? intval($_GET['id_shipping']) : 0;

if (!$id_shipping) {
    echo json_encode(['success' => false, 'message' => 'Missing ID Shipping']);
    exit;
}

$sql = "SELECT id, id_user, user_name, sync_at, sync_type FROM shipping_sync_log WHERE id_shipping = ? ORDER BY sync_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_shipping);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $row['sync_at_formatted'] = date('H:i:s d-M-Y', strtotime($row['sync_at']));
    $logs[] = $row;
}

echo json_encode(['success' => true, 'data' => $logs]);
?>

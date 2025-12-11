<?php
session_start();
require_once '../../inc/config.php';

header('Content-Type: application/json; charset=utf-8');

$username = $_SESSION['username'] ?? '';

if (!$username) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['bom_id']) || !isset($input['product_reference']) || !isset($input['product_name']) || !isset($input['product_img'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$bom_id_odoo = (int)$input['bom_id'];
$product_reference = trim($input['product_reference']);
$product_name = trim($input['product_name']);
$product_img = trim($input['product_img']);

// Check if BOM already exists
$stmt = $conn->prepare("SELECT * FROM bom WHERE id = ?");
$stmt->bind_param("i", $bom_id_odoo);
$stmt->execute();
$result = $stmt->get_result();
$exists = $result->num_rows > 0;
$stmt->close();

if ($exists) {
    // Update existing
    $stmt = $conn->prepare("UPDATE bom SET product_reference = ?, product_name = ?, product_img = ? WHERE id = ?");
    $stmt->bind_param("sssi", $product_reference, $product_name, $product_img, $bom_id_odoo);
} else {
    // Insert new
    $stmt = $conn->prepare("INSERT INTO bom (id, product_reference, product_name, product_img, bom_desc) VALUES (?, ?, ?, ?, '')");
    $stmt->bind_param("isss", $bom_id_odoo, $product_reference, $product_name, $product_img);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'BOM saved successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save BOM: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

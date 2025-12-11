<?php
session_start();
require_once '../../inc/config_odoo.php';

header('Content-Type: application/json; charset=utf-8');

$username = $_SESSION['username'] ?? '';
$bom_id = $_GET['bom_id'] ?? '';

if (!$username) {
    http_response_code(401);
    echo json_encode(['error' => 'Session expired']);
    exit;
}

if (!$bom_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'BOM ID is required']);
    exit;
}

$bom_lines = callOdooRead($username, "mrp.bom.line", [["bom_id", "=", (int)$bom_id]], ["product_id", "product_qty", "product_uom_id"]);

if ($bom_lines === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to connect to Odoo or retrieve data']);
    exit;
}

// Normalisasi hasil
$components = [];
foreach ($bom_lines as $line) {
    $product = is_array($line['product_id']) ? ($line['product_id'][1] ?? $line['product_id'][0] ?? '') : ($line['product_id'] ?? '');
    $qty = $line['product_qty'] ?? 0;
    $uom = is_array($line['product_uom_id']) ? ($line['product_uom_id'][1] ?? $line['product_uom_id'][0] ?? '') : ($line['product_uom_id'] ?? '');
    $components[] = [
        'product' => $product,
        'qty' => $qty,
        'uom' => $uom
    ];
}

echo json_encode([
    'success' => true,
    'components' => $components
]);
?>

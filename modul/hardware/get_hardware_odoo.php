<?php
session_start();
require __DIR__ . '/../../inc/config_odoo.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('callOdooRead')) {
    http_response_code(500);
    echo json_encode(['error' => 'Fungsi callOdooRead tidak ditemukan.']);
    exit;
}

$username = $_SESSION['username'] ?? '';

$product_cats = callOdooRead($username, 'product.category', [['complete_name', 'ilike', 'Persediaan Hardware']], ['id']);
$category_ids = [];
if (is_array($product_cats) && count($product_cats) > 0) {
    $category_ids = array_column($product_cats, 'id');
}

if (!empty($category_ids)) {
    $domain = [['categ_id', 'in', $category_ids], ['active', '=', true]];
} else {
    $domain = [['categ_id', 'ilike', 'Persediaan Hardware'], ['active', '=', true]];
}

$fields = ['id', 'default_code', 'name', 'uom_id', 'active'];
$items = callOdooRead($username, 'product.template', $domain, $fields);

if ($items === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to Odoo or retrieve data']);
    exit;
}

// Normalisasi hasil supaya client mudah pakai
$normalized = [];
foreach ($items as $p) {
    $id = $p['id'] ?? null;
    if (!$id) continue;

    $name = is_array($p['name']) ? ($p['name'][1] ?? $p['name'][0] ?? '') : ($p['name'] ?? '');
    $uom = is_array($p['uom_id']) ? ($p['uom_id'][1] ?? '') : ($p['uom_id'] ?? '');

    $normalized[] = [
        'id' => $id,
        'default_code' => $p['default_code'] ?? '',
        'hardware_name' => $name,
        'hardware_uom' => $uom,
        'is_active' => isset($p['active']) ? ($p['active'] ? 1 : 0) : 1
    ];
}

echo json_encode($normalized);

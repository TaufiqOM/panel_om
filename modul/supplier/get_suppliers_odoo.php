<?php
session_start();
require __DIR__ . '/../../inc/config_odoo.php';

// Start Session
$username = $_SESSION['username'] ?? '';

// Get supplier category ID first
$supplier_categories = callOdooRead($username, "res.partner.category", [["name", "=", "Supplier"]], ["id"]);

$category_ids = [];
if (is_array($supplier_categories) && count($supplier_categories) > 0) {
    $category_ids = array_column($supplier_categories, 'id');
}

// Get supplier data from Odoo (res.partner model) filtered by category
$domain = [];
if (!empty($category_ids)) {
    $domain = [["category_id", "in", $category_ids]];
}

$suppliers = callOdooRead($username, "res.partner", $domain, ["id", "name", "ref", "email", "phone", "mobile"]);

// Return JSON response
header('Content-Type: application/json');

if ($suppliers === false) {
    echo json_encode(['error' => 'Failed to connect to Odoo or retrieve data']);
    exit;
}

echo json_encode($suppliers);
?>

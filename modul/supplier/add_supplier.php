<?php
session_start();
require '../../inc/config.php';
require '../../inc/config_odoo.php';

// Start Session
$username = $_SESSION['username'] ?? '';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid']);
    exit;
}

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

$suppliers = callOdooRead($username, "res.partner", $domain, ["id", "name", "ref"]);

if ($suppliers === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to connect to Odoo or retrieve data']);
    exit;
}

$inserted = 0;
$updated = 0;
$deleted = 0;

// Get current supplier IDs from local DB
$current_supplier_ids = [];
$result = $conn->query("SELECT id FROM supplier");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $current_supplier_ids[] = $row['id'];
    }
    $result->free();
}

// Prepare arrays for new IDs from Odoo
$odoo_supplier_ids = [];

foreach ($suppliers as $supplier) {
    $id = $supplier['id'];
    $odoo_supplier_ids[] = $id;
    $supplier_code = $supplier['ref'] ?? '';
    $supplier_name = $supplier['name'] ?? '';

    // Check if supplier exists in local DB
    $stmt_check = $conn->prepare("SELECT supplier_code, supplier_name FROM supplier WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Exists, check if data is different
        $stmt_check->bind_result($existing_code, $existing_name);
        $stmt_check->fetch();

        if ($existing_code !== $supplier_code || $existing_name !== $supplier_name) {
            // Update
            $stmt_update = $conn->prepare("UPDATE supplier SET supplier_code = ?, supplier_name = ? WHERE id = ?");
            $stmt_update->bind_param("ssi", $supplier_code, $supplier_name, $id);
            if ($stmt_update->execute()) {
                $updated++;
            } else {
                error_log("Error updating supplier $id: " . $stmt_update->error);
            }
            $stmt_update->close();
        }
    } else {
        // Insert new supplier
        $stmt_insert = $conn->prepare("INSERT INTO supplier (id, supplier_code, supplier_name) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iss", $id, $supplier_code, $supplier_name);
        if ($stmt_insert->execute()) {
            $inserted++;
        } else {
            error_log("Error inserting supplier $id: " . $stmt_insert->error);
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
}

// Delete suppliers not in Odoo anymore
$ids_to_delete = array_diff($current_supplier_ids, $odoo_supplier_ids);
if (!empty($ids_to_delete)) {
    $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
    $types = str_repeat('i', count($ids_to_delete));
    $stmt_delete = $conn->prepare("DELETE FROM supplier WHERE id IN ($placeholders)");
    $stmt_delete->bind_param($types, ...$ids_to_delete);
    if ($stmt_delete->execute()) {
        $deleted = $stmt_delete->affected_rows;
    } else {
        error_log("Error deleting suppliers: " . $stmt_delete->error);
    }
    $stmt_delete->close();
}

mysqli_close($conn);

echo json_encode(['success' => true, 'message' => "Suppliers synced successfully. Inserted: $inserted, Updated: $updated, Deleted: $deleted"]);
?>

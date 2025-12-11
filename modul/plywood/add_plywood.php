<?php
session_start();
require '../../inc/config.php';
require '../../inc/config_odoo.php';

// Untuk debugging lokal, uncomment baris berikut (hapus lagi setelah selesai)
 // ini_set('display_errors', 1);
 // ini_set('display_startup_errors', 1);
 // error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid. Gunakan POST.']);
    exit;
}

// Pastikan fungsi Odoo tersedia
if (!function_exists('callOdooRead')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fungsi callOdooRead tidak ditemukan. Periksa inc/config_odoo.php']);
    exit;
}

$username = $_SESSION['username'] ?? '';

try {
    // Cari category product "Persediaan Plywood"
    $product_cats = callOdooRead($username, 'product.category', [['complete_name', 'ilike', 'Persediaan Plywood']], ['id']);
    $category_ids = [];
    if (is_array($product_cats) && count($product_cats) > 0) {
        $category_ids = array_column($product_cats, 'id');
    }

    // Domain untuk product.template
    if (!empty($category_ids)) {
        $domain = [['categ_id', 'in', $category_ids], ['active', '=', true]];
    } else {
        // fallback: coba cari by name-like jika category tidak ditemukan
        $domain = [['categ_id', 'ilike', 'Persediaan Plywood'], ['active', '=', true]];
    }

    $fields = ['id', 'default_code', 'name'];
    $products = callOdooRead($username, 'product.template', $domain, $fields);

    if ($products === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data dari Odoo']);
        exit;
    }

    $inserted = 0;
    $updated = 0;
    $deleted = 0;

    // Get current wood_engineered IDs from local DB
    $current_ids = [];
    $result = $conn->query("SELECT id FROM wood_engineered");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $current_ids[] = $row['id'];
        }
        $result->free();
    }

    // Prepare arrays for new IDs from Odoo
    $odoo_ids = [];

    foreach ($products as $p) {
        $id = isset($p['id']) ? (int)$p['id'] : 0;
        if ($id === 0) continue;
        $odoo_ids[] = $id;

        $wood_engineered_code = $p['default_code'] ?? '';

        // name bisa string atau array tergantung wrapper RPC -> handle both
        if (is_array($p['name'])) {
            // kadang array => [id, "Display Name"] atau [ "Display Name" ]
            $wood_engineered_name = $p['name'][1] ?? $p['name'][0] ?? '';
        } else {
            $wood_engineered_name = $p['name'] ?? '';
        }

        // Check if exists in local DB
        $stmt_check = $conn->prepare("SELECT wood_engineered_code, wood_engineered_name FROM wood_engineered WHERE id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            // Exists, check if data is different
            $stmt_check->bind_result($existing_code, $existing_name);
            $stmt_check->fetch();

            if ($existing_code !== $wood_engineered_code || $existing_name !== $wood_engineered_name) {
                // Update
                $stmt_update = $conn->prepare("UPDATE wood_engineered SET wood_engineered_code = ?, wood_engineered_name = ? WHERE id = ?");
                $stmt_update->bind_param("ssi", $wood_engineered_code, $wood_engineered_name, $id);
                if ($stmt_update->execute()) {
                    $updated++;
                } else {
                    error_log("Error updating wood_engineered $id: " . $stmt_update->error);
                }
                $stmt_update->close();
            }
        } else {
            // Insert new
            $stmt_insert = $conn->prepare("INSERT INTO wood_engineered (id, wood_engineered_code, wood_engineered_name) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("iss", $id, $wood_engineered_code, $wood_engineered_name);
            if ($stmt_insert->execute()) {
                $inserted++;
            } else {
                error_log("Error inserting wood_engineered $id: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }

    // Delete not in Odoo anymore
    $ids_to_delete = array_diff($current_ids, $odoo_ids);
    if (!empty($ids_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $types = str_repeat('i', count($ids_to_delete));
        $stmt_delete = $conn->prepare("DELETE FROM wood_engineered WHERE id IN ($placeholders)");
        $stmt_delete->bind_param($types, ...$ids_to_delete);
        if ($stmt_delete->execute()) {
            $deleted = $stmt_delete->affected_rows;
        } else {
            error_log("Error deleting wood_engineered: " . $stmt_delete->error);
        }
        $stmt_delete->close();
    }

    mysqli_close($conn);

    echo json_encode(['success' => true, 'message' => "Plywood synced successfully. Inserted: $inserted, Updated: $updated, Deleted: $deleted"]);
    exit;

} catch (Throwable $e) {
    // Tangkap exception/fatal lain
    http_response_code(500);
    error_log("add_plywood.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
    exit;
}

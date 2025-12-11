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
    // Cari category product "Persediaan Hardware"
    $product_cats = callOdooRead($username, 'product.category', [['complete_name', 'ilike', 'Persediaan Hardware']], ['id']);
    $category_ids = [];
    if (is_array($product_cats) && count($product_cats) > 0) {
        $category_ids = array_column($product_cats, 'id');
    }

    // Domain untuk product.template
    if (!empty($category_ids)) {
        $domain = [['categ_id', 'in', $category_ids], ['active', '=', true]];
    } else {
        // fallback: coba cari by name-like jika category tidak ditemukan
        $domain = [['categ_id', 'ilike', 'Persediaan Hardware'], ['active', '=', true]];
    }

    $fields = ['id', 'default_code', 'name', 'uom_id', 'active'];
    $products = callOdooRead($username, 'product.template', $domain, $fields);

    if ($products === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data dari Odoo']);
        exit;
    }

    $inserted = 0;
    $updated = 0;
    $deleted = 0;

    // Get current hardware IDs from local DB
    $current_hardware_ids = [];
    $result = $conn->query("SELECT id FROM hardware");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $current_hardware_ids[] = $row['id'];
        }
        $result->free();
    }

    // Prepare arrays for new IDs from Odoo
    $odoo_hardware_ids = [];

    foreach ($products as $p) {
        $id = isset($p['id']) ? (int)$p['id'] : 0;
        if ($id === 0) continue;
        $odoo_hardware_ids[] = $id;

        $hardware_code = $p['default_code'] ?? '';

        // name bisa string atau array tergantung wrapper RPC -> handle both
        if (is_array($p['name'])) {
            // kadang array => [id, "Display Name"] atau [ "Display Name" ]
            $hardware_name = $p['name'][1] ?? $p['name'][0] ?? '';
        } else {
            $hardware_name = $p['name'] ?? '';
        }

        // uom_id biasanya [id, 'Unit']
        if (is_array($p['uom_id'])) {
            $hardware_uom = $p['uom_id'][1] ?? '';
        } else {
            $hardware_uom = $p['uom_id'] ?? '';
        }

        $is_active = isset($p['active']) ? ($p['active'] ? 1 : 0) : 1;

        // Check if hardware exists in local DB
        $stmt_check = $conn->prepare("SELECT hardware_code, hardware_name, hardware_uom, is_active FROM hardware WHERE id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            // Exists, check if data is different
            $stmt_check->bind_result($existing_code, $existing_name, $existing_uom, $existing_active);
            $stmt_check->fetch();

            if ($existing_code !== $hardware_code || $existing_name !== $hardware_name || $existing_uom !== $hardware_uom || $existing_active != $is_active) {
                // Update
                $stmt_update = $conn->prepare("UPDATE hardware SET hardware_code = ?, hardware_name = ?, hardware_uom = ?, is_active = ? WHERE id = ?");
                $stmt_update->bind_param("sssii", $hardware_code, $hardware_name, $hardware_uom, $is_active, $id);
                if ($stmt_update->execute()) {
                    $updated++;
                } else {
                    error_log("Error updating hardware $id: " . $stmt_update->error);
                }
                $stmt_update->close();
            }
        } else {
            // Insert new hardware
            $stmt_insert = $conn->prepare("INSERT INTO hardware (id, hardware_code, hardware_name, hardware_uom, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("isssi", $id, $hardware_code, $hardware_name, $hardware_uom, $is_active);
            if ($stmt_insert->execute()) {
                $inserted++;
            } else {
                error_log("Error inserting hardware $id: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }

    // Delete hardware not in Odoo anymore
    $ids_to_delete = array_diff($current_hardware_ids, $odoo_hardware_ids);
    if (!empty($ids_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $types = str_repeat('i', count($ids_to_delete));
        $stmt_delete = $conn->prepare("DELETE FROM hardware WHERE id IN ($placeholders)");
        $stmt_delete->bind_param($types, ...$ids_to_delete);
        if ($stmt_delete->execute()) {
            $deleted = $stmt_delete->affected_rows;
        } else {
            error_log("Error deleting hardware: " . $stmt_delete->error);
        }
        $stmt_delete->close();
    }

    mysqli_close($conn);

    echo json_encode(['success' => true, 'message' => "Hardware synced successfully. Inserted: $inserted, Updated: $updated, Deleted: $deleted"]);
    exit;

} catch (Throwable $e) {
    // Tangkap exception/fatal lain
    http_response_code(500);
    error_log("add_hardware.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
    exit;
}

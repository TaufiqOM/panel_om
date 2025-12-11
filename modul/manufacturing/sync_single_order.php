<?php
set_time_limit(0);
session_start();
require '../../inc/config.php';
require '../../inc/config_odoo.php';
require '../../inc/csrf.php';

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!CSRF::validateToken($csrf_token)) {
    error_log("Invalid CSRF token received");
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

header('Content-Type: application/json');

$username = $_SESSION['username'] ?? '';
$so_id = $_POST['so_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST allowed.']);
    exit;
}

if (!is_numeric($so_id) || $so_id <= 0) {
    error_log("Invalid so_id: " . $so_id);
    echo json_encode(['success' => false, 'message' => 'Invalid sale order ID. Must be a positive integer.']);
    exit;
}

// Inisialisasi variabel
$stmt_check = null;
$stmt_insert = null;

try {
    // Get sale order details
    $orders = callOdooRead($username, "sale.order", [["id", "=", intval($so_id)]], [
        "id",
        "name",
        "partner_id",
        "create_date",
        "user_id",
        "confirmation_date_order",
        "due_date_order",
        "due_date_update_order",
        "client_order_ref"
    ]);

    if ($orders === false || !is_array($orders) || empty($orders)) {
        throw new Exception("Failed to fetch sale order from Odoo");
    }

    $order = $orders[0];

    // Get all sale order lines for this order
    $lines = callOdooRead($username, "sale.order.line", [["order_id", "=", intval($so_id)]], [
        "id",
        "order_id",
        "product_id",
        "name",
        "product_uom_qty",
        "price_unit",
        "price_subtotal",
        "product_uom",
        "info_to_buyer",
        "info_to_production",
        "type_product",
        "finish_product"
    ]);

    if ($lines === false) {
        throw new Exception("Failed to fetch order lines from Odoo (returned false)");
    }

    if (!is_array($lines)) {
        throw new Exception("Odoo returned non-array for order lines: " . json_encode($lines));
    }

    if (empty($lines)) {
        throw new Exception("No order lines found. Raw response: " . json_encode($lines));
    }

    $inserted = 0;
    $skipped = 0;

    $conn->begin_transaction();

    $prefix_code = null;
    $last_number = null;

    // Siapkan prepared statement
    $stmt_check = $conn->prepare("SELECT id FROM barcode_lot WHERE sale_order_line_id = ? LIMIT 1");
    $stmt_insert = $conn->prepare("
    INSERT INTO barcode_lot
    (sale_order_id, sale_order_line_id, sale_order_name, sale_order_ref, customer_name,
     product_id, product_name, product_ref, image_1920, finishing, prefix_code,
     qty_order, last_number, order_date, order_due_date, country,
     info_to_production, info_to_buyer)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

    if (!$stmt_check || !$stmt_insert) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $so_name = $order['name'] ?? '';
    $customer_name = is_array($order['partner_id']) ? $order['partner_id'][1] : '';
    $order_date = $order['confirmation_date_order'] ?? $order['create_date'] ?? null;
    $order_due_date = $order['due_date_update_order'] ?? $order['due_date_order'] ?? null;
    $client_order_ref = $order['client_order_ref'] ?? null;

    // Get country from partner once
    $partner_country = '';
    if (!empty($order['partner_id'][0])) {
        $partner_data = callOdooRead($username, "res.partner", [["id", "=", $order['partner_id'][0]]], ["country_id"]);
        if (!empty($partner_data[0]['country_id'])) {
            $partner_country = $partner_data[0]['country_id'][1] ?? '';
        }
    }

    foreach ($lines as $line) {
        $line_id = $line['id'];
        $product_id = is_array($line['product_id']) ? $line['product_id'][0] : null;
        $product_name = is_array($line['product_id']) ? $line['product_id'][1] : '';
        $qty_order = intval($line['product_uom_qty'] ?? 0);
        $info_to_production = $line['info_to_production'] ?? '';
        $info_to_buyer = $line['info_to_buyer'] ?? '';
        $finishing_raw = $line['finish_product'] ?? '';
        $finish_product = is_array($finishing_raw) ? ($finishing_raw[1] ?? '') : $finishing_raw;

        // Skip if qty <= 0 or no product
        if ($qty_order <= 0 || !$product_id) {
            $skipped++;
            continue;
        }

        // Check if already exists in barcode_lot
        $stmt_check->bind_param("i", $line_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $skipped++;
            $stmt_check->free_result();
            continue;
        }
        $stmt_check->free_result();

        // Get product details
        $product_details = callOdooRead($username, "product.product", [["id", "=", $product_id]], ["default_code", "image_1920"]);
        $product_ref = $product_details[0]['default_code'] ?? '';
        $image_1920 = $product_details[0]['image_1920'] ?? null;

        // Handle image BLOB
        $image_blob = null;
        if ($image_1920 !== null) {
            $decoded_image = base64_decode($image_1920);
            if ($decoded_image !== false) {
                $image_blob = $decoded_image;
            }
        }

        // Insert into barcode_lot
        $stmt_insert->bind_param(
            "iisssissbssissssss",
            $so_id,
            $line_id,
            $so_name,
            $client_order_ref,
            $customer_name,
            $product_id,
            $product_name,
            $product_ref,
            $image_blob,
            $finish_product,
            $prefix_code,
            $qty_order,
            $last_number,
            $order_date,
            $order_due_date,
            $partner_country,
            $info_to_production,
            $info_to_buyer
        );

        if ($image_blob !== null && strlen($image_blob) > 0) {
            $stmt_insert->send_long_data(8, $image_blob);
        }

        if ($stmt_insert->execute()) {
            $inserted++;
        } else {
            // Fallback tanpa gambar jika gagal
            error_log("Insert failed with image, trying without image. Error: " . $stmt_insert->error);

            $stmt_insert->bind_param(
                "iisssissbssissssss",
                $so_id,
                $line_id,
                $so_name,
                $client_order_ref,
                $customer_name,
                $product_id,
                $product_name,
                $product_ref,
                null,
                $finish_product,
                $prefix_code,
                $qty_order,
                $last_number,
                $order_date,
                $order_due_date,
                $partner_country,
                $info_to_production,
                $info_to_buyer
            );

            if ($stmt_insert->execute()) {
                $inserted++;
            } else {
                error_log("Insert failed even without image. Error: " . $stmt_insert->error);
                $skipped++;
            }
        }
    }

    $conn->commit();

    if ($stmt_check) $stmt_check->close();
    if ($stmt_insert) $stmt_insert->close();

    $total_processed = $inserted + $skipped;

    if ($inserted > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Sync completed for order {$so_name}. Inserted: {$inserted}, Skipped: {$skipped}, Total Processed: {$total_processed}"
        ]);
    } else if ($skipped > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Sync completed for order {$so_name}. All {$skipped} lines already synced (skipped). Total Processed: {$total_processed}"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "No valid data to sync for order {$so_name}. Check if order has valid products and quantities."
        ]);
    }
} catch (Exception $e) {
    $conn->rollback();
    if ($stmt_check) $stmt_check->close();
    if ($stmt_insert) $stmt_insert->close();

    error_log("Sync Single Order Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

if ($conn) {
    $conn->close();
}

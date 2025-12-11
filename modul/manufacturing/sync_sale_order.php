<?php
set_time_limit(0); // Remove time limit for long running script
session_start();
require '../../inc/config.php';
require '../../inc/config_odoo.php';
require '../../inc/csrf.php';

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!CSRF::validateToken($csrf_token)) {
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

header('Content-Type: application/json');

$username = $_SESSION['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid']);
    exit;
}

// Inisialisasi variabel
$stmt_check = null;
$stmt_insert = null;

try {
    // Get all sale orders from Odoo
    $orders = callOdooRead($username, "sale.order", [["state", "=", "sale"]], [
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

    if ($orders === false || !is_array($orders)) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch sale orders from Odoo']);
        exit;
    }

    $inserted = 0;
    $skipped = 0;
    $total_orders = count($orders);

    $conn->begin_transaction();

    // Siapkan prepared statement di luar loop
    $stmt_check = $conn->prepare("SELECT id FROM barcode_lot WHERE sale_order_line_id = ? LIMIT 1");
    $stmt_insert = $conn->prepare("INSERT INTO barcode_lot
        (sale_order_id, sale_order_line_id, sale_order_name, sale_order_ref, customer_name,
         product_id, product_name, product_ref, image_1920, finishing, prefix_code,
         qty_order, last_number, order_date, order_due_date, country,
         info_to_production, info_to_buyer)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt_check || !$stmt_insert) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $processed = 0;
    foreach ($orders as $order) {
        $processed++;
        $so_id = $order['id'];
        $so_name = $order['name'] ?? '';
        $customer_name = is_array($order['partner_id']) ? $order['partner_id'][1] : '';
        $order_date = $order['confirmation_date_order'] ?? $order['create_date'] ?? null;
        $order_due_date = $order['due_date_update_order'] ?? $order['due_date_order'] ?? null;
        $client_order_ref = $order['client_order_ref'] ?? null;

        // Get order lines for this SO
        $lines = callOdooRead($username, "sale.order.line", [["order_id", "=", $so_id]], [
            "id",
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

        if (!is_array($lines)) {
            continue; // Skip if no lines
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
                continue;
            }

            // Check if already exists in barcode_lot
            $stmt_check->bind_param("i", $line_id);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $skipped++;
                $stmt_check->free_result();
                continue; // Already exists, skip
            }
            $stmt_check->free_result();

            // Get product details for product_ref and image_1920
            $product_details = callOdooRead($username, "product.product", [["id", "=", $product_id]], ["default_code", "image_1920"]);
            $product_ref = $product_details[0]['default_code'] ?? '';
            $image_1920 = $product_details[0]['image_1920'] ?? null;

            // Get country from partner
            $partner_country = '';
            if (!empty($order['partner_id'][0])) {
                $partner_data = callOdooRead($username, "res.partner", [["id", "=", $order['partner_id'][0]]], ["country_id"]);
                if (!empty($partner_data[0]['country_id'])) {
                    $partner_country = $partner_data[0]['country_id'][1] ?? '';
                }
            }

            // Handle image BLOB dengan benar
            $image_blob = null;
            if ($image_1920 !== null) {
                $decoded_image = base64_decode($image_1920);
                if ($decoded_image !== false) {
                    $image_blob = $decoded_image;
                }
            }

            // Binding parameter untuk BLOB
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

            // Gunakan send_long_data untuk data BLOB yang besar
            if ($image_blob !== null && strlen($image_blob) > 0) {
                $stmt_insert->send_long_data(8, $image_blob); // Parameter ke-8 adalah image_1920
            }

            if ($stmt_insert->execute()) {
                $inserted++;
            } else {
                // Fallback jika gambar terlalu besar
                if (strpos($stmt_insert->error, 'data too long') !== false || $stmt_insert->errno == 1406) {
                    // Coba insert tanpa gambar
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
                        throw new Exception("Insert failed even without image: " . $stmt_insert->error);
                    }
                } else {
                    throw new Exception("Insert failed: " . $stmt_insert->error);
                }
            }
        }
    }

    $conn->commit();

    // Hanya tutup statements jika masih terbuka
    if ($stmt_check) {
        $stmt_check->close();
    }
    if ($stmt_insert) {
        $stmt_insert->close();
    }

    echo json_encode([
        'success' => true,
        'message' => "Sync completed. Inserted: $inserted, Skipped: $skipped"
    ]);
} catch (Exception $e) {
    $conn->rollback();

    // Tutup statements dalam blok catch juga
    if ($stmt_check) {
        $stmt_check->close();
    }
    if ($stmt_insert) {
        $stmt_insert->close();
    }

    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Tutup koneksi database
if ($conn) {
    $conn->close();
}

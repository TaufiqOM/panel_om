<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
require __DIR__ . '/../../inc/config_odoo.php';
require __DIR__ . '/../../inc/config.php';

header('Content-Type: application/json');

$username    = $_SESSION['username'] ?? 'system';

try {
    // Ambil JSON dari request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !is_array($data)) {
        throw new Exception('Invalid JSON data received');
    }

    $success_count = 0;
    $errors = [];
    $synced_orders = [];
    $order_ids = [];
    $production_dates = [];

    foreach ($data as $product_data) {
        try {
            $so_name_odoo = trim($product_data['so_name']);

            // 1. Cari sale.order by so_name
            $existing_orders = callOdooRead($username, "sale.order", [["name", "=", $so_name_odoo]], ["id"]);

            if (is_array($existing_orders) && count($existing_orders) > 0 && isset($existing_orders[0]['id']) && $existing_orders[0]['id']) {
                $order_id = $existing_orders[0]['id'];

                // 2. Cari sale.order.line by order_id + product name

                $existing_lines = callOdooRead(
                    $username,
                    'sale.order.line',
                    [
                        ['order_id', '=', $order_id],
                        ['product_id.id', 'ilike', $product_data['id']]
                    ],
                    ['id']
                );

                if (!empty($existing_lines)) {
                    $line_id = $existing_lines[0]['id'];

                    // 3. Data yang mau diupdate
                    $update_values = [
                        'production_opn'   => ($product_data['OPN'] ?? 0),
                        'production_av'    => ($product_data['AV'] ?? 0),
                        'production_raw'   => ($product_data['RAW'] ?? 0),
                        'production_snd'   => ($product_data['SND'] ?? 0),
                        'production_rtr'   => ($product_data['RTR'] ?? 0),
                        'production_qcin'  => ($product_data['QCIN'] ?? 0),
                        'production_clr'   => ($product_data['CLR'] ?? 0),
                        'production_qcl'   => ($product_data['QCL'] ?? 0),
                        'production_finp'  => ($product_data['FinP'] ?? 0),
                        'production_qfin'  => ($product_data['QFin'] ?? 0),
                        'production_str'   => ($product_data['STR'] ?? 0),
                        'last_sync_date'   => gmdate('Y-m-d H:i:s')
                    ];

                    // 4. Update ke Odoo
                    $result = callOdooWrite($username, 'sale.order.line', [$line_id], $update_values);
                    if ($result) {
                        $success_count++;
                        $synced_orders[$product_data['so_name']] = true;
                        $order_ids[$product_data['so_name']] = $order_id;
                        $production_dates[$product_data['so_name']] = $product_data['production_date'];
                    } else {
                        $errors[] = "Failed to update sale.order.line for product: {$product_data['product_name']} in SO: {$product_data['so_name']}";
                    }
                } else {
                    $errors[] = "Sale order line not found for product: {$product_data['product_name']} in SO: {$product_data['so_name']}";
                }
            } else {
                $errors[] = "Sale order not found: {$so_name_odoo}, response:" . json_encode($existing_orders);
            }
        } catch (Exception $e) {
            $errors[] = "Error processing product {$product_data['product_name']} in SO {$product_data['so_name']}: " . $e->getMessage();
        }
    }

    // 6. Simpan log sinkronisasi ke MySQL
    foreach ($synced_orders as $so_name => $true) {
        try {
            $order_id = $order_ids[$so_name];
            $production_date = $production_dates[$so_name];

            $sync_query = "INSERT INTO production_periodic_sync (id_so, so_name, production_date)
                           VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE synced_at = CURRENT_TIMESTAMP";

            $stmt = $conn->prepare($sync_query);
            $stmt->bind_param("iss", $order_id, $so_name, $production_date);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // diabaikan kalau gagal
        }
    }

    // 7. Response ke client
    echo json_encode([
        'success' => $success_count > 0,
        'posted_count' => $success_count,
        'total_count' => count($data),
        'errors' => $errors
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

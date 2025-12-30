<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $error['message']]);
    }
});

try {
session_start();
require __DIR__ . '/../../inc/config.php';
require __DIR__ . '/../../inc/config_odoo.php';

// Start Session
$username = $_SESSION['username'] ?? '';

// Get shipping batches from Odoo (stock.picking.batch model)
$batches = callOdooRead($username, 'stock.picking.batch', [], ['id', 'name', 'scheduled_date', 'description', 'picking_ids', 'state']);

// Return JSON response
header('Content-Type: application/json');

if ($batches === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to connect to Odoo or retrieve data']);
    exit;
}

$inserted_count = 0;
$updated_count = 0;

if (is_array($batches) && count($batches) > 0) {
    foreach ($batches as $batch) {
        $batch_id = $batch['id'] ?? 0;
        $name = $batch['name'] ?? '';
        $scheduled_date = $batch['scheduled_date'] ?? null;
        $description = $batch['description'] ?? '';
        $state = $batch['state'] ?? 'draft';
        $ship_to = '';

        // Get picking details to get ship_to information
        if (!empty($batch['picking_ids'])) {
            $picking = callOdooRead($username, 'stock.picking', [['id', 'in', $batch['picking_ids']]], ['partner_id']);
            if ($picking && isset($picking[0]['partner_id'][1])) {
                $ship_to = $picking[0]['partner_id'][1];
            }
        }

        // Check if exists
        $stmt_check = $conn->prepare("SELECT id FROM shipping WHERE id = ?");
        $stmt_check->bind_param("i", $batch_id);
        $stmt_check->execute();
        $exists = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();

        if (!$exists) {
            $stmt = $conn->prepare("INSERT INTO shipping (id, name, sheduled_date, description, ship_to) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $batch_id, $name, $scheduled_date, $description, $ship_to);
            if ($stmt->execute()) {
                $inserted_count++;
            }
            $stmt->close();
        } else {
            // Update existing record
            $stmt = $conn->prepare("UPDATE shipping SET name = ?, sheduled_date = ?, description = ?, ship_to = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $scheduled_date, $description, $ship_to, $batch_id);
            if ($stmt->execute()) {
                $updated_count++;
            }
            $stmt->close();
        }

        // Set shipping_id
        $shipping_id = $batch_id;

        // Insert shipping_detail
        if (!empty($batch['picking_ids'])) {
            $pickings = callOdooRead($username, 'stock.picking', [['id', 'in', $batch['picking_ids']]], ['name', 'sale_id', 'origin', 'group_id', 'move_line_ids_without_package']);
            if ($pickings) {
                // Delete existing shipping_detail for this shipping
                $stmt_del = $conn->prepare("DELETE FROM shipping_detail WHERE id_shipping = ?");
                $stmt_del->bind_param("i", $shipping_id);
                $stmt_del->execute();
                $stmt_del->close();

                // Kumpulkan group_ids unik dari pickings
                $group_ids = [];
                foreach ($pickings as $picking) {
                    if (is_array($picking['group_id']) && count($picking['group_id']) > 0) {
                        $group_ids[] = $picking['group_id'][0];
                    }
                }
                $group_ids = array_unique($group_ids);

                // Ambil client_order_ref dari sale.order berdasarkan procurement_group_id
                $po_map = [];
                if (!empty($group_ids)) {
                    $sale_orders_data = callOdooRead($username, "sale.order", [["procurement_group_id", "in", $group_ids]], ["procurement_group_id", "client_order_ref"]);
                    foreach ($sale_orders_data as $so) {
                        if (is_array($so['procurement_group_id']) && count($so['procurement_group_id']) > 0) {
                            $po_map[$so['procurement_group_id'][0]] = $so['client_order_ref'];
                        }
                    }
                }

                // Insert each picking into shipping_detail
                foreach ($pickings as $picking) {
                    $picking_id = $picking['id'];
                    $picking_name = $picking['name'];
                    $sale_id = null;
                    if (is_array($picking['sale_id']) && count($picking['sale_id']) > 0) {
                        $sale_id = $picking['sale_id'][0];
                    }
                    $group_id = (is_array($picking['group_id']) && count($picking['group_id']) > 0) ? $picking['group_id'][0] : null;
                    $po = $group_id ? ($po_map[$group_id] ?? '') : '';
                    $stmt_ins = $conn->prepare("INSERT INTO shipping_detail (id, id_shipping, name, sale_id, client_order_ref) VALUES (?, ?, ?, ?, ?)");
                    $stmt_ins->bind_param("iisis", $picking_id, $shipping_id, $picking_name, $sale_id, $po);
                    $stmt_ins->execute();
                    $stmt_ins->close();

                    // Note: Lot IDs tidak disimpan ke database, akan diambil realtime dari Odoo
                }
            }
        }
    }
}

    $message = "Sync completed. Inserted: $inserted_count, Updated: $updated_count";
    echo json_encode(['success' => true, 'message' => $message, 'inserted' => $inserted_count, 'updated' => $updated_count]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
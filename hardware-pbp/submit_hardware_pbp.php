<?php
session_start();
require_once '../inc/config_odoo.php';
require_once '../inc/config.php';
header('Content-Type: application/json');
$username = $_SESSION['username'] ?? 'system';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data['hardware_items']) || !is_array($data['hardware_items'])) {
    echo json_encode(['success' => false, 'error' => 'Payload invalid: butuh hardware_items array']);
    exit;
}

$employee_nik = $data['employee_nik'] ?? '';

try {
    // Generate PBP number
    $date = date('Ymd');
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(hardware_pbp_number, '-', -1) AS UNSIGNED)) as max_id FROM hardware_pbp WHERE hardware_pbp_number LIKE 'PBP-HW-$date-%'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $next_id = ($row['max_id'] ?? 0) + 1;
    $pbp_number = 'PBP-HW-' . $date . '-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
    mysqli_free_result($result);

    $item_count = count($data['hardware_items']);
    $date_now = date('Y-m-d');
    $time_now = date('H:i:s');

    // Collect unique picking_ids to get additional data
    $picking_ids = [];
    $so_ids = [];
    foreach ($data['hardware_items'] as $item) {
        if (!empty($item['picking_id'])) {
            $picking_ids[] = $item['picking_id'];
        }
        if (!empty($item['so_number'])) {
            $so_rec = null;
            // Get SO id and name, checking name, client_order_ref, po_cust
            if (is_numeric($item['so_number'])) {
                $so_domain = [['id','=',(int)$item['so_number']]];
            } else {
                $so_domain = [['name','ilike',$item['so_number']]];
                $so_rec = callOdooRead($username, 'sale.order', $so_domain, ['id','name']);
                if (empty($so_rec)) {
                    $so_domain = [['client_order_ref','ilike',$item['so_number']]];
                    $so_rec = callOdooRead($username, 'sale.order', $so_domain, ['id','name']);
                    if (empty($so_rec)) {
                        $so_domain = [['po_cust','ilike',$item['so_number']]];
                        $so_rec = callOdooRead($username, 'sale.order', $so_domain, ['id','name']);
                    }
                }
            }
            if (!isset($so_rec)) {
                $so_rec = callOdooRead($username, 'sale.order', $so_domain, ['id','name']);
            }
            if (!empty($so_rec)) {
                $so_ids[$item['so_number']] = $so_rec[0];
            }
        }
    }
    $picking_ids = array_unique($picking_ids);

    // Get picking names
    $picking_data = [];
    if (!empty($picking_ids)) {
        $pickings = callOdooRead($username, 'stock.picking', [['id','in',$picking_ids]], ['id','name']);
         if (is_array($pickings)) {
            foreach ($pickings as $picking) {
                $picking_data[$picking['id']] = $picking['name'];
            }
        }
    }

    $results = [];
    $processed_picking_ids = []; // Track processed pickings to set unselected to 0

    foreach ($data['hardware_items'] as $item) {
        $line_id = $item['line_id'] ?? null;
        $qty = isset($item['qty']) ? floatval($item['qty']) : null;
        $hardware_code = $item['code'] ?? '';
        $hardware_name = $item['name'] ?? '';
        $hardware_uom = $item['hardware_uom'] ?? '';
        $so_number = $item['so_number'] ?? '';
        $product_code = $item['product_code'] ?? '';
        $picking_id = $item['picking_id'] ?? null;
        $mo_id = $item['mo_id'] ?? null;
        $so_id = $item['so_id'] ?? null;
        $so_name = $item['so_name'] ?? null;

        if (!$line_id || $qty === null || $qty <= 0) {
            $results[] = ['line_id' => $line_id, 'success' => false, 'error' => 'line_id dan qty wajib, qty harus > 0'];
            continue;
        }

        if (!$hardware_uom) {
            $hardware_uom = 'PCS';
        }

        // Fallback for so_id and so_name if not provided
        if (!$so_id && isset($so_ids[$so_number])) {
            $so_id = $so_ids[$so_number]['id'];
            $so_name = $so_ids[$so_number]['name'];
        }

        // Get additional data
        $picking_name = $picking_id && isset($picking_data[$picking_id]) ? $picking_data[$picking_id] : null;
        if (!$picking_name && $picking_id) {
            // Try to fetch individually if bulk call failed
            $single_picking = callOdooRead($username, 'stock.picking', [['id','=',$picking_id]], ['name']);
            if (is_array($single_picking) && !empty($single_picking) && isset($single_picking[0]['name'])) {
                $picking_name = $single_picking[0]['name'];
                $picking_data[$picking_id] = $picking_name; // Cache it
            }
        }

        // Insert into hardware_pbp with new columns
        $insert_query = "INSERT INTO hardware_pbp (hardware_pbp_number, hardware_pbp_date, hardware_pbp_time, hardware_code, hardware_name, hardware_uom, hardware_pbp_qty, so_number, product_code, employee_nik, picking_id, picking_name, so_id, so_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, 'ssssssssssisss', $pbp_number, $date_now, $time_now, $hardware_code, $hardware_name, $hardware_uom, $qty, $so_number, $product_code, $employee_nik, $picking_id, $picking_name, $so_id, $so_name);
        $insert_success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$insert_success) {
            $results[] = ['line_id' => $line_id, 'success' => false, 'error' => 'Gagal menyimpan ke database'];
            continue;
        }

        // Update Odoo quantity for selected item
        $update_result = callOdooWrite($username, 'stock.move', [$line_id], ['quantity' => $qty]);
        if ($update_result) {
            $results[] = ['line_id' => $line_id, 'success' => true, 'action' => 'updated', 'quantity' => $qty];
        } else {
            $results[] = ['line_id' => $line_id, 'success' => false, 'error' => 'Gagal update di Odoo'];
        }

        // Track picking_id for setting unselected to 0
        if ($picking_id && !in_array($picking_id, $processed_picking_ids)) {
            $processed_picking_ids[] = $picking_id;
        }
    }

    // Set unselected moves in processed pickings to qty=0
    foreach ($processed_picking_ids as $picking_id) {
        // Get all move_ids in this picking
        $picking_moves = callOdooRead($username, 'stock.picking', [['id','=',$picking_id]], ['move_ids']);
        if (!empty($picking_moves) && !empty($picking_moves[0]['move_ids'])) {
            $all_move_ids = $picking_moves[0]['move_ids'];

            // Get selected move_ids from this picking
            $selected_move_ids = [];
            foreach ($data['hardware_items'] as $item) {
                if ($item['picking_id'] == $picking_id && !empty($item['line_id'])) {
                    $selected_move_ids[] = $item['line_id'];
                }
            }

            // Set unselected moves to qty=0
            $unselected_move_ids = array_diff($all_move_ids, $selected_move_ids);
            if (!empty($unselected_move_ids)) {
                foreach ($unselected_move_ids as $unselected_id) {
                    callOdooWrite($username, 'stock.move', [$unselected_id], ['quantity' => 0]);
                }
            }
        }
    }

    echo json_encode(['success' => true, 'pbp_number' => $pbp_number, 'item_count' => $item_count, 'results' => $results]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

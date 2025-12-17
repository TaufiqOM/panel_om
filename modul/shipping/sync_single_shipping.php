<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

session_start();
require __DIR__ . '/../../inc/config.php';
require __DIR__ . '/../../inc/config_odoo.php';

$username = $_SESSION['username'] ?? '';

// Ambil shipping_id dari POST
$shipping_id = isset($_POST['shipping_id']) ? intval($_POST['shipping_id']) : 0;

if (!$shipping_id) {
    echo json_encode(['success' => false, 'message' => 'Shipping ID tidak valid']);
    exit;
}

try {
    // Ambil data shipping dari database lokal
    $sql = "SELECT id, name FROM shipping WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $shipping_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Shipping tidak ditemukan']);
        exit;
    }
    
    $shipping = $result->fetch_assoc();
    $batch_name = $shipping['name'];
    $stmt->close();
    
    // Get shipping batch dari Odoo berdasarkan nama
    $batches = callOdooRead($username, 'stock.picking.batch', [['name', '=', $batch_name]], ['id', 'name', 'scheduled_date', 'description', 'picking_ids']);
    
    if ($batches === false || empty($batches)) {
        echo json_encode(['success' => false, 'message' => 'Data batch tidak ditemukan di Odoo']);
        exit;
    }
    
    $batch = $batches[0];
    $batch_id = $batch['id'];
    
    // Update shipping info di database lokal
    $scheduled_date = $batch['scheduled_date'] ?? null;
    $description = $batch['description'] ?? '';
    
    $stmt_update = $conn->prepare("UPDATE shipping SET sheduled_date = ?, description = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $scheduled_date, $description, $shipping_id);
    $stmt_update->execute();
    $stmt_update->close();
    
    // Process picking_ids
    $picking_ids = $batch['picking_ids'] ?? [];
    
    if (empty($picking_ids)) {
        echo json_encode([
            'success' => true,
            'message' => 'Sinkron selesai. Tidak ada picking untuk shipping ini',
            'updated_pickings' => 0
        ]);
        exit;
    }
    
    // Get pickings dengan move_line_ids dan state
    $pickings = callOdooRead($username, 'stock.picking', [['id', 'in', $picking_ids]], ['name', 'sale_id', 'origin', 'group_id', 'move_line_ids_without_package', 'state']);
    
    if (!$pickings) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data picking dari Odoo']);
        exit;
    }
    
    // Kumpulkan group_ids untuk ambil PO
    $group_ids = [];
    foreach ($pickings as $picking) {
        if (is_array($picking['group_id']) && count($picking['group_id']) > 0) {
            $group_ids[] = $picking['group_id'][0];
        }
    }
    $group_ids = array_unique($group_ids);
    
    // Ambil client_order_ref dari sale.order
    $po_map = [];
    if (!empty($group_ids)) {
        $sale_orders_data = callOdooRead($username, "sale.order", [["procurement_group_id", "in", $group_ids]], ["procurement_group_id", "client_order_ref"]);
        foreach ($sale_orders_data as $so) {
            if (is_array($so['procurement_group_id']) && count($so['procurement_group_id']) > 0) {
                $po_map[$so['procurement_group_id'][0]] = $so['client_order_ref'];
            }
        }
    }
    
    // Delete existing shipping_detail for this shipping
    $stmt_del = $conn->prepare("DELETE FROM shipping_detail WHERE id_shipping = ?");
    $stmt_del->bind_param("i", $shipping_id);
    $stmt_del->execute();
    $stmt_del->close();
    
    $updated_pickings = 0;
    $done_pickings = [];
    
    // Insert each picking into shipping_detail
    foreach ($pickings as $picking) {
        $picking_id = $picking['id'];
        
        // Check if picking state is "done" (case insensitive)
        $picking_state = strtolower(trim($picking['state'] ?? ''));
        error_log("Picking ID: $picking_id, State: " . ($picking['state'] ?? 'NULL'));
        
        if ($picking_state === 'done') {
            $done_pickings[] = $picking_id;
            error_log("Picking $picking_id is DONE - will insert lot_ids to manual stuffing");
        }
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
        
        $updated_pickings++;
        
        // Note: Lot IDs tidak disimpan ke database, akan diambil realtime dari Odoo
    }
    
    // Step: Insert lot_ids ke shipping_manual_stuffing untuk picking yang state = "done"
    $inserted_manual = 0;
    $debug_info = [];
    
    error_log("=== Manual Stuffing Insert Process ===");
    error_log("Done pickings count: " . count($done_pickings));
    error_log("Done picking IDs: " . json_encode($done_pickings));
    error_log("Shipping ID: $shipping_id");
    
    if (!empty($done_pickings)) {
        // Ambil lot_ids langsung dari Odoo (realtime) untuk picking yang done
        $done_pickings_safe = array_map('intval', $done_pickings);
        $done_pickings_safe = array_filter($done_pickings_safe);
        
        if (!empty($done_pickings_safe)) {
            // Get pickings dari Odoo untuk ambil move_line_ids
            $pickings_odoo = callOdooRead($username, 'stock.picking', [['id', 'in', $done_pickings_safe]], ['id', 'move_line_ids_without_package']);
            
            $all_lot_names = [];
            
            if ($pickings_odoo && is_array($pickings_odoo)) {
                foreach ($pickings_odoo as $p_odoo) {
                    $move_line_ids = $p_odoo['move_line_ids_without_package'] ?? [];
                    
                    if (!empty($move_line_ids)) {
                        // Ambil data move_line langsung dari Odoo
                        $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name']);
                        
                        if ($move_lines && is_array($move_lines)) {
                            foreach ($move_lines as $line) {
                                $lot_name = null;
                                
                                // Try to get lot info from lot_id field
                                if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                                    $lot_name = $line['lot_id'][1];
                                }
                                // Fallback: try lot_name field
                                else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                                    $lot_name = $line['lot_name'];
                                }
                                
                                if ($lot_name !== null && $lot_name !== '') {
                                    $all_lot_names[] = trim($lot_name);
                                }
                            }
                        }
                    }
                }
            }
            
            // Remove duplicates
            $all_lot_names = array_unique($all_lot_names);
            error_log("Found " . count($all_lot_names) . " unique lot names from Odoo for done pickings");
            
            if (!empty($all_lot_names)) {
                foreach ($all_lot_names as $lot_name) {
                    if (empty($lot_name)) {
                        continue;
                    }
                    
                    error_log("Processing lot_name: $lot_name");
                    
                    // Check if already exists
                    $stmt_check = $conn->prepare("SELECT id FROM shipping_manual_stuffing WHERE id_shipping = ? AND production_code = ?");
                    $stmt_check->bind_param("is", $shipping_id, $lot_name);
                    $stmt_check->execute();
                    $check_result = $stmt_check->get_result();
                    $exists = $check_result->num_rows > 0;
                    $stmt_check->close();
                    
                    if ($exists) {
                        error_log("Lot already exists: $lot_name");
                        continue;
                    }
                    
                    // Insert to manual stuffing
                    $stmt_manual = $conn->prepare("INSERT INTO shipping_manual_stuffing (id_shipping, production_code, status) VALUES (?, ?, 1)");
                    $stmt_manual->bind_param("is", $shipping_id, $lot_name);
                    
                    if ($stmt_manual->execute()) {
                        $inserted_manual++;
                        error_log("✓ Successfully inserted: $lot_name (shipping_id: $shipping_id)");
                    } else {
                        error_log("✗ Failed to insert: $lot_name - Error: " . $stmt_manual->error);
                        $debug_info[] = "Error inserting $lot_name: " . $stmt_manual->error;
                    }
                    $stmt_manual->close();
                }
            } else {
                error_log("No lot names found from Odoo for done pickings");
                $debug_info[] = "Tidak ada lot_ids ditemukan dari Odoo untuk picking yang done";
            }
        } else {
            error_log("No valid picking IDs after sanitization");
            $debug_info[] = "Tidak ada picking ID yang valid";
        }
    } else {
        $all_states = [];
        foreach ($pickings as $p) {
            $all_states[] = $p['state'] ?? 'NULL';
        }
        error_log("No done pickings found. All picking states: " . json_encode($all_states));
        $debug_info[] = "Tidak ada picking dengan state 'done'. States yang ditemukan: " . implode(', ', array_unique($all_states));
    }
    
    error_log("Total inserted to manual stuffing: $inserted_manual");
    error_log("=== End Manual Stuffing Insert ===");
    
    $message = "Sinkron selesai! $updated_pickings picking dan lot/serial berhasil diperbarui";
    if ($inserted_manual > 0) {
        $message .= ". $inserted_manual lot/serial dari picking yang sudah done ditambahkan ke manual stuffing";
    } else if (!empty($done_pickings)) {
        $message .= ". Picking yang done ditemukan (" . count($done_pickings) . ") tapi tidak ada lot_ids untuk diinsert";
        if (!empty($debug_info)) {
            $message .= " - " . implode(", ", $debug_info);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'updated_pickings' => $updated_pickings,
        'inserted_manual' => $inserted_manual,
        'done_pickings_count' => count($done_pickings),
        'done_picking_ids' => $done_pickings,
        'debug_info' => $debug_info,
        'shipping_name' => $batch_name
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>

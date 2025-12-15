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
    
    // Get pickings dengan move_line_ids
    $pickings = callOdooRead($username, 'stock.picking', [['id', 'in', $picking_ids]], ['name', 'sale_id', 'origin', 'group_id', 'move_line_ids_without_package']);
    
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
        
        $updated_pickings++;
        
        // Get lot_ids from stock.move.line
        $move_line_ids = $picking['move_line_ids_without_package'] ?? [];
        if (!empty($move_line_ids)) {
            // Delete existing lot_ids for this picking
            $stmt_del_lot = $conn->prepare("DELETE FROM shipping_lot_ids WHERE picking_id = ?");
            $stmt_del_lot->bind_param("i", $picking_id);
            $stmt_del_lot->execute();
            $stmt_del_lot->close();
            
            // Ambil data move_line dari Odoo
            $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name', 'product_id', 'qty_done', 'state']);
            
            if ($move_lines && is_array($move_lines)) {
                foreach ($move_lines as $line) {
                    $lot_id = null;
                    $lot_name = null;
                    $product_id = null;
                    $product_name = null;
                    $qty_done = $line['qty_done'] ?? 0;
                    
                    // Try to get lot info from lot_id field
                    if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                        $lot_id = $line['lot_id'][0];
                        $lot_name = $line['lot_id'][1];
                    }
                    // Fallback: try lot_name field
                    else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                        $lot_name = $line['lot_name'];
                    }
                    
                    // Extract product_id and product_name
                    if (isset($line['product_id']) && is_array($line['product_id']) && count($line['product_id']) >= 2) {
                        $product_id = $line['product_id'][0];
                        $product_name = $line['product_id'][1];
                    }
                    
                    // Insert lot_ids data
                    if ($lot_name !== null && $lot_name !== '') {
                        $stmt_lot = $conn->prepare("INSERT INTO shipping_lot_ids (picking_id, picking_name, lot_id, lot_name, product_id, product_name, qty_done) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt_lot->bind_param("isisiss", $picking_id, $picking_name, $lot_id, $lot_name, $product_id, $product_name, $qty_done);
                        $stmt_lot->execute();
                        $stmt_lot->close();
                    }
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Sinkron selesai! $updated_pickings picking dan lot/serial berhasil diperbarui",
        'updated_pickings' => $updated_pickings,
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

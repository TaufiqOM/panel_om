<?php
session_start();
require __DIR__ . '/../../inc/config.php';

header('Content-Type: application/json');

// Ambil data id dari POST
$shipping_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if (!$shipping_id) {
    echo json_encode(['success' => false, 'message' => 'Shipping ID tidak valid']);
    exit;
}

try {
    // Ambil data pickings untuk shipping ini
    $sql_pickings = "SELECT id, name, client_order_ref FROM shipping_detail WHERE id_shipping = ? ORDER BY name";
    $stmt_pickings = $conn->prepare($sql_pickings);
    $stmt_pickings->bind_param("i", $shipping_id);
    $stmt_pickings->execute();
    $result_pickings = $stmt_pickings->get_result();
    
    $comparison_data = [];
    
    while ($picking = $result_pickings->fetch_assoc()) {
        $picking_id = $picking['id'];
        $picking_name = $picking['name'];
        
        // Ambil lot_ids dari Odoo untuk picking ini
        $sql_lots = "SELECT lot_name, product_name, qty_done FROM shipping_lot_ids WHERE picking_id = ? ORDER BY lot_name";
        $stmt_lots = $conn->prepare($sql_lots);
        $stmt_lots->bind_param("i", $picking_id);
        $stmt_lots->execute();
        $result_lots = $stmt_lots->get_result();
        
        $odoo_lots = [];
        while ($lot = $result_lots->fetch_assoc()) {
            $odoo_lots[] = [
                'lot_name' => $lot['lot_name'],
                'product_name' => $lot['product_name'],
                'qty_done' => $lot['qty_done']
            ];
        }
        $stmt_lots->close();
        
        // Ambil production_code dari shipping_manual_stuffing
        // Note: Kita perlu tau relasi antara picking dan manual stuffing
        // Asumsikan manual stuffing diinput untuk shipping_id tertentu
        $sql_manual = "SELECT DISTINCT production_code FROM shipping_manual_stuffing WHERE id_shipping = ? ORDER BY production_code";
        $stmt_manual = $conn->prepare($sql_manual);
        $stmt_manual->bind_param("i", $shipping_id);
        $stmt_manual->execute();
        $result_manual = $stmt_manual->get_result();
        
        $manual_codes = [];
        while ($manual = $result_manual->fetch_assoc()) {
            $manual_codes[] = $manual['production_code'];
        }
        $stmt_manual->close();
        
        // Bandingkan lot_names dengan production_codes
        $matched = [];
        $odoo_only = [];
        $manual_only = [];
        
        // Convert to arrays for comparison
        $odoo_lot_names = array_column($odoo_lots, 'lot_name');
        
        // Find matches
        foreach ($odoo_lots as $lot) {
            if (in_array($lot['lot_name'], $manual_codes)) {
                $matched[] = [
                    'code' => $lot['lot_name'],
                    'product' => $lot['product_name'],
                    'qty' => $lot['qty_done'],
                    'source' => 'both'
                ];
            } else {
                $odoo_only[] = [
                    'code' => $lot['lot_name'],
                    'product' => $lot['product_name'],
                    'qty' => $lot['qty_done'],
                    'source' => 'odoo'
                ];
            }
        }
        
        // Find manual-only codes
        foreach ($manual_codes as $code) {
            if (!in_array($code, $odoo_lot_names)) {
                $manual_only[] = [
                    'code' => $code,
                    'product' => '-',
                    'qty' => '-',
                    'source' => 'manual'
                ];
            }
        }
        
        $comparison_data[] = [
            'picking_id' => $picking_id,
            'picking_name' => $picking_name,
            'po' => $picking['client_order_ref'],
            'matched' => $matched,
            'odoo_only' => $odoo_only,
            'manual_only' => $manual_only,
            'total_odoo' => count($odoo_lots),
            'total_manual' => count($manual_codes),
            'total_matched' => count($matched),
            'has_matched' => count($matched) > 0
        ];
    }
    
    $stmt_pickings->close();
    
    echo json_encode([
        'success' => true,
        'data' => $comparison_data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>

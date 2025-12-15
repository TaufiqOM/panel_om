<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

session_start();
require __DIR__ . '/../../inc/config.php';
require __DIR__ . '/../../inc/config_odoo.php';

$username = $_SESSION['username'] ?? '';

// Ambil data dari POST
$shipping_id = isset($_POST['shipping_id']) ? intval($_POST['shipping_id']) : 0;
$picking_id = isset($_POST['picking_id']) ? intval($_POST['picking_id']) : 0;

if (!$shipping_id || !$picking_id) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

try {
    // Ambil scheduled_date dari shipping
    $sql_shipping = "SELECT sheduled_date FROM shipping WHERE id = ?";
    $stmt_shipping = $conn->prepare($sql_shipping);
    $stmt_shipping->bind_param("i", $shipping_id);
    $stmt_shipping->execute();
    $result_shipping = $stmt_shipping->get_result();
    
    if ($result_shipping->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Data shipping tidak ditemukan']);
        exit;
    }
    
    $shipping_data = $result_shipping->fetch_assoc();
    $scheduled_date = $shipping_data['sheduled_date'];
    $stmt_shipping->close();
    
    // Format tanggal untuk Odoo (YYYY-MM-DD HH:MM:SS)
    $odoo_scheduled_date = date('Y-m-d H:i:s', strtotime($scheduled_date));
    
    // Ambil lot_ids yang matched untuk picking ini
    $sql_lots = "SELECT lot_id, lot_name, product_id FROM shipping_lot_ids WHERE picking_id = ?";
    $stmt_lots = $conn->prepare($sql_lots);
    $stmt_lots->bind_param("i", $picking_id);
    $stmt_lots->execute();
    $result_lots = $stmt_lots->get_result();
    
    $odoo_lots = [];
    while ($lot = $result_lots->fetch_assoc()) {
        $odoo_lots[$lot['lot_name']] = [
            'lot_id' => $lot['lot_id'],
            'product_id' => $lot['product_id']
        ];
    }
    $stmt_lots->close();
    
    // Ambil production_code yang matched dari manual stuffing
    $sql_manual = "SELECT DISTINCT production_code FROM shipping_manual_stuffing WHERE id_shipping = ?";
    $stmt_manual = $conn->prepare($sql_manual);
    $stmt_manual->bind_param("i", $shipping_id);
    $stmt_manual->execute();
    $result_manual = $stmt_manual->get_result();
    
    $manual_codes = [];
    while ($manual = $result_manual->fetch_assoc()) {
        $manual_codes[] = $manual['production_code'];
    }
    $stmt_manual->close();
    
    // Find matched lots (yang ada di kedua sistem)
    $matched_lots = [];
    foreach ($manual_codes as $code) {
        if (isset($odoo_lots[$code])) {
            $matched_lots[] = [
                'lot_name' => $code,
                'lot_id' => $odoo_lots[$code]['lot_id'],
                'product_id' => $odoo_lots[$code]['product_id']
            ];
        }
    }
    
    if (empty($matched_lots)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Tidak ada lot/serial yang cocok untuk diproses'
        ]);
        exit;
    }
    
    // Step 1: Update scheduled_date di stock.picking
    error_log("Updating scheduled_date for picking_id: $picking_id to: $odoo_scheduled_date");
    
    $date_update_result = callOdooWrite(
        $username,
        'stock.picking',
        [$picking_id],
        ['scheduled_date' => $odoo_scheduled_date]
    );
    
    if ($date_update_result === false) {
        error_log("Failed to update scheduled_date for picking_id: $picking_id");
    }
    
    // Step 2: Get stock.move dari stock.picking
    $picking_data = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_ids_without_package', 'move_line_ids_without_package']);
    
    if (!$picking_data || empty($picking_data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Data picking tidak ditemukan di Odoo'
        ]);
        exit;
    }
    
    $move_ids = $picking_data[0]['move_ids_without_package'] ?? [];
    $move_line_ids = $picking_data[0]['move_line_ids_without_package'] ?? [];
    
    if (empty($move_ids)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada stock moves untuk picking ini'
        ]);
        exit;
    }
    
    // Step 3: Reset quantity_done di stock.move untuk clear lot assignments
    error_log("Resetting quantities for move_ids: " . json_encode($move_ids));
    
    foreach ($move_ids as $move_id) {
        $reset_result = callOdooWrite(
            $username,
            'stock.move',
            [$move_id],
            ['quantity_done' => 0]
        );
        
        if ($reset_result === false) {
            error_log("Failed to reset move_id: $move_id");
        }
    }
    
    // Step 4: Delete existing move lines (akan dibuat ulang otomatis)
    if (!empty($move_line_ids)) {
        error_log("Deleting move_line_ids: " . json_encode($move_line_ids));
        
        // Unlink (delete) move lines
        $delete_params = [
            "jsonrpc" => "2.0",
            "method" => "call",
            "params" => [
                "service" => "object",
                "method" => "execute_kw",
                "args" => [
                    odooConnectionInfo($username)['db'],
                    odooConnectionInfo($username)['uid'],
                    odooConnectionInfo($username)['password'],
                    'stock.move.line',
                    'unlink',
                    [$move_line_ids]
                ]
            ],
            "id" => 1
        ];
        
        $connInfo = odooConnectionInfo($username);
        $curl = curl_init($connInfo['url']);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($delete_params),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        error_log("Delete move lines response: " . $response);
    }
    
    // Step 5: Get move data untuk create move lines dengan lot_ids yang matched
    $moves_data = callOdooRead($username, 'stock.move', [['id', 'in', $move_ids]], ['id', 'product_id', 'product_uom_qty', 'location_id', 'location_dest_id', 'picking_id']);
    
    // Step 6: Create move lines dengan lot_ids yang matched
    $processed = 0;
    $errors = [];
    
    foreach ($moves_data as $move) {
        $move_id = $move['id'];
        $product_id = is_array($move['product_id']) ? $move['product_id'][0] : $move['product_id'];
        
        // Find matched lots untuk product ini
        $lots_for_product = [];
        foreach ($matched_lots as $lot) {
            if ($lot['product_id'] == $product_id) {
                $lots_for_product[] = $lot;
            }
        }
        
        if (empty($lots_for_product)) {
            continue;
        }
        
        // Create move line untuk setiap matched lot
        foreach ($lots_for_product as $lot) {
            $move_line_data = [
                'move_id' => $move_id,
                'picking_id' => $picking_id,
                'product_id' => $product_id,
                'lot_id' => $lot['lot_id'],
                'qty_done' => 1, // Atau sesuai kebutuhan
                'product_uom_id' => 1, // Unit of measure - biasanya 1 untuk 'Units'
                'location_id' => is_array($move['location_id']) ? $move['location_id'][0] : $move['location_id'],
                'location_dest_id' => is_array($move['location_dest_id']) ? $move['location_dest_id'][0] : $move['location_dest_id']
            ];
            
            $create_result = callOdooCreate($username, 'stock.move.line', $move_line_data);
            
            if ($create_result !== false) {
                $processed++;
                error_log("Created move line for lot: " . $lot['lot_name']);
            } else {
                $errors[] = "Gagal create move line untuk lot: " . $lot['lot_name'];
                error_log("Failed to create move line for lot: " . $lot['lot_name']);
            }
        }
    }
    
    if ($processed > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Berhasil! Update tanggal, reset lot lama dan insert $processed lot/serial yang cocok ke Odoo",
            'processed' => $processed,
            'total_matched' => count($matched_lots),
            'scheduled_date' => date('d M Y', strtotime($scheduled_date)),
            'steps' => [
                'date_update' => 'Tanggal kirim diupdate',
                'reset' => 'Quantity direset',
                'delete' => 'Lot lama dihapus', 
                'insert' => "$processed lot baru diinsert"
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada barcode yang berhasil diproses. ' . implode(', ', $errors),
            'errors' => $errors
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>

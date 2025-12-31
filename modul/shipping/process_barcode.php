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
$sale_id = isset($_POST['sale_id']) ? intval($_POST['sale_id']) : 0;

if (!$shipping_id) {
    echo json_encode(['success' => false, 'message' => 'Shipping ID tidak lengkap']);
    exit;
}

// Jika sale_id diberikan, gunakan sale_id. Jika tidak, gunakan picking_id (backward compatibility)
$target_sale_id = null;
$picking_id_for_log = null;

if ($sale_id && $sale_id > 0) {
    // Mode baru: menggunakan sale_id langsung
    $target_sale_id = $sale_id;
    error_log("Using sale_id directly: $target_sale_id");
} else if ($picking_id && $picking_id > 0) {
    // Mode lama: ambil sale_id dari picking_id
    $picking_id_for_log = $picking_id;
    
    // Ambil sale_id dari shipping_detail untuk picking ini
    $sql_picking_detail = "SELECT sale_id FROM shipping_detail WHERE id = ? AND id_shipping = ?";
    $stmt_picking_detail = $conn->prepare($sql_picking_detail);
    $stmt_picking_detail->bind_param("ii", $picking_id, $shipping_id);
    $stmt_picking_detail->execute();
    $result_picking_detail = $stmt_picking_detail->get_result();
    
    if ($result_picking_detail->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Data picking detail tidak ditemukan']);
        exit;
    }
    
    $picking_detail = $result_picking_detail->fetch_assoc();
    $picking_sale_id = $picking_detail['sale_id'];
    $stmt_picking_detail->close();
    
    error_log("Picking ID: $picking_id, Sale ID from DB: " . ($picking_sale_id ?? 'NULL'));
    
    // Ambil sale_id dari stock.picking di Odoo untuk validasi
    $picking_odoo_data = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['sale_id']);
    $odoo_sale_id = null;
    if ($picking_odoo_data && !empty($picking_odoo_data)) {
        if (isset($picking_odoo_data[0]['sale_id']) && is_array($picking_odoo_data[0]['sale_id']) && count($picking_odoo_data[0]['sale_id']) > 0) {
            $odoo_sale_id = $picking_odoo_data[0]['sale_id'][0];
        }
    }
    
    error_log("Odoo Sale ID: " . ($odoo_sale_id ?? 'NULL'));
    
    // Gunakan sale_id dari Odoo jika ada, jika tidak gunakan dari database lokal
    $target_sale_id = $odoo_sale_id ?? $picking_sale_id;
} else {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap: perlu sale_id atau picking_id']);
    exit;
}

if (!$target_sale_id) {
    echo json_encode([
        'success' => false, 
        'message' => 'Tidak dapat menemukan sale_id. Tidak dapat memproses barcode.'
    ]);
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
    
    error_log("Target Sale ID: $target_sale_id, Shipping ID: $shipping_id");
    
    // Ambil semua picking yang punya sale_id sama di shipping ini
    $sql_same_sale_pickings = "SELECT id FROM shipping_detail WHERE id_shipping = ? AND sale_id = ?";
    $stmt_same_sale = $conn->prepare($sql_same_sale_pickings);
    $stmt_same_sale->bind_param("ii", $shipping_id, $target_sale_id);
    $stmt_same_sale->execute();
    $result_same_sale = $stmt_same_sale->get_result();
    
    $same_sale_picking_ids = [];
    while ($row = $result_same_sale->fetch_assoc()) {
        $same_sale_picking_ids[] = $row['id'];
    }
    $stmt_same_sale->close();
    
    error_log("Pickings dengan sale_id $target_sale_id: " . json_encode($same_sale_picking_ids));
    
    // Ambil lot_ids langsung dari Odoo (realtime) untuk semua picking yang punya sale_id sama
    if (empty($same_sale_picking_ids)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Tidak ada picking lain dengan sale_id yang sama'
        ]);
        exit;
    }
    
    // Get all pickings dari Odoo untuk ambil move_line_ids
    $all_picking_ids = array_unique(array_merge([$picking_id], $same_sale_picking_ids));
    $pickings_odoo = callOdooRead($username, 'stock.picking', [['id', 'in', $all_picking_ids]], ['id', 'move_line_ids_without_package']);
    
    $odoo_lots = [];
    
    if ($pickings_odoo && is_array($pickings_odoo)) {
        foreach ($pickings_odoo as $p_odoo) {
            $p_odoo_id = $p_odoo['id'];
            $move_line_ids = $p_odoo['move_line_ids_without_package'] ?? [];
            
            if (!empty($move_line_ids)) {
                // Ambil data move_line langsung dari Odoo
                $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name', 'product_id', 'qty_done']);
                
                if ($move_lines && is_array($move_lines)) {
                    foreach ($move_lines as $line) {
                        $lot_id = null;
                        $lot_name = null;
                        $product_id = null;
                        
                        // Try to get lot info from lot_id field
                        if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                            $lot_id = $line['lot_id'][0];
                            $lot_name = $line['lot_id'][1];
                        }
                        // Fallback: try lot_name field
                        else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                            $lot_name = $line['lot_name'];
                        }
                        
                        // Extract product_id
                        if (isset($line['product_id']) && is_array($line['product_id']) && count($line['product_id']) >= 2) {
                            $product_id = $line['product_id'][0];
                        }
                        
                        if ($lot_name !== null && $lot_name !== '') {
                            $odoo_lots[$lot_name] = [
                                'lot_id' => $lot_id,
                                'product_id' => $product_id,
                                'picking_id' => $p_odoo_id
                            ];
                        }
                    }
                }
            }
        }
    }
    
    // Ambil production_code yang matched dari manual stuffing untuk shipping ini
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
    
    // Find matched lots (yang ada di kedua sistem) - hanya untuk picking yang sale_id sama
    // Perhatikan: satu lot_name bisa punya product_id berbeda, jadi kita perlu group by lot_name+product_id
    $matched_lots = [];
    
    // Buat map: lot_name -> array of lots (karena bisa ada multiple product_id untuk same lot_name)
    $odoo_lots_by_name = [];
    foreach ($odoo_lots as $lot_name => $lot_data) {
        if (!isset($odoo_lots_by_name[$lot_name])) {
            $odoo_lots_by_name[$lot_name] = [];
        }
        // Hanya ambil lot yang dari picking dengan sale_id yang sama
        if (in_array($lot_data['picking_id'], $same_sale_picking_ids)) {
            $odoo_lots_by_name[$lot_name][] = $lot_data;
        }
    }
    
    // Match dengan manual codes
    foreach ($manual_codes as $code) {
        if (isset($odoo_lots_by_name[$code])) {
            // Ambil semua lot dengan lot_name ini (bisa punya product_id berbeda)
            foreach ($odoo_lots_by_name[$code] as $lot_data) {
                $matched_lots[] = [
                    'lot_name' => $code,
                    'lot_id' => $lot_data['lot_id'],
                    'product_id' => $lot_data['product_id'],
                    'picking_id' => $lot_data['picking_id']
                ];
            }
        }
    }
    
    if (empty($matched_lots)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Tidak ada lot/serial yang cocok untuk diproses'
        ]);
        exit;
    }
    
    // Step 1: Get stock.move dari semua picking yang punya sale_id sama
    $all_picking_ids = array_unique(array_merge([$picking_id], $same_sale_picking_ids));
    $picking_data = callOdooRead($username, 'stock.picking', [['id', 'in', $all_picking_ids]], ['id', 'move_ids_without_package', 'move_line_ids_without_package', 'sale_id']);
    
    if (!$picking_data || empty($picking_data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Data picking tidak ditemukan di Odoo'
        ]);
        exit;
    }
    
    // Group pickings by sale_id untuk validasi
    $pickings_by_sale = [];
    $all_move_ids = [];
    $all_move_line_ids = [];
    
    foreach ($picking_data as $p) {
        $p_id = $p['id'];
        $p_sale_id = null;
        if (isset($p['sale_id']) && is_array($p['sale_id']) && count($p['sale_id']) > 0) {
            $p_sale_id = $p['sale_id'][0];
        }
        
        // Hanya proses picking yang sale_id sama dengan target
        if ($p_sale_id == $target_sale_id) {
            $pickings_by_sale[$p_id] = [
                'move_ids' => $p['move_ids_without_package'] ?? [],
                'move_line_ids' => $p['move_line_ids_without_package'] ?? []
            ];
            
            if (!empty($p['move_ids_without_package'])) {
                $all_move_ids = array_merge($all_move_ids, $p['move_ids_without_package']);
            }
            if (!empty($p['move_line_ids_without_package'])) {
                $all_move_line_ids = array_merge($all_move_line_ids, $p['move_line_ids_without_package']);
            }
        }
    }
    
    if (empty($all_move_ids)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada stock moves untuk picking dengan sale_id yang sama'
        ]);
        exit;
    }
    
    // Step 3: Reset quantity_done di stock.move untuk clear lot assignments (semua picking dengan sale_id sama)
    error_log("Resetting quantities for move_ids: " . json_encode($all_move_ids));
    
    foreach ($all_move_ids as $move_id) {
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
    
    // Step 4: Delete existing move lines dari semua picking dengan sale_id sama
    if (!empty($all_move_line_ids)) {
        error_log("Deleting move_line_ids: " . json_encode($all_move_line_ids));
        
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
                    [$all_move_line_ids]
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
    
    // Step 5: Get move data untuk semua picking dengan sale_id sama
    $moves_data = callOdooRead($username, 'stock.move', [['id', 'in', $all_move_ids]], ['id', 'product_id', 'product_uom_qty', 'location_id', 'location_dest_id', 'picking_id']);
    
    // Step 6: Create move lines dengan lot_ids yang matched - sesuai dengan picking masing-masing
    $processed = 0;
    $errors = [];
    
    // Track move_id yang perlu di-uncheck picked setelah create move line
    // Key: move_id, Value: true jika perlu di-uncheck
    $moves_to_uncheck_picked = [];
    
    // Group matched_lots by picking_id
    $matched_lots_by_picking = [];
    foreach ($matched_lots as $lot) {
        $lot_picking_id = $lot['picking_id'];
        if (!isset($matched_lots_by_picking[$lot_picking_id])) {
            $matched_lots_by_picking[$lot_picking_id] = [];
        }
        $matched_lots_by_picking[$lot_picking_id][] = $lot;
    }
    
    // Process each picking
    foreach ($pickings_by_sale as $process_picking_id => $picking_info) {
        // Update scheduled_date untuk picking ini
        $date_update_result = callOdooWrite(
            $username,
            'stock.picking',
            [$process_picking_id],
            ['scheduled_date' => $odoo_scheduled_date]
        );
        
        // Get moves untuk picking ini
        $picking_move_ids = $picking_info['move_ids'];
        if (empty($picking_move_ids)) {
            continue;
        }
        
        // Filter moves untuk picking ini
        $picking_moves = array_filter($moves_data, function($move) use ($process_picking_id) {
            $move_picking_id = is_array($move['picking_id']) ? $move['picking_id'][0] : $move['picking_id'];
            return $move_picking_id == $process_picking_id;
        });
        
        // Get matched lots untuk picking ini
        $picking_matched_lots = $matched_lots_by_picking[$process_picking_id] ?? [];
        
        if (empty($picking_matched_lots)) {
            error_log("No matched lots for picking_id: $process_picking_id");
            continue;
        }
        
        // Create move lines untuk picking ini
        foreach ($picking_moves as $move) {
            $move_id = $move['id'];
            $product_id = is_array($move['product_id']) ? $move['product_id'][0] : $move['product_id'];
            
            // Find matched lots untuk product ini di picking ini
            $lots_for_product = [];
            foreach ($picking_matched_lots as $lot) {
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
                    'picking_id' => $process_picking_id,
                    'product_id' => $product_id,
                    'lot_id' => $lot['lot_id'],
                    'qty_done' => 1,
                    'product_uom_id' => 1,
                    'location_id' => is_array($move['location_id']) ? $move['location_id'][0] : $move['location_id'],
                    'location_dest_id' => is_array($move['location_dest_id']) ? $move['location_dest_id'][0] : $move['location_dest_id']
                ];
                
                $create_result = callOdooCreate($username, 'stock.move.line', $move_line_data);
                
                if ($create_result !== false) {
                    $processed++;
                    // Mark move_id untuk di-uncheck picked
                    $moves_to_uncheck_picked[$move_id] = true;
                    error_log("Created move line for lot: " . $lot['lot_name'] . " in picking: $process_picking_id");
                } else {
                    $errors[] = "Gagal create move line untuk lot: " . $lot['lot_name'] . " di picking: $process_picking_id";
                    error_log("Failed to create move line for lot: " . $lot['lot_name']);
                }
            }
        }
    }
    
    // Step 7: Uncheck picked untuk semua move_id yang sudah di-create move line-nya
    // Ini untuk mencegah picked otomatis terceklis oleh Odoo
    if (!empty($moves_to_uncheck_picked)) {
        $move_ids_to_uncheck = array_keys($moves_to_uncheck_picked);
        error_log("Unchecking picked untuk " . count($move_ids_to_uncheck) . " move_id: " . implode(', ', $move_ids_to_uncheck));
        
        $uncheck_result = callOdooWrite($username, 'stock.move', $move_ids_to_uncheck, ['picked' => false]);
        
        if ($uncheck_result !== false) {
            error_log("Berhasil uncheck picked untuk " . count($move_ids_to_uncheck) . " move_id");
        } else {
            $errors[] = "Gagal uncheck picked untuk beberapa move_id";
            error_log("Gagal uncheck picked untuk move_ids: " . implode(', ', $move_ids_to_uncheck));
        }
    }
    
    if ($processed > 0) {
        $processed_pickings = count($pickings_by_sale);
        echo json_encode([
            'success' => true,
            'message' => "Berhasil! Update tanggal, reset lot lama dan insert $processed lot/serial yang cocok ke Odoo untuk $processed_pickings picking dengan sale_id $target_sale_id",
            'processed' => $processed,
            'total_matched' => count($matched_lots),
            'processed_pickings' => $processed_pickings,
            'sale_id' => $target_sale_id,
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

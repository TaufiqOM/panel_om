<?php
session_start();

// File Conf
require __DIR__ . '/../../inc/config.php';
require __DIR__ . '/../../inc/config_odoo.php';

// Start Session
$username = $_SESSION['username'] ?? '';

// Ambil data id dari POST
$shipping_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if (!$shipping_id) {
  echo "Shipping ID tidak valid.";
  exit;
}

// Ambil detail shipping dari local database
$sql = "SELECT id, name, sheduled_date, description, ship_to FROM shipping WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $shipping_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Shipping tidak ditemukan.";
    exit;
}

$shipping = $result->fetch_assoc();
$stmt->close();

// Ambil batch dari Odoo
$batch_name = $shipping['name'];
$batches = callOdooRead($username, "stock.picking.batch", [["name", "=", $batch_name]], ["picking_ids"]);

$batch = $batches[0] ?? null;
$picking_ids = $batch['picking_ids'] ?? [];

// Ambil pickings dari Odoo
$pickings = [];
if (!empty($picking_ids)) {
    $pickings = callOdooRead($username, "stock.picking", [["id", "in", $picking_ids]], ["id", "name", "sale_id"]);
}

$comparison_data = [];
$total_product_uom_qty = 0; // Total product_uom_qty dari semua stock.move

// Global barcode allocation tracker
// Key: barcode, Value: array with picking_id, product_id, sale_line_id where it's allocated
$globally_allocated_barcodes = [];

// ====================================================================================
// STEP 1: Kumpulkan semua data Odoo untuk semua picking
// ====================================================================================
$picking_data_map = []; // Key: picking_id, Value: array of picking data with moves

foreach ($pickings as $picking) {
    $picking_id = $picking['id'];
    $picking_name = $picking['name'] ?? '';
    $sale_id = is_array($picking['sale_id']) ? $picking['sale_id'][0] : ($picking['sale_id'] ?? 0);
    
    if (!$sale_id) {
        continue;
    }
    
    // Ambil sale.order
    $sale_order = callOdooRead($username, 'sale.order', [['id', '=', $sale_id]], ['client_order_ref', 'name']);
    if (!$sale_order || empty($sale_order)) {
        continue;
    }
    
    $client_order_ref = $sale_order[0]['client_order_ref'] ?? '';
    $so_name = $sale_order[0]['name'] ?? '';
    
    // Ambil move_ids
    $picking_full = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_ids']);
    $move_ids = $picking_full[0]['move_ids'] ?? [];
    
    if (empty($move_ids)) {
        continue;
    }
    
    // Ambil moves dengan sale_line_id dan product_uom_qty
    $moves = callOdooRead($username, 'stock.move', [['id', 'in', $move_ids]], ['id', 'product_id', 'product_uom_qty', 'sale_line_id', 'move_line_ids']);
    
    if (!$moves || !is_array($moves)) {
        continue;
    }
    
    // Store picking data
    $picking_data_map[$picking_id] = [
        'picking_id' => $picking_id,
        'picking_name' => $picking_name,
        'sale_id' => $sale_id,
        'client_order_ref' => $client_order_ref,
        'so_name' => $so_name,
        'moves' => []
    ];
    
    // Process each move
    foreach ($moves as $move) {
        $move_id = $move['id'];
        $product_id = is_array($move['product_id']) ? $move['product_id'][0] : null;
        $product_name = is_array($move['product_id']) ? $move['product_id'][1] : 'N/A';
        $sale_line_id = is_array($move['sale_line_id']) ? $move['sale_line_id'][0] : null;
        $product_uom_qty = intval($move['product_uom_qty'] ?? 0);
        $move_line_ids = $move['move_line_ids'] ?? [];

        if (!$product_id || !$sale_line_id) {
            continue;
        }

        // Ambil default_code dari product
        $product_data = callOdooRead($username, 'product.product', [['id', '=', $product_id]], ['default_code', 'name']);
        $default_code = '##';
        if ($product_data && !empty($product_data)) {
            $default_code = $product_data[0]['default_code'] ?? '##';
            if (empty($product_name) || $product_name == 'N/A') {
                $product_name = $product_data[0]['name'] ?? $product_name;
            }
        }

        // Ambil barcode dari Odoo (stock.move.line dengan lot_name)
        $odoo_barcodes = [];
        if (!empty($move_line_ids)) {
            $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name']);
            
            if ($move_lines && is_array($move_lines)) {
                foreach ($move_lines as $line) {
                    $lot_name = null;
                    
                    if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                        $lot_name = $line['lot_id'][1];
                    } else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                        $lot_name = $line['lot_name'];
                    }
                    
                    if ($lot_name && !empty($lot_name)) {
                        $odoo_barcodes[] = $lot_name;
                    }
                }
            }
        }

        // Ambil barcode dari shipping_manual_stuffing
        $all_potential_barcodes = [];
        
        // Method 1: Cari dari production_lots_strg
        $sql_strg = "SELECT DISTINCT sms.production_code
                    FROM shipping_manual_stuffing sms
                    INNER JOIN production_lots_strg pls ON pls.production_code = sms.production_code
                    WHERE sms.id_shipping = ?
                    AND pls.sale_order_id = ?
                    AND pls.product_code = ?
                    AND pls.sale_order_line_id = ?
                    ORDER BY sms.production_code";
        
        $stmt_strg = $conn->prepare($sql_strg);
        if ($stmt_strg) {
            $stmt_strg->bind_param("iiii", $shipping_id, $sale_id, $product_id, $sale_line_id);
            $stmt_strg->execute();
            $result_strg = $stmt_strg->get_result();
            
            while ($row = $result_strg->fetch_assoc()) {
                $all_potential_barcodes[] = $row['production_code'];
            }
            $stmt_strg->close();
        }
        
        // Method 2: Jika tidak ada di strg, cari dari barcode_item
        if (empty($all_potential_barcodes)) {
            $sql_bi = "SELECT DISTINCT sms.production_code
                      FROM shipping_manual_stuffing sms
                      INNER JOIN barcode_item bi ON bi.barcode = sms.production_code
                      WHERE sms.id_shipping = ?
                      AND bi.sale_order_id = ?
                      AND bi.product_id = ?
                      AND bi.sale_order_line_id = ?
                      ORDER BY sms.production_code";
            
            $stmt_bi = $conn->prepare($sql_bi);
            if ($stmt_bi) {
                $stmt_bi->bind_param("iiii", $shipping_id, $sale_id, $product_id, $sale_line_id);
                $stmt_bi->execute();
                $result_bi = $stmt_bi->get_result();
                
                while ($row = $result_bi->fetch_assoc()) {
                    $all_potential_barcodes[] = $row['production_code'];
                }
                $stmt_bi->close();
            }
        }
        
        // Add total product_uom_qty
        $total_product_uom_qty += $product_uom_qty;
        
        // Store move data
        $picking_data_map[$picking_id]['moves'][] = [
            'move_id' => $move_id,
            'product_id' => $product_id,
            'product_name' => $product_name,
            'default_code' => $default_code,
            'sale_line_id' => $sale_line_id,
            'product_uom_qty' => $product_uom_qty,
            'move_line_ids' => $move_line_ids,
            'odoo_barcodes' => $odoo_barcodes,
            'all_potential_barcodes' => $all_potential_barcodes,
            'scanned_barcodes' => [] // Will be filled in allocation passes
        ];
    }
}

// ====================================================================================
// STEP 2: PASS 1 - Priority 1 allocation untuk SEMUA picking
// Alokasikan barcode yang SUDAH ADA di Odoo untuk setiap picking
// ====================================================================================
foreach ($picking_data_map as $picking_id => &$picking_data) {
    foreach ($picking_data['moves'] as &$move_data) {
        $odoo_barcodes = $move_data['odoo_barcodes'];
        $all_potential_barcodes = $move_data['all_potential_barcodes'];
        
        $allocated_count = 0;
        foreach ($all_potential_barcodes as $barcode) {
            // PRIORITY 1: Barcode yang ada di Odoo HARUS dialokasikan
            if (in_array($barcode, $odoo_barcodes)) {
                $move_data['scanned_barcodes'][] = $barcode;
                
                // Mark globally allocated
                $globally_allocated_barcodes[$barcode] = [
                    'picking_id' => $picking_id,
                    'product_id' => $move_data['product_id'],
                    'sale_line_id' => $move_data['sale_line_id']
                ];
                $allocated_count++;
            }
        }
        
        $move_data['allocated_count'] = $allocated_count;
    }
}
unset($picking_data, $move_data); // Break references

// ====================================================================================
// STEP 3: PASS 2 - Priority 2 allocation untuk SEMUA picking
// Alokasikan barcode baru sampai mencapai product_uom_qty
// ====================================================================================
foreach ($picking_data_map as $picking_id => &$picking_data) {
    foreach ($picking_data['moves'] as &$move_data) {
        $all_potential_barcodes = $move_data['all_potential_barcodes'];
        $product_uom_qty = $move_data['product_uom_qty'];
        $allocated_count = $move_data['allocated_count'];
        
        foreach ($all_potential_barcodes as $barcode) {
            // Skip if already allocated
            if (isset($globally_allocated_barcodes[$barcode])) {
                continue;
            }
            
            // Limit to product_uom_qty
            if ($allocated_count >= $product_uom_qty) {
                break;
            }
            
            // Allocate barcode
            $move_data['scanned_barcodes'][] = $barcode;
            $globally_allocated_barcodes[$barcode] = [
                'picking_id' => $picking_id,
                'product_id' => $move_data['product_id'],
                'sale_line_id' => $move_data['sale_line_id']
            ];
            $allocated_count++;
        }
        
        $move_data['allocated_count'] = $allocated_count;
    }
}
unset($picking_data, $move_data); // Break references

// ====================================================================================
// STEP 4: Build comparison_data dari picking_data_map
// ====================================================================================
foreach ($picking_data_map as $picking_id => $picking_data) {
    $products = [];
    
    foreach ($picking_data['moves'] as $move_data) {
        $scanned_barcodes = $move_data['scanned_barcodes'];
        $odoo_barcodes = $move_data['odoo_barcodes'];
        $move_line_ids = $move_data['move_line_ids'];
        
        // Ambil move_line_ids untuk sinkronisasi
        $move_line_ids_for_sync = [];
        if (!empty($move_line_ids)) {
            $move_lines_for_sync = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['id', 'lot_id', 'lot_name']);
            if ($move_lines_for_sync && is_array($move_lines_for_sync)) {
                foreach ($move_lines_for_sync as $line) {
                    $line_lot_name = null;
                    if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                        $line_lot_name = $line['lot_id'][1];
                    } else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                        $line_lot_name = $line['lot_name'];
                    }
                    if ($line_lot_name) {
                        $move_line_ids_for_sync[] = [
                            'id' => $line['id'],
                            'lot_name' => $line_lot_name
                        ];
                    }
                }
            }
        }
        
        // Bandingkan barcode
        $matched_barcodes = array_intersect($odoo_barcodes, $scanned_barcodes);
        $odoo_only = array_diff($odoo_barcodes, $scanned_barcodes);
        $scanned_only = array_diff($scanned_barcodes, $odoo_barcodes);
        
        // Ambil move_line_ids yang perlu dihapus
        $move_line_ids_to_delete = [];
        foreach ($move_line_ids_for_sync as $move_line) {
            if (in_array($move_line['lot_name'], $odoo_only)) {
                $move_line_ids_to_delete[] = $move_line['id'];
            }
        }
        
        $products[] = [
            'product_id' => $move_data['product_id'],
            'product_name' => $move_data['product_name'],
            'default_code' => $move_data['default_code'],
            'sale_line_id' => $move_data['sale_line_id'],
            'move_id' => $move_data['move_id'],
            'picking_id' => $picking_id,
            'product_uom_qty' => $move_data['product_uom_qty'],
            'odoo_barcodes' => $odoo_barcodes,
            'scanned_barcodes' => $scanned_barcodes,
            'matched_barcodes' => array_values($matched_barcodes),
            'odoo_only' => array_values($odoo_only),
            'scanned_only' => array_values($scanned_only),
            'move_line_ids_to_delete' => $move_line_ids_to_delete,
            'is_match' => !empty($matched_barcodes) && empty($odoo_only) && empty($scanned_only)
        ];
    }
    
    $comparison_data[] = [
        'picking_id' => $picking_id,
        'picking_name' => $picking_data['picking_name'],
        'sale_id' => $picking_data['sale_id'],
        'client_order_ref' => $picking_data['client_order_ref'],
        'so_name' => $picking_data['so_name'],
        'products' => $products
    ];
}

// Ambil semua barcode yang sudah di-scan untuk shipping ini
$all_scanned_barcodes = [];
$total_scanned_barcodes = 0;
$sql_all_scanned = "SELECT DISTINCT production_code FROM shipping_manual_stuffing WHERE id_shipping = ?";
$stmt_all_scanned = $conn->prepare($sql_all_scanned);
if ($stmt_all_scanned) {
    $stmt_all_scanned->bind_param("i", $shipping_id);
    $stmt_all_scanned->execute();
    $result_all_scanned = $stmt_all_scanned->get_result();
    while ($row = $result_all_scanned->fetch_assoc()) {
        $all_scanned_barcodes[] = $row['production_code'];
    }
    $total_scanned_barcodes = count($all_scanned_barcodes);
    $stmt_all_scanned->close();
}

// Kumpulkan semua barcode yang sudah ter-map ke picking/so
$mapped_barcodes = [];
$product_qty_map = []; // product_id => ['expected_qty' => int, 'scanned_count' => int]
foreach ($comparison_data as $picking) {
    foreach ($picking['products'] as $product) {
        $product_id = $product['product_id'];
        $sale_line_id = $product['sale_line_id'];
        
        // Hitung expected qty dari product_uom_qty di Odoo (bukan dari jumlah barcode)
        $expected_qty = intval($product['product_uom_qty'] ?? 0);
        
        // Hitung scanned count
        $scanned_count = count($product['scanned_barcodes']);
        
        // Simpan untuk cek kelebihan
        $key = $product_id . '_' . $sale_line_id;
        if (!isset($product_qty_map[$key])) {
            $product_qty_map[$key] = [
                'product_id' => $product_id,
                'product_name' => $product['product_name'],
                'default_code' => $product['default_code'] ?? '##',
                'sale_line_id' => $sale_line_id,
                'expected_qty' => $expected_qty,
                'scanned_count' => 0,
                'scanned_barcodes' => []
            ];
        }
        $product_qty_map[$key]['scanned_count'] += $scanned_count;
        $product_qty_map[$key]['scanned_barcodes'] = array_merge(
            $product_qty_map[$key]['scanned_barcodes'],
            $product['scanned_barcodes']
        );
        
        // Kumpulkan semua barcode yang ter-map
        foreach ($product['scanned_barcodes'] as $barcode) {
            $mapped_barcodes[$barcode] = true;
        }
    }
}

// Cari barcode yang tidak ter-map (belum ada picking/so)
$unmapped_barcodes = [];
foreach ($all_scanned_barcodes as $barcode) {
    if (!isset($mapped_barcodes[$barcode])) {
        // Cek apakah barcode ada di production_lots_strg atau barcode_item untuk info
        $barcode_info = null;
        
        // Cek di production_lots_strg
        $sql_info = "SELECT pls.sale_order_id, pls.product_code, pls.sale_order_line_id, pls.customer_name, pls.so_name, pls.sale_order_ref
                     FROM production_lots_strg pls
                     WHERE pls.production_code = ?
                     LIMIT 1";
        $stmt_info = $conn->prepare($sql_info);
        if ($stmt_info) {
            $stmt_info->bind_param("s", $barcode);
            $stmt_info->execute();
            $result_info = $stmt_info->get_result();
            if ($row_info = $result_info->fetch_assoc()) {
                $barcode_info = $row_info;
            }
            $stmt_info->close();
        }
        
        // Jika tidak ada di strg, cek di barcode_item
        if (!$barcode_info) {
            $sql_info2 = "SELECT bi.sale_order_id, bi.product_id, bi.sale_order_line_id
                         FROM barcode_item bi
                         WHERE bi.barcode = ?
                         LIMIT 1";
            $stmt_info2 = $conn->prepare($sql_info2);
            if ($stmt_info2) {
                $stmt_info2->bind_param("s", $barcode);
                $stmt_info2->execute();
                $result_info2 = $stmt_info2->get_result();
                if ($row_info2 = $result_info2->fetch_assoc()) {
                    $barcode_info = $row_info2;
                }
                $stmt_info2->close();
            }
        }
        
        $unmapped_barcodes[] = [
            'barcode' => $barcode,
            'info' => $barcode_info
        ];
    }
}

// Cari barcode yang melebihi jumlah product
$excess_barcodes = [];
foreach ($product_qty_map as $key => $data) {
    if ($data['scanned_count'] > $data['expected_qty']) {
        $excess = $data['scanned_count'] - $data['expected_qty'];
        // Ambil barcode yang melebihi (ambil yang terakhir sesuai urutan scan)
        $excess_barcode_list = array_slice($data['scanned_barcodes'], $data['expected_qty']);
        
        $excess_barcodes[] = [
            'product_id' => $data['product_id'],
            'product_name' => $data['product_name'],
            'default_code' => $data['default_code'] ?? '##',
            'sale_line_id' => $data['sale_line_id'],
            'expected_qty' => $data['expected_qty'],
            'scanned_count' => $data['scanned_count'],
            'excess_count' => $excess,
            'excess_barcodes' => $excess_barcode_list
        ];
    }
}

?>
<div class="card shadow-sm">
  <div class="card-header bg-light py-2 px-3">
    <div class="d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <i class="ki-duotone ki-arrows-circle fs-3 text-primary me-2">
          <span class="path1"></span>
          <span class="path2"></span>
        </i>
        <div>
          <h5 class="mb-0">Perbandingan Data Manual Stuffing</h5>
          <small class="text-muted">Bandingkan Barcode yang di Scan dengan Odoo</small>
        </div>
      </div>
    </div>
  </div>
  
  <div class="card-body p-3">
    <!-- Shipping Info - Compact -->
    <div class="border rounded p-2 mb-3" style="background-color: #f8f9fa; border-color: #dee2e6 !important;">
      <div class="row">
        <div class="col-md-6">
          <small class="text-muted">Nama Batch:</small>
          <div class="fw-bold"><?= htmlspecialchars($shipping['name']) ?></div>
        </div>
        <div class="col-md-6">
          <small class="text-muted">Tanggal Terjadwal:</small>
          <div class="fw-bold"><?= $shipping['sheduled_date'] ? date('Y-m-d', strtotime($shipping['sheduled_date'])) : '-' ?></div>
        </div>
      </div>
    </div>
    
    <!-- Info Total Barcode yang Terscan dan Jumlah Barcode Produk yang Direncanakan -->
    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <div class="py-2 px-3 d-flex align-items-center justify-content-between" style="background-color: #fff3e0; border: 1px solid #ffcc02; border-radius: 4px;">
          <div class="d-flex align-items-center">
            <i class="ki-duotone ki-cube fs-2 me-2" style="color: #ef6c00;">
              <span class="path1"></span>
              <span class="path2"></span>
              <span class="path3"></span>
            </i>
            <div>
              <small class="text-dark fw-bold d-block">Jumlah Barcode Produk yang Direncanakan</small>
              <span class="badge badge-warning fs-6"><?= number_format($total_product_uom_qty, 0, ',', '.') ?> unit</span>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="py-2 px-3 d-flex align-items-center justify-content-between" style="background-color: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px;">
          <div class="d-flex align-items-center">
            <i class="ki-duotone ki-barcode fs-2 me-2" style="color: #1565c0;">
              <span class="path1"></span>
              <span class="path2"></span>
            </i>
            <div>
              <small class="text-dark fw-bold d-block">Total Barcode yang Terscan di Manual Stuffing</small>
              <span class="badge badge-primary fs-6"><?= $total_scanned_barcodes ?> barcode</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="separator my-3"></div>
    
    <h6 class="mb-2 d-flex align-items-center">
      <i class="ki-duotone ki-delivery-3 fs-3 text-primary me-1">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
      </i>
      Perbandingan Barcode
    </h6>
    
    <div class="table-responsive">
      <table class="table table-sm table-row-bordered align-middle">
        <thead class="table-light">
          <tr class="fs-7">
            <th style="width: 30px;"></th>
            <th>Nama Picking</th>
            <th style="width: 120px;">SO</th>
            <th style="width: 150px;">PO</th>
            <th style="width: 100px;" class="text-center">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($comparison_data)): ?>
            <?php
            $row_index = 0;
            $has_any_picking_mismatch = false; // Flag untuk cek apakah ada picking yang tidak cocok
            
            foreach ($comparison_data as $picking):
              // Hitung status per picking
              $all_match = true;
              $has_mismatch = false;
              foreach ($picking['products'] as $product) {
                  if (!$product['is_match']) {
                      $all_match = false;
                      $has_mismatch = true;
                      $has_any_picking_mismatch = true; // Set flag jika ada picking yang tidak cocok
                      break;
                  }
              }
            ?>
              <!-- Main Row -->
              <tr class="picking-row clickable-row" 
                  onclick="event.stopPropagation(); const detailRow = document.getElementById('compare-detail-row-<?= $row_index ?>'); const icon = document.getElementById('compare-icon-<?= $row_index ?>'); if (detailRow.style.display === 'none' || detailRow.style.display === '') { detailRow.style.display = 'table-row'; if (icon) { const iconElement = icon.querySelector('i'); if (iconElement) { iconElement.classList.remove('ki-down'); iconElement.classList.add('ki-up'); } } } else { detailRow.style.display = 'none'; if (icon) { const iconElement = icon.querySelector('i'); if (iconElement) { iconElement.classList.remove('ki-up'); iconElement.classList.add('ki-down'); } } }"
                  data-row-index="<?= $row_index ?>" 
                  style="cursor: pointer;">
                <td class="text-center py-2">
                  <div class="toggle-icon-wrapper" id="compare-icon-<?= $row_index ?>">
                    <i class="ki-duotone ki-down fs-4 text-primary">
                      <span class="path1"></span>
                      <span class="path2"></span>
                    </i>
                  </div>
                </td>
                <td class="py-2">
                  <span class="fw-bold"><?= htmlspecialchars($picking['picking_name']) ?></span>
                </td>
                <td class="py-2">
                  <span class="badge" style="background-color: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb;"><?= htmlspecialchars($picking['so_name']) ?></span>
                </td>
                <td class="py-2">
                  <span class="badge" style="background-color: #f3e5f5; color: #6a1b9a; border: 1px solid #ce93d8;"><?= htmlspecialchars($picking['client_order_ref']) ?></span>
                </td>
                <td class="text-center py-2">
                  <?php if ($all_match): ?>
                    <span class="badge fw-bold" style="background-color: #e8f5e8; color: #2e7d32; border: 1px solid #c8e6c9;">✓ Cocok</span>
                  <?php else: ?>
                    <span class="badge fw-bold" style="background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2;">✗ Tidak Cocok</span>
                  <?php endif; ?>
                </td>
              </tr>
              
              <!-- Detail Row (hidden by default) -->
              <tr id="compare-detail-row-<?= $row_index ?>" style="display: none;">
                <td colspan="5" class="p-0">
                  <div class="p-3" style="background-color: #f8f9fa;">
                    <h6 class="fw-bold mb-2">Detail Perbandingan:</h6>
                    <?php foreach ($picking['products'] as $product): ?>
                      <div class="mb-3 p-2 border rounded" style="background-color: white;">
                        <div class="fw-bold mb-2">
                          [<?= htmlspecialchars($product['default_code'] ?? '##') ?>] <?= htmlspecialchars($product['product_name']) ?> (<?= $product['product_uom_qty'] ?>)
                        </div>
                        
                        <?php if ($product['is_match']): ?>
                          <div class="alert alert-success py-2 px-3 mb-2" style="font-size: 0.85rem;">
                            <i class="ki-duotone ki-check fs-4 me-1">
                              <span class="path1"></span>
                              <span class="path2"></span>
                            </i>
                            <strong>Status: Cocok</strong> - Semua barcode sesuai antara Odoo dan yang di scan
                          </div>
                        <?php else: ?>
                          <!-- Tampilan Kiri-Kanan untuk yang Cocok -->
                          <?php if (!empty($product['matched_barcodes'])): ?>
                            <div class="mb-3">
                              <div class="fw-bold mb-2 text-success">✓ Barcode yang Cocok:</div>
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="p-2 border rounded" style="background-color: #f0f8ff;">
                                    <div class="fw-bold mb-1" style="font-size: 0.85rem;">Odoo:</div>
                                    <div class="d-flex flex-wrap gap-1">
                                      <?php foreach ($product['matched_barcodes'] as $barcode): ?>
                                        <span class="badge" style="background-color: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; font-size: 0.7rem;"><?= htmlspecialchars($barcode) ?></span>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="p-2 border rounded" style="background-color: #f0f8ff;">
                                    <div class="fw-bold mb-1" style="font-size: 0.85rem;">Yang di Scan:</div>
                                    <div class="d-flex flex-wrap gap-1">
                                      <?php foreach ($product['matched_barcodes'] as $barcode): ?>
                                        <span class="badge" style="background-color: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; font-size: 0.7rem;"><?= htmlspecialchars($barcode) ?></span>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          <?php endif; ?>
                          
                          <!-- Tampilan untuk yang Tidak Cocok -->
                          <?php if (!empty($product['odoo_only']) || !empty($product['scanned_only'])): ?>
                            <div class="alert alert-warning py-2 px-3 mb-2" style="font-size: 0.85rem;">
                              <i class="ki-duotone ki-information fs-4 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                              </i>
                              <strong>Status: Tidak Cocok</strong> - Ada perbedaan antara Odoo dan yang di scan
                            </div>
                            
                            <div class="row">
                              <?php if (!empty($product['odoo_only'])): ?>
                                <div class="col-md-6">
                                  <div class="p-2 border rounded" style="background-color: #fff5f5;">
                                    <div class="fw-bold mb-1 text-danger" style="font-size: 0.85rem;">
                                      <i class="ki-duotone ki-cross fs-5 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                      </i>
                                      Hanya di Odoo (Akan Dihapus) (<?= count($product['odoo_only']) ?>):
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                      <?php foreach ($product['odoo_only'] as $barcode): ?>
                                        <span class="badge" style="background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; font-size: 0.7rem;"><?= htmlspecialchars($barcode) ?></span>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                </div>
                              <?php endif; ?>
                              
                              <?php if (!empty($product['scanned_only'])): ?>
                                <div class="col-md-6">
                                  <div class="p-2 border rounded" style="background-color: #fff8e1;">
                                    <div class="fw-bold mb-1 text-warning" style="font-size: 0.85rem;">
                                      <i class="ki-duotone ki-arrow-right fs-5 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                      </i>
                                      Sudah di Scan Tapi Belum di Odoo (Akan Ditambahkan) (<?= count($product['scanned_only']) ?>):
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                      <?php foreach ($product['scanned_only'] as $barcode): ?>
                                        <span class="badge" style="background-color: #fff3e0; color: #ef6c00; border: 1px solid #ffcc02; font-size: 0.7rem;"><?= htmlspecialchars($barcode) ?></span>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                </div>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </td>
              </tr>
              
              <?php $row_index++; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center">Tidak ada data untuk dibandingkan</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <?php if (!empty($comparison_data)): ?>
    <div class="separator my-4"></div>
    
    <!-- Summary -->
    <?php
    $total_match = 0; // Total barcode yang cocok
    $total_mismatch = 0; // Total barcode yang tidak cocok (odoo_only + scanned_only)
    $total_products = 0;
    $total_line_items = 0; // Jumlah semua stock.move entries
    $unique_products = []; // Untuk menghitung unique product

    foreach ($comparison_data as $picking) {
        foreach ($picking['products'] as $product) {
            $total_line_items++;
            
            // Hitung unique product berdasarkan product_id + sale_line_id
            $unique_key = $product['product_id'] . '_' . $product['sale_line_id'];
            if (!isset($unique_products[$unique_key])) {
                $unique_products[$unique_key] = true;
                $total_products++;
            }
            
            // Hitung berdasarkan barcode, bukan product
            // Barcode yang cocok (matched_barcodes)
            $total_match += count($product['matched_barcodes'] ?? []);
            
            // Barcode yang tidak cocok (hanya di Odoo atau hanya di scan)
            $total_mismatch += count($product['odoo_only'] ?? []);
            $total_mismatch += count($product['scanned_only'] ?? []);
        }
    }
    ?>
    
    <!-- Summary Box -->
    <div class="border rounded p-2 mb-3" style="background-color: #ffffff; border-color: #dee2e6 !important;">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="flex-grow-1">
          <div class="row g-2">
            <div class="col-6 col-md-3">
              <div class="px-3 py-2 rounded h-100" style="background-color: #e8f5e9; border-left: 3px solid #4caf50; min-height: 60px;">
                <div class="fw-bold mb-1" style="font-size: 0.8rem; color: #1b5e20 !important;">Cocok</div>
                <div class="fw-bolder" style="font-size: 1.3rem; color: #1b5e20 !important;"><?= $total_match ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="px-3 py-2 rounded h-100" style="background-color: #ffebee; border-left: 3px solid #f44336; min-height: 60px;">
                <div class="fw-bold mb-1" style="font-size: 0.8rem; color: #b71c1c !important;">Tidak Cocok</div>
                <div class="fw-bolder" style="font-size: 1.3rem; color: #b71c1c !important;"><?= $total_mismatch ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="px-3 py-2 rounded h-100" style="background-color: #fff3e0; border-left: 3px solid #ff9800; min-height: 60px;">
                <div class="fw-bold mb-1" style="font-size: 0.8rem; color: #e65100 !important;">Direncanakan</div>
                <div class="fw-bolder" style="font-size: 1.3rem; color: #e65100 !important;"><?= number_format($total_product_uom_qty, 0, ',', '.') ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="px-3 py-2 rounded h-100" style="background-color: #e3f2fd; border-left: 3px solid #2196f3; min-height: 60px;">
                <div class="fw-bold mb-1" style="font-size: 0.8rem; color: #0d47a1 !important;">Terscan</div>
                <div class="fw-bolder" style="font-size: 1.3rem; color: #0d47a1 !important;"><?= $total_scanned_barcodes ?></div>
              </div>
            </div>
          </div>
        </div>
        <?php if (isset($has_any_picking_mismatch) && $has_any_picking_mismatch): ?>
        <div>
          <button class="btn btn-primary btn-sm btn-sync-compare" id="btnSyncCompare" data-shipping-id="<?= $shipping_id ?>">
            <i class="ki-duotone ki-arrows-circle fs-5 me-1">
              <span class="path1"></span>
              <span class="path2"></span>
            </i>
            Sinkron
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>
    
    <?php if (isset($has_any_picking_mismatch) && $has_any_picking_mismatch): ?>
    <div class="alert alert-warning d-flex align-items-center p-2">
      <i class="ki-duotone ki-information fs-2 text-warning me-2">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
      </i>
      <div class="fs-7">
        <strong>Perhatian:</strong>
        Ada <?= $total_mismatch ?> produk yang tidak cocok antara data Odoo dan yang di scan. Silakan periksa detail di atas.
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Barcode yang Tidak Ter-map (Belum Ada Picking/SO) -->
    <?php if (!empty($unmapped_barcodes)): ?>
    <div class="separator my-4"></div>
    
    <div class="card shadow-sm border-danger">
      <div class="card-header bg-danger text-white py-2 px-3">
        <div class="d-flex align-items-center">
          <i class="ki-duotone ki-cross-circle fs-3 me-2">
            <span class="path1"></span>
            <span class="path2"></span>
          </i>
          <div>
            <h6 class="mb-0">Barcode Belum Ada Picking/SO</h6>
            <small>Barcode yang di-scan tidak cocok dengan picking/so yang ada di list</small>
          </div>
        </div>
      </div>
      <div class="card-body p-3">
        <div class="alert alert-danger d-flex align-items-center p-2 mb-3">
          <i class="ki-duotone ki-information fs-2 text-danger me-2">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
          </i>
          <div class="fs-7">
            <strong>Total: <?= count($unmapped_barcodes) ?> barcode</strong> yang tidak ter-map ke picking/so manapun
          </div>
        </div>
        
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="table-danger">
              <tr class="fs-7">
                <th style="width: 50px;">No</th>
                <th>Barcode</th>
                <th>Info (jika ada)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($unmapped_barcodes as $index => $item): ?>
                <tr>
                  <td class="text-center"><?= $index + 1 ?></td>
                  <td>
                    <span class="badge" style="background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; font-size: 0.85rem;">
                      <?= htmlspecialchars($item['barcode']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($item['info']): ?>
                      <?php if (isset($item['info']['customer_name'])): ?>
                        <div class="fs-7">
                          <strong>Customer:</strong> <?= htmlspecialchars($item['info']['customer_name']) ?><br>
                          <strong>SO:</strong> <?= htmlspecialchars($item['info']['so_name'] ?? '-') ?><br>
                          <strong>PO:</strong> <?= htmlspecialchars($item['info']['sale_order_ref'] ?? '-') ?>
                        </div>
                      <?php else: ?>
                        <div class="fs-7 text-muted">
                          Sale Order ID: <?= $item['info']['sale_order_id'] ?? '-' ?><br>
                          Product ID: <?= $item['info']['product_id'] ?? '-' ?>
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted fs-7">Tidak ada info</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- Barcode yang Melebihi Jumlah Product -->
    <!-- Tampilkan jika: 
         1. Ada barcode yang tidak ter-map ke picking manapun (tidak ada SO/SOL yang cocok)
         ATAU
         2. TOTAL terscan > TOTAL direncanakan 
    -->
    <?php if (!empty($excess_barcodes) && (!empty($unmapped_barcodes) || $total_scanned_barcodes > $total_product_uom_qty)): ?>
    <div class="separator my-4"></div>
    
    <div class="card shadow-sm border-warning">
      <div class="card-header bg-warning text-dark py-2 px-3">
        <div class="d-flex align-items-center">
          <i class="ki-duotone ki-up fs-3 me-2">
            <span class="path1"></span>
            <span class="path2"></span>
          </i>
          <div>
            <h6 class="mb-0">Barcode Melebihi Jumlah Product</h6>
            <small>Barcode yang di-scan melebihi jumlah yang seharusnya</small>
          </div>
        </div>
      </div>
      <div class="card-body p-3">
        <div class="alert alert-warning d-flex align-items-center p-2 mb-3">
          <i class="ki-duotone ki-information fs-2 text-warning me-2">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
          </i>
          <div class="fs-7">
            <strong>Total: <?= count($excess_barcodes) ?> product</strong> yang memiliki barcode melebihi jumlah yang seharusnya
          </div>
        </div>
        
        <?php foreach ($excess_barcodes as $excess): ?>
          <div class="mb-3 p-2 border rounded" style="background-color: #fff8e1;">
            <div class="fw-bold mb-2">
              [<?= htmlspecialchars($excess['default_code'] ?? '##') ?>] <?= htmlspecialchars($excess['product_name']) ?> (<?= $excess['expected_qty'] ?>)
            </div>
            <div class="fs-7 mb-2">
              <span class="badge badge-light-primary">Expected: <?= $excess['expected_qty'] ?></span>
              <span class="badge badge-light-warning ms-2">Scanned: <?= $excess['scanned_count'] ?></span>
              <span class="badge badge-light-danger ms-2">Excess: <?= $excess['excess_count'] ?></span>
            </div>
            <div class="fw-bold mb-1 text-danger" style="font-size: 0.85rem;">
              Barcode yang Melebihi (<?= count($excess['excess_barcodes']) ?>):
            </div>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($excess['excess_barcodes'] as $barcode): ?>
                <span class="badge" style="background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; font-size: 0.7rem;">
                  <?= htmlspecialchars($barcode) ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Function to toggle comparison details
window.toggleComparisonDetails = function(rowIndex) {
    const detailRow = document.getElementById('compare-detail-row-' + rowIndex);
    const icon = document.getElementById('compare-icon-' + rowIndex);
    
    if (!detailRow) {
        console.error('Detail row not found for index:', rowIndex);
        return;
    }
    
    const currentDisplay = detailRow.style.display;
    
    if (currentDisplay === 'none' || currentDisplay === '') {
        detailRow.style.display = 'table-row';
        if (icon) {
            const iconElement = icon.querySelector('i');
            if (iconElement) {
                iconElement.classList.remove('ki-down');
                iconElement.classList.add('ki-up');
            }
        }
    } else {
        detailRow.style.display = 'none';
        if (icon) {
            const iconElement = icon.querySelector('i');
            if (iconElement) {
                iconElement.classList.remove('ki-up');
                iconElement.classList.add('ki-down');
            }
        }
    }
};

// Function untuk sinkronisasi compare manual stuffing
window.syncCompareManualStuffing = function(btn) {
    const shippingId = btn.dataset.shippingId;
    
    if (!shippingId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Shipping ID tidak ditemukan',
            confirmButtonColor: '#F1416C'
        });
        return;
    }
    
    // Show confirmation modal using SweetAlert
    Swal.fire({
        title: 'Konfirmasi Sinkronisasi',
        html: '<div class="text-start">' +
              '<p class="mb-3">Apakah Anda yakin ingin melakukan sinkronisasi?</p>' +
              '<div class="alert alert-light-warning d-flex align-items-start p-3">' +
              '<i class="ki-duotone ki-information fs-2 text-warning me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>' +
              '<div class="fs-7">' +
              '<strong>Proses ini akan:</strong>' +
              '<ol class="mb-0 mt-2 ps-3">' +
              '<li>Menghapus barcode yang hanya ada di Odoo (tidak ada di scan)</li>' +
              '<li>Menambahkan barcode yang sudah di scan tapi belum ada di Odoo</li>' +
              '</ol>' +
              '</div>' +
              '</div>' +
              '</div>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="ki-duotone ki-check fs-3 me-1"><span class="path1"></span><span class="path2"></span></i> Ya, Sinkronkan',
        cancelButtonText: '<i class="ki-duotone ki-cross fs-3 me-1"><span class="path1"></span><span class="path2"></span></i> Batal',
        confirmButtonColor: '#50CD89',
        cancelButtonColor: '#F1416C',
        reverseButtons: true,
        allowOutsideClick: false,
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        }
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }
        
        // User confirmed, proceed with sync
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
        
        fetch('shipping/sync_compare_manual_stuffing.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'shipping_id=' + encodeURIComponent(shippingId)
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Error response:', text);
                    throw new Error('HTTP error! status: ' + response.status + ', body: ' + text);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            
            if (data && data.success) {
                // Gunakan SweetAlert jika tersedia, jika tidak gunakan alert biasa
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message || 'Berhasil melakukan sinkronisasi',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#50CD89'
                    }).then(() => {
                        // AGGRESSIVE CLEANUP - tutup semua modal dan hapus backdrop
                        
                        // 1. Close SweetAlert explicitly
                        Swal.close();
                        
                        // 2. Hapus semua elemen backdrop SweetAlert
                        document.querySelectorAll('.swal2-container, .swal2-backdrop-show').forEach(el => el.remove());
                        
                        // 3. Hapus backdrop Bootstrap modal (INI YANG PALING PENTING!)
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        
                        // 4. Hapus semua class dari body dan html
                        document.body.classList.remove('swal2-shown', 'swal2-height-auto', 'modal-open');
                        document.documentElement.classList.remove('swal2-shown', 'swal2-height-auto');
                        
                        // 5. Reset inline styles
                        document.body.style.removeProperty('padding-right');
                        document.body.style.removeProperty('overflow');
                        
                        // 6. Reset scroll
                        window.scrollTo(0, document.body.scrollTop || document.documentElement.scrollTop);
                        
                        // 7. Reload compare dengan delay
                        setTimeout(() => {
                            const compareButton = document.querySelector('.btn-compare-manual-stuffing[data-id="' + shippingId + '"]');
                            if (compareButton) {
                                compareButton.click();
                            }
                        }, 300);
                    });
                } else {
                    alert('✓ ' + (data.message || 'Berhasil melakukan sinkronisasi'));
                    // Reload compare
                    const compareButton = document.querySelector('.btn-compare-manual-stuffing[data-id="' + shippingId + '"]');
                    if (compareButton) {
                        compareButton.click();
                    }
                }
            } else {
                // Gunakan SweetAlert untuk error juga jika tersedia
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Terjadi kesalahan saat sinkronisasi',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#F1416C'
                    });
                } else {
                    alert('✗ Error: ' + (data.message || 'Terjadi kesalahan saat sinkronisasi'));
                }
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            // Gunakan SweetAlert untuk error jika tersedia
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Terjadi kesalahan saat sinkronisasi',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#F1416C'
                });
            } else {
                alert('✗ Error: ' + (error.message || 'Terjadi kesalahan saat sinkronisasi'));
            }
        });
    }); // Close Swal.fire().then()
};

// Attach event listener untuk tombol sinkron setelah script di-load
(function() {
    // Event delegation untuk tombol sinkron (karena di-load via AJAX)
    const modalBody = document.querySelector('#shippingDetailModal .modal-body');
    if (modalBody) {
        modalBody.addEventListener('click', function(e) {
            if (e.target.closest('.btn-sync-compare')) {
                const btn = e.target.closest('.btn-sync-compare');
                syncCompareManualStuffing(btn);
            }
        });
    }
    
    // Juga attach langsung jika elemen sudah ada
    setTimeout(function() {
        const syncBtn = document.getElementById('btnSyncCompare');
        if (syncBtn) {
            syncBtn.addEventListener('click', function(e) {
                e.preventDefault();
                syncCompareManualStuffing(this);
            });
        }
    }, 100);
})();
</script>


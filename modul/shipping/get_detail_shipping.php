<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Ambil data dari Odoo stock.picking.batch berdasarkan name shipping
$batch_name = $shipping['name'];
$batches = callOdooRead($username, "stock.picking.batch", [["name", "=", $batch_name]], ["name", "scheduled_date", "description", "picking_ids"]);

if (!$batches || count($batches) == 0) {
    echo "Data batch tidak ditemukan di Odoo.";
    exit;
}

$batch = $batches[0];
$picking_ids = $batch['picking_ids'] ?? [];

// Debug: Log picking_ids
error_log("Batch picking_ids: " . json_encode($picking_ids));

// Ambil data stock.picking berdasarkan picking_ids
$pickings = [];
if (!empty($picking_ids)) {
    $pickings = callOdooRead($username, "stock.picking", [["id", "in", $picking_ids]], ["id", "name", "sale_id", "origin", "group_id", "move_line_ids_without_package"]);
    
    if (!$pickings) {
        $pickings = [];
        error_log("Error: callOdooRead returned false or null for stock.picking");
    } else if (!is_array($pickings)) {
        $pickings = [];
        error_log("Error: callOdooRead returned non-array for stock.picking: " . gettype($pickings));
    } else {
        error_log("Pickings found from Odoo: " . count($pickings));
    }
} else {
    error_log("No picking_ids found in batch. Batch data: " . json_encode($batch));
}

// Fallback: Jika tidak ada data dari Odoo, ambil dari database lokal dan fetch data lengkap dari Odoo
if (empty($pickings)) {
    error_log("No pickings from Odoo, trying to get from local database...");
    $sql_local = "SELECT id, name, sale_id, client_order_ref FROM shipping_detail WHERE id_shipping = ? ORDER BY name";
    $stmt_local = $conn->prepare($sql_local);
    if ($stmt_local) {
        $stmt_local->bind_param("i", $shipping_id);
        $stmt_local->execute();
        $result_local = $stmt_local->get_result();
        
        $local_picking_ids = [];
        $local_pickings_map = [];
        while ($row = $result_local->fetch_assoc()) {
            $local_picking_ids[] = $row['id'];
            $local_pickings_map[$row['id']] = $row;
        }
        $stmt_local->close();
        
        if (!empty($local_picking_ids)) {
            // Ambil data lengkap dari Odoo untuk picking yang ada di database lokal
            $pickings_from_odoo = callOdooRead($username, "stock.picking", [["id", "in", $local_picking_ids]], ["id", "name", "sale_id", "origin", "group_id", "move_line_ids_without_package"]);
            
            if ($pickings_from_odoo && is_array($pickings_from_odoo) && count($pickings_from_odoo) > 0) {
                $pickings = $pickings_from_odoo;
                error_log("Found " . count($pickings) . " pickings from Odoo (using local DB IDs)");
            } else {
                // Jika masih tidak ada dari Odoo, gunakan data lokal (tanpa lot_ids)
                $pickings = [];
                foreach ($local_pickings_map as $id => $row) {
                    $pickings[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'sale_id' => $row['sale_id'] ? [$row['sale_id'], ''] : [],
                        'origin' => '',
                        'group_id' => [],
                        'move_line_ids_without_package' => []
                    ];
                }
                error_log("Found " . count($pickings) . " pickings from local database (no Odoo data)");
            }
        } else {
            error_log("No pickings found in local database either");
        }
    }
}

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
    if ($sale_orders_data && is_array($sale_orders_data)) {
        foreach ($sale_orders_data as $so) {
            if (is_array($so['procurement_group_id']) && count($so['procurement_group_id']) > 0) {
                $po_map[$so['procurement_group_id'][0]] = $so['client_order_ref'];
            }
        }
    }
}

// Ambil data manual stuffing untuk shipping ini (untuk tombol sync)
$manual_stuffing_count = 0;
try {
    $sql_manual_count = "SELECT COUNT(*) as count FROM shipping_manual_stuffing WHERE id_shipping = ?";
    $stmt_manual_count = $conn->prepare($sql_manual_count);
    if ($stmt_manual_count) {
        $stmt_manual_count->bind_param("i", $shipping_id);
        $stmt_manual_count->execute();
        $manual_count_result = $stmt_manual_count->get_result()->fetch_assoc();
        $manual_stuffing_count = $manual_count_result['count'];
        $stmt_manual_count->close();
    }
} catch (Exception $e) {
    // Table might not exist, set count to 0
    error_log("Error querying shipping_manual_stuffing: " . $e->getMessage());
    $manual_stuffing_count = 0;
}

// Kumpulkan semua product_id dari semua pickings untuk fetch M3
$all_product_ids = [];
$product_m3_map = [];

// Loop melalui semua pickings untuk kumpulkan product_ids
foreach ($pickings as $picking) {
    $picking_id = $picking['id'];
    
    // Ambil move_lines untuk picking ini
    $picking_full = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_line_ids_without_package']);
    $move_line_ids = [];
    if ($picking_full && !empty($picking_full) && isset($picking_full[0]['move_line_ids_without_package'])) {
        $move_line_ids = $picking_full[0]['move_line_ids_without_package'];
    } else {
        $move_line_ids = $picking['move_line_ids_without_package'] ?? [];
    }
    
    if (empty($move_line_ids)) {
        $move_lines_direct = callOdooRead($username, 'stock.move.line', [
            ['picking_id', '=', $picking_id],
            ['package_id', '=', false]
        ], ['product_id']);
        if ($move_lines_direct && is_array($move_lines_direct)) {
            $move_lines = $move_lines_direct;
        } else {
            $move_lines = [];
        }
    } else {
        $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['product_id']);
    }
    
    // Kumpulkan product_ids
    if ($move_lines && is_array($move_lines)) {
        foreach ($move_lines as $line) {
            if (isset($line['product_id']) && is_array($line['product_id']) && count($line['product_id']) >= 1) {
                $prod_id = $line['product_id'][0];
                if ($prod_id && !in_array($prod_id, $all_product_ids)) {
                    $all_product_ids[] = $prod_id;
                }
            }
        }
    }
}

// Fetch M3 dari Odoo untuk semua product_ids
// Ambil langsung dari product.packaging berdasarkan product_id atau product_tmpl_id
if (!empty($all_product_ids) && !empty($username)) {
    // Ambil product.product untuk mendapatkan product_tmpl_id
    $products = callOdooRead($username, 'product.product', [['id', 'in', $all_product_ids]], ['id', 'product_tmpl_id']);
    
    $product_tmpl_ids = [];
    $product_id_to_tmpl = [];
    
    if ($products && is_array($products)) {
        foreach ($products as $product) {
            $prod_id = $product['id'] ?? null;
            if (!$prod_id) continue;
            
            // Ambil product_tmpl_id
            $tmpl_id = null;
            if (isset($product['product_tmpl_id']) && is_array($product['product_tmpl_id']) && count($product['product_tmpl_id']) >= 1) {
                $tmpl_id = $product['product_tmpl_id'][0];
            } else if (isset($product['product_tmpl_id']) && !is_array($product['product_tmpl_id'])) {
                $tmpl_id = $product['product_tmpl_id'];
            }
            
            if ($tmpl_id) {
                if (!in_array($tmpl_id, $product_tmpl_ids)) {
                    $product_tmpl_ids[] = $tmpl_id;
                }
                $product_id_to_tmpl[$prod_id] = $tmpl_id;
            }
        }
    }
    
    // Ambil packaging berdasarkan product_id langsung
    $packagings_by_product = callOdooRead($username, 'product.packaging', [['product_id', 'in', $all_product_ids]], ['id', 'product_id', 'cbm']);
    
    // Ambil packaging berdasarkan product_tmpl_id
    $packagings_by_template = [];
    if (!empty($product_tmpl_ids)) {
        $packagings_by_template = callOdooRead($username, 'product.packaging', [['product_tmpl_id', 'in', $product_tmpl_ids]], ['id', 'product_tmpl_id', 'cbm']);
    }
    
    // Process packaging by product_id
    if ($packagings_by_product && is_array($packagings_by_product)) {
        foreach ($packagings_by_product as $pkg) {
            $pkg_product_id = null;
            if (isset($pkg['product_id']) && is_array($pkg['product_id']) && count($pkg['product_id']) >= 1) {
                $pkg_product_id = $pkg['product_id'][0];
            } else if (isset($pkg['product_id']) && !is_array($pkg['product_id'])) {
                $pkg_product_id = $pkg['product_id'];
            }
            
            if ($pkg_product_id) {
                $cmb = 0;
                if (isset($pkg['cbm'])) {
                    $cmb_val = $pkg['cbm'];
                    if (is_numeric($cmb_val) && $cmb_val > 0) {
                        $cmb = floatval($cmb_val);
                    }
                }
                
                if ($cmb > 0) {
                    if (!isset($product_m3_map[$pkg_product_id])) {
                        $product_m3_map[$pkg_product_id] = 0;
                    }
                    $product_m3_map[$pkg_product_id] += $cmb;
                }
            }
        }
    }
    
    // Process packaging by product_tmpl_id
    if ($packagings_by_template && is_array($packagings_by_template)) {
        foreach ($packagings_by_template as $pkg) {
            $pkg_tmpl_id = null;
            if (isset($pkg['product_tmpl_id']) && is_array($pkg['product_tmpl_id']) && count($pkg['product_tmpl_id']) >= 1) {
                $pkg_tmpl_id = $pkg['product_tmpl_id'][0];
            } else if (isset($pkg['product_tmpl_id']) && !is_array($pkg['product_tmpl_id'])) {
                $pkg_tmpl_id = $pkg['product_tmpl_id'];
            }
            
            if ($pkg_tmpl_id) {
                $cmb = 0;
                if (isset($pkg['cbm'])) {
                    $cmb_val = $pkg['cbm'];
                    if (is_numeric($cmb_val) && $cmb_val > 0) {
                        $cmb = floatval($cmb_val);
                    }
                }
                
                if ($cmb > 0) {
                    // Assign ke semua product_id yang memiliki template ini
                    foreach ($product_id_to_tmpl as $prod_id => $tmpl_id) {
                        if ($tmpl_id == $pkg_tmpl_id) {
                            if (!isset($product_m3_map[$prod_id])) {
                                $product_m3_map[$prod_id] = 0;
                            }
                            $product_m3_map[$prod_id] += $cmb;
                        }
                    }
                }
            }
        }
    }
}
?>
<div class="card shadow-sm">
  <!-- Header with Sync Buttons -->
  <div class="card-header bg-light py-2 px-3">
    <div class="d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <i class="ki-duotone ki-package fs-3 text-primary me-2">
          <span class="path1"></span>
          <span class="path2"></span>
          <span class="path3"></span>
        </i>
        <div>
          <h5 class="mb-0">Detail Pengiriman</h5>
          <small class="text-muted">Lot/Serial & Manual Stuffing</small>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-light-primary btn-sm" id="btnSyncShipping" data-shipping-id="<?= $shipping_id ?>">
          <i class="ki-duotone ki-cloud-change fs-4 me-1">
            <span class="path1"></span>
            <span class="path2"></span>
          </i>
          Sinkron Pengiriman
        </button>
        <?php if ($manual_stuffing_count > 0): ?>
        <button class="btn btn-primary btn-sm" id="btnCompareLotSync" data-shipping-id="<?= $shipping_id ?>">
          <i class="ki-duotone ki-arrows-loop fs-4 me-1">
            <span class="path1"></span>
            <span class="path2"></span>
          </i>
          Bandingkan Data
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="card-body p-3">

    <!-- Shipping Info - Compact -->
    <div class="border rounded p-2 mb-3" style="background-color: #f8f9fa; border-color: #dee2e6 !important;">
      <div class="row g-2">
        <div class="col-md-3 col-6">
          <small class="text-muted d-block">Nama Pengiriman</small>
          <strong class="fs-7 text-dark"><?= htmlspecialchars($shipping['name']) ?></strong>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted d-block">Tanggal Kirim</small>
          <strong class="fs-7 text-dark"><?= date('d M Y', strtotime($shipping['sheduled_date'])) ?></strong>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted d-block">Keterangan</small>
          <strong class="fs-7 text-dark"><?= htmlspecialchars($batch['description']) ?></strong>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted d-block">Tujuan Kirim</small>
          <strong class="fs-7 text-dark"><?= htmlspecialchars($shipping['ship_to']) ?></strong>
        </div>
      </div>
    </div>
    
    <?php if ($manual_stuffing_count > 0): ?>
    <div class="py-2 px-3 mb-3 d-flex align-items-center" style="background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px;">
      <i class="ki-duotone ki-information-5 fs-2 me-2" style="color: #0c5460;">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
      </i>
      <small style="color: #0c5460;"><strong><?= $manual_stuffing_count ?></strong> data manual stuffing tersedia. Klik tombol "Bandingkan Data" untuk melihat perbandingan.</small>
    </div>
    <?php endif; ?>

    <!-- Picking List Section -->
    <div class="separator my-3"></div>
    
    <h6 class="mb-2 d-flex align-items-center">
      <i class="ki-duotone ki-delivery-3 fs-3 text-primary me-1">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
      </i>
      Surat Jalan (Picking)
    </h6>
    
    <div class="table-responsive">
      <table class="table table-sm table-row-bordered align-middle">
        <thead class="table-light">
          <tr class="fs-7">
            <th style="width: 30px;"></th>
            <th>Nama Picking</th>
            <th style="width: 120px;">SO</th>
            <th style="width: 150px;">PO</th>
          </tr>
        </thead>
      <tbody>
        <?php if (is_array($pickings) && count($pickings) > 0): ?>
          <?php 
          $row_index = 0;
          foreach ($pickings as $picking): 
            // Ambil lot_ids langsung dari Odoo (realtime) untuk picking ini
            $picking_id = $picking['id'];
            $lots = [];
            $has_lots = false;
            
            // Ambil move_lines langsung dari Odoo berdasarkan picking_id (realtime)
            // Cara 1: Coba ambil dari move_line_ids_without_package
            $picking_full = callOdooRead($username, 'stock.picking', [['id', '=', $picking_id]], ['move_line_ids_without_package']);
            
            $move_line_ids = [];
            if ($picking_full && !empty($picking_full) && isset($picking_full[0]['move_line_ids_without_package'])) {
                $move_line_ids = $picking_full[0]['move_line_ids_without_package'];
            } else {
                // Fallback: coba dari data picking yang sudah ada
                $move_line_ids = $picking['move_line_ids_without_package'] ?? [];
            }
            
            // Cara 2: Jika tidak ada move_line_ids, ambil langsung berdasarkan picking_id (tanpa package)
            if (empty($move_line_ids)) {
                // Query stock.move.line langsung berdasarkan picking_id dan package_id = False (tidak di-package)
                $move_lines_direct = callOdooRead($username, 'stock.move.line', [
                    ['picking_id', '=', $picking_id],
                    ['package_id', '=', false]
                ], ['lot_id', 'lot_name', 'product_id', 'qty_done']);
                if ($move_lines_direct && is_array($move_lines_direct) && !empty($move_lines_direct)) {
                    $move_lines = $move_lines_direct;
                } else {
                    $move_lines = [];
                }
            } else {
                // Ambil data move_line langsung dari Odoo (realtime) berdasarkan move_line_ids
                $move_lines = callOdooRead($username, 'stock.move.line', [['id', 'in', $move_line_ids]], ['lot_id', 'lot_name', 'product_id', 'qty_done']);
            }
            
            // Process move_lines untuk mendapatkan lots
            if ($move_lines && is_array($move_lines) && !empty($move_lines)) {
                foreach ($move_lines as $line) {
                    $lot_name = null;
                    $product_name = null;
                    $product_id = null;
                    $qty_done = $line['qty_done'] ?? 0;
                    
                    // Try to get lot info from lot_id field
                    if (isset($line['lot_id']) && is_array($line['lot_id']) && count($line['lot_id']) >= 2) {
                        $lot_name = $line['lot_id'][1];
                    }
                    // Fallback: try lot_name field
                    else if (isset($line['lot_name']) && !empty($line['lot_name'])) {
                        $lot_name = $line['lot_name'];
                    }
                    
                    // Extract product_id and product_name
                    if (isset($line['product_id']) && is_array($line['product_id']) && count($line['product_id']) >= 1) {
                        $product_id = $line['product_id'][0];
                        if (count($line['product_id']) >= 2) {
                            $product_name = $line['product_id'][1];
                        }
                    }
                    
                    if ($lot_name !== null && $lot_name !== '') {
                        $lots[] = [
                            'lot_name' => $lot_name,
                            'product_name' => $product_name,
                            'product_id' => $product_id,
                            'qty_done' => $qty_done
                        ];
                    }
                }
            }
            
            $has_lots = count($lots) > 0;
          ?>
            <!-- Main Row -->
            <tr class="picking-row <?= $has_lots ? 'clickable-row' : '' ?>" 
                <?= $has_lots ? 'onclick="toggleLotDetails(' . $row_index . ')"' : '' ?>
                data-row-index="<?= $row_index ?>" 
                style="<?= $has_lots ? 'cursor: pointer;' : '' ?>">
              <td class="text-center py-2">
                <?php if ($has_lots): ?>
                  <div class="toggle-icon-wrapper" id="icon-<?= $row_index ?>">
                    <i class="ki-duotone ki-down fs-4 text-primary">
                      <span class="path1"></span>
                      <span class="path2"></span>
                    </i>
                  </div>
                <?php else: ?>
                  <i class="ki-duotone ki-minus fs-5 text-muted">
                    <span class="path1"></span>
                    <span class="path2"></span>
                  </i>
                <?php endif; ?>
              </td>
              <td class="py-2">
                <div class="d-flex align-items-center">
                  <div>
                    <span class="text-dark fw-bold fs-7 d-block"><?= htmlspecialchars($picking['name']) ?></span>
                    <?php if ($has_lots): ?>
                      <small class="text-primary"><i class="ki-duotone ki-barcode fs-7"><span class="path1"></span><span class="path2"></span></i> <?= count($lots) ?> lot</small>
                    <?php else: ?>
                      <small class="text-muted">Tidak ada lot</small>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="py-2">
                <?php if (is_array($picking['sale_id']) && count($picking['sale_id']) > 1): ?>
                  <small class="text-dark"><?= htmlspecialchars($picking['sale_id'][1]) ?></small>
                <?php else: ?>
                  <small class="text-muted">-</small>
                <?php endif; ?>
              </td>
              <td class="py-2">
                <?php
                $group_id = (is_array($picking['group_id']) && count($picking['group_id']) > 0) ? $picking['group_id'][0] : null;
                $po = $group_id ? ($po_map[$group_id] ?? null) : null;
                if ($po):
                ?>
                  <small class="text-dark fw-bold"><?= htmlspecialchars($po) ?></small>
                <?php else: ?>
                  <small class="text-muted">-</small>
                <?php endif; ?>
              </td>
            </tr>
            
            <!-- Collapsible Row for Lot IDs -->
            <?php if ($has_lots): ?>
            <tr class="lot-details-row" id="lot-row-<?= $row_index ?>" style="display: none;">
              <td colspan="4" class="p-0" style="background: #f8f9fa;">
                <div class="p-2">
                  <div class="card border mb-0">
                    <div class="card-header py-2 px-3" style="background-color: #6c757d; border-bottom: 2px solid #5a6268;">
                      <div class="d-flex justify-content-between align-items-center">
                        <small class="text-white fw-bold">
                          <i class="ki-duotone ki-barcode fs-5 text-white me-1"><span class="path1"></span><span class="path2"></span></i>
                          Daftar Lot/Serial: <?= htmlspecialchars($picking['name']) ?>
                        </small>
                        <span class="badge badge-light badge-sm"><?= count($lots) ?> item</span>
                      </div>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table table-sm table-row-bordered mb-0">
                          <thead style="background-color: #f1f3f5;">
                            <tr class="fs-8">
                              <th style="width: 40px;">#</th>
                              <th>Nomor Lot/Serial</th>
                              <th>Nama Produk</th>
                              <th style="width: 80px;" class="text-end">Jumlah</th>
                              <th style="width: 100px;" class="text-end">M3</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php 
                            $lot_no = 1;
                            $product_m3_summary = []; // Untuk summary per product
                            foreach ($lots as $lot): 
                              $lot_product_id = $lot['product_id'] ?? null;
                              $lot_m3 = ($lot_product_id && isset($product_m3_map[$lot_product_id])) ? $product_m3_map[$lot_product_id] : 0;
                              
                              // Kumpulkan untuk summary per product
                              if ($lot_product_id) {
                                if (!isset($product_m3_summary[$lot_product_id])) {
                                  $product_m3_summary[$lot_product_id] = [
                                    'product_name' => $lot['product_name'] ?? 'Unknown',
                                    'm3' => $lot_m3,
                                    'count' => 0
                                  ];
                                }
                                $product_m3_summary[$lot_product_id]['count']++;
                              }
                            ?>
                              <tr class="fs-8">
                                <td class="text-center"><?= $lot_no++ ?></td>
                                <td>
                                  <span class="badge badge-light-primary badge-sm">
                                    <?= htmlspecialchars($lot['lot_name']) ?>
                                  </span>
                                </td>
                                <td><small><?= htmlspecialchars($lot['product_name']) ?></small></td>
                                <td class="text-end">
                                  <span class="badge badge-light-success badge-sm">
                                    <?= htmlspecialchars($lot['qty_done']) ?>
                                  </span>
                                </td>
                                <td class="text-end">
                                  <?php if ($lot_m3 > 0): ?>
                                    <span class="badge badge-light-info badge-sm">
                                      <?= number_format($lot_m3, 3) ?>
                                    </span>
                                  <?php else: ?>
                                    <small class="text-muted">-</small>
                                  <?php endif; ?>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                        
                        <?php if (!empty($product_m3_summary)): ?>
                        <div class="p-2 mt-2" style="background-color: #e7f3ff; border-top: 1px solid #b3d9ff;">
                          <small class="fw-bold text-primary d-block mb-1">
                            <i class="ki-duotone ki-cube fs-6 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Ringkasan M3 per Produk:
                          </small>
                          <div class="row g-1">
                            <?php foreach ($product_m3_summary as $prod_id => $summary): ?>
                            <div class="col-12">
                              <small class="text-dark">
                                <strong><?= htmlspecialchars($summary['product_name']) ?></strong>: 
                                <span class="text-primary fw-bold"><?= number_format($summary['m3'], 3) ?></span> M3 
                                <span class="text-muted">(<?= $summary['count'] ?> lot)</span>
                              </small>
                            </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
            <?php endif; ?>
            
          <?php 
          $row_index++;
          endforeach; 
          ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="text-center py-5">
              <i class="ki-duotone ki-file-deleted fs-3x text-muted mb-3">
                <span class="path1"></span>
                <span class="path2"></span>
              </i>
              <div class="text-muted mb-2">Tidak ada data picking ditemukan</div>
              <small class="text-muted">
                <?php if (empty($picking_ids)): ?>
                  Batch tidak memiliki picking_ids di Odoo.
                <?php else: ?>
                  Data picking tidak ditemukan di Odoo untuk batch ini. Coba klik tombol "Sinkron Pengiriman" untuk sync ulang.
                <?php endif; ?>
              </small>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
    <!-- Comparison Result Section (Initially Hidden) -->
    <div id="comparisonResult" style="display: none;" class="mt-3">
      <div class="separator my-3"></div>
      
      <h6 class="mb-2 d-flex align-items-center">
        <i class="ki-duotone ki-chart-line-up fs-3 text-primary me-1">
          <span class="path1"></span>
          <span class="path2"></span>
        </i>
        Hasil Perbandingan
        <small class="text-muted ms-2">Odoo vs Manual Stuffing</small>
      </h6>
      
      <div id="comparisonContent"></div>
    </div>
  </div>
</div>

<style>
/* Compact Styles */
.clickable-row:hover {
  background-color: #f5f8fa !important;
}

.toggle-icon-wrapper {
  transition: transform 0.2s ease;
}

.toggle-icon-wrapper.rotated {
  transform: rotate(180deg);
}
</style>
<?php
// Tutup koneksi
mysqli_close($conn);
?>
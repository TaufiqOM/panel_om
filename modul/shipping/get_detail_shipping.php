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

// Ambil data stock.picking berdasarkan picking_ids
$pickings = [];
if (!empty($picking_ids)) {
    $pickings = callOdooRead($username, "stock.picking", [["id", "in", $picking_ids]], ["name", "sale_id", "origin", "group_id"]);
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
    foreach ($sale_orders_data as $so) {
        if (is_array($so['procurement_group_id']) && count($so['procurement_group_id']) > 0) {
            $po_map[$so['procurement_group_id'][0]] = $so['client_order_ref'];
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
            // Ambil lot_ids dari database lokal untuk picking ini
            $picking_id = $picking['id'];
            $sql_lot = "SELECT lot_name, product_name, qty_done FROM shipping_lot_ids WHERE picking_id = ?";
            $stmt_lot = $conn->prepare($sql_lot);
            $stmt_lot->bind_param("i", $picking_id);
            $stmt_lot->execute();
            $lot_result = $stmt_lot->get_result();
            $lots = [];
            while ($lot_row = $lot_result->fetch_assoc()) {
              $lots[] = $lot_row;
            }
            $stmt_lot->close();
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
                            </tr>
                          </thead>
                          <tbody>
                            <?php 
                            $lot_no = 1;
                            foreach ($lots as $lot): 
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
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
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
              <div class="text-muted">Tidak ada data picking ditemukan</div>
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
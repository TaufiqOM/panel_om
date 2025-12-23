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

$picking_details = [];

// Loop per picking untuk mengumpulkan data
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
    
    // Ambil moves dengan move_line_ids untuk cek yang sudah terinsert
    $moves = callOdooRead($username, 'stock.move', [['id', 'in', $move_ids]], ['id', 'product_id', 'product_uom_qty', 'sale_line_id', 'move_line_ids']);

    $products = [];

    if ($moves && is_array($moves)) {
        foreach ($moves as $move) {
            $move_id = $move['id'];
            $product_id = is_array($move['product_id']) ? $move['product_id'][0] : null;
            $product_name = is_array($move['product_id']) ? $move['product_id'][1] : 'N/A';
            $product_uom_qty = intval($move['product_uom_qty'] ?? 0);
            $sale_line_id = is_array($move['sale_line_id']) ? $move['sale_line_id'][0] : null;
            $move_line_ids = $move['move_line_ids'] ?? [];

            if (!$product_id || $product_uom_qty <= 0) {
                continue;
            }

            // Hitung barcode yang sudah terinsert di Odoo (dari move lines)
            $already_inserted = count($move_line_ids);

            // Hitung barcode yang akan di-insert dari production_lots_strg
            $sql_count_strg = "SELECT COUNT(*) as count
                              FROM production_lots_strg pls
                              LEFT JOIN shipping_manual_stuffing sms ON sms.production_code = pls.production_code AND sms.id_shipping = ?
                              WHERE pls.sale_order_id = ?
                              AND pls.product_code = ?
                              AND pls.sale_order_line_id = ?
                              AND sms.production_code IS NULL";

            $stmt_count = $conn->prepare($sql_count_strg);
            $stmt_count->bind_param("iiii", $shipping_id, $sale_id, $product_id, $sale_line_id);
            $stmt_count->execute();
            $result_count = $stmt_count->get_result();
            $count_strg = 0;
            if ($result_count->num_rows > 0) {
                $count_data = $result_count->fetch_assoc();
                $count_strg = intval($count_data['count']);
            }
            $stmt_count->close();

            // Hitung yang belum diinsert (Qty SO - Sudah Insert)
            $not_inserted = max(0, $product_uom_qty - $already_inserted);

            // Hitung kekurangan total (kurang dari SO qty)
            $shortage = max(0, $product_uom_qty - $count_strg);

            // Ambil sample production_code yang belum diinsert
            $sql_sample = "SELECT pls.production_code
                          FROM production_lots_strg pls
                          LEFT JOIN shipping_manual_stuffing sms ON sms.production_code = pls.production_code AND sms.id_shipping = ?
                          WHERE pls.sale_order_id = ?
                          AND pls.product_code = ?
                          AND pls.sale_order_line_id = ?
                          AND sms.production_code IS NULL
                          ORDER BY pls.id DESC
                          LIMIT ?";

            $stmt_sample = $conn->prepare($sql_sample);
            $limit_sample = min($product_uom_qty, 10); // Ambil max 10 untuk sample
            $stmt_sample->bind_param("iiiii", $shipping_id, $sale_id, $product_id, $sale_line_id, $limit_sample);
            $stmt_sample->execute();
            $result_sample = $stmt_sample->get_result();

            $sample_codes = [];
            while ($row = $result_sample->fetch_assoc()) {
                $sample_codes[] = $row['production_code'];
            }
            $stmt_sample->close();

            $products[] = [
                'product_id' => $product_id,
                'product_name' => $product_name,
                'product_uom_qty' => $product_uom_qty,
                'count_strg' => $count_strg,
                'already_inserted' => $already_inserted,
                'not_inserted' => $not_inserted,
                'shortage' => $shortage,
                'sample_codes' => $sample_codes,
                'sale_line_id' => $sale_line_id
            ];
        }
    }
    
    $picking_details[] = [
        'picking_id' => $picking_id,
        'picking_name' => $picking_name,
        'sale_id' => $sale_id,
        'client_order_ref' => $client_order_ref,
        'so_name' => $so_name,
        'products' => $products
    ];
}

?>
<div class="card shadow-sm">
  <div class="card-header bg-light py-2 px-3">
    <div class="d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <i class="ki-duotone ki-barcode fs-3 text-primary me-2">
          <span class="path1"></span>
          <span class="path2"></span>
          <span class="path3"></span>
          <span class="path4"></span>
          <span class="path5"></span>
          <span class="path6"></span>
          <span class="path7"></span>
          <span class="path8"></span>
        </i>
        <div>
          <h5 class="mb-0">Detail Manual Stuffing</h5>
          <small class="text-muted">Production Code dari Storage</small>
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
            <th style="width: 100px;" class="text-center">Total Qty</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($picking_details)): ?>
            <?php 
            $row_index = 0;
            foreach ($picking_details as $picking): 
              $total_qty = array_sum(array_column($picking['products'], 'product_uom_qty'));
              $total_shortage = array_sum(array_column($picking['products'], 'shortage'));
            ?>
              <!-- Main Row -->
              <tr class="picking-row clickable-row" 
                  onclick="event.stopPropagation(); const detailRow = document.getElementById('ms-detail-row-<?= $row_index ?>'); const icon = document.getElementById('ms-icon-<?= $row_index ?>'); if (detailRow.style.display === 'none' || detailRow.style.display === '') { detailRow.style.display = 'table-row'; if (icon) { const iconElement = icon.querySelector('i'); if (iconElement) { iconElement.classList.remove('ki-down'); iconElement.classList.add('ki-up'); } } } else { detailRow.style.display = 'none'; if (icon) { const iconElement = icon.querySelector('i'); if (iconElement) { iconElement.classList.remove('ki-up'); iconElement.classList.add('ki-down'); } } }"
                  data-row-index="<?= $row_index ?>" 
                  style="cursor: pointer;">
                <td class="text-center py-2">
                  <div class="toggle-icon-wrapper" id="ms-icon-<?= $row_index ?>">
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
                  <span class="badge badge-light"><?= htmlspecialchars($picking['so_name']) ?></span>
                </td>
                <td class="py-2">
                  <span class="badge badge-light-primary"><?= htmlspecialchars($picking['client_order_ref']) ?></span>
                </td>
                <td class="text-center py-2">
                  <span class="badge badge-light-success"><?= $total_qty ?></span>
                  <?php if ($total_shortage > 0): ?>
                    <span class="badge badge-light-danger ms-1" title="Kekurangan">-<?= $total_shortage ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              
              <!-- Detail Row (hidden by default) -->
              <tr id="ms-detail-row-<?= $row_index ?>" style="display: none;">
                <td colspan="5" class="p-0">
                  <div class="p-3" style="background-color: #f8f9fa;">
                    <h6 class="fw-bold mb-2">Detail Produk:</h6>
                    <table class="table table-sm table-bordered mb-0">
                      <thead class="table-secondary">
                        <tr class="fs-8">
                          <th style="width: 200px;">Nama Produk</th>
                          <th class="text-center" style="width: 80px;">Qty SO</th>
                          <th class="text-center" style="width: 80px;">Sudah Insert</th>
                          <th class="text-center" style="width: 80px;">Belum Insert</th>
                          <th class="text-center" style="width: 80px;">Tersedia</th>
                          <th class="text-center" style="width: 80px;">Kurang</th>
                          <th>Sample Production Code</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($picking['products'] as $product): ?>
                          <tr class="fs-8">
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td class="text-center fw-bold"><?= $product['product_uom_qty'] ?></td>
                            <td class="text-center text-primary fw-bold">
                              <?= $product['already_inserted'] ?>
                            </td>
                            <td class="text-center <?= $product['not_inserted'] > 0 ? 'text-warning fw-bold' : 'text-muted' ?>">
                              <?= $product['not_inserted'] > 0 ? $product['not_inserted'] : '-' ?>
                            </td>
                            <td class="text-center <?= $product['count_strg'] >= $product['product_uom_qty'] ? 'text-success' : 'text-warning' ?>">
                              <?= $product['count_strg'] ?>
                            </td>
                            <td class="text-center <?= $product['shortage'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                              <?= $product['shortage'] > 0 ? '-' . $product['shortage'] : '✓' ?>
                            </td>
                            <td>
                              <?php if (!empty($product['sample_codes'])): ?>
                                <div class="d-flex flex-wrap gap-1">
                                  <?php foreach ($product['sample_codes'] as $code): ?>
                                    <span class="badge badge-light-info fs-9"><?= htmlspecialchars($code) ?></span>
                                  <?php endforeach; ?>
                                  <?php if ($product['count_strg'] > count($product['sample_codes'])): ?>
                                    <span class="badge badge-light fs-9">... +<?= $product['count_strg'] - count($product['sample_codes']) ?> more</span>
                                  <?php endif; ?>
                                </div>
                              <?php else: ?>
                                <span class="text-muted">-</span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>
              
              <?php $row_index++; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center">Tidak ada data picking</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <?php if (!empty($picking_details)): ?>
    <div class="separator my-4"></div>
    
    <!-- Summary -->
    <?php
    $total_all_qty = 0;
    $total_already_inserted = 0;
    $total_not_inserted = 0;
    $total_all_available = 0;
    $total_all_shortage = 0;

    foreach ($picking_details as $picking) {
        foreach ($picking['products'] as $product) {
            $total_all_qty += $product['product_uom_qty'];
            $total_already_inserted += $product['already_inserted'];
            $total_not_inserted += $product['not_inserted'];
            $total_all_available += $product['count_strg'];
            $total_all_shortage += $product['shortage'];
        }
    }
    ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h6 class="mb-1">Ringkasan:</h6>
        <div class="fs-7">
          <span class="badge badge-light-primary">Total Qty SO: <?= $total_all_qty ?></span>
          <span class="badge badge-light-info ms-2">Sudah Insert: <?= $total_already_inserted ?></span>
          <?php if ($total_not_inserted > 0): ?>
            <span class="badge badge-light-warning ms-2">Belum Insert: <?= $total_not_inserted ?></span>
          <?php endif; ?>
          <span class="badge badge-light-success ms-2">Tersedia: <?= $total_all_available ?></span>
          <?php if ($total_all_shortage > 0): ?>
            <span class="badge badge-light-danger ms-2">Kurang: <?= $total_all_shortage ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <button class="btn btn-primary" id="btnInsertBarcode" data-shipping-id="<?= $shipping_id ?>" onclick="insertBarcodeToOdoo(this)">
          <i class="ki-duotone ki-check fs-4 me-1">
            <span class="path1"></span>
            <span class="path2"></span>
          </i>
          Insert Barcode ke Odoo
        </button>
      </div>
    </div>
    
    <?php if ($total_not_inserted > 0 || $total_all_shortage > 0): ?>
    <div class="alert alert-warning d-flex align-items-center p-2">
      <i class="ki-duotone ki-information fs-2 text-warning me-2">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
      </i>
      <div class="fs-7">
        <strong>Perhatian:</strong>
        <?php if ($total_not_inserted > 0): ?>
          Ada <?= $total_not_inserted ?> barcode yang tersedia tetapi belum diinsert ke Odoo.
        <?php endif; ?>
        <?php if ($total_all_shortage > 0): ?>
          <?php if ($total_not_inserted > 0) echo ' Dan '; ?>Ada <?= $total_all_shortage ?> item yang belum tersedia di production_lots_strg.
        <?php endif; ?>
        Proses insert akan tetap berjalan untuk item yang tersedia.
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
// Function to toggle manual stuffing details
window.toggleManualStuffingDetails = function(rowIndex) {
    const detailRow = document.getElementById('ms-detail-row-' + rowIndex);
    const icon = document.getElementById('ms-icon-' + rowIndex);
    
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

// Function untuk insert barcode ke Odoo (dipanggil via onclick)
window.insertBarcodeToOdoo = function(btn) {
    const shippingId = btn.dataset.shippingId;
    
    if (!shippingId) {
        alert('✗ Error: Shipping ID tidak ditemukan');
        return;
    }
    
    if (!confirm('Apakah Anda yakin ingin insert barcode ke Odoo?\n\nProses ini akan:\n1. Reset quantity_done menjadi 0\n2. Hapus move lines yang ada\n3. Insert barcode dari production_lots_strg')) {
        return;
    }
    
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
    
    fetch('shipping/process_manual_stuffing.php', {
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
                    text: data.message || 'Berhasil memproses barcode ke Odoo',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#50CD89'
                }).then(() => {
                    // Reload detail setelah user klik OK
                    const detailButton = document.querySelector('.btn-detail-manual-stuffing[data-id="' + shippingId + '"]');
                    if (detailButton) {
                        detailButton.click();
                    }
                });
            } else {
                alert('✓ ' + (data.message || 'Berhasil memproses barcode'));
                // Reload detail
                const detailButton = document.querySelector('.btn-detail-manual-stuffing[data-id="' + shippingId + '"]');
                if (detailButton) {
                    detailButton.click();
                }
            }
        } else {
            // Gunakan SweetAlert untuk error juga jika tersedia
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Terjadi kesalahan saat memproses',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#F1416C'
                });
            } else {
                alert('✗ Error: ' + (data.message || 'Terjadi kesalahan saat memproses'));
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
                text: error.message || 'Terjadi kesalahan saat memproses',
                confirmButtonText: 'OK',
                confirmButtonColor: '#F1416C'
            });
        } else {
            alert('✗ Error: ' + (error.message || 'Terjadi kesalahan saat memproses'));
        }
    });
};
</script>



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

?>
<div class="card p-3">
  <!-- Shipping Info -->
  <div class="row mb-3">
    <div class="col-md-6">
      <h6 class="fw-bold">Shipping Name</h6>
      <p><?= htmlspecialchars($shipping['name']) ?></p>
    </div>
    <div class="col-md-6">
      <h6 class="fw-bold">Scheduled Date</h6>
      <p><?= htmlspecialchars($shipping['sheduled_date']) ?></p>
    </div>
  </div>

  <!-- Batch Info -->
  <div class="row mb-3">
    <div class="col-md-6">
      <h6 class="fw-bold">Batch Description</h6>
      <p><?= htmlspecialchars($batch['description']) ?></p>
    </div>
    <div class="col-md-6">
      <h6 class="fw-bold">Ship To</h6>
      <p><?= htmlspecialchars($shipping['ship_to']) ?></p>
    </div>
  </div>

  <!-- Table Pickings -->
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Nama Picking</th>
          <th>Sale ID</th>
          <th>PO</th>
        </tr>
      </thead>
      <tbody>
        <?php if (is_array($pickings) && count($pickings) > 0): ?>
          <?php foreach ($pickings as $picking): ?>
            <tr>
              <td><?= htmlspecialchars($picking['name']) ?></td>
              <td>
                <?php
                if (is_array($picking['sale_id']) && count($picking['sale_id']) > 1) {
                  echo htmlspecialchars($picking['sale_id'][1]);
                } else {
                  echo '-';
                }
                ?>
              </td>
              <td>
                <?php
                $group_id = (is_array($picking['group_id']) && count($picking['group_id']) > 0) ? $picking['group_id'][0] : null;
                $po = $group_id ? ($po_map[$group_id] ?? null) : null;
                echo htmlspecialchars($po ?? '-');
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="3" class="text-center">Tidak ada data picking ditemukan</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
// Tutup koneksi
mysqli_close($conn);
?>
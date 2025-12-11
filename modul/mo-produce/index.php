<?php
require __DIR__ . '/../../inc/config_odoo.php';
require __DIR__ . '/../../inc/config.php';
require __DIR__ . '/../../inc/csrf.php';

$username = $_SESSION['username'] ?? null;
if (!$username) {
  header('Location: ../../index.php');
  exit;
}

$csrf_token = CSRF::generateToken();

$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lot_serial'])) {

  if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $message = "Invalid CSRF token.";
    $message_type = 'danger';
  } else {

    $lot_serial = trim($_POST['lot_serial']);

    if ($lot_serial !== '') {

      // 1️⃣ Cari MO berdasarkan lot_producing_id.name
      $productions = callOdooRead(
        $username,
        'mrp.production',
        [['lot_producing_id.name', '=', $lot_serial]],
        ['id', 'name', 'move_raw_ids', 'move_finished_ids', 'state', 'consumption', 'picking_ids']
      );

      if ($productions === false) {
        $message = "Error connecting to Odoo.";
        $message_type = 'danger';

      } elseif (empty($productions)) {
        $message = "No production found for lot $lot_serial.";
        $message_type = 'warning';

      } else {

        $production = $productions[0];
        $production_id = $production['id'];
        $production_name = $production['name'];
        $initial_state = $production['state'];

        // Jika MO sudah selesai
        if ($initial_state === 'done') {
          $message = "Production $production_name is already done.";
          $message_type = 'info';

        } else {

          // ======================================
          // 2️⃣ VALIDASI PICKING — STOP jika error
          // ======================================
          $picking_ids = $production['picking_ids'] ?? [];
          $picking_error = false;

          if (!empty($picking_ids)) {
            foreach ($picking_ids as $pick_id) {

              $pick = callOdooRead(
                $username,
                'stock.picking',
                [['id', '=', $pick_id]],
                ['id', 'state', 'cdp_is_done']
              );

              if (!$pick) continue;

              $p = $pick[0];
              $p_state = $p['state'];
              $p_cdp = $p['cdp_is_done'] ?? false;

              // VALIDASI ERROR
              if ($p_state === 'done' && !$p_cdp) {
                $message = "Silahkan Make MO WIP terlebih dahulu!.";
                $message_type = 'danger';
                $picking_error = true;
                break;
              }
            }
          }

          // ❗ Stop total jika error picking
          if ($picking_error) {
            // Tidak melanjutkan proses lain
          } 
          
          // ======================================
          // 3️⃣ LANJUT PROSES JIKA TIDAK ADA ERROR
          // ======================================
          else {

            // Ubah consumption menjadi flexible
            $update_consumption = callOdooWrite(
              $username,
              'mrp.production',
              [$production_id],
              ['consumption' => 'flexible']
            );

            if ($update_consumption === false) {
              $message = "Failed to update consumption to flexible for $production_name.";
              $message_type = 'danger';

            } else {

              $error_occurred = false;

              // 4️⃣ Update stock moves
              foreach ($production['move_raw_ids'] as $move_id) {

                $moves = callOdooRead(
                  $username,
                  'stock.move',
                  [['id', '=', $move_id]],
                  ['id', 'product_id', 'product_uom_qty', 'quantity', 'state', 'move_line_ids']
                );

                if (!$moves) { $error_occurred = true; break; }

                $move = $moves[0];
                $product_id = $move['product_id'][0];

                // Skip move cancel
                if ($move['state'] === 'cancel') continue;

                // product info
                $products = callOdooRead(
                  $username,
                  'product.product',
                  [['id', '=', $product_id]],
                  ['categ_id', 'display_name', 'purchase_ok']
                );

                if (!$products) { $error_occurred = true; break; }

                $product = $products[0];
                $category_id = $product['categ_id'][0] ?? 0;
                $purchase_ok = $product['purchase_ok'] ?? false;

                // Quantity plan
                $planned_quantity = $move['quantity'] > 0 ? $move['quantity'] : $move['product_uom_qty'];

                // LOGIC
                if ($category_id == 154) {
                  $quantity_to_set = $purchase_ok ? 0.0 : $planned_quantity;
                } else {
                  $quantity_to_set = 0.0;
                }

                // Write move
                $update_move = callOdooWrite(
                  $username,
                  'stock.move',
                  [$move_id],
                  [
                    'quantity' => $quantity_to_set,
                    'product_uom_qty' => $quantity_to_set,
                    'picked' => true
                  ]
                );

                if ($update_move === false) {
                  $error_occurred = true;
                  break;
                }
              }

              // 5️⃣ Mark done hanya jika semua sukses
              if (!$error_occurred) {
                $mark_done = callOdooRaw($username, 'mrp.production', 'button_mark_done', [[$production_id]]);

                if ($mark_done === false) {
                  $message = "Failed to complete production $production_name.";
                  $message_type = 'danger';

                } else {

                  sleep(2);
                  $final = callOdooRead($username, 'mrp.production', [['id', '=', $production_id]], ['state']);
                  $final_state = $final[0]['state'] ?? '';

                  if ($final_state === 'done' || $final_state === 'to_close') {
                    $message = "Production $production_name successfully completed.";
                    $message_type = 'success';
                  } else {
                    $message = "Production processed, but final state is $final_state.";
                    $message_type = 'warning';
                  }
                }

              } else {
                $message = "Error updating stock moves for production $production_name.";
                $message_type = 'danger';
              }
            }
          }
        }
      }
    } else {
      $message = "Please enter a lot serial number.";
      $message_type = 'warning';
    }
  }
}

// HISTORY
$todayWIB = date("Y-m-d");
$startUTC = gmdate("Y-m-d H:i:s", strtotime($todayWIB . " 00:00:00 -7 hours"));
$endUTC   = gmdate("Y-m-d H:i:s", strtotime($todayWIB . " 23:59:59 -7 hours"));

$history = callOdooRead(
  $username,
  'mrp.production',
  [
    ['state', 'in', ['done']],
    ['write_date', '>=', $startUTC],
    ['write_date', '<=', $endUTC],
    ['lot_producing_id.name', 'not ilike', 'PWIP%']
  ],
  ['name', 'origin', 'product_id', 'lot_producing_id', 'date_finished', 'state'],
  0,
  100,
  'date_finished desc'
);
?>


<!-- HTML UI -->
<div id="kt_app_content" class="app-content flex-column-fluid">
  <div id="kt_app_content_container" class="app-container container-fluid">
    <div class="row g-5 g-xxl-10">
      <div class="col-xxl-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Produce All Products</h3>
          </div>
          <div class="card-body">
            <?php if ($message): ?>
              <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
              <script>
                document.addEventListener('DOMContentLoaded', function() {
                  const el = document.querySelector('.alert');
                  if (el) setTimeout(() => el.style.display = 'none', 5000);
                });
              </script>
            <?php endif; ?>

            <form method="post" class="row g-3">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
              <div class="col-md-4">
                <label for="lot_serial" class="form-label">Lot Serial Number</label>
                <input type="text" class="form-control" id="lot_serial" name="lot_serial" placeholder="Scan or enter lot serial" required autofocus>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Produce All</button>
              </div>
            </form>

            <div class="mt-3">
              <small class="text-muted">
                <strong>Process:</strong> Set consumption to flexible → Update move lines (0 for non-WIP, planned for WIP) → Mark as done
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-5 g-xxl-10">
      <div class="col-xxl-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Production History</h3>
          </div>
          <div class="card-body">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th>Sale Order</th>
                  <th>Product</th>
                  <th>Lot Serial</th>
                  <th>Status</th>
                  <th>Date Finished</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $prod): ?>
                  <tr>
                    <td><?= htmlspecialchars($prod['origin'] ?? '') ?></td>
                    <td><?= htmlspecialchars($prod['product_id'][1] ?? '') ?></td>
                    <td><?= htmlspecialchars($prod['lot_producing_id'][1] ?? '') ?></td>
                    <td>
                      <span class="badge <?= $prod['state'] === 'done' ? 'bg-success' : 'bg-warning' ?>">
                        <?= htmlspecialchars($prod['state']) ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($prod['date_finished'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
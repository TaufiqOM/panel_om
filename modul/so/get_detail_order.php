<?php
session_start(); 

// File Conf
require __DIR__ . '/../../inc/config_odoo.php';
// Start Session
$username = $_SESSION['username'] ?? '';

// Ambil data id dari GET
$order_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if (!$order_id) {
  echo "Order ID tidak valid.";
  exit;
}

// Ambil detail order
$order_data = callOdooRead($username, "sale.order", [["id", "=", $order_id]], [
  "name",
  "partner_id",
  "partner_invoice_id",
  "partner_shipping_id",
  "date_order",
  "validity_date",
  "pricelist_id",
  "amount_total",
  "payment_term_id",
  "confirmation_date_order",
  "due_date_order",
  "count_revisi_order",
  "due_date_update_order",
  "date_revisi_order",
  "amount_total",
  "dp_order",
  "dp_blanket",
]);

$order = $order_data[0];

// Ambil order lines
$lines = callOdooRead($username, "sale.order.line", [["order_id", "=", $order_id]], [
  "product_id",
  "name",
  "product_uom_qty",
  "price_unit",
  "price_subtotal",
  "product_uom",
  "info_to_buyer",
  "info_to_production",
  "type_product",
]);
?>
<div class="card p-3">
  <!-- Customer Info -->
  <div class="row mb-3">
    <div class="col-md-6">
      <h6 class="fw-bold">Customer</h6>
      <p><?= htmlspecialchars($order['partner_id'][1]) ?></p>
    </div>
    <div class="col-md-6">
      <h6 class="fw-bold">Delivery Address</h6>
      <p><?= htmlspecialchars($order['partner_shipping_id'][1]) ?></p>
    </div>
  </div>

  <!-- Order Dates & Payments -->
  <div class="row mb-3">
    <div class="col-md-6">
      <p><strong>Order Date:</strong> <?= htmlspecialchars($order['date_order']) ?></p>
      <p><strong>Confirmation Date:</strong> <?= htmlspecialchars($order['confirmation_date'] ?? '-') ?></p>
      <p><strong>Due Date:</strong> <?= htmlspecialchars($order['due_date_order'] ?? '-') ?></p>
      <p><strong>Due Date Update:</strong> <?php htmlspecialchars($order['due_date_update_order']) ?? '-' ?></p>
    </div>
    <div class="col-md-6">
      <p><strong>Down Payment:</strong> <?php htmlspecialchars($order['dp_blanket']) ?? '-' ?></p>
      <p><strong>Order Down Payment:</strong> <?php htmlspecialchars($order['dp_order']) ?? '-' ?></p>
      <p><strong>Revisi Order:</strong> <?php htmlspecialchars($order['count_revisi_order']) ?? '-' ?></p>
      <p><strong>Date Revisi Order:</strong> <?php htmlspecialchars($order['date_revisi_order']) ?? '-' ?></p>
    </div>
  </div>

  <!-- Table Product -->
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Product</th>
          <th>Info To Buyer</th>
          <th>Info To Production</th>
          <th>Type Product</th>
          <th>Qty</th>
          <th>UoM</th>
          <th>Unit Price</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $line): ?>
          <tr>
            <td><?= is_array($line['product_id']) ? htmlspecialchars($line['product_id'][1]) : '-' ?></td>
            <td><?= htmlspecialchars($line['info_to_buyer']) ?></td>
            <td><?= htmlspecialchars($line['info_to_production']) ?></td>
            <td><?= htmlspecialchars($line['type_product']) ?></td>
            <td class="text-end"><?= htmlspecialchars($line['product_uom_qty']) ?></td>
            <td><?= is_array($line['product_uom']) ? htmlspecialchars($line['product_uom'][1]) : '-' ?></td>
            <td class="text-end"><?= number_format($line['price_unit'], 2) ?></td>
            <td class="text-end"><?= number_format($line['price_subtotal'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="7" class="text-end"><strong>Total:</strong></td>
          <td class="text-end"><strong><?= number_format($order['amount_total'], 2) ?></strong></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
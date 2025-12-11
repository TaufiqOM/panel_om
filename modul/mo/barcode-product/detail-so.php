<?php
// File Config odoo
require __DIR__ . '/../../../inc/config_odoo.php';
require __DIR__ . '/../../../inc/csrf.php';

$so_id = isset($_GET['order_no']) ? intval($_GET['order_no']) : 0;

if ($so_id <= 0) {
    die("ID Sales Order tidak valid.");
}

// Get session username
$username = $_SESSION['username'] ?? '';

// Get Detail Order
$get_data_order = callOdooRead($username, "sale.order", [["id", "=", $so_id]], [
    "id",
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
    "mrp_production_ids",
]);

$data_order = $get_data_order[0];

// Get order lines
$order_lines = callOdooRead($username, "sale.order.line", [["order_id", "=", $so_id]], [
    "id",
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
// Ambil data barcode per order line
$barcode_data = [];
$sql = "SELECT sale_order_line_id, prefix_code, last_number, qty_order 
        FROM barcode_lot 
        WHERE sale_order_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $so_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $barcode_data[$row['sale_order_line_id']] = $row;
}
$stmt->close();

// Generate CSRF token
$csrf_token = CSRF::generateToken();

?>

<!--begin::Main-->
<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
    <!--begin::Content wrapper-->
    <div class="d-flex flex-column flex-column-fluid">

        <!--begin::Content-->
        <div id="kt_app_content" class="app-content flex-column-fluid">

            <!--begin::Content container-->
            <div id="kt_app_content_container" class="app-container container-fluid">

                <!-- Sales Order Header -->
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <div class="me-0">
                            <button type="button" class="btn btn-secondary" onclick="history.back();">
                                &larr; Kembali
                            </button>
                        </div>
                        <h3 class="card-title mx-auto mb-0">Detail Sales Order #<?= htmlspecialchars($data_order['name'] ?: "-") ?></h3>
                    </div>

                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Customer: </strong> <span id="customerName"> <?= htmlspecialchars($data_order['partner_id'][1] ?: "-") ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Order Date: </strong> <?= htmlspecialchars($data_order['date_order'] ?: "-") ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Delivery Address: </strong><?= htmlspecialchars($data_order['partner_shipping_id'][1] ?: "-") ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Due Date: </strong> <?= htmlspecialchars($data_order['due_date_order'] ?: "-") ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Order Lines -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Order Produk</h4>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr class="text-center">
                                    <th>No</th>
                                    <th>Produk</th>
                                    <th>Qty Order</th>
                                    <th>Qty Generated</th>
                                    <th>Nomor Seri</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                foreach ($order_lines as $line):
                                    $line_id = $line['id'];
                                    $qty = $line['product_uom_qty'] ?? 0;
                                    // Lewati (jangan tampilkan) baris jika qty < 1
                                    if ($qty < 1) {
                                        continue;
                                    }
                                    $barcode = $barcode_data[$line_id]['prefix_code'] ?? '-';
                                    $last_no = $barcode_data[$line_id]['last_number'] ?? '-';
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $no ?></td>
                                        <td><?= is_array($line['product_id']) ? htmlspecialchars($line['product_id'][1]) : '-' ?></td>
                                        <td class="text-center"><?= htmlspecialchars($line['product_uom_qty']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($last_no) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($barcode) ?></td>
                                        <td class="text-center">
                                            <?php if ($last_no > 0): ?>
                                                <button type="button" class="btn btn-primary me-2 p-2 ps-3 pe-3" onclick="window.open('mo/barcode-product/print-barcode.php?line_id=<?= $line_id ?>', '_blank');">
                                                    <i class="ki-outline ki-printer text-dark fs-2"></i> Cetak
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-primary me-2 p-2 ps-3 pe-3" disabled>
                                                    <i class="ki-outline ki-printer text-dark fs-2"></i> Cetak
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-success p-2 ps-3 pe-3"
                                                onclick='openGenerateModal(
                                                    <?= json_encode($data_order["id"]) ?>,
                                                    <?= json_encode($line["id"]) ?>,
                                                    <?= json_encode($line["product_id"][0]) ?>,
                                                    <?= json_encode($line["product_uom_qty"]) ?>,
                                                    <?= json_encode($data_order["name"])  ?>,
                                                    <?= json_encode($line["name"]) ?>
                                                )'>
                                                <i class="ki-outline ki-gear fs-2"></i> Generate
                                            </button>
                                        </td>
                                    </tr>
                                <?php
                                    $no++;
                                endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Modal Generate -->
                <div class="modal fade" id="generateModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-3 shadow">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Generate Barcode</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p><strong>Sales Order:</strong> <span id="modalSO"></span></p>
                                <p><strong>Produk:</strong> <span id="modalProduct"></span></p>

                                <input type="number" id="idSo" class="form-control text-center" hidden>
                                <input type="number" id="idOrderpProd" class="form-control text-center" hidden>
                                <input type="number" id="idProduct" class="form-control text-center" hidden>
                                <input type="number" id="qtyOrder" class="form-control text-center" hidden>

                                <div class="input-group mt-3">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQty(-1)">-</button>
                                    <input type="number" id="QtyGenerate" class="form-control text-center" value="1" min="1">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQty(1)">+</button>
                                </div>
                                <!-- Nomor TTB -->
                                <div class="mt-3">
                                    <label class="form-label">Nomor TTB</label>
                                    <input type="number" id="NomorTTB" class="form-control text-center" placeholder="Masukkan Nomor TTB">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="button" class="btn btn-success" onclick="confirmGenerate()">Generate</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Content container-->
        </div>
        <!--end::Content-->
    </div>
    <!--end::Content wrapper-->
</div>
<!--end:::Main-->

<script>
    function confirmGenerate() {
        let id_so = parseInt(document.getElementById("idSo").value);
        let id_order_prod = parseInt(document.getElementById("idOrderpProd").value);
        let id_product = parseInt(document.getElementById("idProduct").value);
        let qty_order = parseInt(document.getElementById("qtyOrder").value);
        let qty_generate = parseInt(document.getElementById("QtyGenerate").value);
        let product_name = document.getElementById("modalProduct").textContent.trim();
        let customer_name = document.getElementById("customerName").textContent.trim();
        let so_name = document.getElementById("modalSO").textContent.trim();
        let no_ttb_raw = document.getElementById("NomorTTB").value.trim();
        if (isNaN(qty_generate) || qty_generate <= 0 || isNaN(id_so) || isNaN(id_product) || isNaN(qty_order)) {
            alert("Jumlah tidak valid!");
            return;
        }
        if (!no_ttb_raw ) {
            alert("Nomor TTB harus diisi!");
            return;
        }
        let no_ttb = "TTB " + no_ttb_raw.replace(/\s+/g, "");
        // Data yang dikirimkan
        let bodyData = {
            so_id: id_so,
            line_id: id_order_prod,
            customer_name: customer_name,
            product_id: id_product,
            product_name: product_name,
            so_name: so_name,
            qty_order: qty_order,
            qty: qty_generate,
            no_ttb: no_ttb,
            csrf_token: '<?= htmlspecialchars($csrf_token) ?>',
        };

        fetch("mo/barcode-product/generate-barcode.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams(bodyData)
            })
            .then(r => r.json())
            .then(res => {
                // hapus alert lama dulu biar tidak numpuk
                document.querySelectorAll("#generateModal .modal-body .alert").forEach(el => el.remove());
                // Add Alert HTML
                let modalBody = document.querySelector("#generateModal .modal-body");
                let alertBox = document.createElement("div");

                if (res.success) {
                    alertBox.className = "alert alert-success mt-3";
                    alertBox.innerHTML = `<strong>Sukses!</strong> ${res.message}`;
                    modalBody.appendChild(alertBox);

                    // Tutup modal setelah 1.5 detik
                    setTimeout(() => {
                        let modal = bootstrap.Modal.getInstance(document.getElementById("generateModal"));
                        modal.hide();
                    }, 1500);

                    // Open print page
                    window.open('mo/barcode-product/print-barcode.php?line_id=' + id_order_prod, '_blank');

                    // Reload halaman setelah 2 detik
                    setTimeout(() => location.reload(), 2000);
                } else {
                    // Alert Error
                    alertBox.className = "alert alert-danger mt-3";
                    alertBox.innerHTML = `<strong>Gagal!</strong> ${res.message}`;
                    modalBody.appendChild(alertBox);

                    // Hilangkan alert setelah 3 detik (modal tetap terbuka)
                    setTimeout(() => {
                        if (alertBox && alertBox.parentNode) {
                            alertBox.parentNode.removeChild(alertBox);
                        }
                    }, 3000);
                }
            });
        // log data
        // console.log("Data POST yang dikirim:", bodyData);
    }

    function openGenerateModal(soId, idOrderpProd, idProduct, qtyOrder, soName, productName) {
        document.getElementById("idSo").value = soId;
        document.getElementById("idOrderpProd").value = idOrderpProd;
        document.getElementById("idProduct").value = idProduct;
        document.getElementById("qtyOrder").value = qtyOrder;
        document.getElementById("modalSO").textContent = soName;
        document.getElementById("modalProduct").textContent = productName;
        document.getElementById("QtyGenerate").value = 1;

        let modal = new bootstrap.Modal(document.getElementById("generateModal"));
        modal.show();
    }

    function changeQty(val) {
        let qtyInput = document.getElementById("QtyGenerate");
        let qty = parseInt(qtyInput.value) || 1;
        qty = Math.max(1, qty + val);
        qtyInput.value = qty;
    }
</script>
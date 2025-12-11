<?php
require __DIR__ . '/../../inc/config_odoo.php';
require __DIR__ . '/../../inc/csrf.php';

// Start Session
$username = $_SESSION['username'] ?? '';

// Get data order
$orders = callOdooRead($username, "sale.order", [["state", "=", "sale"]], ["id", "name", "partner_id", "client_order_ref", "create_date", "user_id", "confirmation_date_order", "due_date_order", "due_date_update_order"]);

// Untuk deteksi format base64 karena beda format file beda base64 konvert
function detectMimeFromBase64($base64)
{
    $base64 = ltrim($base64);
    if (strpos($base64, 'iVBORw0KGgo') === 0) {
        return 'image/png';
    } elseif (strpos($base64, '/9j/') === 0) {
        return 'image/jpeg';
    } elseif (strpos($base64, 'PD94') === 0) {
        return 'image/svg+xml';
    } else {
        return 'application/octet-stream'; // fallback
    }
}

// Check sync status for each order - PERBAIKAN: Cek lebih longgar
$sync_status = [];
$order_lines_count = [];
if (is_array($orders) && count($orders) > 0) {
    foreach ($orders as $order) {
        $so_id = $order['id'];

        // Cek di database lokal: hitung berapa line yang sudah disync untuk SO ini
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM barcode_lot WHERE sale_order_id = ?");
        $stmt->bind_param("i", $so_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $local_count = $result['count'];
        $stmt->close();

        // Cek di Odoo: hitung berapa line yang ada di SO ini
        $lines = callOdooRead($username, "sale.order.line", [["order_id", "=", $so_id]], ["id"]);
        $odoo_count = is_array($lines) ? count($lines) : 0;

        // Simpan kedua count untuk digunakan nanti
        $order_lines_count[$so_id] = [
            'local' => $local_count,
            'odoo' => $odoo_count
        ];

        // PERBAIKAN: Status synced jika ada minimal 1 line yang sudah disync
        // Tidak perlu semua line sama, karena mungkin ada line yang tidak valid (qty = 0)
        $sync_status[$so_id] = ($local_count > 0);
    }
}

// Generate CSRF token
$csrf_token = CSRF::generateToken();
?>
<div class="app-main flex-column flex-row-fluid " id="kt_app_main">
    <div class="d-flex flex-column flex-column-fluid">
        <div id="kt_app_content" class="app-content  flex-column-fluid ">
            <div id="kt_app_content_container" class="app-container  container-fluid ">
                <!--begin::Row Order-->
                <div class="row g-5 g-xxl-10">
                    <!--begin::Col-->
                    <div class="col-xl-12 mb-5 mb-xxl-10">

                        <!--begin::Table Widget 3-->
                        <div class="card card-flush h-xl-100">
                            <!--begin::Card header-->
                            <div class="card-header py-7">
                                <!--begin::Tabs-->
                                <div class="card-title pt-3 mb-0 gap-4 gap-lg-10 gap-xl-15 nav nav-tabs border-bottom-0"
                                    data-kt-table-widget-3="tabs_nav">
                                    <!--begin::Tab item-->
                                    <div class="fs-4 fw-bold pb-3 border-bottom border-3 border-primary cursor-pointer"
                                        data-kt-table-widget-3="tab"
                                        data-kt-table-widget-3-value="Show All">
                                        All Order
                                    </div>
                                </div>
                                <!--end::Tabs-->

                                <!--begin::Filter button-->
                                <div class="card-toolbar">
                                    <!--begin::Filter & Search-->
                                    <div class="card-toolbar d-flex align-items-center gap-3">

                                        <!--begin::Search-->
                                        <div class="position-relative">
                                            <span class="svg-icon svg-icon-2 svg-icon-gray-500 position-absolute top-50 translate-middle-y ms-3">
                                                <!--begin::Icon (search)-->
                                                <i class="ki-duotone ki-magnifier fs-2 fs-lg-3 text-gray-800 position-absolute top-50 translate-middle-y me-5"><span class="path1"></span><span class="path2"></span></i>
                                                <!--end::Icon-->
                                            </span>
                                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search order..." />
                                        </div>
                                        <!--end::Search-->
                                    </div>
                                    <!--end::Filter & Search-->

                                    <!--begin::Sync button-->
                                    <button type="button" class="btn btn-primary" id="syncBtn">
                                        <i class="ki-duotone ki-sync fs-4 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Sync All Orders
                                    </button>
                                    <!--end::Sync button-->
                                </div>
                                <!--end::Filter button-->
                            </div>
                            <!--end::Card header-->

                            <!--begin::Card body-->
                            <div class="card-body pt-1">
                                <!-- Loader -->
                                <div id="skeletonLoader">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th><span class="placeholder col-6"></span></th>
                                                <th><span class="placeholder col-6"></span></th>
                                                <th><span class="placeholder col-4"></span></th>
                                                <th><span class="placeholder col-4"></span></th>
                                                <th><span class="placeholder col-6"></span></th>
                                                <th><span class="placeholder col-6"></span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- beberapa baris dummy -->
                                            <tr>
                                                <td><span class="placeholder col-8"></span></td>
                                                <td><span class="placeholder col-6"></span></td>
                                                <td><span class="placeholder col-4"></span></td>
                                                <td><span class="placeholder col-5"></span></td>
                                                <td><span class="placeholder col-7"></span></td>
                                                <td><span class="placeholder col-5"></span></td>
                                            </tr>
                                            <tr>
                                                <td><span class="placeholder col-8"></span></td>
                                                <td><span class="placeholder col-6"></span></td>
                                                <td><span class="placeholder col-4"></span></td>
                                                <td><span class="placeholder col-5"></span></td>
                                                <td><span class="placeholder col-7"></span></td>
                                                <td><span class="placeholder col-5"></span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!--begin::Table-->
                                <table id="ordersTable"
                                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                                    data-kt-table-widget-3="all" style="display:none;">
                                    <thead class="">
                                        <tr>
                                            <th>Number SO</th>
                                            <th>PO</th>
                                            <th>Customer</th>
                                            <th>Sales Person</th>
                                            <th>Due Date</th>
                                            <th>Sync Status</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php if (is_array($orders) && count($orders) > 0): ?>
                                            <?php foreach ($orders as $order): ?>
                                                <?php
                                                $is_synced = $sync_status[$order['id']] ?? false;
                                                $line_counts = $order_lines_count[$order['id']] ?? ['local' => 0, 'odoo' => 0];
                                                ?>
                                                <tr>
                                                    <td class="min-w-175px">
                                                        <div class="position-relative pe-3 py-2">
                                                            <a href="javascript:void(0);" class="mb-1 text-gray-900 text-hover-primary fw-bold btn-detail-order" data-id="<?= $order['id'] ?>" data-name="<?= htmlspecialchars($order['name']) ?>"> <?= htmlspecialchars($order['name']) ?></a>
                                                        </div>
                                                    </td>
                                                    <td class="min-w-175px">
                                                        <div class="position-relative pe-3 py-2">
                                                            <?= htmlspecialchars($order['client_order_ref'] ?? '-') ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <!--begin::Icons-->
                                                        <div class="d-flex gap-2 mb-2">
                                                            <?= is_array($order['partner_id']) ? htmlspecialchars($order['partner_id'][1]) : '-' ?>
                                                        </div>
                                                        <!--end::Icons-->
                                                    </td>
                                                    <td class="min-w-125px">
                                                        <!--begin::Team members-->
                                                        <div class="d-flex align-items-center">
                                                            <!-- Foto -->
                                                            <div class="symbol symbol-circle symbol-25px me-2">
                                                                <img src="/siomas-odoo/good/assets/media/avatars/300-6.jpg" alt="" />
                                                            </div>

                                                            <!-- Nama -->
                                                            <div class="fs-7 fw-bold text-muted">
                                                                <?= is_array($order['user_id']) ? htmlspecialchars($order['user_id'][1]) : '-' ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="min-w-150px">
                                                        <div class="mb-2 fw-bold"><?= htmlspecialchars($order['confirmation_date_order'] ?? '-') ?> - <?= htmlspecialchars($order['due_date_update_order'] ?? '-') ?></div>
                                                    </td>
                                                    <td class="min-w-150px">
                                                        <?php if ($is_synced): ?>
                                                            <span class="badge badge-light-success">
                                                                <i class="ki-duotone ki-check fs-4 me-1">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                </i>
                                                                Synced
                                                            </span>
                                                            <div class="text-muted fs-8 mt-1">
                                                                Lines: <?= $line_counts['local'] ?>/<?= $line_counts['odoo'] ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge badge-light-warning">
                                                                    <i class="ki-duotone ki-clock fs-4 me-1">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                    </i>
                                                                    Not Synced
                                                                </span>
                                                                <button type="button" class="btn btn-sm btn-primary btn-sync-single"
                                                                    data-so-id="<?= $order['id'] ?>"
                                                                    data-so-name="<?= htmlspecialchars($order['name']) ?>">
                                                                    <i class="ki-duotone ki-sync fs-4 me-1">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                    </i>
                                                                    Sync
                                                                </button>
                                                            </div>
                                                            <div class="text-muted fs-8 mt-1">
                                                                Lines: <?= $line_counts['local'] ?>/<?= $line_counts['odoo'] ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">Tidak ada data ditemukan</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <!--end::Table-->
                                </table>
                                <!--end::Table-->
                                <!-- begin:: modal detail order -->
                                <div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    Detail Order <span id="modalOrderName" class="text-primary fw-bold"> </span>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="text-center p-5">Loading...</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- end:: modal detail order -->

                            </div>
                            <!--end::Card body-->
                        </div>
                        <!--end::Table Widget 3-->
                    </div>
                    <!--end::Col-->
                </div>
                <!--end::Row Order-->
            </div>
            <!--end::Content container-->
        </div>
        <!--end::Content-->
    </div>
    <!--end::Content wrapper-->
</div>
<!--end:::Main-->

<script>
    document.addEventListener("DOMContentLoaded", function() {

        const detailButtons = document.querySelectorAll(".btn-detail-order");
        detailButtons.forEach(btn => {
            btn.addEventListener("click", function() {
                const orderId = this.dataset.id;
                const orderName = this.dataset.name;
                const modalBody = document.querySelector("#orderDetailModal .modal-body");
                const modalOrderName = document.getElementById("modalOrderName");

                // Update Judul Modal
                modalOrderName.textContent = "# " + orderName;

                modalBody.innerHTML = "<div class='text-center p-5'>Loading...</div>";

                fetch("so/get_detail_order.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: "id=" + encodeURIComponent(orderId)
                    })
                    .then(response => response.text())
                    .then(data => {
                        modalBody.innerHTML = data;
                    })
                    .catch(err => {
                        modalBody.innerHTML = "<div class='alert alert-danger'>Error load data</div>";
                        console.error(err);
                    });

                const modal = new bootstrap.Modal(document.getElementById("orderDetailModal"));
                modal.show();
            });
        });

        // Sync single order
        const syncSingleButtons = document.querySelectorAll(".btn-sync-single");
        syncSingleButtons.forEach(btn => {
            btn.addEventListener("click", function() {
                const soId = this.dataset.soId;
                const soName = this.dataset.soName;
                const btn = this;
                const originalText = btn.innerHTML;
                const parentTd = btn.closest('td');

                // Show loading state
                btn.innerHTML = '<i class="ki-duotone ki-loader fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Syncing...';
                btn.disabled = true;

                Swal.fire({
                    title: 'Syncing Order',
                    html: `Syncing order <strong>${soName}</strong>...`,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        fetch('manufacturing/sync_single_order.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: `so_id=${soId}&csrf_token=<?= htmlspecialchars($csrf_token) ?>`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Sync Completed',
                                        text: data.message,
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Sync Failed',
                                        text: data.message,
                                        confirmButtonText: 'OK'
                                    });
                                    // Restore button state on error
                                    btn.innerHTML = originalText;
                                    btn.disabled = false;
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'An error occurred during sync.',
                                    confirmButtonText: 'OK'
                                });
                                // Restore button state on error
                                btn.innerHTML = originalText;
                                btn.disabled = false;
                            });
                    }
                });
            });
        });

        // === Init DataTable ===
        if ($.fn.DataTable.isDataTable('#ordersTable')) {
            $('#ordersTable').DataTable().destroy();
        }
        var table = $('#ordersTable').DataTable({
            paging: true,
            lengthChange: false, // hide "show 10/25/50"
            searching: true, // tetap aktif agar search API jalan
            ordering: true,
            pageLength: 10,
            language: {
                paginate: {
                    previous: "Prev",
                    next: "Next"
                }
            },
            initComplete: function() {
                // Sembunyikan loader, tampilkan tabel setelah datatable selesai render
                document.getElementById("skeletonLoader").style.display = "none";
                document.getElementById("ordersTable").style.display = "table";
            }
        });

        // === Search Bar ===
        // Hubungkan input search custom dengan DataTables API
        $('#tableSearch').on('keyup', function() {
            table.search(this.value).draw();
        });

        // === Sync All Button ===
        document.getElementById('syncBtn').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;

            // Show loading state
            btn.innerHTML = '<i class="ki-duotone ki-loader fs-4 me-2"><span class="path1"></span><span class="path2"></span></i> Syncing...';
            btn.disabled = true;

            // Show progress modal
            Swal.fire({
                title: 'Syncing All Sale Orders',
                html: '<div class="progress mb-3"><div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%" id="syncProgress"></div></div><p id="syncStatus">Initializing sync process...</p>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    // AJAX call to sync
                    fetch('manufacturing/sync_sale_order.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'csrf_token=<?= htmlspecialchars($csrf_token) ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sync Completed',
                                    text: data.message,
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload(); // Reload to update sync status
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Sync Failed',
                                    text: data.message,
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred during sync.',
                                confirmButtonText: 'OK'
                            });
                        })
                        .finally(() => {
                            // Restore button state
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        });
                }
            });
        });
    
    });
</script>
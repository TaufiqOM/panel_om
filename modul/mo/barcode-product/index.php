<?php
require __DIR__ . '/../../../inc/config_odoo.php';

$username = $_SESSION['username'] ?? '';

// Get data all order
$orders = callOdooRead($username, "sale.order", [], ["id", "name", "partner_id", "create_date", "user_id", "confirmation_date_order", "due_date_order", "due_date_update_order", "client_order_ref"]);

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
?>

<!--begin::Main-->
<div class="app-main flex-column flex-row-fluid " id="kt_app_main">
    <!--begin::Content wrapper-->
    <div class="d-flex flex-column flex-column-fluid">

        <!--begin::Content-->
        <div id="kt_app_content" class="app-content  flex-column-fluid ">

            <!--begin::Content container-->
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
                                            </tr>
                                            <tr>
                                                <td><span class="placeholder col-8"></span></td>
                                                <td><span class="placeholder col-6"></span></td>
                                                <td><span class="placeholder col-4"></span></td>
                                                <td><span class="placeholder col-5"></span></td>
                                                <td><span class="placeholder col-7"></span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!--begin::Table-->
                                <table id="ordersTable"
                                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3 table-hover"
                                    data-kt-table-widget-3="all" style="display:none;">
                                    <thead class="">
                                        <tr>
                                            <th>Number SO</th>
                                            <th>PO</th>
                                            <th>Customer</th>
                                            <th>Sales Person</th>
                                            <th>Order Date</th>
                                            <th>Due Date</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php
                                        if (is_array($orders) && count($orders) > 0) :
                                            foreach ($orders as $order):
                                        ?>
                                                <tr onclick="window.location.href='?module=mo/barcode-product/detail-so&order_no=<?= $order['id'] ?>'" style="cursor:pointer;">
                                                    <td><?= htmlspecialchars($order['name'] ?: "-") ?></td>
                                                                                                        <td class="min-w-175px">
                                                        <div class="position-relative pe-3 py-2">
                                                            <?= htmlspecialchars($order['client_order_ref'] ?? '-') ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-2 mb-2">
                                                            <?= is_array($order['partner_id']) ? htmlspecialchars($order['partner_id'][1]) : '-' ?>
                                                        </div>
                                                    </td>
                                                    <td><?= is_array($order['user_id']) ? htmlspecialchars($order['user_id'][1]) : '-' ?></td>
                                                    <td><?= htmlspecialchars($order['create_date'] ?: "-")  ?></td>
                                                    <td>
                                                        <div class="mb-2 fw-bold"><?= htmlspecialchars($order['confirmation_date_order']) ?> - <?= htmlspecialchars($order['due_date_update_order']) ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>Tidak ada data ditemukan</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td>-</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <!--end::Table-->
                                </table>
                                <!--end::Table-->

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
        $('#tableSearch').on('keyup', function() {
            table.search(this.value).draw();
        });

    });
</script>
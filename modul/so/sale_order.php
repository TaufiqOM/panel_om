<?php
require __DIR__ . '/../../inc/config_odoo.php';

// Start Session
$username = $_SESSION['username'] ?? '';

// Get data order
$orders = callOdooRead($username, "sale.order", [], ["id", "name", "partner_id", "create_date", "user_id", "confirmation_date_order", "due_date_order", "due_date_update_order"]);

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


                    <!--begin::Filter button-->
                    <a href="#" class="text-hover-primary ps-4"
                        data-kt-menu-trigger="click"
                        data-kt-menu-placement="bottom-end">
                        <i class="ki-duotone ki-filter fs-2 text-gray-500"><span
                                class="path1"></span><span class="path2"></span></i>
                    </a>
                    <!--begin::Menu 1-->
                    <div class="menu menu-sub menu-sub-dropdown w-250px w-md-300px"
                        data-kt-menu="true" id="kt_menu_675dc1e117e49">
                        <!--begin::Header-->
                        <div class="px-7 py-5">
                            <div class="fs-5 text-gray-900 fw-bold">Filter Options
                            </div>
                        </div>
                        <!--end::Header-->

                        <!--begin::Form-->
                        <div class="px-7 py-5">
                            <!--begin::Input group-->
                            <div class="mb-10">
                                <!--begin::Label-->
                                <label
                                    class="form-label fw-semibold">Status:</label>
                                <!--end::Label-->

                                <!--begin::Input-->
                                <div>
                                    <select class="form-select form-select-solid"
                                        multiple data-kt-select2="true"
                                        data-close-on-select="false"
                                        data-placeholder="Select option"
                                        data-dropdown-parent="#kt_menu_675dc1e117e49"
                                        data-allow-clear="true">
                                        <option></option>
                                        <option value="1">Approved</option>
                                        <option value="2">Pending</option>
                                        <option value="2">In Process</option>
                                        <option value="2">Rejected</option>
                                    </select>
                                </div>
                                <!--end::Input-->
                            </div>
                            <!--end::Input group-->

                            <!--begin::Actions-->
                            <div class="d-flex justify-content-end">
                                <button type="reset"
                                    class="btn btn-sm btn-light btn-active-light-primary me-2"
                                    data-kt-menu-dismiss="true">Reset</button>

                                <button type="submit" class="btn btn-sm btn-primary"
                                    data-kt-menu-dismiss="true">Apply</button>
                            </div>
                            <!--end::Actions-->
                        </div>
                        <!--end::Form-->
                    </div>
                    <!--end::Menu 1--> <!--end::Filter button-->
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
                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead class="">
                        <tr>
                            <th>Number SO</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Sales Person</th>
                            <th>Due Date</th>
                            <!-- <th>Progress</th> -->
                            <!-- <th>Action</th> -->
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (is_array($orders) && count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $user = callOdooRead("res.users", [["id", "=", $order['user_id'][0] ?? 0]], ["id", "image_1920"]);
                                if ($user && is_array($user) && count($user) > 0) {
                                    $user_image = $user[0]['image_1920'] ?? null;
                                } else {
                                    $user_image = null; // fallback kalau kosong
                                }

                                $user_image = null;
                                $mime_type = null;

                                if ($user && is_array($user) && count($user) > 0) {
                                    $user_image = $user[0]['image_1920'] ?? null;
                                    if ($user_image) {
                                        $mime_type = detectMimeFromBase64($user_image);
                                    }
                                }

                                ?>
                                <tr>
                                    <td class="min-w-175px">
                                        <div class="position-relative pe-3 py-2">
                                            <a href="javascript:void(0);" class="mb-1 text-gray-900 text-hover-primary fw-bold btn-detail-order" data-id="<?= $order['id'] ?>" data-name="<?= htmlspecialchars($order['name']) ?>"> <?= htmlspecialchars($order['name']) ?></a>
                                        </div>
                                    </td>
                                    <td>
                                        <!--begin::Icons-->
                                        <div class="d-flex gap-2 mb-2">
                                            <?= is_array($order['partner_id']) ? htmlspecialchars($order['partner_id'][1]) : '-' ?>
                                        </div>
                                        <!--end::Icons-->

                                        <!-- <div class="fs-7 text-muted fw-bold">Labor 24 - 35 years</div> -->
                                    </td>
                                    <td>
                                        <span class="badge badge-light-success">Live</span>
                                    </td>
                                    <td class="min-w-125px">
                                        <!--begin::Team members-->
                                        <div class="d-flex align-items-center">
                                            <!-- Foto -->
                                            <div class="symbol symbol-circle symbol-25px me-2">
                                                <?php if ($user_image): ?>
                                                    <img src="data:<?= $mime_type ?>;base64,<?= $user_image ?>" alt="User" style="width: 30px; height:30px; border-radius:50%; object-fit:cover;" />
                                                <?php else: ?>
                                                    <img src="/good/assets/media/avatars/300-6.jpg" alt="" />
                                                <?php endif; ?>
                                            </div>

                                            <!-- Nama -->
                                            <div class="fs-7 fw-bold text-muted">
                                                <?= is_array($order['user_id']) ? htmlspecialchars($order['user_id'][1]) : '-' ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="min-w-150px">
                                        <div class="mb-2 fw-bold"><?= htmlspecialchars($order['confirmation_date_order']) ?> - <?= htmlspecialchars($order['due_date_update_order']) ?></div>
                                        <!-- <div class="fs-7 fw-bold text-muted">Date range</div> -->
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


    });
</script>
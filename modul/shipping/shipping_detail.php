<?php
require_once __DIR__ . '/../../inc/config.php';
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
                        All Shipping
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
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search shipping..." />
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
                <button class="btn btn-sm btn-primary btn-sync-shipping mb-5" id="syncShippingBtn">
                    <i class="ki-duotone ki-plus fs-4"></i> Sync Shipping from Odoo
                </button>

                <!-- Loader -->
                <div id="skeletonLoader">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><span class="placeholder col-6"></span></th>
                                <th><span class="placeholder col-6"></span></th>
                                <th><span class="placeholder col-4"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- beberapa baris dummy -->
                            <tr>
                                <td><span class="placeholder col-8"></span></td>
                                <td><span class="placeholder col-6"></span></td>
                                <td><span class="placeholder col-5"></span></td>
                            </tr>
                            <tr>
                                <td><span class="placeholder col-8"></span></td>
                                <td><span class="placeholder col-6"></span></td>
                                <td><span class="placeholder col-5"></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!--begin::Table-->
                <table id="shippingTable"
                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead class="">
                        <tr>
                            <th>No</th>
                            <th>Name</th>
                            <th>Scheduled Date</th>
                            <th>Description</th>
                            <th>Ship To</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // Query untuk mengambil data shipping
                        $sql = "SELECT id, name, sheduled_date, description, ship_to FROM shipping ORDER BY id";
                        $result = mysqli_query($conn, $sql);

                        if ($result && mysqli_num_rows($result) > 0):
                            $no = 1;
                        ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="min-w-50px">
                                        <div class="position-relative pe-3 py-2">
                                            <span class="mb-1 text-gray-900 fw-bold"><?= $no++ ?></span>
                                        </div>
                                    </td>
                                    <td class="min-w-150px">
                                        <div class="d-flex gap-2 mb-2">
                                            <a href="javascript:void(0);" class="badge badge-light-primary fs-7 btn-detail-shipping" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></a>
                                        </div>
                                    </td>
                                    <td class="min-w-200px">
                                        <span class="fw-bold text-primary"><?= $row['sheduled_date'] ? date('Y-m-d', strtotime($row['sheduled_date'])) : '-' ?></span>
                                    </td>
                                    <td class="min-w-200px">
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($row['description']) ?></span>
                                    </td>
                                    <td class="min-w-200px">
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($row['ship_to']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-icon btn-light-primary btn-print-shipping" 
                                                data-id="<?= $row['id'] ?>" 
                                                data-name="<?= htmlspecialchars($row['name']) ?>"
                                                title="Print">
                                            <i class="ki-duotone ki-printer fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Tidak ada data shipping ditemukan</td>
                            </tr>
                        <?php endif;

                        // Tutup koneksi
                        if ($result) {
                            mysqli_free_result($result);
                        }
                        mysqli_close($conn);
                        ?>
                    </tbody>
                    <!--end::Table-->
                </table>
                <!--end::Table-->

                <!-- begin:: modal detail shipping -->
                <div class="modal fade" id="shippingDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Detail Shipping <span id="modalShippingName" class="text-primary fw-bold"> </span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center p-5">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal detail shipping -->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<script>
// Script untuk menangani sync shipping
document.addEventListener('DOMContentLoaded', function() {
    // Tombol sync shipping
    document.getElementById('syncShippingBtn').addEventListener('click', function() {
        // Tampilkan loading state
        const submitBtn = document.getElementById('syncShippingBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Syncing...';
        submitBtn.disabled = true;

        // Kirim permintaan AJAX
        fetch('shipping/sync_shipping.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: ''
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Shipping berhasil disinkronkan!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    // Handle detail shipping modal
    const detailButtons = document.querySelectorAll(".btn-detail-shipping");
    detailButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            const shippingId = this.dataset.id;
            const shippingName = this.dataset.name;
            const modalBody = document.querySelector("#shippingDetailModal .modal-body");
            const modalShippingName = document.getElementById("modalShippingName");

            // Update Judul Modal
            modalShippingName.textContent = "# " + shippingName;

            modalBody.innerHTML = "<div class='text-center p-5'>Loading...</div>";

            fetch("shipping/get_detail_shipping.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: "id=" + encodeURIComponent(shippingId)
                })
                .then(response => response.text())
                .then(data => {
                    modalBody.innerHTML = data;
                })
                .catch(err => {
                    modalBody.innerHTML = "<div class='alert alert-danger'>Error load data</div>";
                    console.error(err);
                });

            const modal = new bootstrap.Modal(document.getElementById("shippingDetailModal"));
            modal.show();
        });
    });

    // Handle print shipping
    const printButtons = document.querySelectorAll(".btn-print-shipping");
    printButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            const shippingId = this.dataset.id;
            // Buka window baru untuk print
            window.open('shipping/print_shipping.php?id=' + encodeURIComponent(shippingId), '_blank');
        });
    });

    // Pagination will handle showing the table
});
</script>

<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>

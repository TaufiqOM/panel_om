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
                        All Suppliers
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
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search supplier..." />
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
                <button class="btn btn-sm btn-primary btn-sync-suppliers mb-5" id="syncSuppliersBtn">
                    <i class="ki-duotone ki-plus fs-4"></i> Sync Suppliers from Odoo
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
                <table id="suppliersTable"
                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead class="">
                        <tr>
                            <th>No</th>
                            <th>Supplier Name</th>
                            <th>Supplier Code</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // Query untuk mengambil data supplier
                        $sql = "SELECT id, supplier_code, supplier_name FROM supplier ORDER BY id";
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
                                            <span class="badge badge-light-primary fs-7"><?= htmlspecialchars($row['supplier_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="min-w-200px">
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($row['supplier_code']) ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Tidak ada data supplier ditemukan</td>
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

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<script>
// Script untuk menangani sync suppliers
document.addEventListener('DOMContentLoaded', function() {
    // Tombol sync suppliers
    document.getElementById('syncSuppliersBtn').addEventListener('click', function() {
        // Tampilkan loading state
        const submitBtn = document.getElementById('syncSuppliersBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Syncing...';
        submitBtn.disabled = true;

        // Kirim permintaan AJAX
        fetch('supplier/add_supplier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: ''
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Suppliers berhasil disinkronkan!');
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

    // Pagination will handle showing the table
});
</script>

<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>

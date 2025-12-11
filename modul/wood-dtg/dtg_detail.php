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
                        All Wood DTG
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
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search DTG..." />
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
                <table id="woodPalletsTable"
                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead class="">
                        <tr>
                            <th width="10%">No</th>
                            <th width="20%">Tanggal</th>
                            <th width="25%">Pallet</th>
                            <th width="25%">Jumlah Barcode</th>
                            <th width="20%">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // Query untuk mengambil data dari wood_solid_dtg dengan grouping berdasarkan group dan tanggal
                        $sql = "SELECT 
                                    wood_solid_group,
                                    wood_solid_dtg_date,
                                    operator_fullname,
                                    COUNT(wood_solid_barcode) as jumlah_barcode,
                                    GROUP_CONCAT(wood_solid_barcode ORDER BY wood_solid_barcode SEPARATOR ',') as barcodes
                                FROM wood_solid_dtg 
                                GROUP BY wood_solid_group, wood_solid_dtg_date, operator_fullname
                                ORDER BY wood_solid_dtg_date DESC, wood_solid_group ASC";
                        $result = mysqli_query($conn, $sql);
                        $no = 1;
                        if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): 
                                $wood_solid_group = $row['wood_solid_group'];
                                $wood_solid_dtg_date = $row['wood_solid_dtg_date'];
                                $operator_fullname = $row['operator_fullname'];
                                $jumlah_barcode = $row['jumlah_barcode'];
                                $barcodes = $row['barcodes'];
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td class="min-w-150px">
                                        <span class="mb-1 text-gray-900 fw-bold"><?= htmlspecialchars($wood_solid_dtg_date) ?></span>
                                        <br>
                                        <small class="text-muted">Operator: <?= htmlspecialchars($operator_fullname) ?></small>
                                    </td>
                                    <td class="min-w-150px">
                                        <span class="badge badge-light-primary fs-7"><?= htmlspecialchars($wood_solid_group) ?></span>
                                    </td>
                                    <td class="min-w-100px">
                                        <span class="fw-bold text-primary"><?= $jumlah_barcode ?> Barcode</span>
                                    </td>
                                    <td class="min-w-150px">
                                        <button class="btn btn-sm btn-primary btn-view-detail" 
                                                data-group="<?= htmlspecialchars($wood_solid_group) ?>"
                                                data-date="<?= htmlspecialchars($wood_solid_dtg_date) ?>"
                                                data-operator="<?= htmlspecialchars($operator_fullname) ?>"
                                                data-barcodes="<?= htmlspecialchars($barcodes) ?>"
                                                data-jumlah="<?= $jumlah_barcode ?>">
                                            <i class="ki-duotone ki-eye fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Tidak ada data DTG ditemukan</td>
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

                <!-- begin:: modal detail DTG -->
                <div class="modal fade" id="dtgDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Detail DTG - <span id="modalDtgGroup" class="text-primary fw-bold"></span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div id="detailContent">
                                    <!-- Content akan diisi oleh JavaScript -->
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal detail DTG -->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<script>
// Script untuk menangani aksi data DTG
document.addEventListener('DOMContentLoaded', function() {
    // Tombol view detail DTG
document.querySelectorAll('.btn-view-detail').forEach(button => {
    button.addEventListener('click', function() {
        const group = this.getAttribute('data-group');
        const date = this.getAttribute('data-date');
        const operator = this.getAttribute('data-operator');
        const jumlah = this.getAttribute('data-jumlah');
        
        // Set judul modal
        document.getElementById('modalDtgGroup').textContent = group;
        
        // Tampilkan loading
        const content = document.getElementById('detailContent');
        content.innerHTML = `
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Memuat data detail...</p>
            </div>
        `;
        
        // Ambil data detail via AJAX
        fetch(`wood-dtg/get_dtg_detail.php?group=${encodeURIComponent(group)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Tampilkan data detail
                    content.innerHTML = `
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <strong>Pallet Group:</strong> <span class="text-primary">${group}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Tanggal DTG:</strong> <span class="text-primary">${date}</span>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <strong>Operator:</strong> <span class="text-primary">${operator}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Jumlah Barcode:</strong> <span class="text-primary">${jumlah} item</span>
                            </div>
                        </div>
                        
                        <hr>
                        <h6>Daftar Barcode:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="10%">No</th>
                                        <th width="45%">Kode Barcode</th>
                                        <th width="45%">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.barcodes.map((item, index) => `
                                        <tr>
                                            <td align="center">${index + 1}</td>
                                            <td>${item.wood_solid_barcode}</td>
                                            <td>${item.wood_solid_dtg_time}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    content.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan saat memuat data</div>`;
            });
        
        // Tampilkan modal
        const modal = new bootstrap.Modal(document.getElementById('dtgDetailModal'));
        modal.show();
    });
});
    
});
</script>
<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>
</script>
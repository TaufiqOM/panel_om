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
                        All Wood LPB
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
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search LPB..." />
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
                            <th width="5%">No</th>
                            <th width="13%">Nomor LPB</th>
                            <th width="15%">Nomor PO</th>
                            <th width="15%">Tanggal Invoice</th>
                            <th width="13%">Jenis Kayu</th>
                            <th width="15%">Supplier</th>
                            <th width="10%">Tanggal Dibuat</th>
                            <th width="14%">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // Query untuk mengambil data dari wood_solid_lpb
                        $sql = "SELECT 
                                    wood_solid_lpb_id,
                                    wood_solid_lpb_number,
                                    wood_solid_lpb_po,
                                    wood_solid_lpb_date_invoice,
                                    wood_name,
                                    supplier_name,
                                    created_at,
                                    updated_at
                                FROM wood_solid_lpb 
                                ORDER BY created_at DESC, wood_solid_lpb_number ASC";
                        $result = mysqli_query($conn, $sql);
                        $no = 1;
                        if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): 
                                $wood_solid_lpb_id = $row['wood_solid_lpb_id'];
                                $wood_solid_lpb_number = $row['wood_solid_lpb_number'];
                                $wood_solid_lpb_po = $row['wood_solid_lpb_po'];
                                $wood_solid_lpb_date_invoice = $row['wood_solid_lpb_date_invoice'];
                                $wood_name = $row['wood_name'];
                                $supplier_name = $row['supplier_name'];
                                $created_at = date('d/m/Y', strtotime($row['created_at']));
                                $updated_at = $row['updated_at'];
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td class="min-w-150px">
                                        <span class="badge badge-light-primary fs-7"><?= htmlspecialchars($wood_solid_lpb_number) ?></span>
                                    </td>
                                    <td class="min-w-150px">
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($wood_solid_lpb_po) ?></span>
                                    </td>
                                    <td class="min-w-100px">
                                        <span class="mb-1 text-gray-900 fw-bold"><?= htmlspecialchars($wood_solid_lpb_date_invoice) ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($wood_name); ?></td>
                                    <td><?php echo htmlspecialchars($supplier_name); ?></td>
                                    <td><?php echo $created_at; ?></td>
                                    <td class="min-w-150px">
                                        <button class="btn btn-sm btn-primary btn-view-detail" 
                                                data-id="<?= $wood_solid_lpb_id ?>"
                                                data-lpb-number="<?= htmlspecialchars($wood_solid_lpb_number) ?>">
                                            <i class="ki-duotone ki-eye fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i> Detail
                                        </button>
                                        
                                        <button class="btn btn-sm btn-success btn-cetak-laporan" 
                                                data-id="<?= $wood_solid_lpb_id ?>"
                                                data-lpb-number="<?= htmlspecialchars($wood_solid_lpb_number) ?>">
                                            <i class="ki-duotone ki-printer fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i> Cetak
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Tidak ada data LPB ditemukan</td>
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

                <!-- begin:: modal detail LPB -->
                <div class="modal fade" id="lpbDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Detail LPB - <span id="modalLpbNumber" class="text-primary fw-bold"></span>
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
                <!-- end:: modal detail LPB -->

                <!-- begin:: modal pilihan cetak -->
                <div class="modal fade" id="cetakLaporanModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-sm modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Pilih Jenis Laporan</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <input type="hidden" id="selectedLpbId">
                                <input type="hidden" id="selectedLpbNumber">
                                
                                <div class="d-grid gap-3">
                                    <button type="button" class="btn btn-primary btn-lg" id="btnCetakLPB">
                                        <i class="ki-duotone ki-notepad text-gray-900 fs-2tx">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                        <br>
                                        Cetak LPB
                                    </button>
                                    <button type="button" class="btn btn-primary btn-lg" id="btnCetakDetailLPB">
                                        <i class="ki-duotone ki-notepad text-gray-900 fs-2tx">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                        <br>
                                        Cetak Detail LPB
                                    </button>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal pilihan cetak -->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<script>
// Script untuk menangani aksi data LPB
document.addEventListener('DOMContentLoaded', function() {
    // Tombol view detail LPB
    document.querySelectorAll('.btn-view-detail').forEach(button => {
        button.addEventListener('click', function() {
            const lpbId = this.getAttribute('data-id');
            const lpbNumber = this.getAttribute('data-lpb-number');
            
            // Set judul modal
            document.getElementById('modalLpbNumber').textContent = lpbNumber;
            
            // Tampilkan loading state
            const content = document.getElementById('detailContent');
            content.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div>Memuat data LPB...</div>
                </div>
            `;
            
            // Tampilkan modal
            const modal = new bootstrap.Modal(document.getElementById('lpbDetailModal'));
            modal.show();
            
            // Ambil data detail dari server via AJAX
            fetch('wood-lpb/get_lpb_detail.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${encodeURIComponent(lpbId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Tampilkan data detail
                    content.innerHTML = `
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <strong>Nomor LPB:</strong> <span class="text-primary">${data.lpb.wood_solid_lpb_number}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Nomor PO:</strong> <span class="text-primary">${data.lpb.wood_solid_lpb_po}</span>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <strong>Tanggal Invoice:</strong> <span class="text-primary">${data.lpb.wood_solid_lpb_date_invoice}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Supplier:</strong> <span class="text-primary">${data.lpb.supplier_name}</span>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <strong>Jenis Kayu:</strong> <span class="text-primary">${data.lpb.wood_name}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Dibuat pada:</strong> <span class="text-primary">${data.lpb.created_at}</span>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <strong>Diupdate pada:</strong> <span class="text-primary">${data.lpb.updated_at}</span>
                            </div>
                        </div>
                        
                        <hr>
                        <h6>Item yang termasuk dalam LPB ini:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Barcode</th>
                                        <th>Jenis Kayu</th>
                                        <th>Dimensi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.details && data.details.length > 0 ? 
                                        data.details.map((detail, index) => `
                                            <tr>
                                                <td>${index + 1}</td>
                                                <td>${detail.wood_solid_barcode || '-'}</td>
                                                <td>${detail.wood_name || '-'}</td>
                                                <td>${detail.wood_solid_height || '-'}×${detail.wood_solid_width || '-'}×${detail.wood_solid_length || '-'} cm</td>
                                            </tr>
                                        `).join('') : 
                                        '<tr><td colspan="4" class="text-center">Tidak ada item detail</td></tr>'
                                    }
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    content.innerHTML = `
                        <div class="alert alert-danger">${data.message || 'Gagal memuat data LPB'}</div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = `
                    <div class="alert alert-danger">Terjadi kesalahan saat memuat data LPB</div>
                `;
            });
        });
    });
    
    // Tombol cetak laporan
    document.querySelectorAll('.btn-cetak-laporan').forEach(button => {
        button.addEventListener('click', function() {
            const lpbId = this.getAttribute('data-id');
            const lpbNumber = this.getAttribute('data-lpb-number');
            
            // Set data ke modal pilihan cetak
            document.getElementById('selectedLpbId').value = lpbId;
            document.getElementById('selectedLpbNumber').value = lpbNumber;
            
            // Tampilkan modal pilihan cetak
            const modal = new bootstrap.Modal(document.getElementById('cetakLaporanModal'));
            modal.show();
        });
    });
    
    // Tombol Cetak LPB
    document.getElementById('btnCetakLPB').addEventListener('click', function() {
        const lpbId = document.getElementById('selectedLpbId').value;
        const lpbNumber = document.getElementById('selectedLpbNumber').value;
        
        // Redirect ke halaman cetak LPB
        window.open(`wood-lpb/print_out_lpb.php?number=${lpbNumber}`, '_blank');
        
        // Tutup modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('cetakLaporanModal'));
        modal.hide();
    });

    // Tombol Cetak Detail LPB
    document.getElementById('btnCetakDetailLPB').addEventListener('click', function() {
        const lpbId = document.getElementById('selectedLpbId').value;
        const lpbNumber = document.getElementById('selectedLpbNumber').value;
        
        // Redirect ke halaman cetak LPB
        window.open(`wood-lpb/print_out_detail_lpb.php?number=${lpbNumber}`, '_blank');
        
        // Tutup modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('cetakLaporanModal'));
        modal.hide();
    });
    
});
</script>
<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>

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
                        All Wood Barcodes
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
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search Barcode..." />
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
                <button class="btn btn-sm btn-primary btn-add-wood mb-5" data-bs-toggle="modal" data-bs-target="#addWoodModal">
                    <i class="ki-duotone ki-plus fs-4"></i> Tambah Barcode
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
                <table id="woodSolidTable"
                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead class="">
                        <tr>
                            <th>Tanggal Dibuat</th>
                            <th>Kode Barcode</th>
                            <th>Jumlah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // Query untuk mengambil data wood_solid dengan grouping berdasarkan tanggal
                        $sql = "SELECT 
                                DATE(created_at) as created_date,
                                COUNT(*) as total_barcodes,
                                GROUP_CONCAT(wood_solid_barcode ORDER BY wood_solid_barcode SEPARATOR ',') as barcodes,
                                MIN(wood_solid_barcode) as first_code,
                                MAX(wood_solid_barcode) as last_code
                                FROM wood_solid 
                                GROUP BY DATE(created_at) 
                                ORDER BY created_date DESC";
                        $result = mysqli_query($conn, $sql);
                        
                        if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): 
                                $created_date = $row['created_date'];
                                $total_barcodes = $row['total_barcodes'];
                                $first_code = $row['first_code'];
                                $last_code = $row['last_code'];
                                
                                // Format range kode barcode
                                $display_code = $first_code;
                                if ($first_code != $last_code) {
                                    $display_code = $first_code . " - " . $last_code;
                                }
                            ?>
                                <tr>
                                    <td class="min-w-150px">
                                        <div class="position-relative pe-3 py-2">
                                            <span class="mb-1 text-gray-900 fw-bold"><?= htmlspecialchars($created_date) ?></span>
                                        </div>
                                    </td>
                                    <td class="min-w-200px">
                                        <div class="d-flex gap-2 mb-2">
                                            <span class="fw-bold text-primary"><?= htmlspecialchars($display_code) ?></span>
                                        </div>
                                        <div class="fs-7 text-muted">
                                            <?= $total_barcodes ?> barcode(s)
                                        </div>
                                    </td>
                                    <td class="min-w-100px">
                                        <span class="badge badge-light-primary fs-7"><?= $total_barcodes ?></span>
                                    </td>
                                    <td class="min-w-150px">
                                        <button class="btn btn-sm btn-primary btn-view-wood" 
                                                data-date="<?= $created_date ?>"
                                                data-codes="<?= htmlspecialchars($row['barcodes']) ?>">
                                            <i class="ki-duotone ki-eye fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i> View
                                        </button>
                                        <a href="wood-barcode/print_out.php?date=<?= $created_date ?>" 
                                           class="btn btn-sm btn-success" target="_blank">
                                            <i class="ki-duotone ki-printer fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i> Cetak
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Tidak ada data barcode kayu ditemukan</td>
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

                <!-- begin:: modal detail wood solid -->
                <div class="modal fade" id="woodDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Detail Barcode Kayu - <span id="modalWoodDate" class="text-primary fw-bold"></span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="woodDetailTable">
                                        <thead>
                                            <tr>
                                                <th>Kode Barcode</th>
                                                <!-- Kolom tanggal dihapus sesuai permintaan -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data akan diisi oleh JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal detail wood solid -->

                <!-- begin:: modal tambah wood solid -->
                <div class="modal fade" id="addWoodModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Tambah Barcode Kayu Baru</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addWoodForm">
                                    <div class="mb-3">
                                        <label for="woodCount" class="form-label">Jumlah Barcode</label>
                                        <input type="number" class="form-control" id="woodCount" min="1" max="100" value="1" required>
                                        <div class="form-text">Masukkan jumlah barcode yang akan ditambahkan (1-100)</div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="button" class="btn btn-primary" id="submitAddWood">Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal tambah wood solid -->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<script>
// Script untuk menangani aksi view, print, dan tambah barcode kayu
document.addEventListener('DOMContentLoaded', function() {
    // Tombol view wood solid
    document.querySelectorAll('.btn-view-wood').forEach(button => {
        button.addEventListener('click', function() {
            const woodDate = this.getAttribute('data-date');
            const woodCodes = this.getAttribute('data-codes').split(',');
            
            document.getElementById('modalWoodDate').textContent = woodDate;
            
            // Isi tabel detail
            const tbody = document.querySelector('#woodDetailTable tbody');
            tbody.innerHTML = '';
            
            woodCodes.forEach(code => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${code}</td>
                `;
                tbody.appendChild(row);
            });
            
            // Tampilkan modal
            const modal = new bootstrap.Modal(document.getElementById('woodDetailModal'));
            modal.show();
        });
    });
    
    // Tombol tambah wood solid
    document.getElementById('submitAddWood').addEventListener('click', function() {
        const count = parseInt(document.getElementById('woodCount').value);
        
        if (count < 1 || count > 100) {
            alert('Jumlah barcode harus antara 1 dan 100');
            return;
        }
        
        // Tampilkan loading state
        const submitBtn = document.getElementById('submitAddWood');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...';
        submitBtn.disabled = true;
        
        // Kirim permintaan AJAX
        fetch('wood-barcode/add_barcode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `count=${count}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Tutup modal dan refresh halaman
                const modal = bootstrap.Modal.getInstance(document.getElementById('addWoodModal'));
                modal.hide();
                alert('Barcode kayu berhasil ditambahkan!');
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
    
});
</script>
<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>

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
                        All Wood Pallets
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
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search pallet..." />
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
                <button class="btn btn-sm btn-primary btn-add-pallet mb-5" data-bs-toggle="modal" data-bs-target="#addPalletModal">
                    <i class="ki-duotone ki-plus fs-4"></i> Tambah Pallet
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
                <table id="woodPalletsTable"
                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead class="">
                        <tr>
                            <th>Tanggal Dibuat</th>
                            <th>Kode Pallet</th>
                            <th>Jumlah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // Query untuk mengambil data wood_pallet dengan grouping berdasarkan tanggal
                        $sql = "SELECT 
                                DATE(created_at) as created_date,
                                COUNT(*) as total_pallets,
                                GROUP_CONCAT(wood_pallet_code ORDER BY wood_pallet_code SEPARATOR ',') as pallet_codes,
                                MIN(wood_pallet_code) as first_code,
                                MAX(wood_pallet_code) as last_code
                                FROM wood_pallet 
                                GROUP BY DATE(created_at) 
                                ORDER BY created_date DESC";
                        $result = mysqli_query($conn, $sql);
                        
                        if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): 
                                $created_date = $row['created_date'];
                                $total_pallets = $row['total_pallets'];
                                $first_code = $row['first_code'];
                                $last_code = $row['last_code'];
                                
                                // Format range kode pallet
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
                                            <?= $total_pallets ?> pallet(s)
                                        </div>
                                    </td>
                                    <td class="min-w-100px">
                                        <span class="badge badge-light-primary fs-7"><?= $total_pallets ?></span>
                                    </td>
                                    <td class="min-w-150px">
                                        <button class="btn btn-sm btn-primary btn-view-pallet" 
                                                data-date="<?= $created_date ?>"
                                                data-codes="<?= htmlspecialchars($row['pallet_codes']) ?>">
                                            <i class="ki-duotone ki-eye fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i> View
                                        </button>
                                        <a href="wood-pallet/print_out.php?date=<?= $created_date ?>" 
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
                                <td colspan="4" class="text-center">Tidak ada data pallet ditemukan</td>
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

                <!-- begin:: modal detail pallet -->
                <div class="modal fade" id="palletDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Detail Pallet - <span id="modalPalletDate" class="text-primary fw-bold"></span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="palletDetailTable">
                                        <thead>
                                            <tr>
                                                <th>Kode Pallet</th>
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
                <!-- end:: modal detail pallet -->

                <!-- begin:: modal tambah pallet -->
                <div class="modal fade" id="addPalletModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Tambah Pallet Baru</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addPalletForm">
                                    <div class="mb-3">
                                        <label for="palletCount" class="form-label">Jumlah Pallet</label>
                                        <input type="number" class="form-control" id="palletCount" min="1" max="100" value="1" required>
                                        <div class="form-text">Masukkan jumlah pallet yang akan ditambahkan (1-100)</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="palletPrefix" class="form-label">Prefix Kode</label>
                                        <input type="text" class="form-control" id="palletPrefix" value="<?= date('ym') ?>" required>
                                        <div class="form-text">Format: YYMM (contoh: <?= date('ym') ?> untuk <?= date('Y-m') ?>)</div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="button" class="btn btn-primary" id="submitAddPallet">Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal tambah pallet -->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<script>
// Script untuk menangani aksi view, print, dan delete
document.addEventListener('DOMContentLoaded', function() {
    // Tombol view pallet
    document.querySelectorAll('.btn-view-pallet').forEach(button => {
        button.addEventListener('click', function() {
            const palletDate = this.getAttribute('data-date');
            const palletCodes = this.getAttribute('data-codes').split(',');
            
            document.getElementById('modalPalletDate').textContent = palletDate;
            
            // Isi tabel detail
            const tbody = document.querySelector('#palletDetailTable tbody');
            tbody.innerHTML = '';
            
            palletCodes.forEach(code => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${code}</td>
                `;
                tbody.appendChild(row);
            });
            
            // Tampilkan modal
            const modal = new bootstrap.Modal(document.getElementById('palletDetailModal'));
            modal.show();
        });
    });
    
    // Tombol tambah pallet
    document.getElementById('submitAddPallet').addEventListener('click', function() {
        const count = parseInt(document.getElementById('palletCount').value);
        const prefix = document.getElementById('palletPrefix').value;
        
        if (count < 1 || count > 100) {
            alert('Jumlah pallet harus antara 1 dan 100');
            return;
        }
        
        if (!prefix || prefix.length !== 4) {
            alert('Prefix harus 4 karakter (YYMM)');
            return;
        }
        
        // Tampilkan loading state
        const submitBtn = document.getElementById('submitAddPallet');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...';
        submitBtn.disabled = true;
        
        // Kirim permintaan AJAX
        fetch('wood-pallet/add_pallet.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `count=${count}&prefix=${prefix}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Tutup modal dan refresh halaman
                const modal = bootstrap.Modal.getInstance(document.getElementById('addPalletModal'));
                modal.hide();
                alert('Pallet berhasil ditambahkan!');
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

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
                        All Wood Grade
                    </div>
                </div>
                <!--end::Tabs-->

                <!--begin::Filter button-->
                <div class="card-toolbar">
                    <!--begin::Filter & Search-->
                    <div class="card-toolbar d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-primary btn-sm" id="pullGradeDataBtn">
                            <i class="ki-duotone ki-cloud-download fs-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Tarik Data Grade
                        </button>
                        <!--begin::Search-->
                        <div class="position-relative">
                            <span class="svg-icon svg-icon-2 svg-icon-gray-500 position-absolute top-50 translate-middle-y ms-3">
                                <!--begin::Icon (search)-->
                                <i class="ki-duotone ki-magnifier fs-2 fs-lg-3 text-gray-800 position-absolute top-50 translate-middle-y me-5"><span class="path1"></span><span class="path2"></span></i>
                                <!--end::Icon-->
                            </span>
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search Grade..." />
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
                            <th width="7%">No</th>
                            <th width="15%">Tanggal</th>
                            <th width="20%">Operator</th>
                            <th width="20%">Jenis Kayu</th>
                            <th width="20%">Lokasi</th>
                            <th width="18%">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // Query untuk mengambil data wood_pallet dengan grouping berdasarkan tanggal dan lokasi
                        // TAMBAHKAN field wood_solid_grade_status untuk pengecekan
                        $sql = "SELECT 
                                    wood_solid_grade_date,
                                    location_name,
                                    operator_fullname,
                                    wood_name,
                                    COUNT(*) as total_records,
                                    GROUP_CONCAT(wood_solid_barcode ORDER BY wood_solid_barcode SEPARATOR ',') as barcodes,
                                    MAX(wood_solid_grade_status) as grade_status
                                FROM wood_solid_grade 
                                GROUP BY wood_solid_grade_date, location_name, operator_fullname, wood_name
                                ORDER BY wood_solid_grade_date DESC";
                        $result = mysqli_query($conn, $sql);
                        $no = 1;
                        if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): 
                                $wood_solid_grade_date = $row['wood_solid_grade_date'];
                                $operator_fullname = $row['operator_fullname'];
                                $wood_name = $row['wood_name'];
                                $location_name = $row['location_name'];
                                $total_records = $row['total_records'];
                                $barcodes = $row['barcodes'];
                                $grade_status = $row['grade_status']; // Status untuk pengecekan
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td class="min-w-150px">
                                        <div class="position-relative pe-3 py-2">
                                            <span class="mb-1 text-gray-900 fw-bold"><?= htmlspecialchars($wood_solid_grade_date) ?></span>
                                        </div>
                                    </td>
                                    <td class="min-w-200px">
                                        <div class="d-flex gap-2 mb-2">
                                            <span class="fw-bold text-primary"><?= htmlspecialchars($operator_fullname) ?></span>
                                        </div>
                                    </td>
                                    <td class="min-w-100px">
                                        <span class="badge badge-light-primary fs-7"><?= $wood_name; ?></span>
                                    </td>
                                    <td><?php echo $location_name; ?></td>
                                    <td class="min-w-150px">
                                        <button class="btn btn-sm btn-primary btn-view-grade" 
                                                data-date="<?= $wood_solid_grade_date ?>"
                                                data-location="<?= htmlspecialchars($location_name) ?>"
                                                data-codes="<?= htmlspecialchars($barcodes) ?>"
                                                data-wood="<?= htmlspecialchars($wood_name) ?>"
                                                data-operator="<?= htmlspecialchars($operator_fullname) ?>">
                                            <i class="ki-duotone ki-eye fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i> Detail
                                        </button>
                                        
                                        <?php if ($grade_status == '1'): ?>

                                        <?php else: ?>
                                            <!-- Tombol LPB ditampilkan jika status belum 1 -->
                                            <button class="btn btn-sm btn-success btn-create-lpb" 
                                                    data-date="<?= $wood_solid_grade_date ?>"
                                                    data-location="<?= htmlspecialchars($location_name) ?>"
                                                    data-wood="<?= htmlspecialchars($wood_name) ?>">
                                                <i class="ki-duotone ki-printer fs-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                    <span class="path5"></span>
                                                </i> Buat LPB
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Tidak ada data wood grade ditemukan</td>
                            </tr>
                        <?php endif; 
                        
                        // Tutup koneksi
                        if ($result) {
                            mysqli_free_result($result);
                        }
                        ?>
                    </tbody>
                    <!--end::Table-->
                </table>
                <!--end::Table-->

                <!-- begin:: modal detail wood grade -->
                <div class="modal fade" id="woodGradeDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Detail Wood Grade - <span id="modalGradeDate" class="text-primary fw-bold"></span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <strong>Lokasi:</strong> <span id="modalGradeLocation" class="text-primary"></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Jenis Kayu:</strong> <span id="modalGradeWood" class="text-primary"></span>
                                    </div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <strong>Operator:</strong> <span id="modalGradeOperator" class="text-primary"></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Total Barcode:</strong> <span id="modalGradeTotal" class="badge badge-light-primary"></span>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="woodGradeDetailTable">
                                        <thead>
                                            <tr>
                                                <th width="10%">No</th>
                                                <th width="30%">Kode Barcode</th>
                                                <th width="20%">Tinggi</th>
                                                <th width="20%">Lebar</th>
                                                <th width="20%">Panjang</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data akan diisi oleh JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal detail wood grade -->

                <!-- begin:: modal buat LPB -->
                <?php include 'modal_create_lpb.php'; ?>
                <!-- end:: modal buat LPB -->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<script>
// Script untuk menangani aksi view detail wood grade
document.addEventListener('DOMContentLoaded', function() {
    // Tombol view wood grade
    document.querySelectorAll('.btn-view-grade').forEach(button => {
        button.addEventListener('click', function() {
            const gradeDate = this.getAttribute('data-date');
            const gradeLocation = this.getAttribute('data-location');
            const gradeCodes = this.getAttribute('data-codes').split(',');
            const gradeWood = this.getAttribute('data-wood');
            const gradeOperator = this.getAttribute('data-operator');
            
            // Set informasi header modal
            document.getElementById('modalGradeDate').textContent = gradeDate;
            document.getElementById('modalGradeLocation').textContent = gradeLocation;
            document.getElementById('modalGradeWood').textContent = gradeWood;
            document.getElementById('modalGradeOperator').textContent = gradeOperator;
            document.getElementById('modalGradeTotal').textContent = gradeCodes.length + ' barcode';
            
            // Tampilkan loading state
            const tbody = document.querySelector('#woodGradeDetailTable tbody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div>Memuat data...</div>
                    </td>
                </tr>
            `;
            
            // Tampilkan modal terlebih dahulu
            const modal = new bootstrap.Modal(document.getElementById('woodGradeDetailModal'));
            modal.show();
            
            // Ambil data detail dari server via AJAX
            fetch('wood-grade/get_wood_grade_detail.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `date=${encodeURIComponent(gradeDate)}&location=${encodeURIComponent(gradeLocation)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Isi tabel dengan data dari server
                    tbody.innerHTML = '';
                    
                    data.details.forEach((item, index) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${item.wood_solid_barcode}</td>
                            <td>${item.wood_solid_height || '-'} cm</td>
                            <td>${item.wood_solid_width || '-'} cm</td>
                            <td>${item.wood_solid_length || '-'} cm</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-danger">${data.message || 'Gagal memuat data'}</td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-danger">Terjadi kesalahan saat memuat data</td>
                    </tr>
                `;
            });
        });
    });
    
    // Tombol buat LPB - Hanya akan ada jika status belum 1
    document.querySelectorAll('.btn-create-lpb').forEach(button => {
        button.addEventListener('click', function() {
            const lpbDate = this.getAttribute('data-date');
            const lpbLocation = this.getAttribute('data-location');
            const lpbWood = this.getAttribute('data-wood');
            
            // Set data ke form modal
            document.getElementById('lpbDate').value = lpbDate;
            document.getElementById('lpbLocation').value = lpbLocation;
            document.getElementById('lpbWood').value = lpbWood;
            
            // Set informasi di alert box
            document.getElementById('lpbInfo').innerHTML = `
                Tanggal: <strong>${lpbDate}</strong><br>
                Lokasi: <strong>${lpbLocation}</strong><br>
                Jenis Kayu: <strong>${lpbWood}</strong>
            `;
            
            // Reset form
            document.getElementById('createLpbForm').reset();
            
            // Set tanggal invoice default ke hari ini
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('lpbDateInvoice').value = today;
            
            // Tampilkan modal
            const modal = new bootstrap.Modal(document.getElementById('createLpbModal'));
            modal.show();
        });
    });
    
    // Submit form buat LPB
    document.getElementById('submitCreateLpb').addEventListener('click', function() {
        const form = document.getElementById('createLpbForm');
        const formData = new FormData(form);
        
        // Validasi form
        if (!formData.get('lpb_number') || !formData.get('lpb_po') || !formData.get('lpb_date_invoice')) {
            alert('Harap lengkapi semua field yang wajib diisi!');
            return;
        }
        
        // Tampilkan loading state
        const submitBtn = document.getElementById('submitCreateLpb');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...';
        submitBtn.disabled = true;
        
        // Kirim data ke server
        fetch('wood-grade/create_lpb.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('LPB berhasil dibuat!');
                // Tutup modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('createLpbModal'));
                modal.hide();
                // Refresh halaman atau update tabel jika perlu
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

    // === SCRIPT BARU UNTUK MENANGANI MODAL DATE RANGE ===

    // 1. Inisialisasi Modal Bootstrap
    const dateRangeModalElement = document.getElementById('dateRangeModal');
    const dateRangeModal = new bootstrap.Modal(dateRangeModalElement);

    // 2. Inisialisasi Daterangepicker saat modal ditampilkan
    // Kita menggunakan jQuery ($) di sini karena Daterangepicker adalah plugin jQuery
    $('#dateRangePicker').daterangepicker({
        autoUpdateInput: false, // Jangan update otomatis agar placeholder terlihat
        locale: {
            cancelLabel: 'Clear',
            format: 'YYYY-MM-DD'
        }
    });

    // Event saat tanggal dipilih dan tombol 'Apply' diklik
    $('#dateRangePicker').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
    });

    // Event saat tombol 'Cancel' diklik
    $('#dateRangePicker').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
    });


    // 3. Tambahkan event listener ke tombol utama untuk MEMBUKA modal
    const pullDataBtn = document.getElementById('pullGradeDataBtn');
    if (pullDataBtn) {
        pullDataBtn.addEventListener('click', function() {
            // Reset input field setiap kali modal dibuka
            $('#dateRangePicker').val('');
            // Tampilkan modal
            dateRangeModal.show();
        });
    }

    // 4. Tambahkan event listener ke tombol 'Tarik Data' di DALAM MODAL
    const submitBtn = document.getElementById('submitPullDataBtn');
    submitBtn.addEventListener('click', function() {
        const dateRangeValue = document.getElementById('dateRangePicker').value;

        if (!dateRangeValue) {
            alert('Harap pilih rentang tanggal terlebih dahulu!');
            return;
        }

        // Ambil tanggal mulai dan akhir
        const dates = dateRangeValue.split(' - ');
        const startDate = dates[0];
        const endDate = dates[1];

        // Tampilkan loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            Memproses...
        `;

        // Siapkan data untuk dikirim
        const formData = new FormData();
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);

        // Kirim data ke server
        fetch('wood-grade/pull_grade_data.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Data grade berhasil ditarik!');
                dateRangeModal.hide(); // Sembunyikan modal
                location.reload(); // Muat ulang halaman
            } else {
                alert('Gagal: ' + (data.message || 'Terjadi kesalahan.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan koneksi saat menarik data.');
        })
        .finally(() => {
            // Kembalikan tombol ke keadaan semula
            submitBtn.disabled = false;
            submitBtn.innerHTML = `
                <i class="ki-duotone ki-cloud-download fs-5"><span class="path1"></span><span class="path2"></span></i>
                Tarik Data
            `;
        });
    });
    
});
</script>
<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>

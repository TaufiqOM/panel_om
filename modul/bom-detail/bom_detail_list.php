<?php
// Ambil bom_id dari URL
$bom_id = $_GET['bom_id'] ?? '';
$bom_components = [];
$error_message = '';

// Hanya jalankan query jika ada 'bom_id' di URL
if (!empty($bom_id)) {
    // Ambil data dari database berdasarkan bom_id
    $search_param = $bom_id . '-%';
    $sql = "SELECT * FROM bom_component WHERE bom_component_number LIKE ? ORDER BY bom_component_number ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        $bom_components = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error_message = "Gagal mempersiapkan query: " . $conn->error;
    }
    $conn->close();
}
?>

<div class="row g-5 g-xxl-10">
    <div class="col-xl-12 mb-5 mb-xxl-10">
        <div class="card card-flush h-xl-100">
            <div class="card-header py-7">
                <div class="card-title pt-3 mb-0">
                    <h3 class="fw-bold">Manajemen Bill of Materials (BOM)</h3>
                </div>
                <div class="card-toolbar gap-3">
                    <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#importBomModal">
                        <i class="ki-duotone ki-file-up fs-3"><span class="path1"></span><span class="path2"></span></i>
                        Import File CSV
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="printReportBtn" data-bs-toggle="modal" data-bs-target="#printReportModal">
                        <i class="ki-duotone ki-printer fs-3"><span class="path1"></span><span class="path2"></span></i>
                        Cetak Laporan
                    </button>
                </div>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-10">
                <h4 class="fw-semibold text-gray-800 mb-3">Pilih Opsi Manajemen BOM</h4>
                <p class="text-muted fs-6 mb-10">
                    Silakan pilih salah satu menu di bawah ini untuk melihat daftar material <br />atau daftar komponen penyusun produk.
                </p>
                <div class="d-flex justify-content-center gap-5">
                    <a href="bom-material/" class="btn btn-primary">
                        <i class="ki-duotone ki-abstract-26 fs-2"><span class="path1"></span><span class="path2"></span></i>
                        BOM Material
                    </a>
                    <a href="bom-component/?bom_id=<?php echo $bom_id; ?>" class="btn btn-primary">
                        <i class="ki-duotone ki-lots-shopping fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span><span class="path7"></span><span class="path8"></span></i>
                        BOM Component
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($bom_components)): ?>
<div class="row g-5 g-xxl-10">
    <div class="col-xl-12 mb-5 mb-xxl-10">
        <div class="card card-flush h-xl-100">
            <div class="card-header py-7">
                <div class="card-title">
                    <h3 class="fw-bold">Hasil Import BOM - Referensi: <span class="text-primary"><?= htmlspecialchars($bom_id) ?></span></h3>
                </div>
                 <div class="card-toolbar">
                    <div class="position-relative">
                        <i class="ki-duotone ki-magnifier fs-2 text-gray-800 position-absolute top-50 translate-middle-y ms-4"></i>
                        <input type="text" id="tableSearchInput" class="form-control form-control-sm form-control-solid ps-12 rounded-pill" placeholder="Cari di tabel..." />
                    </div>
                </div>
            </div>
            <div class="card-body pt-1">
                <table id="bomComponentTable" class="table table-row-dashed align-middle fs-6 gy-4 my-0">
                    <thead>
                        <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                            <th>No. Komponen</th>
                            <th>Nama Komponen</th>
                            <th>Grup</th>
                            <th>Bahan</th>
                            <th class="text-center">Qty</th>
                            <th>P x L x T</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bom_components as $item): ?>
                            <?php
                                $is_group_header = (strpos($item['bom_component_name'], 'CAB ') === 0 && (int)$item['bom_component_qty'] === 0);
                            ?>
                            <?php if ($is_group_header): ?>
                                <tr class="table-light">
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($item['bom_component_number']) ?></td>
                                    <td class="fw-bolder fs-6 text-gray-800" colspan="5">
                                        <i class="ki-duotone ki-abstract-26 fs-4 text-primary me-2"><span class="path1"></span><span class="path2"></span></i>
                                        <?= htmlspecialchars($item['bom_component_name']) ?>
                                    </td>
                                    <td class="d-none"></td>
                                    <td class="d-none"></td>
                                    <td class="d-none"></td>
                                    <td class="d-none"></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td><span class="badge badge-light-success"><?= htmlspecialchars($item['bom_component_number']) ?></span></td>
                                    <td><?= htmlspecialchars($item['bom_component_name']) ?></td>
                                    <td><span class="badge badge-light-info"><?= htmlspecialchars($item['bom_component_group']) ?></span></td>
                                    <td><?= htmlspecialchars($item['bom_component_bahan']) ?></td>
                                    <td class="text-center fw-bold"><?= htmlspecialchars($item['bom_component_qty']) ?></td>
                                    <td><?= htmlspecialchars($item['bom_component_panjang']) ?> x <?= htmlspecialchars($item['bom_component_lebar']) ?> x <?= htmlspecialchars($item['bom_component_tebal']) ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php elseif (!empty($bom_id) && empty($bom_components) && empty($error_message)): ?>
    <div class="alert alert-warning">Tidak ada data BOM ditemukan untuk Referensi <strong><?= htmlspecialchars($bom_id) ?></strong>.</div>
<?php elseif (!empty($error_message)): ?>
     <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>


<div class="modal fade" tabindex="-1" id="importBomModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Import BOM dari File CSV</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <form id="importBomForm">
                    <input type="hidden" name="bom_id" value="<?= htmlspecialchars($_GET['bom_id'] ?? '') ?>">
                    <div class="mb-5">
                        <label class="form-label required">Pilih File CSV</label>
                        <input type="file" class="form-control form-control-solid" name="bom_file" id="bomFileInput" accept=".csv" required>
                        <div class="form-text text-muted">Hanya file dengan format .csv yang diizinkan.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="uploadBomFileBtn">
                    <span class="indicator-label">Upload dan Proses</span>
                    <span class="indicator-progress">
                        Mohon tunggu... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" tabindex="-1" id="printReportModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Pilih Laporan untuk Dicetak</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <p class="text-muted">Pilih salah satu laporan di bawah ini untuk membukanya di tab baru.</p>
                <div id="reportListContainer" class="mt-5"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi DataTables jika tabel ada di halaman
    if (document.getElementById('bomComponentTable')) {
        const dataTable = $('#bomComponentTable').DataTable({
            "ordering": true,
            "paging": true,
            "info": true,
            "lengthChange": true,
            "language": { "search": "", "searchPlaceholder": "Cari di dalam tabel..." }
        });

        // Hubungkan input search kustom dengan DataTables
        $('#tableSearchInput').on('keyup', function(){
            dataTable.search(this.value).draw();
        });
    }

    // --- Logika untuk Modal Import CSV ---
    const uploadBtn = document.getElementById('uploadBomFileBtn');
    const importForm = document.getElementById('importBomForm');
    const bomFileInput = document.getElementById('bomFileInput');
    const importModal = new bootstrap.Modal(document.getElementById('importBomModal'));

    uploadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (!bomFileInput.files.length) {
            Swal.fire({ text: "Silakan pilih file CSV terlebih dahulu.", icon: "warning", buttonsStyling: false, confirmButtonText: "Ok, mengerti!", customClass: { confirmButton: "btn btn-primary" } });
            return;
        }

        uploadBtn.setAttribute('data-kt-indicator', 'on');
        uploadBtn.disabled = true;

        const formData = new FormData(importForm);
        
        fetch('bom-detail/import_csv.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ text: "File BOM berhasil diimpor! Halaman akan dimuat ulang.", icon: "success", buttonsStyling: false, confirmButtonText: "Selesai", customClass: { confirmButton: "btn btn-primary" }})
                .then(() => {
                    importModal.hide();
                    location.reload(); // Reload halaman untuk menampilkan tabel baru
                });
            } else {
                Swal.fire({ text: "Error: " + data.message, icon: "error", buttonsStyling: false, confirmButtonText: "Coba Lagi", customClass: { confirmButton: "btn btn-danger" } });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({ text: "Terjadi kesalahan teknis.", icon: "error", buttonsStyling: false, confirmButtonText: "Ok", customClass: { confirmButton: "btn btn-danger" } });
        })
        .finally(() => {
            uploadBtn.removeAttribute('data-kt-indicator');
            uploadBtn.disabled = false;
        });
    });

    // --- Logika untuk Modal Cetak Laporan ---
    const reportListContainer = document.getElementById('reportListContainer');
    const reports = [
        { name: 'Cetak Identitas', url: 'reports/print_identity.php', icon: 'ki-document' },
        { name: 'Monitoring BOM', url: 'reports/print_bom_monitoring.php', icon: 'ki-chart-line-star' },
    ];

    function populateReportList() {
        reportListContainer.innerHTML = '';
        reports.forEach(report => {
            const reportButton = document.createElement('a');
            reportButton.href = report.url + '?bom_id=<?= htmlspecialchars($bom_id) ?>'; // Tambahkan bom_id ke URL laporan
            reportButton.target = '_blank';
            reportButton.className = 'btn btn-outline btn-outline-dashed btn-outline-primary btn-active-light-primary d-block mb-3';
            reportButton.innerHTML = `<i class="ki-duotone ${report.icon} fs-2 me-2"></i> ${report.name}`;
            reportListContainer.appendChild(reportButton);
        });
    }

    populateReportList();
});
</script>
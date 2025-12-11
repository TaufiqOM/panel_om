<?php 
session_start();
require_once '../panel/session_manager.php';

// Cek session
$sessionCheck = SessionManager::checkEmployeeSession();
if (!$sessionCheck['logged_in'] || !SessionManager::checkSessionTimeout()) {
    header('Location: ../panel/');
    exit;
}

$employee = $sessionCheck['employee'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Permintaan Pengambilan Bahan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <!-- Konten Utama (Awalnya Disembunyikan) -->
    <div class="container py-4">        
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><i class="bi bi-clipboard-check"></i> Form Permintaan Pengambilan Bahan</h4>
                            <div class="employee-info mt-2">
                                <i class="bi bi-person-circle"></i> 
                                <span id="employeeNameDisplay"><?php echo htmlspecialchars($employee['name']); ?></span> | 
                                <span id="employeeIdDisplay"><?php echo htmlspecialchars($employee['id_employee']); ?></span> | 
                                <span id="employeeBarcodeDisplay"><?php echo htmlspecialchars($employee['barcode']); ?></span>
                                <a href="../panel/">
                                    <button type="button" class="logout-btn ms-2">Menu Utama</button>
                                </a>
                            </div>
                        </div>
                        <button type="button" class="btn btn-reprint" id="reprintToggleBtn">
                            <i class="bi bi-printer"></i> Cetak Ulang
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Form Utama -->
                        <div id="mainForm">
                            <form id="materialRequestForm">
                                <!-- Informasi Permintaan -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="form-title"><i class="bi bi-info-circle"></i> Informasi Permintaan</h5>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="operatorFullname" class="form-label">Nama Operator</label>
                                        <input type="text" class="form-control" id="operatorFullname" name="operator_fullname" value="<?php echo htmlspecialchars($employee['name']); ?>" required readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="soNumber" class="form-label">Nomor SO</label>
                                        <input type="text" class="form-control" id="soNumber" name="so_number" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="productCode" class="form-label">Kode Produk</label>
                                        <input type="text" class="form-control" id="productCode" name="product_code" required>
                                    </div>
                                </div>

                                <!-- Input Barcode -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="form-title"><i class="bi bi-upc-scan"></i> Input Barcode</h5>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label for="barcodeInput" class="form-label">Scan atau Masukkan Barcode Material</label>
                                        <input type="text" class="form-control" id="barcodeInput" placeholder="Tempatkan kursor di sini dan scan barcode material">
                                        <div class="alert alert-danger mt-2 alert-barcode" id="barcodeAlert" style="display: none;">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                            <span id="alertMessage"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-scan text-white w-100" id="scanBtn">
                                            <i class="bi bi-upc-scan"></i> Scan Barcode
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Daftar Material Ter-scan -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <h5 class="form-title"><i class="bi bi-list-check"></i> Daftar Material Ter-scan</h5>
                                        
                                        <div class="scanned-items" id="scannedItems">
                                            <div class="empty-state" id="emptyState">
                                                <i class="bi bi-inbox"></i>
                                                <h5>Belum ada barcode diinput</h5>
                                                <p>Scan barcode untuk menambahkan material ke daftar</p>
                                            </div>
                                            
                                            <div class="table-header" style="display: none;" id="tableHeader">
                                                <div class="table-row">
                                                    <div>Barcode</div>
                                                    <div>Jenis Kayu</div>
                                                    <div>T (mm)</div>
                                                    <div>P (mm)</div>
                                                    <div>L (mm)</div>
                                                    <div>Aksi</div>
                                                </div>
                                            </div>
                                            
                                            <div class="table-body" style="display: none;" id="tableBody">
                                                <!-- Item yang di-scan akan ditampilkan di sini -->
                                            </div>
                                        </div>
                                        
                                        <!-- Footer untuk total item -->
                                        <div class="total-footer" style="display: none;" id="totalFooter">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <span class="total-label">Total Item:</span>
                                                </div>
                                                <div class="col-md-6 text-end">
                                                    <span class="total-value" id="grandTotal">0</span> item
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                    <button type="reset" class="btn btn-reset me-md-2" id="resetBtn">Reset</button>
                                    <button type="submit" class="btn btn-submit">Submit</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Form Cetak Ulang -->
                        <div id="reprintForm" style="display: none;">
                            <div class="reprint-section">
                                <h5 class="form-title"><i class="bi bi-search"></i> Pencarian Berdasarkan Operator</h5>
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3">
                                        <label for="operatorSearch" class="form-label">Nama Operator</label>
                                        <input type="text" class="form-control" id="operatorSearch" placeholder="Masukkan nama operator" value="<?php echo htmlspecialchars($employee['barcode']); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-primary w-100" id="searchBtn">
                                            <i class="bi bi-search"></i> Cari
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="searchResults" style="display: none;">
                                    <h5 class="form-title"><i class="bi bi-list-ul"></i> Hasil Pencarian</h5>
                                    <div class="search-results mt-3" id="resultsContainer">
                                        <!-- Hasil pencarian akan ditampilkan di sini -->
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                    <button type="button" class="btn btn-reset me-md-2" id="backToMainBtn">Kembali ke Form Utama</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data karyawan dari PHP session
            const currentEmployee = {
                id: '<?php echo $employee["id"]; ?>',
                id_employee: '<?php echo $employee["id_employee"]; ?>',
                name: '<?php echo $employee["name"]; ?>',
                barcode: '<?php echo $employee["barcode"]; ?>',
                full_name: '<?php echo $employee["name"]; ?>'
            };

            // Elemen untuk autentikasi
            const employeeNameDisplay = document.getElementById('employeeNameDisplay');
            const employeeIdDisplay = document.getElementById('employeeIdDisplay');
            const employeeBarcodeDisplay = document.getElementById('employeeBarcodeDisplay');
            const operatorFullname = document.getElementById('operatorFullname');
            
            // Set nilai display dari data session PHP
            employeeNameDisplay.textContent = currentEmployee.name;
            employeeIdDisplay.textContent = currentEmployee.id_employee;
            employeeBarcodeDisplay.textContent = currentEmployee.barcode;
            operatorFullname.value = currentEmployee.name;
            
            // Elemen untuk form utama
            const barcodeInput = document.getElementById('barcodeInput');
            const emptyState = document.getElementById('emptyState');
            const tableHeader = document.getElementById('tableHeader');
            const tableBody = document.getElementById('tableBody');
            const scanBtn = document.getElementById('scanBtn');
            const totalFooter = document.getElementById('totalFooter');
            const grandTotalEl = document.getElementById('grandTotal');
            const resetBtn = document.getElementById('resetBtn');
            const barcodeAlert = document.getElementById('barcodeAlert');
            const alertMessage = document.getElementById('alertMessage');
            const reprintToggleBtn = document.getElementById('reprintToggleBtn');
            const mainForm = document.getElementById('mainForm');
            const reprintForm = document.getElementById('reprintForm');
            const operatorSearch = document.getElementById('operatorSearch');
            const searchBtn = document.getElementById('searchBtn');
            const searchResults = document.getElementById('searchResults');
            const resultsContainer = document.getElementById('resultsContainer');
            const backToMainBtn = document.getElementById('backToMainBtn');
            
            let scannedBarcodes = [];
            
            // Fungsi untuk toggle antara form utama dan form cetak ulang
            reprintToggleBtn.addEventListener('click', function() {
                if (mainForm.style.display === 'none') {
                    // Kembali ke form utama
                    mainForm.style.display = 'block';
                    reprintForm.style.display = 'none';
                    reprintToggleBtn.innerHTML = '<i class="bi bi-printer"></i> Cetak Ulang';
                } else {
                    // Buka form cetak ulang
                    mainForm.style.display = 'none';
                    reprintForm.style.display = 'block';
                    reprintToggleBtn.innerHTML = '<i class="bi bi-clipboard-check"></i> Form Utama';
                    operatorSearch.focus();
                }
            });
            
            backToMainBtn.addEventListener('click', function() {
                mainForm.style.display = 'block';
                reprintForm.style.display = 'none';
                reprintToggleBtn.innerHTML = '<i class="bi bi-printer"></i> Cetak Ulang';
            });
            
            // Fungsi untuk mencari PBP berdasarkan operator
            searchBtn.addEventListener('click', function() {
                const operatorName = operatorSearch.value.trim();
                
                if (!operatorName) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Masukkan nama operator terlebih dahulu!',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                searchBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Mencari...';
                searchBtn.disabled = true;
                
                fetch('search_pbp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'employee_nik=' + encodeURIComponent(operatorName)
                })
                .then(response => response.json())
                .then(data => {
                    searchBtn.innerHTML = '<i class="bi bi-search"></i> Cari';
                    searchBtn.disabled = false;
                    
                    if (data.success) {
                        if (data.pbp_list && data.pbp_list.length > 0) {
                            displayPbpList(data.pbp_list);
                        } else {
                            resultsContainer.innerHTML = '<div class="alert alert-info">Tidak ada data PBP ditemukan untuk operator ini.</div>';
                            searchResults.style.display = 'block';
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Terjadi kesalahan saat mencari data.',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    searchBtn.innerHTML = '<i class="bi bi-search"></i> Cari';
                    searchBtn.disabled = false;
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan saat mencari data.',
                        confirmButtonText: 'OK'
                    });
                });
            });
            
            // Fungsi untuk menampilkan daftar PBP
            function displayPbpList(pbpList) {
                resultsContainer.innerHTML = '';
                
                const table = document.createElement('div');
                table.className = 'table-responsive';
                
                let html = `
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No. PBP</th>
                                <th>No. SO</th>
                                <th>Kode Produk</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                pbpList.forEach(pbp => {
                    html += `
                        <tr class="pbp-item">
                            <td>${pbp.wood_solid_pbp_number}</td>
                            <td>${pbp.so_number}</td>
                            <td>${pbp.product_code}</td>
                            <td>${pbp.wood_solid_pbp_date}</td>
                            <td>${pbp.wood_solid_pbp_time}</td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-print" data-pbp="${pbp.wood_solid_pbp_number}">
                                    <i class="bi bi-printer"></i> Cetak
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += `</tbody></table>`;
                table.innerHTML = html;
                resultsContainer.appendChild(table);
                searchResults.style.display = 'block';
                
                // Tambahkan event listener untuk tombol cetak
                resultsContainer.querySelectorAll('.btn-print').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const pbpNumber = this.getAttribute('data-pbp');
                        window.open(`print_out_pbp.php?pbp_number=${pbpNumber}`, '_blank');
                    });
                });
            }
            
            // Fungsi-fungsi untuk form utama
            function isBarcodeAlreadyScanned(barcode) {
                return scannedBarcodes.includes(barcode);
            }
            
            function showAlert(message) {
                alertMessage.textContent = message;
                barcodeAlert.style.display = 'flex';
                barcodeInput.classList.add('is-invalid');
                barcodeAlert.classList.add('shake');
                
                setTimeout(() => {
                    barcodeAlert.classList.remove('shake');
                }, 500);
                
                barcodeInput.focus();
                barcodeInput.select();
            }
            
            function hideBarcodeAlert() {
                barcodeAlert.style.display = 'none';
                barcodeInput.classList.remove('is-invalid');
            }
            
            async function getMaterialFromDatabase(barcode) {
                try {
                    const response = await fetch('get_material.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'barcode=' + encodeURIComponent(barcode)
                    });
                    
                    if (!response.ok) throw new Error('Network response was not ok');
                    return await response.json();
                } catch (error) {
                    console.error('Error fetching material:', error);
                    return null;
                }
            }
            
            function calculateGrandTotal() {
                const total = tableBody.children.length;
                grandTotalEl.textContent = total;
                return total;
            }
            
            async function addScannedItem(barcode) {
                hideBarcodeAlert();
                
                if (isBarcodeAlreadyScanned(barcode)) {
                    showAlert(`Barcode "${barcode}" sudah pernah di-scan sebelumnya.`);
                    return false;
                }
                
                const material = await getMaterialFromDatabase(barcode);
                
                if (!material || material.error) {
                    if (material && material.error && material.error.includes('PBP Number')) {
                        showAlert(`Barcode "${barcode}" sudah di scan di ${material.error}`);
                    } else {
                        showAlert(`Barcode "${barcode}" tidak terdaftar dalam sistem.`);
                    }
                    return false;
                }
                
                if (emptyState) emptyState.style.display = 'none';
                
                tableHeader.style.display = 'block';
                tableBody.style.display = 'block';
                totalFooter.style.display = 'block';
                
                scannedBarcodes.push(barcode);
                
                const scannedItem = document.createElement('div');
                scannedItem.className = 'scanned-item';
                scannedItem.setAttribute('data-barcode', barcode);
                scannedItem.innerHTML = `
                    <div class="table-row">
                        <div><input type="text" class="form-control form-control-sm" value="${barcode}" readonly></div>
                        <div><input type="text" class="form-control form-control-sm" value="${material.wood_name || 'N/A'}" readonly></div>
                        <div><input type="text" class="form-control form-control-sm text-center" value="${material.thickness || 'N/A'}" readonly></div>
                        <div><input type="text" class="form-control form-control-sm text-center" value="${material.length || 'N/A'}" readonly></div>
                        <div><input type="text" class="form-control form-control-sm text-center" value="${material.width || 'N/A'}" readonly></div>
                        <div><button type="button" class="btn btn-sm btn-danger w-100 btn-remove"><i class="bi bi-trash"></i></button></div>
                    </div>
                `;
                
                tableBody.appendChild(scannedItem);
                
                scannedItem.querySelector('.btn-remove').addEventListener('click', function() {
                    const itemBarcode = scannedItem.getAttribute('data-barcode');
                    scannedBarcodes = scannedBarcodes.filter(bc => bc !== itemBarcode);
                    scannedItem.remove();
                    
                    if (tableBody.children.length === 0) {
                        emptyState.style.display = 'block';
                        tableHeader.style.display = 'none';
                        tableBody.style.display = 'none';
                        totalFooter.style.display = 'none';
                    } else {
                        calculateGrandTotal();
                    }
                });
                
                calculateGrandTotal();
                barcodeInput.value = '';
                barcodeInput.focus();
                return true;
            }
            
            // Event Listeners untuk form utama
            barcodeInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (barcodeInput.value.trim() !== '') {
                        addScannedItem(barcodeInput.value.trim());
                    }
                }
            });
            
            barcodeInput.addEventListener('input', hideBarcodeAlert);
            
            scanBtn.addEventListener('click', function() {
                if (barcodeInput.value.trim() !== '') {
                    addScannedItem(barcodeInput.value.trim());
                } else {
                    showAlert('Masukkan kode barcode terlebih dahulu');
                    barcodeInput.focus();
                }
            });
            
            resetBtn.addEventListener('click', function() {
                tableBody.innerHTML = '';
                scannedBarcodes = [];
                emptyState.style.display = 'block';
                tableHeader.style.display = 'none';
                tableBody.style.display = 'none';
                totalFooter.style.display = 'none';
                grandTotalEl.textContent = '0';
                hideBarcodeAlert();
            });
            
            // Submit Form dengan Redirect ke Halaman Cetak
            document.getElementById('materialRequestForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (tableBody.children.length === 0) {
                    showAlert('Harap scan minimal satu barcode material!');
                    return;
                }
                
                const soNumber = document.getElementById('soNumber').value;
                const productCode = document.getElementById('productCode').value;
                
                const scannedMaterials = [];
                const scannedItems = tableBody.querySelectorAll('.scanned-item');
                
                scannedItems.forEach(item => {
                    const inputs = item.querySelectorAll('input');
                    scannedMaterials.push({
                        barcode: inputs[0].value,
                        woodType: inputs[1].value,
                        thickness: inputs[2].value,
                        length: inputs[3].value,
                        width: inputs[4].value
                    });
                });
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
                submitBtn.disabled = true;
                
                fetch('submit_materials.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        materials: scannedMaterials,
                        so_number: soNumber,
                        product_code: productCode,
                        employee_nik: currentEmployee.barcode,
                        employee_id: currentEmployee.id_employee
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: `Data berhasil disimpan!<br>
                                   <strong>Nomor PBP:</strong> ${data.pbp_number}<br>
                                   <strong>Jumlah Material:</strong> ${data.saved_count} item`,
                            confirmButtonText: 'Cetak Laporan'
                        }).then((result) => {
                            // Redirect ke halaman cetak laporan dengan parameter PBP
                            window.location.href = `print_out_pbp.php?pbp_number=${data.pbp_number}`;
                        });
                    } else {
                        let errorMessage = data.message;
                        if (data.errors && data.errors.length > 0) {
                            errorMessage += '<br><br>Detail Error:<br>' + data.errors.join('<br>');
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            html: errorMessage,
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat mengirim data.',
                        confirmButtonText: 'OK'
                    });
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        });
    </script>
</body>
</html>
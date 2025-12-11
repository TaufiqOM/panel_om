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
    <title>Form Return Material</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="style.css">
<!--     <style>
        .form-title {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .scanned-items {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            min-height: 200px;
        }
        .empty-state {
            text-align: center;
            color: #6c757d;
            padding: 40px 0;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .table-header, .scanned-item {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 80px;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .table-header {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .shake {
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .btn-submit {
            background-color: #27ae60;
            color: white;
        }
        .btn-reset {
            background-color: #e74c3c;
            color: white;
        }
        .btn-scan {
            background-color: #3498db;
        }
        .logout-btn {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
        }
        .pbp-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
    </style> -->
</head>
<body>
    <div class="container py-4">        
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><i class="bi bi-arrow-return-left"></i> Form Return Material</h4>
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
                    </div>
                    <div class="card-body">
                        <!-- Form Return -->
                        <form id="returnForm">
                            <!-- Informasi PBP (Akan muncul setelah scan barcode pertama) -->
                            <div class="row mb-4" id="pbpInfoSection" style="display: none;">
                                <div class="col-md-12">
                                    <h5 class="form-title"><i class="bi bi-info-circle"></i> Informasi PBP</h5>
                                    <div class="card pbp-info-card">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <strong><i class="bi bi-file-earmark-text"></i> No. PBP:</strong><br>
                                                    <span id="pbpNumberDisplay" class="h5">-</span>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong><i class="bi bi-card-checklist"></i> No. SO:</strong><br>
                                                    <span id="soNumberDisplay">-</span>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong><i class="bi bi-qr-code"></i> Kode Produk:</strong><br>
                                                    <span id="productCodeDisplay">-</span>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong><i class="bi bi-calendar"></i> Tanggal PBP:</strong><br>
                                                    <span id="pbpDateDisplay">-</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong><i class="bi bi-person"></i> Operator:</strong><br>
                                                    <span id="operatorDisplay">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Input Barcode Return -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="form-title"><i class="bi bi-upc-scan"></i> Input Barcode Return</h5>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="barcodeInput" class="form-label">Scan atau Masukkan Barcode Material yang Dikembalikan</label>
                                    <input type="text" class="form-control" id="barcodeInput" placeholder="Tempatkan kursor di sini dan scan barcode material" autofocus>
                                    <div class="form-text">System akan otomatis mendeteksi PBP dari barcode material</div>
                                    <div class="alert alert-danger mt-2" id="barcodeAlert" style="display: none;">
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
                            
                            <!-- Daftar Material Ter-return -->
                            <div class="row">
                                <div class="col-md-12">
                                    <h5 class="form-title"><i class="bi bi-list-check"></i> Daftar Material yang Dikembalikan</h5>
                                    
                                    <div class="scanned-items" id="scannedItems">
                                        <div class="empty-state" id="emptyState">
                                            <i class="bi bi-inbox"></i>
                                            <h5>Belum ada barcode diinput</h5>
                                            <p>Scan barcode material untuk memulai proses return</p>
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
                                                <span class="total-label">Total Item Dikembalikan:</span>
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
                                <button type="submit" class="btn btn-submit">Submit Return</button>
                            </div>
                        </form>
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

            // Elemen DOM
            const pbpInfoSection = document.getElementById('pbpInfoSection');
            const pbpNumberDisplay = document.getElementById('pbpNumberDisplay');
            const soNumberDisplay = document.getElementById('soNumberDisplay');
            const productCodeDisplay = document.getElementById('productCodeDisplay');
            const pbpDateDisplay = document.getElementById('pbpDateDisplay');
            const operatorDisplay = document.getElementById('operatorDisplay');
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
            
            let currentPbpData = null;
            let scannedBarcodes = [];
            let returnedMaterials = [];

            // Fungsi untuk mendapatkan data material dan PBP dari barcode
            async function getMaterialAndPbpData(barcode) {
                try {
                    const response = await fetch('get_material_pbp_data.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'barcode=' + encodeURIComponent(barcode)
                    });
                    
                    if (!response.ok) throw new Error('Network response was not ok');
                    return await response.json();
                } catch (error) {
                    console.error('Error fetching material data:', error);
                    return null;
                }
            }

            // Fungsi untuk validasi barcode return
            function validateBarcodeForReturn(barcode) {
                // Cek apakah barcode sudah di-return sebelumnya dalam session ini
                if (isBarcodeAlreadyScanned(barcode)) {
                    return { valid: false, message: `Barcode "${barcode}" sudah diinput sebelumnya` };
                }
                
                return { valid: true };
            }
            
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
            
            // Fungsi untuk menampilkan informasi PBP
            function displayPbpInfo(pbpData) {
                pbpNumberDisplay.textContent = pbpData.wood_solid_pbp_number;
                soNumberDisplay.textContent = pbpData.so_number;
                productCodeDisplay.textContent = pbpData.product_code;
                pbpDateDisplay.textContent = pbpData.wood_solid_pbp_date;
                operatorDisplay.textContent = pbpData.employee_name || pbpData.employee_nik;
                pbpInfoSection.style.display = 'block';
            }

            async function addScannedItem(barcode) {
                hideBarcodeAlert();
                
                // Get data material dan PBP dari database
                const materialData = await getMaterialAndPbpData(barcode);
                
                if (!materialData || !materialData.success) {
                    let errorMsg = materialData?.message || `Barcode "${barcode}" tidak terdaftar dalam sistem`;
                    
                    // Cek jika material sudah di-return sebelumnya
                    if (materialData?.message?.includes('sudah di-return')) {
                        errorMsg = materialData.message;
                    }
                    
                    showAlert(errorMsg);
                    return false;
                }
                
                const material = materialData.material;
                const pbpData = materialData.pbp_data;
                
                // Validasi konsistensi PBP
                if (currentPbpData && currentPbpData.wood_solid_pbp_number !== pbpData.wood_solid_pbp_number) {
                    showAlert(`Barcode ini berasal dari PBP berbeda! PBP saat ini: ${currentPbpData.wood_solid_pbp_number}`);
                    return false;
                }
                
                // Set PBP data jika belum ada
                if (!currentPbpData) {
                    currentPbpData = pbpData;
                    displayPbpInfo(pbpData);
                }
                
                scannedBarcodes.push(barcode);
                returnedMaterials.push(material);
                
                if (emptyState) emptyState.style.display = 'none';
                tableHeader.style.display = 'block';
                tableBody.style.display = 'block';
                totalFooter.style.display = 'block';
                
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
                    returnedMaterials = returnedMaterials.filter(m => m.wood_solid_barcode !== itemBarcode);
                    scannedItem.remove();
                    
                    if (tableBody.children.length === 0) {
                        emptyState.style.display = 'block';
                        tableHeader.style.display = 'none';
                        tableBody.style.display = 'none';
                        totalFooter.style.display = 'none';
                        pbpInfoSection.style.display = 'none';
                        currentPbpData = null;
                    } else {
                        calculateGrandTotal();
                    }
                });
                
                calculateGrandTotal();
                barcodeInput.value = '';
                barcodeInput.focus();
                return true;
            }
            
            function calculateGrandTotal() {
                const total = tableBody.children.length;
                grandTotalEl.textContent = total;
                return total;
            }
            
            // Event Listeners
            barcodeInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const barcode = barcodeInput.value.trim();
                    
                    if (!barcode) {
                        showAlert('Masukkan kode barcode terlebih dahulu');
                        return;
                    }
                    
                    const validation = validateBarcodeForReturn(barcode);
                    if (!validation.valid) {
                        showAlert(validation.message);
                        return;
                    }
                    
                    addScannedItem(barcode);
                }
            });
            
            barcodeInput.addEventListener('input', hideBarcodeAlert);
            
            scanBtn.addEventListener('click', function() {
                const barcode = barcodeInput.value.trim();
                
                if (!barcode) {
                    showAlert('Masukkan kode barcode terlebih dahulu');
                    return;
                }
                
                const validation = validateBarcodeForReturn(barcode);
                if (!validation.valid) {
                    showAlert(validation.message);
                    return;
                }
                
                addScannedItem(barcode);
            });
            
            resetBtn.addEventListener('click', function() {
                // Reset form
                tableBody.innerHTML = '';
                scannedBarcodes = [];
                returnedMaterials = [];
                emptyState.style.display = 'block';
                tableHeader.style.display = 'none';
                tableBody.style.display = 'none';
                totalFooter.style.display = 'none';
                pbpInfoSection.style.display = 'none';
                grandTotalEl.textContent = '0';
                hideBarcodeAlert();
                
                // Reset PBP data
                currentPbpData = null;
                barcodeInput.focus();
            });
            
            // Submit Form Return
            document.getElementById('returnForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (returnedMaterials.length === 0) {
                    showAlert('Harap scan minimal satu barcode material untuk di-return!');
                    return;
                }
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
                submitBtn.disabled = true;
                
                fetch('submit_return.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        returned_materials: returnedMaterials,
                        employee_nik: currentEmployee.barcode
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: `Return material berhasil disimpan!<br>
                                   <strong>Nomor Return:</strong> ${data.return_number}<br>
                                   <strong>Jumlah Material:</strong> ${data.returned_count} item`,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Reset form setelah sukses
                            resetBtn.click();
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

            // Auto focus ke input barcode
            barcodeInput.focus();
        });
    </script>
</body>
</html>
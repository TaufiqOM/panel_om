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
    <title>Form Permintaan Pengambilan Hardware</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <div class="container-xl py-4">        
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><i class="bi bi-tools"></i> Form Permintaan Pengambilan Hardware</h4>
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
                        <div id="mainForm">
                            <form id="hardwareRequestForm">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="form-title"><i class="bi bi-person-check"></i> Informasi Operator</h5>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="operatorFullname" class="form-label">Nama Operator</label>
                                        <input type="text" class="form-control" id="operatorFullname" name="operator_fullname" value="<?php echo htmlspecialchars($employee['name']); ?>" required readonly>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h5 class="form-title"><i class="bi bi-tools"></i> Input Detail Hardware</h5>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-lg-2 col-md-6">
                                            <label for="soNumber" class="form-label">Nomor SO</label>
                                            <input type="text" class="form-control" id="soNumber" name="so_number" list="soDatalist">
                                            <datalist id="soDatalist"></datalist>
                                        </div>
                                        <div class="col-lg-2 col-md-6">
                                            <label for="productCode" class="form-label">Kode Produk</label>
                                            <input type="text" class="form-control" id="productCode" name="product_code" list="productCodeDatalist">
                                            <datalist id="productCodeDatalist"></datalist>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label for="hardwareCodeInput" class="form-label">Cari Hardware (Kode/Nama)</label>
                                            <input class="form-control" list="hardwareDatalist" id="hardwareCodeInput" placeholder="Ketik untuk mencari...">
                                            <datalist id="hardwareDatalist"></datalist>
                                        </div>
                                        <div class="col-lg-1 col-md-6">
                                            <label for="hardwareQty" class="form-label">Jumlah</label>
                                            <input type="number" class="form-control" id="hardwareQty" step="0.0001" value="1" required>
                                        </div>
                                        <div class="col-lg-2 col-md-6">
                                            <label for="hardwareUom" class="form-label">Satuan</label>
                                            <input type="text" class="form-control" id="hardwareUom" readonly>
                                        </div>
                                        <div class="col-lg-2 col-md-6">
                                            <button type="button" class="btn btn-scan text-white w-100" id="addHardwareBtn">
                                                <i class="bi bi-plus-circle"></i> Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <h5 class="form-title"><i class="bi bi-list-check"></i> Daftar Hardware</h5>
                                        <div class="scanned-items" id="hardwareItems">
                                            <div class="empty-state" id="emptyState">
                                                <i class="bi bi-inbox"></i>
                                                <h5>Belum ada hardware dipilih</h5>
                                                <p>Cari hardware dan tambahkan ke daftar</p>
                                            </div>
                                            
                                            <div class="table-header" style="display: none;" id="tableHeader">
                                                <div class="row g-2">
                                                    <div class="col-lg-2"><strong>Kode Hardware</strong></div>
                                                    <div class="col-lg-3"><strong>Nama Hardware</strong></div>
                                                    <div class="col-lg-2"><strong>No. SO</strong></div>
                                                    <div class="col-lg-1"><strong>Kode Produk</strong></div>
                                                    <div class="col-lg-1 text-center"><strong>Jumlah</strong></div>
                                                    <div class="col-lg-2 text-center"><strong>Satuan</strong></div>
                                                    <div class="col-lg-1 text-center"><strong>Aksi</strong></div>
                                                </div>
                                            </div>
                                            
                                            <div class="table-body" style="display: none;" id="tableBody"></div>
                                        </div>
                                        
                                        <div class="total-footer" style="display: none;" id="totalFooter">
                                            <div class="row">
                                                <div class="col-md-6"><span class="total-label">Total Item:</span></div>
                                                <div class="col-md-6 text-end"><span class="total-value" id="grandTotal">0</span> jenis hardware</div>
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
            const currentEmployee = {
                id: '<?php echo $employee["id"]; ?>',
                id_employee: '<?php echo $employee["id_employee"]; ?>',
                name: '<?php echo $employee["name"]; ?>',
                barcode: '<?php echo $employee["barcode"]; ?>',
                full_name: '<?php echo $employee["name"]; ?>'
            };

            const hardwareCodeInput = document.getElementById('hardwareCodeInput');
            const hardwareDatalist = document.getElementById('hardwareDatalist');
            const hardwareQty = document.getElementById('hardwareQty');
            const hardwareUom = document.getElementById('hardwareUom');
            const soNumberEl = document.getElementById('soNumber');
            const soDatalist = document.getElementById('soDatalist');
            const productCodeEl = document.getElementById('productCode');
            const productCodeDatalist = document.getElementById('productCodeDatalist');
            const addHardwareBtn = document.getElementById('addHardwareBtn');
            const emptyState = document.getElementById('emptyState');
            const tableHeader = document.getElementById('tableHeader');
            const tableBody = document.getElementById('tableBody');
            const totalFooter = document.getElementById('totalFooter');
            const grandTotalEl = document.getElementById('grandTotal');
            const resetBtn = document.getElementById('resetBtn');
            const reprintToggleBtn = document.getElementById('reprintToggleBtn');
            const mainForm = document.getElementById('mainForm');
            const reprintForm = document.getElementById('reprintForm');
            const operatorSearch = document.getElementById('operatorSearch');
            const searchBtn = document.getElementById('searchBtn');
            const searchResults = document.getElementById('searchResults');
            const resultsContainer = document.getElementById('resultsContainer');
            const backToMainBtn = document.getElementById('backToMainBtn');
            
            let selectedHardware = [];
            let hardwareList = [];
            let currentSelectedValidHardware = null;
            let selectedSOId = null;
            let selectedSOName = null;

            function loadHardwareList(so = '', product = '') {
                return new Promise((resolve, reject) => {
                    let url = 'get_hardware_list.php';
                    if (so && product) {
                        url = `get_hardware_for_so_product.php?so=${encodeURIComponent(so)}&product=${encodeURIComponent(product)}`;
                    }
                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                hardwareList = data.hardware || [];
                                hardwareDatalist.innerHTML = '';

                                hardwareList.forEach(hw => {
                                    const option = document.createElement('option');
                                    option.value = `${hw.hardware_code} - ${hw.hardware_name}`;
                                    option.setAttribute('data-code', hw.hardware_code);
                                    hardwareDatalist.appendChild(option);
                                });
                                resolve();
                            } else {
                                Swal.fire('Error', data.message || 'Gagal memuat daftar hardware', 'error');
                                reject(new Error(data.message));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            reject(error);
                        });
                });
            }

            function loadSOList() {
                // Load from localStorage first
                const cached = localStorage.getItem('soList');
                if (cached) {
                    const data = JSON.parse(cached);
                    populateSODatalist(data);
                }
                fetch('get_so_list.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            localStorage.setItem('soList', JSON.stringify(data.data));
                            populateSODatalist(data.data);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading SO list:', error);
                        // Already loaded from cache above
                    });
            }

            function populateSODatalist(soList) {
                soDatalist.innerHTML = '';
                soList.forEach(so => {
                    // Option for name
                    const optionName = document.createElement('option');
                    optionName.value = so.display_name;
                    optionName.setAttribute('data-so-id', so.id);
                    optionName.setAttribute('data-so-name', so.display_name);
                    soDatalist.appendChild(optionName);

                    // Option for po_client if exists
                    if (so.po_client) {
                        const optionPo = document.createElement('option');
                        optionPo.value = so.po_client;
                        optionPo.setAttribute('data-so-id', so.id);
                        optionPo.setAttribute('data-so-name', so.display_name);
                        soDatalist.appendChild(optionPo);
                    }

                    // Option for client_order_ref if exists
                    if (so.client_order_ref) {
                        const optionRef = document.createElement('option');
                        optionRef.value = so.client_order_ref;
                        optionRef.setAttribute('data-so-id', so.id);
                        optionRef.setAttribute('data-so-name', so.display_name);
                        soDatalist.appendChild(optionRef);
                    }
                });
            }

            function loadProductCodes(so = '') {
                return new Promise((resolve, reject) => {
                    const url = so ? `get_product_codes.php?so=${encodeURIComponent(so)}` : 'get_product_codes.php';
                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                productCodeDatalist.innerHTML = '';
                                if (data.data) {
                                    localStorage.setItem(`productCodes${so ? '_' + so : ''}`, JSON.stringify(data.data));
                                    data.data.forEach(product => {
                                        const option = document.createElement('option');
                                        option.value = product.default_code;
                                        option.textContent = `[${product.default_code}] ${product.name}`;
                                        productCodeDatalist.appendChild(option);
                                    });
                                }
                                resolve();
                            } else {
                                Swal.fire('Error', data.error || 'Gagal memuat kode produk', 'error');
                                reject(new Error(data.error));
                            }
                        })
                        .catch(error => {
                            console.error('Error loading product codes:', error);
                            // Load from localStorage
                            const cached = localStorage.getItem(`productCodes${so ? '_' + so : ''}`);
                            if (cached) {
                                const data = JSON.parse(cached);
                                productCodeDatalist.innerHTML = '';
                                data.forEach(product => {
                                    const option = document.createElement('option');
                                    option.value = product.default_code;
                                    option.textContent = `[${product.default_code}] ${product.name}`;
                                    productCodeDatalist.appendChild(option);
                                });
                                resolve(); // resolve with cache
                            } else {
                                reject(error);
                            }
                        });
                });
            }

            soNumberEl.addEventListener('input', function() {
                const inputValue = this.value.trim();
                const selectedOption = Array.from(soDatalist.options).find(opt => opt.value === inputValue);
                if (selectedOption) {
                    selectedSOId = selectedOption.getAttribute('data-so-id');
                    selectedSOName = selectedOption.getAttribute('data-so-name');
                } else {
                    selectedSOId = null;
                    selectedSOName = null;
                }
            });

            soNumberEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const so = this.value.trim();
                    if (so) {
                        Swal.fire({
                            title: 'Memuat produk...',
                            text: 'Mohon tunggu sebentar',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            willOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        loadProductCodes(so).then(() => {
                            Swal.close();
                            productCodeEl.focus();
                        }).catch(() => {
                            Swal.close();
                        });
                        productCodeEl.value = '';
                        selectedHardware = [];
                        updateHardwareList();
                        // Do not load all hardware, only when product selected
                    } else {
                        loadProductCodes(); // load all
                        productCodeEl.value = '';
                        selectedHardware = [];
                        updateHardwareList();
                        // Do not load all hardware
                    }
                }
            });

            productCodeEl.addEventListener('change', function() {
                const so = soNumberEl.value.trim();
                const product = this.value.trim();
                if (so && product) {
                    Swal.fire({
                        title: 'Memuat hardware...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    loadHardwareList(so, product).then(() => {
                        Swal.close();
                    }).catch(() => {
                        Swal.close();
                    });
                } else {
                    // Do not load all hardware, only when product selected
                    hardwareDatalist.innerHTML = '';
                    hardwareList = [];
                    currentSelectedValidHardware = null;
                    hardwareUom.value = '';
                }
            });
            
            hardwareCodeInput.addEventListener('input', function() {
                const inputValue = this.value;
                const selectedOption = Array.from(hardwareDatalist.options).find(opt => opt.value === inputValue);

                if (selectedOption) {
                    const hardwareCode = selectedOption.getAttribute('data-code');
                    const hardware = hardwareList.find(hw => hw.hardware_code === hardwareCode);
                    if (hardware) {
                        currentSelectedValidHardware = hardware;
                        hardwareUom.value = hardware.hardware_uom || 'PCS';
                    }
                } else {
                    currentSelectedValidHardware = null;
                    hardwareUom.value = '';
                }
            });
            
            addHardwareBtn.addEventListener('click', function() {
                const so_number = soNumberEl.value.trim();
                const product_code = productCodeEl.value.trim();

                if (!so_number) {
                    Swal.fire('Peringatan', 'Nomor SO harus diisi.', 'warning');
                    soNumberEl.focus();
                    return;
                }

                if (!product_code) {
                    Swal.fire('Peringatan', 'Kode produk harus diisi.', 'warning');
                    productCodeEl.focus();
                    return;
                }

                const validProductCodes = Array.from(productCodeDatalist.options).map(opt => opt.value);
                if (!validProductCodes.includes(product_code)) {
                    Swal.fire('Peringatan', 'Kode produk tidak valid untuk SO yang dipilih.', 'warning');
                    productCodeEl.focus();
                    return;
                }

                if (!currentSelectedValidHardware) {
                    Swal.fire('Peringatan', 'Pilih hardware yang valid dari daftar.', 'warning');
                    hardwareCodeInput.focus();
                    return;
                }

                const qty = parseFloat(hardwareQty.value);

                if (qty <= 0) {
                    Swal.fire('Peringatan', 'Jumlah harus lebih dari 0', 'warning');
                    return;
                }

                // Ensure SO ID and Name are set
                if (!selectedSOId || !selectedSOName) {
                    const selectedOption = Array.from(soDatalist.options).find(opt => opt.value === so_number);
                    if (selectedOption) {
                        selectedSOId = selectedOption.getAttribute('data-so-id');
                        selectedSOName = selectedOption.getAttribute('data-so-name');
                    }
                }

                const code = currentSelectedValidHardware.hardware_code;

                const existingIndex = selectedHardware.findIndex(hw =>
                    hw.code === code && hw.so_number === so_number && hw.product_code === product_code
                );

                if (existingIndex > -1) {
                    selectedHardware[existingIndex].qty += qty;
                } else {
                    selectedHardware.push({
                        code: currentSelectedValidHardware.hardware_code,
                        name: currentSelectedValidHardware.hardware_name,
                        hardware_uom: currentSelectedValidHardware.hardware_uom || 'PCS',
                        qty: qty,
                        so_number: so_number,
                        so_id: selectedSOId,
                        so_name: selectedSOName,
                        product_code: product_code,
                        line_id: currentSelectedValidHardware.line_id,
                        picking_id: currentSelectedValidHardware.picking_id,
                        mo_id: currentSelectedValidHardware.mo_id
                    });
                }

                updateHardwareList();

                hardwareCodeInput.value = '';
                hardwareQty.value = '1';
                hardwareUom.value = '';
                currentSelectedValidHardware = null;
                hardwareCodeInput.focus();
            });

            function updateHardwareList() {
                if (selectedHardware.length === 0) {
                    emptyState.style.display = 'block';
                    tableHeader.style.display = 'none';
                    tableBody.style.display = 'none';
                    totalFooter.style.display = 'none';
                    return;
                }
                
                emptyState.style.display = 'none';
                tableHeader.style.display = 'block';
                tableBody.style.display = 'block';
                totalFooter.style.display = 'block';
                
                tableBody.innerHTML = '';
                
                selectedHardware.forEach((hw, index) => {
                    const row = document.createElement('div');
                    row.className = 'row g-2 align-items-center mb-2 hardware-item';
                    row.innerHTML = `
                        <div class="col-lg-2"><input type="text" class="form-control form-control-sm" value="${hw.code}" readonly></div>
                        <div class="col-lg-3"><input type="text" class="form-control form-control-sm" value="${hw.name}" readonly></div>
                        <div class="col-lg-2"><input type="text" class="form-control form-control-sm" value="${hw.so_number}" readonly></div>
                        <div class="col-lg-1"><input type="text" class="form-control form-control-sm" value="${hw.product_code}" readonly></div>
                        <div class="col-lg-1"><input type="number" class="form-control form-control-sm text-center" value="${hw.qty}" min="0.0001" step="0.0001" onchange="updateHardwareQty(${index}, this.value)"></div>
                        <div class="col-lg-2"><input type="text" class="form-control form-control-sm text-center" value="${hw.hardware_uom}" readonly></div>
                        <div class="col-lg-1"><button type="button" class="btn btn-sm btn-danger w-100" onclick="removeHardware(${index})"><i class="bi bi-trash"></i></button></div>
                    `;
                    tableBody.appendChild(row);
                });
                
                grandTotalEl.textContent = selectedHardware.length;
            }
            
            window.updateHardwareQty = function(index, newQty) {
                newQty = parseFloat(newQty);
                if (newQty <= 0) {
                    Swal.fire('Peringatan', 'Jumlah harus lebih dari 0', 'warning');
                    updateHardwareList();
                    return;
                }
                selectedHardware[index].qty = newQty;
            };
            
            window.removeHardware = function(index) {
                selectedHardware.splice(index, 1);
                updateHardwareList();
            };
            
            resetBtn.addEventListener('click', function() {
                document.getElementById('hardwareRequestForm').reset();
                selectedHardware = [];
                currentSelectedValidHardware = null;
                updateHardwareList();
            });
            
            reprintToggleBtn.addEventListener('click', function() {
                if (mainForm.style.display === 'none') {
                    mainForm.style.display = 'block';
                    reprintForm.style.display = 'none';
                    reprintToggleBtn.innerHTML = '<i class="bi bi-printer"></i> Cetak Ulang';
                } else {
                    mainForm.style.display = 'none';
                    reprintForm.style.display = 'block';
                    reprintToggleBtn.innerHTML = '<i class="bi bi-tools"></i> Form Utama';
                    operatorSearch.focus();
                }
            });
            
            backToMainBtn.addEventListener('click', function() {
                mainForm.style.display = 'block';
                reprintForm.style.display = 'none';
                reprintToggleBtn.innerHTML = '<i class="bi bi-printer"></i> Cetak Ulang';
            });
            
            searchBtn.addEventListener('click', function() {
                const operatorName = operatorSearch.value.trim();

                if (!operatorName) {
                    Swal.fire('Peringatan', 'Masukkan nama operator terlebih dahulu!', 'warning');
                    return;
                }

                searchBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Mencari...';
                searchBtn.disabled = true;

                fetch('search_hardware_pbp.php', {
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
                        displayPbpList(data.pbp_list || []);
                    } else {
                        Swal.fire('Error', data.message || 'Terjadi kesalahan saat mencari data.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    searchBtn.innerHTML = '<i class="bi bi-search"></i> Cari';
                    searchBtn.disabled = false;
                    Swal.fire('Error', 'Terjadi kesalahan saat mencari data.', 'error');
                });
            });
            
            function displayPbpList(pbpList) {
                resultsContainer.innerHTML = '';
                
                if (pbpList.length === 0) {
                    resultsContainer.innerHTML = '<div class="alert alert-info">Tidak ada data PBP Hardware ditemukan.</div>';
                    searchResults.style.display = 'block';
                    return;
                }
                
                const table = document.createElement('div');
                table.className = 'table-responsive';
                
                let html = `
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No. PBP</th>
                                <th>Picking Name</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Jumlah Item</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                pbpList.forEach(pbp => {
                    html += `
                            <tr>
                                <td>${pbp.hardware_pbp_number}</td>
                                <td>${pbp.picking_names || '-'}</td>
                                <td>${pbp.hardware_pbp_date}</td>
                                <td>${pbp.hardware_pbp_time}</td>
                                <td>${pbp.item_count || 1} item</td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-print" data-pbp="${pbp.hardware_pbp_number}">
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
                
                resultsContainer.querySelectorAll('.btn-print').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const pbpNumber = this.getAttribute('data-pbp');
                        window.open(`print_out_hardware_pbp.php?pbp_number=${pbpNumber}`, '_blank');
                    });
                });
            }
            
            document.getElementById('hardwareRequestForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (selectedHardware.length === 0) {
                    Swal.fire('Peringatan', 'Pilih minimal satu hardware!', 'warning');
                    return;
                }
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
                submitBtn.disabled = true;
                
                fetch('submit_hardware_pbp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        hardware_items: selectedHardware,
                        employee_nik: currentEmployee.barcode
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
                                   <strong>Jumlah Item:</strong> ${data.item_count} jenis hardware`,
                            confirmButtonText: 'Cetak Laporan'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open(`print_out_hardware_pbp.php?pbp_number=${data.pbp_number}`, '_blank');
                            }
                            resetBtn.click(); 
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Gagal menyimpan data.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Terjadi kesalahan saat mengirim data.', 'error');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });

            loadSOList();
            // Do not load product codes initially, only when SO is entered
        });
    </script>
</body>
</html>
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
    <title>Form Permintaan Pengambilan Wood Engineered</title>
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
                            <h4 class="mb-0"><i class="bi bi-stack"></i> Form Permintaan Pengambilan Wood Engineered</h4>
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
                            <form id="requestForm">
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
                                    <h5 class="form-title"><i class="bi bi-stack"></i> Input Detail Wood Engineered</h5>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-lg-3 col-md-6">
                                            <label for="soNumber" class="form-label">Nomor SO</label>
                                            <input type="text" class="form-control" id="soNumber" name="so_number">
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label for="productCode" class="form-label">Kode Produk</label>
                                            <input type="text" class="form-control" id="productCode" name="product_code">
                                        </div>
                                        <div class="col-lg-3 col-md-12">
                                            <label for="itemCodeInput" class="form-label">Cari Wood Engineered (Kode)</label>
                                            <input class="form-control" list="itemDatalist" id="itemCodeInput" placeholder="Ketik untuk mencari...">
                                            <datalist id="itemDatalist"></datalist>
                                        </div>
                                        <div class="col-lg-1 col-md-6">
                                            <label for="itemQty" class="form-label">Jumlah</label>
                                            <input type="number" step="0.01" class="form-control" id="itemQty" min="0.01" value="1" required>
                                        </div>
                                        <div class="col-lg-2 col-md-6">
                                            <button type="button" class="btn btn-scan text-white w-100" id="addItemBtn">
                                                <i class="bi bi-plus-circle"></i> Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <h5 class="form-title"><i class="bi bi-list-check"></i> Daftar Pilihan</h5>
                                        <div class="scanned-items" id="itemsContainer">
                                            <div class="empty-state" id="emptyState">
                                                <i class="bi bi-inbox"></i>
                                                <h5>Belum ada item dipilih</h5>
                                                <p>Cari item dan tambahkan ke daftar</p>
                                            </div>
                                            
                                            <div class="table-header" style="display: none;" id="tableHeader">
                                                <div class="row g-2">
                                                    <div class="col-lg-3"><strong>Kode</strong></div>
                                                    <div class="col-lg-4"><strong>Nama Item</strong></div>
                                                    <div class="col-lg-2"><strong>No. SO</strong></div>
                                                    <div class="col-lg-1"><strong>Produk</strong></div>
                                                    <div class="col-lg-1 text-center"><strong>Jumlah</strong></div>
                                                    <div class="col-lg-1 text-center"><strong>Aksi</strong></div>
                                                </div>
                                            </div>
                                            
                                            <div class="table-body" style="display: none;" id="tableBody"></div>
                                        </div>
                                        
                                        <div class="total-footer" style="display: none;" id="totalFooter">
                                            <div class="row">
                                                <div class="col-md-6"><span class="total-label">Total Jenis Item:</span></div>
                                                <div class="col-md-6 text-end"><span class="total-value" id="grandTotal">0</span> jenis item</div>
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
                barcode: '<?php echo $employee["barcode"]; ?>'
            };

            const itemCodeInput = document.getElementById('itemCodeInput');
            const itemDatalist = document.getElementById('itemDatalist');
            const itemQty = document.getElementById('itemQty');
            const soNumberEl = document.getElementById('soNumber');
            const productCodeEl = document.getElementById('productCode');
            const addItemBtn = document.getElementById('addItemBtn');
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
            
            let selectedItems = [];
            let masterItemList = [];
            let currentSelectedValidItem = null;
            
            function loadMasterList() {
                fetch('get_wood_engineered_list.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            masterItemList = data.items;
                            itemDatalist.innerHTML = '';
                            
                            masterItemList.forEach(item => {
                                const option = document.createElement('option');
                                option.value = `${item.wood_engineered_code}`; // Datalist hanya menampilkan kode
                                option.setAttribute('data-code', item.wood_engineered_code);
                                itemDatalist.appendChild(option);
                            });
                        } else {
                            Swal.fire('Error', 'Gagal memuat daftar item', 'error');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
            
            itemCodeInput.addEventListener('input', function() {
                const inputValue = this.value;
                const selectedOption = Array.from(itemDatalist.options).find(opt => opt.value === inputValue);

                if (selectedOption) {
                    const itemCode = selectedOption.getAttribute('data-code');
                    const item = masterItemList.find(i => i.wood_engineered_code === itemCode);
                    if (item) {
                        currentSelectedValidItem = item;
                    }
                } else {
                    currentSelectedValidItem = null;
                }
            });
            
            addItemBtn.addEventListener('click', function() {
                if (!currentSelectedValidItem) {
                    Swal.fire('Peringatan', 'Pilih item yang valid dari daftar.', 'warning');
                    itemCodeInput.focus();
                    return;
                }
                
                const qty = parseFloat(itemQty.value);
                const so_number = soNumberEl.value.trim();
                const product_code = productCodeEl.value.trim();

                if (isNaN(qty) || qty <= 0) {
                    Swal.fire('Peringatan', 'Jumlah harus angka lebih dari 0', 'warning');
                    return;
                }
                
                const code = currentSelectedValidItem.wood_engineered_code;

                const existingIndex = selectedItems.findIndex(i => 
                    i.code === code && i.so_number === so_number && i.product_code === product_code
                );
                
                if (existingIndex > -1) {
                    selectedItems[existingIndex].qty += qty;
                } else {
                    selectedItems.push({
                        code: currentSelectedValidItem.wood_engineered_code,
                        name: currentSelectedValidItem.wood_engineered_name || code,
                        qty: qty,
                        so_number: so_number,
                        product_code: product_code
                    });
                }
                
                updateItemList();
                
                itemCodeInput.value = '';
                itemQty.value = '1';
                currentSelectedValidItem = null;
                itemCodeInput.focus();
            });

            function updateItemList() {
                if (selectedItems.length === 0) {
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
                
                selectedItems.forEach((item, index) => {
                    const row = document.createElement('div');
                    row.className = 'row g-2 align-items-center mb-2';
                    row.innerHTML = `
                        <div class="col-lg-3"><input type="text" class="form-control form-control-sm" value="${item.code}" readonly></div>
                        <div class="col-lg-4"><input type="text" class="form-control form-control-sm" value="${item.name}" readonly></div>
                        <div class="col-lg-2"><input type="text" class="form-control form-control-sm" value="${item.so_number}" readonly></div>
                        <div class="col-lg-1"><input type="text" class="form-control form-control-sm" value="${item.product_code}" readonly></div>
                        <div class="col-lg-1"><input type="number" step="0.01" class="form-control form-control-sm text-center" value="${item.qty}" min="0.01" onchange="updateItemQty(${index}, this.value)"></div>
                        <div class="col-lg-1"><button type="button" class="btn btn-sm btn-danger w-100" onclick="removeItem(${index})"><i class="bi bi-trash"></i></button></div>
                    `;
                    tableBody.appendChild(row);
                });
                
                grandTotalEl.textContent = selectedItems.length;
            }
            
            window.updateItemQty = function(index, newQty) {
                newQty = parseFloat(newQty);
                if (isNaN(newQty) || newQty <= 0) {
                    Swal.fire('Peringatan', 'Jumlah harus angka lebih dari 0', 'warning');
                    updateItemList();
                    return;
                }
                selectedItems[index].qty = newQty;
            };
            
            window.removeItem = function(index) {
                selectedItems.splice(index, 1);
                updateItemList();
            };
            
            resetBtn.addEventListener('click', function() {
                document.getElementById('requestForm').reset();
                selectedItems = [];
                currentSelectedValidItem = null;
                updateItemList();
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
                
                fetch('search_wood_engineered_pbp.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
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
                    resultsContainer.innerHTML = '<div class="alert alert-info">Tidak ada data ditemukan.</div>';
                    searchResults.style.display = 'block';
                    return;
                }
                
                const table = document.createElement('div');
                table.className = 'table-responsive';
                
                let html = `<table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No. PBP</th>
                                        <th>Tanggal</th>
                                        <th>Waktu</th>
                                        <th>Jumlah Item</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                
                pbpList.forEach(pbp => {
                    html += `<tr>
                                <td>${pbp.wood_engineered_number}</td>
                                <td>${pbp.wood_engineered_date}</td>
                                <td>${pbp.wood_engineered_time}</td>
                                <td>${pbp.item_count || 1} item</td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-print" data-pbp="${pbp.wood_engineered_number}">
                                        <i class="bi bi-printer"></i> Cetak
                                    </button>
                                </td>
                            </tr>`;
                });
                
                html += `</tbody></table>`;
                table.innerHTML = html;
                resultsContainer.appendChild(table);
                searchResults.style.display = 'block';
                
                resultsContainer.querySelectorAll('.btn-print').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const pbpNumber = this.getAttribute('data-pbp');
                        window.open(`print_out_wood_engineered_pbp.php?pbp_number=${pbpNumber}`, '_blank');
                    });
                });
            }
            
            document.getElementById('requestForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (selectedItems.length === 0) {
                    Swal.fire('Peringatan', 'Pilih minimal satu item!', 'warning');
                    return;
                }
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
                submitBtn.disabled = true;
                
                fetch('submit_wood_engineered_pbp.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        items: selectedItems,
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
                                   <strong>Jumlah Item:</strong> ${data.item_count} jenis item`,
                            confirmButtonText: 'Cetak Laporan'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open(`print_out_wood_engineered_pbp.php?pbp_number=${data.pbp_number}`, '_blank');
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
            
            loadMasterList();
        });
    </script>
</body>
</html>
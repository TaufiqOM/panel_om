<?php
require __DIR__ . '/../../inc/config_odoo.php';

// Start Session
$username = $_SESSION['username'] ?? '';

// Coba ambil data
$orders = callOdooRead($username, "sale.blanket.order", [], ["id", "display_name", "client_order_ref", "user_id", "date_create_blanket_order", "state", "partner_id", "amount_total"]);
?>

<!-- CSS Custom -->
<style>
    .blanket-order-table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 500;
    }
    
    .status-draft { background-color: #e8f4ff; color: #0066cc; }
    .status-confirmed { background-color: #e6f7e9; color: #0a7c0a; }
    .status-done { background-color: #f0f0f0; color: #666; }
    .status-cancel { background-color: #ffe6e6; color: #cc0000; }
    
    .action-btn {
        padding: 5px 10px;
        font-size: 0.875rem;
        transition: all 0.3s;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .refresh-btn {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        border: none;
        color: white;
    }
    
    .refresh-btn:hover {
        background: linear-gradient(135deg, #3a9bf4 0%, #00d9e4 100%);
        color: white;
    }
    
    /* Modal Styles */
    .print-modal .modal-dialog {
        max-width: 800px;
    }
    
    .print-preview {
        background: white;
        padding: 20px;
        border-radius: 8px;
        max-height: 500px;
        overflow-y: auto;
    }
    
    .print-header {
        border-bottom: 2px solid #333;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    
    .print-content table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .print-content th,
    .print-content td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    
    .print-content th {
        background-color: #f5f5f5;
    }
    
    /* DataTables custom styling */
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 5px !important;
        margin: 0 2px !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
        border: none !important;
        color: white !important;
    }
    
    .dataTables_wrapper .dataTables_length select {
        border-radius: 5px;
        padding: 5px 10px;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 20px;
        padding: 5px 15px;
    }
    
    /* Icon styles */
    .btn-icon {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }
    
    .ki-duotone {
        font-size: 1.2em;
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        
        .dt-buttons,
        .dataTables_length,
        .dataTables_filter,
        .dataTables_info,
        .dataTables_paginate {
            display: none !important;
        }
        
        .dataTables_wrapper .dataTables_wrapper {
            overflow: visible !important;
        }
        
        table.dataTable {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        
        body {
            background-color: white !important;
        }
    }
</style>

<div class="row g-5 g-xxl-10">
    <div class="col-xl-12 mb-5 mb-xxl-10">
        <div class="card card-flush h-xl-100">
            <div class="card-header py-7">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="card-title fw-bold">
                            <i class="ki-duotone ki-document-text fs-2"><span class="path1"></span><span class="path2"></span></i>
                            Blanket Order
                        </h3>
                        <p class="text-muted mb-0">Daftar blanket order yang tersedia</p>
                    </div>
                </div>
            </div>

            <div class="card-body pt-1">
                <?php if ($orders === false || empty($orders)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="ki-duotone ki-information-2 fs-2hx me-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <h4 class="alert-heading">Gagal mengambil data!</h4>
                            <p class="mb-0">Tidak dapat mengambil data dari Odoo. Kemungkinan penyebab:</p>
                            <ul class="mb-0">
                                <li>Koneksi ke Odoo bermasalah</li>
                                <li>Username tidak valid atau session expired</li>
                                <li>Model 'sale.blanket.order' tidak ada</li>
                                <li>Tidak memiliki akses ke data tersebut</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-x fs-4hx text-danger mb-4"><span class="path1"></span><span class="path2"></span></i>
                        <h4 class="text-gray-800 mb-3">Data tidak tersedia</h4>
                        <p class="text-muted mb-6">Silahkan coba refresh halaman atau hubungi administrator</p>
                        <button class="btn btn-primary" onclick="window.location.reload()">
                            <i class="ki-duotone ki-refresh fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                            Muat Ulang
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Summary Cards -->
                    <div class="row mb-6 g-4 no-print">
                        <div class="col-md-4">
                            <div class="card bg-primary bg-opacity-10 border border-primary border-opacity-25">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-muted fw-semibold d-block">Total Orders</span>
                                            <span class="fw-bold fs-3"><?php echo count($orders); ?></span>
                                        </div>
                                        <i class="ki-duotone ki-chart-line fs-2hx text-primary"><span class="path1"></span><span class="path2"></span></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-success bg-opacity-10 border border-success border-opacity-25">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-muted fw-semibold d-block">Total Nilai</span>
                                            <span class="fw-bold fs-3">
                                                <?php 
                                                    $total = 0;
                                                    foreach($orders as $order) {
                                                        $total += $order['amount_total'] ?? 0;
                                                    }
                                                    echo 'Rp ' . number_format($total, 0, ',', '.');
                                                ?>
                                            </span>
                                        </div>
                                        <i class="ki-duotone ki-dollar fs-2hx text-success"><span class="path1"></span><span class="path2"></span></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-muted fw-semibold d-block">Terakhir Diperbarui</span>
                                            <span class="fw-bold fs-6"><?php echo date('d M Y H:i'); ?></span>
                                        </div>
                                        <i class="ki-duotone ki-clock fs-2hx text-warning"><span class="path1"></span><span class="path2"></span></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table with DataTables -->
                    <div class="table-responsive">
                        <table class="table table-hover table-row-bordered table-row-gray-300 gy-4 blanket-order-table" id="ordersTable">
                            <thead>
                                <tr class="fw-bold fs-6 text-gray-800">
                                    <th class="text-center">No</th>
                                    <th>Order Number</th>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Sales Person</th>
                                    <th>Date Created</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-center no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $index => $order): ?>
                                    <?php
                                        // Format status
                                        $statusClass = 'status-draft';
                                        $statusText = ucfirst($order['state'] ?? 'Draft');
                                        
                                        switch(strtolower($order['state'] ?? '')) {
                                            case 'confirmed':
                                                $statusClass = 'status-confirmed';
                                                break;
                                            case 'done':
                                                $statusClass = 'status-done';
                                                break;
                                            case 'cancel':
                                                $statusClass = 'status-cancel';
                                                break;
                                        }
                                        
                                        // Format date
                                        $date = $order['date_create_blanket_order'] ?? '';
                                        if ($date) {
                                            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
                                            $formattedDate = $dateObj ? $dateObj->format('d M Y') : $date;
                                        } else {
                                            $formattedDate = '-';
                                        }
                                        
                                        // Get customer name (assuming partner_id is an array with name at index 1)
                                        $customerName = '';
                                        if (isset($order['partner_id']) && is_array($order['partner_id'])) {
                                            $customerName = $order['partner_id'][1] ?? '';
                                        }
                                        
                                        // Get sales person name (assuming user_id is an array with name at index 1)
                                        $salesPerson = '';
                                        if (isset($order['user_id']) && is_array($order['user_id'])) {
                                            $salesPerson = $order['user_id'][1] ?? '';
                                        }
                                    ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="fw-bold text-gray-800"><?php echo htmlspecialchars($order['display_name'] ?? '-'); ?></div>
                                            <small class="text-muted">ID: <?php echo $order['id'] ?? '-'; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($order['client_order_ref'] ?? '-'); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($customerName); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-35px symbol-circle me-3">
                                                    <div class="symbol-label bg-light-primary">
                                                        <span class="fs-7 fw-bold text-primary"><?php echo substr($salesPerson, 0, 1); ?></span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($salesPerson); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?php echo $formattedDate; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-gray-800">
                                            Rp <?php echo number_format($order['amount_total'] ?? 0, 0, ',', '.'); ?>
                                        </td>
                                        <td class="text-center no-print">
                                            <div class="d-flex justify-content-center gap-2">
                                                <button class="btn btn-sm btn-icon btn-light-info print-single-btn" 
                                                        onclick="showPrintModal(
                                                            <?php echo $order['id']; ?>, 
                                                            '<?php echo htmlspecialchars($order['display_name'] ?? '-', ENT_QUOTES); ?>',
                                                            '<?php echo htmlspecialchars($order['client_order_ref'] ?? '', ENT_QUOTES); ?>'
                                                        )"
                                                        title="Print Order"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#printModal">
                                                    <i class="ki-duotone ki-printer fs-3"><span class="path1"></span><span class="path2"></span></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade print-modal" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printModalLabel">
                    <i class="ki-duotone ki-printer fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                    Cetak Blanket Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="print-preview">
                    <div class="print-header">
                        <h4 id="printOrderTitle" class="mb-2">Blanket Order: <span id="orderNumber"></span></h4>
                        <p class="text-muted" id="orderDetails"></p>
                    </div>
                    <div class="print-content" id="printContent">
                        <!-- Content will be populated by JavaScript -->
                        <p class="text-center text-muted">Loading preview...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                    Batal
                </button>
                <button type="button" class="btn btn-success" onclick="printSelectedOrder()">
                    <i class="ki-duotone ki-printer fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                    Cetak
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include DataTables -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

<script>
    // Initialize DataTables
    $(document).ready(function() {
        const table = $('#ordersTable').DataTable({
            "language": {
                "lengthMenu": "Tampilkan _MENU_ data per halaman",
                "zeroRecords": "Data tidak ditemukan",
                "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                "infoEmpty": "Tidak ada data tersedia",
                "infoFiltered": "(disaring dari _MAX_ total data)",
                "search": "Cari:",
                "paginate": {
                    "first": "Pertama",
                    "last": "Terakhir",
                    "next": "Selanjutnya",
                    "previous": "Sebelumnya"
                }
            },
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
            "pageLength": 10,
            "order": [[0, 'asc']],
            "responsive": true,
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-md-end"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "drawCallback": function(settings) {
                // Update row numbers after filtering and pagination
                const api = this.api();
                const startIndex = api.page.info().start;
                
                api.rows({page:'current'}).nodes().each(function(cell, i) {
                    cell.childNodes[0].innerHTML = startIndex + i + 1;
                });
            },
            "initComplete": function() {
                // Add custom classes after initialization
                $('.dataTables_length select').addClass('form-select form-select-sm');
                $('.dataTables_filter input').addClass('form-control form-control-sm');
            }
        });
        
        // Add custom search box
        $('#searchOrder').on('keyup', function() {
            table.search(this.value).draw();
        });
    });
    
    // Current order ID and Client Reference for printing
    let currentPrintOrderId = null;
    let currentClientOrderRef = null;
    
    // Show Print Modal
    function showPrintModal(orderId, orderNumber, clientOrderRef) {
        currentPrintOrderId = orderId;
        currentClientOrderRef = clientOrderRef;
        
        // Set modal title
        document.getElementById('orderNumber').textContent = orderNumber;
        document.getElementById('orderDetails').textContent = `ID: ${orderId} - Ref: ${clientOrderRef || 'N/A'} - ${new Date().toLocaleDateString('id-ID')}`;
        
        // Show loading in print content
        const printContent = document.getElementById('printContent');
        printContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted">Menyiapkan preview cetakan...</p>
            </div>
        `;
        
        // Simulate loading data for preview
        setTimeout(() => {
            printContent.innerHTML = `
                <h5 class="mb-3">Detail Order</h5>
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Order Number</th>
                        <td>${orderNumber}</td>
                    </tr>
                    <tr>
                        <th>Order ID</th>
                        <td>${orderId}</td>
                    </tr>
                    <tr>
                        <th>Client Reference</th>
                        <td>${clientOrderRef || '-'}</td>
                    </tr>
                    <tr>
                        <th>Tanggal Cetak</th>
                        <td>${new Date().toLocaleDateString('id-ID', { 
                            weekday: 'long', 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}</td>
                    </tr>
                    <tr>
                        <th>Dicetak Oleh</th>
                        <td>${document.querySelector('.card-title').textContent.split(' - ')[0] || 'User'}</td>
                    </tr>
                </table>
                
                <div class="alert alert-info mt-3">
                    <i class="ki-duotone ki-information fs-3 me-2"><span class="path1"></span><span class="path2"></span></i>
                    <strong>Informasi:</strong> Dokumen akan dicetak menggunakan Client Reference: <strong>${clientOrderRef || 'N/A'}</strong>
                </div>
            `;
        }, 500);
    }
    
    // Print Selected Order - Using the existing PHP file with client_order_ref
    function printSelectedOrder() {
        if (!currentPrintOrderId) return;
        
        // Close modal first
        const modal = bootstrap.Modal.getInstance(document.getElementById('printModal'));
        if (modal) modal.hide();
        
        // Determine what to use as ID_Order parameter
        // Prioritize client_order_ref, fallback to display_name
        let idOrderParam = currentClientOrderRef || 
                          document.getElementById('orderNumber').textContent;
        
        if (!idOrderParam) {
            Swal.fire({
                title: 'Error!',
                text: 'Tidak ada referensi order yang tersedia untuk dicetak.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Show printing message
        Swal.fire({
            title: 'Mencetak...',
            text: 'Sedang menyiapkan dokumen untuk dicetak',
            icon: 'info',
            showConfirmButton: false,
            timer: 1500
        });
        
        // Construct print URL with ID_Order parameter
        const printUrl = `confirm-check-costing/print-pre-confirm-check.php?ID_Order=${encodeURIComponent(idOrderParam)}`;
        
        // Open in new window
        const printWindow = window.open(printUrl, '_blank');
        
        // Wait for window to load then print
        setTimeout(() => {
            try {
                if (printWindow && !printWindow.closed) {
                    printWindow.focus();
                    
                    // Auto print after 2 seconds to allow page to load completely
                    setTimeout(() => {
                        try {
                            printWindow.print();
                            
                            // Close after print
                            printWindow.onafterprint = function() {
                                setTimeout(() => {
                                    if (!printWindow.closed) {
                                        printWindow.close();
                                    }
                                }, 1000);
                            };
                        } catch (e) {
                            // If auto-print fails, show instructions
                            Swal.fire({
                                title: 'Perhatian',
                                html: `Halaman print telah terbuka.<br>
                                      Silakan klik tombol print pada halaman tersebut atau gunakan shortcut <kbd>Ctrl+P</kbd>.<br><br>
                                      <a href="${printUrl}" target="_blank" class="btn btn-primary">
                                          <i class="ki-duotone ki-printer fs-2 me-2"></i>
                                          Buka Halaman Print
                                      </a>`,
                                icon: 'info',
                                showConfirmButton: true,
                                confirmButtonText: 'OK'
                            });
                        }
                    }, 2000);
                } else {
                    // If popup is blocked, show direct link
                    Swal.fire({
                        title: 'Pop-up diblokir!',
                        html: `Browser memblokir pop-up. Silakan klik link di bawah untuk membuka halaman print:<br><br>
                              <a href="${printUrl}" target="_blank" class="btn btn-primary btn-lg">
                                  <i class="ki-duotone ki-printer fs-2 me-2"></i>
                                  Buka Halaman Print
                              </a>`,
                        icon: 'warning',
                        showConfirmButton: false
                    });
                }
            } catch (e) {
                console.error('Print error:', e);
                
                // Fallback: show direct link
                Swal.fire({
                    title: 'Buka Halaman Print',
                    html: `Klik link di bawah untuk membuka halaman print:<br><br>
                          <a href="${printUrl}" target="_blank" class="btn btn-primary btn-lg">
                              <i class="ki-duotone ki-printer fs-2 me-2"></i>
                              Buka & Cetak
                          </a>`,
                    icon: 'warning',
                    showConfirmButton: false
                });
            }
        }, 1000);
    }
    
    // View Order Details
    function viewOrder(orderId) {
        // Show loading
        Swal.fire({
            title: 'Loading...',
            text: 'Mengambil data order',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Simulate API call
        setTimeout(() => {
            Swal.fire({
                title: 'View Order',
                html: `Menampilkan detail untuk order ID: <b>${orderId}</b><br><br>
                      <small>Fitur ini dapat diintegrasikan dengan API Odoo untuk menampilkan detail lengkap</small>`,
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }, 1000);
    }
    
    // Edit Order
    function editOrder(orderId) {
        // Show loading
        Swal.fire({
            title: 'Loading...',
            text: 'Membuka form edit',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Simulate API call
        setTimeout(() => {
            Swal.fire({
                title: 'Edit Order',
                html: `Membuka form edit untuk order ID: <b>${orderId}</b><br><br>
                      <small>Fitur ini dapat diintegrasikan dengan form edit Odoo</small>`,
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        }, 1000);
    }
    
    // Refresh Data
    function refreshData() {
        Swal.fire({
            title: 'Memperbarui Data',
            text: 'Sedang mengambil data terbaru...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Reload page after 1 second
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
</script>
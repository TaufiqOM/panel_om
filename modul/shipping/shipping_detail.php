<?php
require_once __DIR__ . '/../../inc/config.php';
?>

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
                        All Shipping
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
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search shipping..." />
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
                <button class="btn btn-sm btn-primary btn-sync-shipping mb-5" id="syncShippingBtn">
                    <i class="ki-duotone ki-plus fs-4"></i> Sync Shipping from Odoo
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
                <table id="shippingTable"
                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all">
                    <thead class="">
                        <tr>
                            <th>No</th>
                            <th>Name</th>
                            <th>Scheduled Date</th>
                            <th>Description</th>
                            <th>Ship To</th>
                            <th class="text-end" style="min-width: 120px; width: 120px;">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // Query untuk mengambil data shipping
                        $sql = "SELECT id, name, sheduled_date, description, ship_to FROM shipping ORDER BY id";
                        $result = mysqli_query($conn, $sql);

                        if ($result && mysqli_num_rows($result) > 0):
                            $no = 1;
                        ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="min-w-50px">
                                        <div class="position-relative pe-3 py-2">
                                            <span class="mb-1 text-gray-900 fw-bold"><?= $no++ ?></span>
                                        </div>
                                    </td>
                                    <td class="min-w-150px">
                                        <div class="d-flex gap-2 mb-2">
                                            <a href="javascript:void(0);" class="badge badge-light-primary fs-7 btn-detail-shipping" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></a>
                                        </div>
                                    </td>
                                    <td class="min-w-200px">
                                        <span class="fw-bold text-primary"><?= $row['sheduled_date'] ? date('Y-m-d', strtotime($row['sheduled_date'])) : '-' ?></span>
                                    </td>
                                    <td class="min-w-200px">
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($row['description']) ?></span>
                                    </td>
                                    <td class="min-w-200px">
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($row['ship_to']) ?></span>
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <div class="btn-group" style="flex-shrink: 0;">
                                                <button type="button" class="btn btn-sm btn-icon btn-light-primary btn-print-shipping dropdown-toggle" 
                                                        data-bs-toggle="dropdown" 
                                                        data-id="<?= $row['id'] ?>" 
                                                        data-name="<?= htmlspecialchars($row['name']) ?>"
                                                        aria-expanded="false"
                                                        title="Print">
                                                    <i class="ki-duotone ki-printer fs-2">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item btn-print-shipping-product" 
                                                           href="javascript:void(0);" 
                                                           data-id="<?= $row['id'] ?>"
                                                           data-name="<?= htmlspecialchars($row['name']) ?>">
                                                            <i class="ki-duotone ki-printer fs-4 me-2">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                            Cetak Shipping Product
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item btn-print-manual-stuffing" 
                                                           href="javascript:void(0);" 
                                                           data-id="<?= $row['id'] ?>"
                                                           data-name="<?= htmlspecialchars($row['name']) ?>">
                                                            <i class="ki-duotone ki-file fs-4 me-2">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                            </i>
                                                            Cetak Manual Stuffing
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <button class="btn btn-sm btn-icon btn-light-info btn-detail-manual-stuffing" 
                                                    style="flex-shrink: 0;"
                                                    data-id="<?= $row['id'] ?>" 
                                                    data-name="<?= htmlspecialchars($row['name']) ?>"
                                                    title="Manual Stuffing Detail">
                                                <i class="ki-duotone ki-barcode fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                    <span class="path5"></span>
                                                    <span class="path6"></span>
                                                    <span class="path7"></span>
                                                    <span class="path8"></span>
                                                </i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Tidak ada data shipping ditemukan</td>
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

                <!-- begin:: modal detail shipping -->
                <div class="modal fade" id="shippingDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Detail Shipping <span id="modalShippingName" class="text-primary fw-bold"> </span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center p-5">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal detail shipping -->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<style>
/* Soft Color Scheme - Comparison Cards */
.comparison-card {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    overflow: hidden;
    background: #f8f9fa;
}

.comparison-header {
    background: #6c757d;
    color: white;
}

.comparison-body {
    background: #fff;
}

.comparison-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.comparison-column {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    overflow: hidden;
    background: white;
}

.column-header {
    font-size: 0.8rem;
    color: #495057;
}

.column-header.odoo {
    background: #e3f2fd;
    border-bottom: 2px solid #90caf9;
}

.column-header.manual {
    background: #fff3e0;
    border-bottom: 2px solid #ffb74d;
}

.column-content {
    overflow-y: auto;
    scrollbar-width: thin;
    background: #fafafa;
}

.column-content::-webkit-scrollbar {
    width: 4px;
}

.column-content::-webkit-scrollbar-thumb {
    background: #bdbdbd;
    border-radius: 2px;
}

.lot-item {
    border-bottom: 1px solid #f0f0f0;
    transition: transform 0.2s;
    margin-bottom: 2px;
}

.lot-item:last-child {
    border-bottom: none;
}

.lot-item:hover {
    transform: translateX(3px);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Soft Statistics Colors */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.stat-card {
    padding: 12px;
    border-radius: 6px;
    text-align: center;
    border: 1px solid;
}

.stat-card.matched {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.stat-card.odoo-only {
    background: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

.stat-card.manual-only {
    background: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    margin: 5px 0;
}

.stat-label {
    font-size: 11px;
    font-weight: 600;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #3f4254;
}

.empty-state-text {
    color: #7e8299;
    font-size: 0.9rem;
}
</style>

<script>
// Script untuk menangani sync shipping
document.addEventListener('DOMContentLoaded', function() {
    // Tombol sync shipping
    document.getElementById('syncShippingBtn').addEventListener('click', function() {
        // Tampilkan loading state
        const submitBtn = document.getElementById('syncShippingBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Syncing...';
        submitBtn.disabled = true;

        // Kirim permintaan AJAX
        fetch('shipping/sync_shipping.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: ''
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Shipping berhasil disinkronkan!');
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

    // Handle detail shipping modal
    const detailButtons = document.querySelectorAll(".btn-detail-shipping");
    detailButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            const shippingId = this.dataset.id;
            const shippingName = this.dataset.name;
            const modalBody = document.querySelector("#shippingDetailModal .modal-body");
            const modalShippingName = document.getElementById("modalShippingName");

            // Update Judul Modal
            modalShippingName.textContent = "# " + shippingName;

            modalBody.innerHTML = "<div class='text-center p-5'>Loading...</div>";

            fetch("shipping/get_detail_shipping.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: "id=" + encodeURIComponent(shippingId)
                })
                .then(response => response.text())
                .then(data => {
                    modalBody.innerHTML = data;
                    
                    // Initialize buttons after content loaded
                    initializeSyncShippingButton();
                    initializeCompareButton();
                })
                .catch(err => {
                    modalBody.innerHTML = "<div class='alert alert-danger'>Error load data</div>";
                    console.error(err);
                });

            const modal = new bootstrap.Modal(document.getElementById("shippingDetailModal"));
            modal.show();
        });
    });

    // Handle print shipping product
    const printShippingProductButtons = document.querySelectorAll(".btn-print-shipping-product");
    printShippingProductButtons.forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            const shippingId = this.dataset.id;
            // Buka window baru untuk print shipping product
            window.open('shipping/print_shipping.php?id=' + encodeURIComponent(shippingId), '_blank');
        });
    });

    // Handle print manual stuffing
    const printManualStuffingButtons = document.querySelectorAll(".btn-print-manual-stuffing");
    printManualStuffingButtons.forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            const shippingId = this.dataset.id;
            // Buka window baru untuk print manual stuffing
            window.open('shipping/print_manual_stuffing.php?id=' + encodeURIComponent(shippingId), '_blank');
        });
    });
    
    // Manual Stuffing Detail button
    const detailManualStuffingButtons = document.querySelectorAll(".btn-detail-manual-stuffing");
    detailManualStuffingButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            const shippingId = this.dataset.id;
            const shippingName = this.dataset.name;
            const modalBody = document.querySelector("#shippingDetailModal .modal-body");
            const modalShippingName = document.getElementById("modalShippingName");

            // Update Judul Modal
            modalShippingName.textContent = "Manual Stuffing - # " + shippingName;

            modalBody.innerHTML = "<div class='text-center p-5'>Loading...</div>";

            fetch("shipping/get_manual_stuffing_detail.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: "id=" + encodeURIComponent(shippingId)
                })
                .then(response => response.text())
                .then(data => {
                    modalBody.innerHTML = data;
                })
                .catch(err => {
                    modalBody.innerHTML = "<div class='alert alert-danger'>Error load data</div>";
                    console.error(err);
                });

            const modal = new bootstrap.Modal(document.getElementById("shippingDetailModal"));
            modal.show();
        });
    });

    // Pagination will handle showing the table
});

// Fungsi global untuk toggle lot details (digunakan di modal yang dimuat via AJAX)
window.toggleLotDetails = function(rowIndex) {
    const lotRow = document.getElementById('lot-row-' + rowIndex);
    const icon = document.getElementById('icon-' + rowIndex);
    
    if (!lotRow) {
        console.error('Lot row not found for index:', rowIndex);
        return;
    }
    
    if (lotRow.style.display === 'none' || lotRow.style.display === '') {
        // Expand
        lotRow.style.display = 'table-row';
        if (icon) {
            const iconElement = icon.querySelector('i');
            if (iconElement) {
                iconElement.classList.remove('ki-down');
                iconElement.classList.add('ki-up');
            }
            icon.classList.add('rotated');
        }
    } else {
        // Collapse
        lotRow.style.display = 'none';
        if (icon) {
            const iconElement = icon.querySelector('i');
            if (iconElement) {
                iconElement.classList.remove('ki-up');
                iconElement.classList.add('ki-down');
            }
            icon.classList.remove('rotated');
        }
    }
};

// Initialize sync shipping button
function initializeSyncShippingButton() {
    const btnSync = document.getElementById('btnSyncShipping');
    if (btnSync) {
        btnSync.addEventListener('click', function() {
            const shippingId = this.getAttribute('data-shipping-id');
            const originalText = this.innerHTML;
            
            // Confirm before syncing
            if (!confirm('Sinkronkan data pengiriman ini dari Odoo?\n\nProses ini akan:\n1. Update info shipping\n2. Update semua picking\n3. Update semua lot/serial\n\nLanjutkan?')) {
                return;
            }
            
            // Show loading
            this.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                <span>Menyinkronkan...</span>
            `;
            this.disabled = true;
            this.classList.add('btn-loading');
            
            // Call sync API
            fetch('shipping/sync_single_shipping.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'shipping_id=' + encodeURIComponent(shippingId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success feedback
                    this.innerHTML = `
                        <i class="ki-duotone ki-check-circle fs-4 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <span>Sinkron Berhasil!</span>
                    `;
                    this.classList.remove('btn-loading', 'btn-light-primary');
                    this.classList.add('btn-success');
                    
                    showNotification(data.message, 'success');
                    
                    // Reload modal content tanpa refresh page
                    const shippingId = this.getAttribute('data-shipping-id');
                    const modalBody = document.querySelector("#shippingDetailModal .modal-body");
                    
                    // Reload modal content
                    fetch("shipping/get_detail_shipping.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: "id=" + encodeURIComponent(shippingId)
                    })
                    .then(response => response.text())
                    .then(html => {
                        modalBody.innerHTML = html;
                        
                        // Re-initialize buttons after content reloaded
                        initializeSyncShippingButton();
                        initializeCompareButton();
                    })
                    .catch(err => {
                        console.error('Error reloading modal:', err);
                    });
                    
                    // Reset button after 3 seconds
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-light-primary');
                        this.disabled = false;
                    }, 3000);
                } else {
                    this.innerHTML = originalText;
                    this.disabled = false;
                    this.classList.remove('btn-loading');
                    
                    showNotification('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                this.innerHTML = originalText;
                this.disabled = false;
                this.classList.remove('btn-loading');
                
                showNotification('Error Koneksi: ' + error.message, 'danger');
            });
        });
    }
}

// Initialize compare button
function initializeCompareButton() {
    const btnCompare = document.getElementById('btnCompareLotSync');
    if (btnCompare) {
        btnCompare.addEventListener('click', function() {
            const shippingId = this.getAttribute('data-shipping-id');
            const originalText = this.innerHTML;
            
            // Show loading with better animation
            this.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                <span>Menganalisis data...</span>
            `;
            this.disabled = true;
            this.classList.add('btn-loading');
            
            // Call compare API
            fetch('shipping/compare_lot_sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + encodeURIComponent(shippingId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayComparisonResult(data.data, data.barcodes_without_picking || []);
                    const resultSection = document.getElementById('comparisonResult');
                    resultSection.style.display = 'block';
                    
                    // Success feedback
                    this.innerHTML = `
                        <i class="ki-duotone ki-check-circle fs-3 me-1 text-white">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <span>Perbandingan Selesai!</span>
                    `;
                    this.classList.remove('btn-loading');
                    this.classList.add('btn-success');
                    
                    // Smooth scroll with offset
                    setTimeout(() => {
                        resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-primary');
                        this.disabled = false;
                    }, 2000);
                } else {
                    this.innerHTML = originalText;
                    this.disabled = false;
                    this.classList.remove('btn-loading');
                    
                    // Show error notification
                    showNotification('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                this.innerHTML = originalText;
                this.disabled = false;
                this.classList.remove('btn-loading');
                
                // Show error notification
                showNotification('Error Koneksi: ' + error.message, 'danger');
            });
        });
    }
}

function displayComparisonResult(data, barcodesWithoutPicking) {
    const container = document.getElementById('comparisonContent');
    let html = '';
    
    if (data.length === 0) {
        html = `
            <div class="empty-state">
                <div class="empty-state-icon">📊</div>
                <div class="empty-state-title">Tidak Ada Data Perbandingan</div>
                <div class="empty-state-text">Tidak ada data yang tersedia untuk dibandingkan saat ini.</div>
            </div>
        `;
    } else {
        data.forEach((pickingGroup, index) => {
            const totalMatched = pickingGroup.total_matched || 0;
            const totalOdooOnly = pickingGroup.total_odoo_only || 0;
            const totalDbOnly = pickingGroup.total_db_only || 0;
            const totalOdoo = pickingGroup.total_odoo || 0;
            const totalDb = pickingGroup.total_db || 0;
            const matchPercentage = totalOdoo > 0 ? 
                Math.round((totalMatched / totalOdoo) * 100) : 0;
            
            // Determine color based on match percentage
            let matchColor = 'danger';
            if (matchPercentage >= 80) matchColor = 'success';
            else if (matchPercentage >= 50) matchColor = 'warning';
            
            // Sale order display
            const saleDisplay = pickingGroup.sale_order_name 
                ? `SO: ${pickingGroup.sale_order_name}` 
                : (pickingGroup.sale_id ? `Sale ID: ${pickingGroup.sale_id}` : 'Tidak ada Sale Order');
            
            html += `
                <div class="comparison-card mb-3" data-picking-id="${pickingGroup.picking_id || 0}" data-sale-id="${pickingGroup.sale_id || 0}">
                    <div class="comparison-header py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-white fw-bold">Picking: ${pickingGroup.picking_name || '-'}</small>
                                ${saleDisplay ? '<br><small class="text-white-50" style="font-size: 0.7rem;">' + saleDisplay + '</small>' : ''}
                                ${pickingGroup.client_order_ref ? '<br><small class="text-white-50" style="font-size: 0.7rem;">PO: ' + pickingGroup.client_order_ref + '</small>' : ''}
                            </div>
                            <div class="text-end">
                                <span class="badge badge-${matchColor} badge-sm">${matchPercentage}% Cocok</span>
                                <br><small class="text-white-50" style="font-size: 0.7rem;">${totalMatched}/${totalOdoo} item</small>
                            </div>
                        </div>
                    </div>
                    <div class="comparison-body p-2">
                        <!-- Statistics Summary -->
                        <div class="stats-row mb-3">
                            <div class="stat-card matched">
                                <div class="stat-number">${totalMatched}</div>
                                <div class="stat-label">Sudah Sama</div>
                                <small style="opacity: 0.8;">Odoo = DB</small>
                            </div>
                            <div class="stat-card odoo-only">
                                <div class="stat-number">${totalOdooOnly}</div>
                                <div class="stat-label">Hanya di Odoo</div>
                                <small style="opacity: 0.8;">Kebanyakan (${totalOdooOnly} item)</small>
                            </div>
                            <div class="stat-card manual-only">
                                <div class="stat-number">${totalDbOnly}</div>
                                <div class="stat-label">Hanya di DB</div>
                                <small style="opacity: 0.8;">Kurang (${totalDbOnly} item)</small>
                            </div>
                        </div>
                        <div class="mb-2 text-center" style="font-size: 0.75rem;">
                            <span class="badge badge-info me-2">Odoo: ${totalOdoo} barcode</span>
                            <span class="badge badge-warning">DB: ${totalDb} barcode</span>
                        </div>
                        
                        <!-- Detail Per Product -->
                        ${pickingGroup.products && pickingGroup.products.length > 0 ? `
                        <div class="mb-3">
                            <h6 class="mb-2" style="font-size: 0.85rem; font-weight: 600; color: #495057;">
                                <i class="ki-duotone ki-box fs-4 text-primary me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Detail Per Product (${pickingGroup.products.length} product)
                            </h6>
                            ${pickingGroup.products.map((product, prodIndex) => renderProductDetailPicking(product, pickingGroup.picking_id || index.toString(), prodIndex)).join('')}
                        </div>
                        ` : '<div class="text-center text-muted py-2"><small>Tidak ada product</small></div>'}
                        
                        <!-- Action Button for matched items -->
                        ${totalMatched > 0 ? `
                        <div class="mt-2 pt-2" style="border-top: 1px solid #dee2e6;">
                            <button class="btn btn-sm btn-success w-100 btn-process-barcode" 
                                    data-sale-id="${pickingGroup.sale_id || 0}"
                                    data-sale-order-name="${pickingGroup.sale_order_name || ''}"
                                    data-picking-names="${pickingGroup.picking_name || ''}"
                                    data-matched-count="${totalMatched}">
                                <i class="ki-duotone ki-barcode fs-4 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Proses Barcode (${totalMatched} item)
                            </button>
                            <small class="text-muted d-block mt-1 text-center" style="font-size: 0.7rem;">
                                Update tanggal, reset lot & insert yang cocok untuk Picking ini
                            </small>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        // Add event listeners for process barcode buttons after rendering
        setTimeout(() => {
            initProcessBarcodeButtons();
            initToggleCodeButtons();
        }, 100);
    }
    
    // Tampilkan barcode tanpa picking di bagian bawah (warna merah)
    if (barcodesWithoutPicking && barcodesWithoutPicking.length > 0) {
        html += `
            <div class="mt-4">
                <div class="card" style="border: 2px solid #dc3545; border-radius: 6px; background-color: #fff5f5;">
                    <div class="card-header" style="background-color: #dc3545; color: white; padding: 10px 15px;">
                        <h6 class="mb-0" style="font-size: 0.9rem; font-weight: 600;">
                            <i class="ki-duotone ki-cross-circle fs-4 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Barcode Tanpa Picking di Odoo
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        ${barcodesWithoutPicking.map((group, groupIndex) => {
                            const saleDisplay = group.sale_order_name 
                                ? `SO: ${group.sale_order_name}` 
                                : (group.sale_order_id ? `Sale ID: ${group.sale_order_id}` : 'Tidak diketahui');
                            
                            return `
                                <div class="mb-3 ${groupIndex > 0 ? 'mt-3 pt-3 border-top' : ''}" style="border-color: #ffcccc !important;">
                                    <div class="mb-2">
                                        <strong style="color: #dc3545; font-size: 0.85rem;">
                                            <i class="ki-duotone ki-file fs-5 me-1">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Seharusnya di Picking: ${saleDisplay}
                                        </strong>
                                    </div>
                                    <div class="mb-2" style="font-size: 0.75rem;">
                                        <span class="badge badge-info me-2" style="background-color: #17a2b8;">
                                            <i class="ki-duotone ki-chart fs-6 me-1">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Di Odoo: ${group.count_odoo || 0} barcode
                                        </span>
                                        <span class="badge badge-warning" style="background-color: #ffc107; color: #000;">
                                            <i class="ki-duotone ki-chart fs-6 me-1">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Di Manual Stuffing: ${group.count_manual || 0} barcode
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        ${group.barcodes.map(barcode => `
                                            <span class="badge badge-danger badge-lg" style="font-size: 0.8rem; padding: 6px 10px; background-color: #dc3545;">
                                                ${barcode.barcode}
                                                ${barcode.product_id ? `<small style="opacity: 0.8;"> (Product ID: ${barcode.product_id})</small>` : ''}
                                            </span>
                                        `).join('')}
                                    </div>
                                    <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                                        Total barcode tanpa picking: ${group.barcodes.length} barcode
                                    </small>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Initialize process barcode buttons
function initProcessBarcodeButtons() {
    const buttons = document.querySelectorAll('.btn-process-barcode');
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            const saleId = this.getAttribute('data-sale-id');
            const saleOrderName = this.getAttribute('data-sale-order-name');
            const pickingNames = this.getAttribute('data-picking-names');
            const matchedCount = this.getAttribute('data-matched-count');
            
            // Get shipping scheduled date for display
            const shippingInfo = document.querySelector('.info-value');
            
            // Confirm before processing
            const displayName = saleOrderName || `Sale ID: ${saleId}`;
            if (!confirm(`Proses Barcode untuk ${displayName}?\n\nPicking: ${pickingNames}\n\nProses ini akan:\n1. Update tanggal kirim di picking\n2. Reset semua lot/serial yang ada\n3. Insert ${matchedCount} lot/serial yang cocok\n\nLanjutkan?`)) {
                return;
            }
            
            const originalHtml = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';
            this.disabled = true;
            
            // Get shipping ID from compare button
            const shippingId = document.getElementById('btnCompareLotSync').getAttribute('data-shipping-id');
            
            // Call process API with sale_id
            fetch('shipping/process_barcode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `shipping_id=${shippingId}&sale_id=${saleId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.innerHTML = '<i class="ki-duotone ki-check-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>Berhasil!';
                    this.classList.remove('btn-success');
                    this.classList.add('btn-primary');
                    
                    showNotification(data.message, 'success');
                    
                    // Reset button after 3 seconds
                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                        this.classList.remove('btn-primary');
                        this.classList.add('btn-success');
                        this.disabled = false;
                    }, 3000);
                } else {
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                    showNotification('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                this.innerHTML = originalHtml;
                this.disabled = false;
                showNotification('Error koneksi: ' + error.message, 'danger');
            });
        });
    });
}

// Initialize toggle buttons untuk show/hide detail codes
function initToggleCodeButtons() {
    // Handle toggle buttons untuk collapse detail codes (matched codes)
    const toggleButtons = document.querySelectorAll('[data-bs-toggle="collapse"][data-bs-target^="#matchedCodes-"]');
    toggleButtons.forEach(btn => {
        const targetId = btn.getAttribute('data-bs-target');
        const targetElement = document.querySelector(targetId);
        
        if (targetElement) {
            targetElement.addEventListener('show.bs.collapse', function() {
                const toggleText = btn.querySelector('.toggle-text');
                const toggleIcon = btn.querySelector('.toggle-icon');
                if (toggleText) toggleText.textContent = 'Sembunyikan Detail Code yang Cocok';
                if (toggleIcon) {
                    toggleIcon.className = 'ki-duotone ki-up fs-6 me-1 toggle-icon';
                    toggleIcon.innerHTML = '<span class="path1"></span><span class="path2"></span>';
                }
                btn.classList.remove('collapsed');
            });
            
            targetElement.addEventListener('hide.bs.collapse', function() {
                const toggleText = btn.querySelector('.toggle-text');
                const toggleIcon = btn.querySelector('.toggle-icon');
                if (toggleText) toggleText.textContent = 'Lihat Detail Code yang Cocok';
                if (toggleIcon) {
                    toggleIcon.className = 'ki-duotone ki-down fs-6 me-1 toggle-icon';
                    toggleIcon.innerHTML = '<span class="path1"></span><span class="path2"></span>';
                }
                btn.classList.add('collapsed');
            });
        }
    });
    
    // Handle toggle buttons untuk collapse perbandingan 2 sisi
    const comparisonToggleButtons = document.querySelectorAll('[data-bs-toggle="collapse"][data-bs-target^="#comparison-"]');
    comparisonToggleButtons.forEach(btn => {
        const targetId = btn.getAttribute('data-bs-target');
        const targetElement = document.querySelector(targetId);
        
        if (targetElement) {
            targetElement.addEventListener('show.bs.collapse', function() {
                const toggleText = btn.querySelector('.toggle-text');
                const toggleIcon = btn.querySelector('.toggle-icon');
                if (toggleText) toggleText.textContent = 'Sembunyikan Perbandingan 2 Sisi';
                if (toggleIcon) {
                    toggleIcon.className = 'ki-duotone ki-up fs-6 me-1 toggle-icon';
                    toggleIcon.innerHTML = '<span class="path1"></span><span class="path2"></span>';
                }
                btn.classList.remove('collapsed');
            });
            
            targetElement.addEventListener('hide.bs.collapse', function() {
                const toggleText = btn.querySelector('.toggle-text');
                const toggleIcon = btn.querySelector('.toggle-icon');
                if (toggleText) toggleText.textContent = 'Lihat Perbandingan 2 Sisi';
                if (toggleIcon) {
                    toggleIcon.className = 'ki-duotone ki-down fs-6 me-1 toggle-icon';
                    toggleIcon.innerHTML = '<span class="path1"></span><span class="path2"></span>';
                }
                btn.classList.add('collapsed');
            });
        }
    });
}

// Render orphan codes (code yang tidak ada di picking manapun)
function renderOrphanCodes(orphanCodes) {
    if (!orphanCodes || orphanCodes.length === 0) {
        return '';
    }
    
    return `
        <div class="card" style="border: 1px solid #ffc107; border-radius: 6px;">
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                        <thead class="table-warning">
                            <tr>
                                <th style="padding: 6px; width: 22%;">Production Code</th>
                                <th style="padding: 6px; width: 10%;">Product ID</th>
                                <th style="padding: 6px; width: 12%;">Sale Order ID</th>
                                <th style="padding: 6px; width: 56%;">Seharusnya di Picking</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${orphanCodes.map((orphan, idx) => `
                                <tr ${orphan.wrong_sale_order ? 'style="background: #fff3cd;"' : ''}>
                                    <td style="padding: 6px;">
                                        <span class="badge badge-warning badge-sm">${orphan.code}</span>
                                        ${orphan.wrong_sale_order ? `
                                            <br><small class="text-danger" style="font-size: 0.65rem;">
                                                <i class="ki-duotone ki-information-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Sale Order berbeda!
                                            </small>
                                        ` : ''}
                                    </td>
                                    <td style="padding: 6px;">
                                        ${orphan.product_id ? `<span class="badge badge-info badge-sm">${orphan.product_id}</span>` : '<small class="text-muted">-</small>'}
                                    </td>
                                    <td style="padding: 6px;">
                                        ${orphan.sale_order_id ? `
                                            <span class="badge ${orphan.wrong_sale_order ? 'badge-danger' : 'badge-secondary'} badge-sm">
                                                ${orphan.sale_order_id}
                                            </span>
                                            ${orphan.wrong_sale_order ? `
                                                <br><small class="text-muted" style="font-size: 0.65rem;">
                                                    Current: ${orphan.current_sale_id}
                                                </small>
                                            ` : ''}
                                        ` : '<small class="text-muted">-</small>'}
                                    </td>
                                    <td style="padding: 6px;">
                                        ${(orphan.suggested_pickings && orphan.suggested_pickings.length > 0) || (orphan.suggested_pickings_other_shipping && orphan.suggested_pickings_other_shipping.length > 0) ? `
                                            ${orphan.suggested_pickings && orphan.suggested_pickings.length > 0 ? `
                                                <div class="mb-1">
                                                    <small class="text-muted d-block mb-1" style="font-size: 0.65rem; font-weight: 600;">Di Shipping Ini:</small>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        ${orphan.suggested_pickings.map(sp => `
                                                            <span class="badge badge-primary badge-sm" title="${sp.reason || ''}">
                                                                ${sp.picking_name}
                                                            </span>
                                                        `).join('')}
                                                    </div>
                                                </div>
                                            ` : ''}
                                            ${orphan.suggested_pickings_other_shipping && orphan.suggested_pickings_other_shipping.length > 0 ? `
                                                <div class="mt-2 pt-2" style="border-top: 1px dashed #ffc107;">
                                                    <small class="text-warning d-block mb-1" style="font-size: 0.65rem; font-weight: 600;">
                                                        <i class="ki-duotone ki-arrow-right fs-5">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Di Shipping Lain (${orphan.suggested_pickings_other_shipping.length} picking):
                                                    </small>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        ${orphan.suggested_pickings_other_shipping.map(sp => `
                                                            <div class="badge badge-warning badge-sm p-1" style="display: inline-block;" title="${sp.reason || ''} | Shipping: ${sp.shipping_name || 'Tidak diketahui'}">
                                                                <div>${sp.picking_name}</div>
                                                                ${sp.shipping_name ? `<small style="font-size: 0.6rem; display: block; margin-top: 2px;">📦 ${sp.shipping_name}</small>` : ''}
                                                            </div>
                                                        `).join('')}
                                                    </div>
                                                </div>
                                            ` : ''}
                                            ${orphan.suggested_pickings && orphan.suggested_pickings.length > 0 && orphan.suggested_pickings[0].reason ? `
                                                <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">
                                                    <em>${orphan.suggested_pickings[0].reason}</em>
                                                </small>
                                            ` : ''}
                                        ` : `
                                            <small class="text-muted">Tidak dapat diidentifikasi</small>
                                        `}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 p-2" style="background: #fff3cd; border-radius: 4px;">
                    <small class="text-muted" style="font-size: 0.7rem;">
                        <strong>Catatan:</strong> Code-code ini ada di manual stuffing tapi tidak ditemukan di picking manapun di sale order ini. 
                        ${orphanCodes.some(oc => oc.product_id) ? 
                            'Beberapa code memiliki product_id yang dapat membantu identifikasi picking yang tepat. ' : 
                            ''}
                        ${orphanCodes.some(oc => oc.wrong_sale_order) ? 
                            '<strong class="text-danger">Beberapa code memiliki Sale Order ID yang berbeda dengan sale order saat ini - kemungkinan code ini seharusnya ada di sale order lain.</strong>' : 
                            ''}
                        Silakan cek kembali apakah code ini seharusnya ada di picking lain atau di sale order lain.
                    </small>
                </div>
            </div>
        </div>
    `;
}

// Render product detail dengan breakdown per picking
function renderProductDetail(product, pickingDetails) {
    const productName = product.product_name || `Product ID: ${product.product_id}`;
    const totalMatched = product.total_matched || 0;
    const totalOdooOnly = product.total_odoo_only || 0;
    const totalManualOnly = product.total_manual_only || 0;
    
    // Hitung per picking
    const pickingBreakdown = [];
    const matchedLotNames = product.matched.map(m => m.code);
    
    pickingDetails.forEach(picking => {
        const pickingLots = picking.lots || [];
        const productLots = pickingLots.filter(lot => lot.product_id == product.product_id);
        
        let matchedCount = 0;
        let odooOnlyCount = 0;
        
        productLots.forEach(lot => {
            if (matchedLotNames.includes(lot.lot_name)) {
                matchedCount++;
            } else {
                odooOnlyCount++;
            }
        });
        
        if (productLots.length > 0) {
            pickingBreakdown.push({
                picking_name: picking.picking_name,
                matched: matchedCount,
                odoo_only: odooOnlyCount,
                total: productLots.length
            });
        }
    });
    
    // Manual only tidak bisa di-breakdown per picking karena manual stuffing tidak punya relasi ke picking
    // Manual only dihitung untuk seluruh sale order
    
    return `
        <div class="card mb-2" style="border: 1px solid #dee2e6; border-radius: 6px;">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0" style="font-size: 0.8rem; font-weight: 600; color: #495057;">
                        ${productName}
                    </h6>
                    <span class="badge badge-light-primary badge-sm">ID: ${product.product_id || '-'}</span>
                </div>
                
                <!-- Summary per product -->
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <div class="text-center p-1" style="background: #d4edda; border-radius: 4px; border: 1px solid #c3e6cb;">
                            <div style="font-size: 1.1rem; font-weight: bold; color: #155724;">${totalMatched}</div>
                            <small style="font-size: 0.65rem; color: #155724;">Cocok</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-1" style="background: #fff3cd; border-radius: 4px; border: 1px solid #ffeaa7;">
                            <div style="font-size: 1.1rem; font-weight: bold; color: #856404;">${totalOdooOnly}</div>
                            <small style="font-size: 0.65rem; color: #856404;">Kebanyakan</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-1" style="background: #d1ecf1; border-radius: 4px; border: 1px solid #bee5eb;">
                            <div style="font-size: 1.1rem; font-weight: bold; color: #0c5460;">${totalManualOnly}</div>
                            <small style="font-size: 0.65rem; color: #0c5460;">Kurang</small>
                        </div>
                    </div>
                </div>
                
                <!-- Detail per picking -->
                ${pickingBreakdown.length > 0 ? `
                <div class="mt-2">
                    <small class="text-muted d-block mb-1" style="font-size: 0.7rem; font-weight: 600;">Detail Per Picking:</small>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size: 0.7rem;">
                            <thead class="table-light">
                                <tr>
                                    <th style="padding: 4px;">Picking</th>
                                    <th class="text-center" style="padding: 4px; background: #d4edda;">Cocok</th>
                                    <th class="text-center" style="padding: 4px; background: #fff3cd;">Kebanyakan</th>
                                    <th class="text-center" style="padding: 4px; background: #d1ecf1;">Kurang</th>
                                    <th class="text-center" style="padding: 4px;">Total Odoo</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${pickingBreakdown.map(pb => `
                                    <tr>
                                        <td style="padding: 4px;">
                                            <small class="fw-bold">${pb.picking_name}</small>
                                        </td>
                                        <td class="text-center" style="padding: 4px; background: #d4edda;">
                                            <span class="badge badge-success badge-sm">${pb.matched}</span>
                                        </td>
                                        <td class="text-center" style="padding: 4px; background: #fff3cd;">
                                            <span class="badge badge-warning badge-sm">${pb.odoo_only}</span>
                                        </td>
                                        <td class="text-center" style="padding: 4px; background: #d1ecf1;">
                                            <small class="text-muted">-</small>
                                        </td>
                                        <td class="text-center" style="padding: 4px;">
                                            <small class="fw-bold">${pb.total}</small>
                                        </td>
                                    </tr>
                                `).join('')}
                                ${totalManualOnly > 0 ? `
                                    <tr style="background: #f8f9fa;">
                                        <td colspan="3" style="padding: 4px;">
                                            <small class="text-muted"><em>Manual Only (tidak bisa di-breakdown per picking)</em></small>
                                        </td>
                                        <td class="text-center" style="padding: 4px; background: #d1ecf1;">
                                            <span class="badge badge-info badge-sm">${totalManualOnly}</span>
                                        </td>
                                        <td class="text-center" style="padding: 4px;">
                                            <small>-</small>
                                        </td>
                                    </tr>
                                ` : ''}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;
}

// Render product detail untuk struktur baru (per picking)
function renderProductDetailPicking(product, pickingId, productIndex) {
    const productName = product.product_name || `Product ID: ${product.product_id || '-'}`;
    const productId = product.product_id || 0;
    
    // Data dari struktur baru
    const odooSide = product.odoo_side || { barcodes: [], count: 0 };
    const dbSide = product.db_side || { barcodes: [], count: 0 };
    const matched = product.matched || { barcodes: [], count: 0 };
    const odooOnly = product.odoo_only || { barcodes: [], count: 0 };
    const dbOnly = product.db_only || { barcodes: [], count: 0 };
    
    // Sanitize ID untuk menghindari karakter yang tidak valid
    const sanitizedPickingId = String(pickingId).replace(/[^a-zA-Z0-9]/g, '_');
    const sanitizedProductId = String(productId).replace(/[^a-zA-Z0-9]/g, '_');
    const uniqueId = `product-${sanitizedPickingId}-${sanitizedProductId}-${productIndex}`;
    
    return `
        <div class="card mb-2" style="border: 1px solid #dee2e6; border-radius: 6px;">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0" style="font-size: 0.8rem; font-weight: 600; color: #495057;">
                        ${productName}
                    </h6>
                    <span class="badge badge-light-primary badge-sm">ID: ${product.product_id || '-'}</span>
                </div>
                
                <!-- Summary per product -->
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <div class="text-center p-1" style="background: #d4edda; border-radius: 4px; border: 1px solid #c3e6cb;">
                            <div style="font-size: 1.1rem; font-weight: bold; color: #155724;">${matched.count}</div>
                            <small style="font-size: 0.65rem; color: #155724;">Cocok</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-1" style="background: #fff3cd; border-radius: 4px; border: 1px solid #ffeaa7;">
                            <div style="font-size: 1.1rem; font-weight: bold; color: #856404;">${odooOnly.count}</div>
                            <small style="font-size: 0.65rem; color: #856404;">Hanya Odoo</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-1" style="background: #d1ecf1; border-radius: 4px; border: 1px solid #bee5eb;">
                            <div style="font-size: 1.1rem; font-weight: bold; color: #0c5460;">${dbOnly.count}</div>
                            <small style="font-size: 0.65rem; color: #0c5460;">Hanya DB</small>
                        </div>
                    </div>
                </div>
                
                <div class="mt-2 mb-2">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center p-1" style="background: #e7f3ff; border-radius: 4px; border: 1px solid #b3d9ff;">
                                <small style="font-size: 0.65rem; color: #004085; font-weight: 600;">Sisi Odoo</small>
                                <div style="font-size: 0.9rem; font-weight: bold; color: #004085;">${odooSide.count} barcode</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-1" style="background: #fff5e6; border-radius: 4px; border: 1px solid #ffd9b3;">
                                <small style="font-size: 0.65rem; color: #856404; font-weight: 600;">Sisi DB</small>
                                <div style="font-size: 0.9rem; font-weight: bold; color: #856404;">${dbSide.count} barcode</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Perbandingan 2 Sisi -->
                <div class="mt-2">
                    <button class="btn btn-sm btn-outline-primary w-100 text-start collapsed" type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#comparison-${uniqueId}" 
                            aria-expanded="false"
                            aria-controls="comparison-${uniqueId}"
                            style="font-size: 0.75rem;">
                        <i class="ki-duotone ki-down fs-6 me-1 toggle-icon">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <span class="toggle-text">Lihat Perbandingan 2 Sisi</span>
                    </button>
                </div>
                <div class="collapse mt-2" id="comparison-${uniqueId}">
                    <div class="row g-2">
                        <!-- Sisi Odoo -->
                        <div class="col-6">
                            <div class="card" style="background: #e7f3ff; border: 1px solid #b3d9ff;">
                                <div class="card-header p-2" style="background: #b3d9ff; border-bottom: 1px solid #b3d9ff;">
                                    <h6 class="mb-0" style="font-size: 0.75rem; font-weight: 600; color: #004085;">
                                        <i class="ki-duotone ki-chart fs-5 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Sisi Odoo (${odooSide.count})
                                    </h6>
                                </div>
                                <div class="card-body p-2" style="max-height: 300px; overflow-y: auto;">
                                    ${odooSide.barcodes.length > 0 ? `
                                        <div class="d-flex flex-wrap gap-1">
                                            ${odooSide.barcodes.map(barcode => `
                                                <span class="badge badge-info badge-sm" style="font-size: 0.7rem; padding: 4px 8px;">
                                                    ${barcode.code}
                                                    ${barcode.qty && barcode.qty !== '-' ? `<small style="opacity: 0.8;"> (Qty: ${barcode.qty})</small>` : ''}
                                                </span>
                                            `).join('')}
                                        </div>
                                    ` : '<small class="text-muted">Tidak ada barcode</small>'}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sisi DB -->
                        <div class="col-6">
                            <div class="card" style="background: #fff5e6; border: 1px solid #ffd9b3;">
                                <div class="card-header p-2" style="background: #ffd9b3; border-bottom: 1px solid #ffd9b3;">
                                    <h6 class="mb-0" style="font-size: 0.75rem; font-weight: 600; color: #856404;">
                                        <i class="ki-duotone ki-database fs-5 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Sisi DB (${dbSide.count})
                                    </h6>
                                </div>
                                <div class="card-body p-2" style="max-height: 300px; overflow-y: auto;">
                                    ${dbSide.barcodes.length > 0 ? `
                                        <div class="d-flex flex-wrap gap-1">
                                            ${dbSide.barcodes.map(barcode => `
                                                <span class="badge badge-warning badge-sm" style="font-size: 0.7rem; padding: 4px 8px; color: #000;">
                                                    ${barcode.code}
                                                </span>
                                            `).join('')}
                                        </div>
                                    ` : '<small class="text-muted">Tidak ada barcode</small>'}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Matched, Odoo Only, DB Only -->
                    <div class="row g-2 mt-2">
                        <!-- Matched -->
                        ${matched.count > 0 ? `
                        <div class="col-12">
                            <div class="card" style="background: #d4edda; border: 1px solid #c3e6cb;">
                                <div class="card-header p-2" style="background: #c3e6cb; border-bottom: 1px solid #c3e6cb;">
                                    <h6 class="mb-0" style="font-size: 0.75rem; font-weight: 600; color: #155724;">
                                        <i class="ki-duotone ki-check-circle fs-5 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Cocok (${matched.count})
                                    </h6>
                                </div>
                                <div class="card-body p-2">
                                    <div class="d-flex flex-wrap gap-1">
                                        ${matched.barcodes.map(barcode => `
                                            <span class="badge badge-success badge-sm" style="font-size: 0.7rem; padding: 4px 8px;">
                                                ${barcode.code}
                                                ${barcode.qty_odoo && barcode.qty_odoo !== '-' ? `<small style="opacity: 0.8;"> (Qty Odoo: ${barcode.qty_odoo})</small>` : ''}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Odoo Only -->
                        ${odooOnly.count > 0 ? `
                        <div class="col-12">
                            <div class="card" style="background: #fff3cd; border: 1px solid #ffeaa7;">
                                <div class="card-header p-2" style="background: #ffeaa7; border-bottom: 1px solid #ffeaa7;">
                                    <h6 class="mb-0" style="font-size: 0.75rem; font-weight: 600; color: #856404;">
                                        <i class="ki-duotone ki-information fs-5 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Hanya di Odoo (${odooOnly.count})
                                    </h6>
                                </div>
                                <div class="card-body p-2">
                                    <div class="d-flex flex-wrap gap-1">
                                        ${odooOnly.barcodes.map(barcode => `
                                            <span class="badge badge-warning badge-sm" style="font-size: 0.7rem; padding: 4px 8px; color: #000;">
                                                ${barcode.code}
                                                ${barcode.qty && barcode.qty !== '-' ? `<small style="opacity: 0.8;"> (Qty: ${barcode.qty})</small>` : ''}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- DB Only -->
                        ${dbOnly.count > 0 ? `
                        <div class="col-12">
                            <div class="card" style="background: #d1ecf1; border: 1px solid #bee5eb;">
                                <div class="card-header p-2" style="background: #bee5eb; border-bottom: 1px solid #bee5eb;">
                                    <h6 class="mb-0" style="font-size: 0.75rem; font-weight: 600; color: #0c5460;">
                                        <i class="ki-duotone ki-information fs-5 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Hanya di DB (${dbOnly.count})
                                    </h6>
                                </div>
                                <div class="card-body p-2">
                                    <div class="d-flex flex-wrap gap-1">
                                        ${dbOnly.barcodes.map(barcode => `
                                            <span class="badge badge-info badge-sm" style="font-size: 0.7rem; padding: 4px 8px;">
                                                ${barcode.code}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderLotItems(items, type, source) {
    if (items.length === 0) {
        return '';
    }
    
    let html = '';
    items.forEach((item, index) => {
        const isMatched = type === 'matched';
        
        // Different status with clear label
        let statusIcon, statusText, statusColor;
        if (isMatched) {
            statusIcon = '✓';
            statusText = 'Sama';
            statusColor = '#d4edda';
        } else {
            if (source === 'odoo') {
                statusIcon = '⚠';
                statusText = 'Hanya Odoo';
                statusColor = '#fff3cd';
            } else {
                statusIcon = '!';
                statusText = 'Hanya Manual';
                statusColor = '#d1ecf1';
            }
        }
        
        html += `
            <div class="lot-item ${type} py-2 px-2" style="background-color: ${statusColor}; border-left: 3px solid ${isMatched ? '#28a745' : (source === 'odoo' ? '#ffc107' : '#17a2b8')};">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center">
                            <span style="width: 20px; height: 20px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; margin-right: 8px;">${statusIcon}</span>
                            <div>
                                <small class="fw-bold text-dark d-block">${item.code}</small>
                                <small class="text-muted" style="font-size: 0.65rem;">${statusText}</small>
                                ${source === 'odoo' && item.product !== '-' ? 
                                    '<br><small class="text-muted" style="font-size: 0.65rem;">' + item.product.substring(0, 35) + (item.product.length > 35 ? '...' : '') + '</small>' : ''}
                            </div>
                        </div>
                    </div>
                    ${source === 'odoo' && item.qty !== '-' ? 
                        '<span class="badge" style="background: white; color: #333; font-size: 0.7rem;">Qty: ' + item.qty + '</span>' : ''}
                </div>
            </div>
        `;
    });
    
    return html;
}

// Add slide in animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(style);

// Show notification function
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 5px 20px rgba(0,0,0,0.2);';
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="ki-duotone ki-information-5 fs-2 me-3">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div>${message}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Function untuk insert barcode ke Odoo (dipanggil via onclick)
window.insertBarcodeToOdoo = function(btn) {
    const shippingId = btn.dataset.shippingId;

    if (!shippingId) {
        alert('✗ Error: Shipping ID tidak ditemukan');
        return;
    }

    if (!confirm('Apakah Anda yakin ingin insert barcode ke Odoo?\n\nProses ini akan:\n1. Reset quantity_done menjadi 0\n2. Hapus move lines yang ada\n3. Insert barcode dari production_lots_strg')) {
        return;
    }

    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';

    fetch('shipping/process_manual_stuffing.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'shipping_id=' + encodeURIComponent(shippingId)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Error response:', text);
                throw new Error('HTTP error! status: ' + response.status + ', body: ' + text);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        btn.disabled = false;
        btn.innerHTML = originalHTML;

        if (data && data.success) {
            // Gunakan SweetAlert jika tersedia, jika tidak gunakan alert biasa
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: data.message || 'Berhasil memproses barcode ke Odoo',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#50CD89'
                }).then(() => {
                    // Reload detail setelah user klik OK
                    const detailButton = document.querySelector('.btn-detail-manual-stuffing[data-id="' + shippingId + '"]');
                    if (detailButton) {
                        detailButton.click();
                    }
                });
            } else {
                alert('✓ ' + (data.message || 'Berhasil memproses barcode'));
                // Reload detail
                const detailButton = document.querySelector('.btn-detail-manual-stuffing[data-id="' + shippingId + '"]');
                if (detailButton) {
                    detailButton.click();
                }
            }
        } else {
            // Gunakan SweetAlert untuk error juga jika tersedia
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Terjadi kesalahan saat memproses',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#F1416C'
                });
            } else {
                alert('✗ Error: ' + (data.message || 'Terjadi kesalahan saat memproses'));
            }
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        // Gunakan SweetAlert untuk error jika tersedia
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Terjadi kesalahan saat memproses',
                confirmButtonText: 'OK',
                confirmButtonColor: '#F1416C'
            });
        } else {
            alert('✗ Error: ' + (error.message || 'Terjadi kesalahan saat memproses'));
        }
    });
};
</script>

<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>

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
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead class="">
                        <tr>
                            <th>No</th>
                            <th>Name</th>
                            <th>Scheduled Date</th>
                            <th>Description</th>
                            <th>Ship To</th>
                            <th class="text-end">Aksi</th>
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
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-icon btn-light-primary btn-print-shipping" 
                                                data-id="<?= $row['id'] ?>" 
                                                data-name="<?= htmlspecialchars($row['name']) ?>"
                                                title="Print">
                                            <i class="ki-duotone ki-printer fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
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

    // Handle print shipping
    const printButtons = document.querySelectorAll(".btn-print-shipping");
    printButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            const shippingId = this.dataset.id;
            // Buka window baru untuk print
            window.open('shipping/print_shipping.php?id=' + encodeURIComponent(shippingId), '_blank');
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
                    displayComparisonResult(data.data);
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

function displayComparisonResult(data) {
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
        data.forEach((picking, index) => {
            const totalMatched = picking.matched.length;
            const totalOdooOnly = picking.odoo_only.length;
            const totalManualOnly = picking.manual_only.length;
            const matchPercentage = picking.total_odoo > 0 ? 
                Math.round((totalMatched / picking.total_odoo) * 100) : 0;
            
            // Determine color based on match percentage
            let matchColor = 'danger';
            if (matchPercentage >= 80) matchColor = 'success';
            else if (matchPercentage >= 50) matchColor = 'warning';
            
            html += `
                <div class="comparison-card mb-3" data-picking-id="${picking.picking_id}">
                    <div class="comparison-header py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-white fw-bold">${picking.picking_name}</small>
                                ${picking.po ? '<br><small class="text-white-50" style="font-size: 0.7rem;">PO: ' + picking.po + '</small>' : ''}
                            </div>
                            <div class="text-end">
                                <span class="badge badge-${matchColor} badge-sm">${matchPercentage}% Cocok</span>
                                <br><small class="text-white-50" style="font-size: 0.7rem;">${totalMatched}/${picking.total_odoo} item</small>
                            </div>
                        </div>
                    </div>
                    <div class="comparison-body p-2">
                        <!-- Statistics -->
                        <div class="stats-row mb-2">
                            <div class="stat-card matched">
                                <div class="stat-number">${totalMatched}</div>
                                <div class="stat-label">Sudah Sama</div>
                                <small style="opacity: 0.8;">Odoo = Manual</small>
                            </div>
                            <div class="stat-card odoo-only">
                                <div class="stat-number">${totalOdooOnly}</div>
                                <div class="stat-label">Hanya di Odoo</div>
                                <small style="opacity: 0.8;">Belum input manual</small>
                            </div>
                            <div class="stat-card manual-only">
                                <div class="stat-number">${totalManualOnly}</div>
                                <div class="stat-label">Hanya Manual</div>
                                <small style="opacity: 0.8;">Belum ada di Odoo</small>
                            </div>
                        </div>
                        
                        <!-- Comparison Columns -->
                        <div class="comparison-columns">
                            <!-- Odoo Column -->
                            <div class="comparison-column">
                                <div class="column-header odoo py-2 px-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="fw-bold">💻 Data Odoo (Sistem)</small>
                                        <span class="badge badge-light badge-sm">${picking.total_odoo}</span>
                                    </div>
                                </div>
                                <div class="column-content" style="max-height: 300px;">
                                    ${renderLotItems(picking.matched, 'matched', 'odoo')}
                                    ${renderLotItems(picking.odoo_only, 'unmatched', 'odoo')}
                                    ${picking.total_odoo === 0 ? '<div class="text-center text-muted py-3"><small>Tidak ada data</small></div>' : ''}
                                </div>
                            </div>
                            
                            <!-- Manual Column -->
                            <div class="comparison-column">
                                <div class="column-header manual py-2 px-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="fw-bold">✍️ Input Manual (Fisik)</small>
                                        <span class="badge badge-light badge-sm">${picking.total_manual}</span>
                                    </div>
                                </div>
                                <div class="column-content" style="max-height: 300px;">
                                    ${renderLotItems(picking.matched, 'matched', 'manual')}
                                    ${renderLotItems(picking.manual_only, 'unmatched', 'manual')}
                                    ${picking.total_manual === 0 ? '<div class="text-center text-muted py-3"><small>Tidak ada data</small></div>' : ''}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Button for matched items -->
                        ${totalMatched > 0 ? `
                        <div class="mt-2 pt-2" style="border-top: 1px solid #dee2e6;">
                            <button class="btn btn-sm btn-success w-100 btn-process-barcode" 
                                    data-picking-id="${picking.picking_id}"
                                    data-picking-name="${picking.picking_name}"
                                    data-matched-count="${totalMatched}">
                                <i class="ki-duotone ki-barcode fs-4 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Proses Barcode (${totalMatched} item)
                            </button>
                            <small class="text-muted d-block mt-1 text-center" style="font-size: 0.7rem;">
                                Update tanggal, reset lot & insert yang cocok
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
        }, 100);
    }
    
    container.innerHTML = html;
}

// Initialize process barcode buttons
function initProcessBarcodeButtons() {
    const buttons = document.querySelectorAll('.btn-process-barcode');
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            const pickingId = this.getAttribute('data-picking-id');
            const pickingName = this.getAttribute('data-picking-name');
            const matchedCount = this.getAttribute('data-matched-count');
            
            // Get shipping scheduled date for display
            const shippingInfo = document.querySelector('.info-value');
            
            // Confirm before processing
            if (!confirm(`Proses Barcode untuk ${pickingName}?\n\nProses ini akan:\n1. Update tanggal kirim di picking\n2. Reset semua lot/serial yang ada\n3. Insert ${matchedCount} lot/serial yang cocok\n\nLanjutkan?`)) {
                return;
            }
            
            const originalHtml = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';
            this.disabled = true;
            
            // Get shipping ID from compare button
            const shippingId = document.getElementById('btnCompareLotSync').getAttribute('data-shipping-id');
            
            // Call process API
            fetch('shipping/process_barcode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `shipping_id=${shippingId}&picking_id=${pickingId}`
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
</script>

<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>

<div class="row g-5 g-xxl-10">
    <!--begin::Col-->
    <div class="col-xl-12 mb-5 mb-xxl-10">

        <!--begin::Table Widget 3-->
        <div class="card card-flush h-xl-100">
            <!--begin::Card header-->
            <div class="card-header pt-7">
                <!--begin::Title-->
                <div class="card-title">
                    <h2 class="fw-bold text-gray-800">Employee Management</h2>
                </div>
                <!--end::Title-->

                <!--begin::Toolbar-->
                <div class="card-toolbar">
                    <div class="d-flex align-items-center gap-3">
                        <!--begin::Search-->
                        <div class="position-relative w-250px">
                            <span class="svg-icon svg-icon-2 svg-icon-gray-500 position-absolute top-50 translate-middle-y ms-4">
                                <i class="ki-duotone ki-magnifier fs-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <input type="text" id="tableSearch" class="form-control form-control-solid ps-12" placeholder="Search employees..." />
                        </div>
                        <!--end::Search-->

                        <!--begin::Sync button-->
                        <button class="btn btn-primary btn-sync-employees" id="syncEmployeesBtn">
                            <i class="ki-duotone ki-update fs-3 me-2"></i>
                            Sync from Odoo
                        </button>
                        <!--end::Sync button-->
                    </div>
                </div>
                <!--end::Toolbar-->
            </div>
            <!--end::Card header-->

            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Loader-->
                <div id="skeletonLoader" class="d-none">
                    <div class="card">
                        <div class="card-body">
                            <?php for($i = 0; $i < 5; $i++): ?>
                            <div class="d-flex align-items-center mb-7">
                                <div class="symbol symbol-50px me-5">
                                    <span class="symbol-label bg-light-secondary"></span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="placeholder-wave">
                                        <span class="placeholder col-8 bg-secondary"></span>
                                        <span class="placeholder col-5 bg-secondary mt-2"></span>
                                        <span class="placeholder col-3 bg-secondary mt-2"></span>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <!--end::Loader-->

                <!--begin::Table container-->
                <div class="table-responsive">
                    <table id="employeesTable" class="table table-hover table-row-bordered table-row-gray-300 align-middle gs-0 gy-4">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800 border-bottom border-gray-200">
                                <th class="min-w-50px">No</th>
                                <th class="min-w-200px">Employee</th>
                                <th class="min-w-150px">Contact</th>
                                <th class="min-w-120px">Barcode</th>
                                <th class="min-w-100px">Company</th>
                                <th class="min-w-100px">Categories</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            <?php
                            // Query untuk mengambil data employee
                            $sql = "SELECT id_employee, name, email, phone, barcode, image_1920, company_id, category_ids FROM employee ORDER BY name";
                            $result = mysqli_query($conn, $sql);

                            if ($result && mysqli_num_rows($result) > 0):
                                $no = 1;
                            ?>
                                <?php while($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td>
                                            <span class="text-gray-600 fw-bold"><?= $no++ ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-45px me-5">
                                                    <?php if(!empty($row['image_1920'])): ?>
                                                        <img src="../assets/img/employees/<?= $row['image_1920'] ?>" 
                                                             alt="<?= htmlspecialchars($row['name']) ?>" 
                                                             class="rounded-circle"
                                                             style="width: 45px; height: 45px; object-fit: cover;"
                                                             onerror="this.src='../assets/img/avatar.jpg'">
                                                    <?php else: ?>
                                                        <div class="symbol-label bg-light-primary text-primary fw-bold fs-6">
                                                            <?= substr($row['name'], 0, 1) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($row['name']) ?></span>
                                                    <span class="text-gray-600 fs-7">ID: <?= $row['id_employee'] ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <?php if(!empty($row['email'])): ?>
                                                    <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="text-gray-800 fw-bold fs-7 text-hover-primary">
                                                        <?= htmlspecialchars($row['email']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted fs-8">No email</span>
                                                <?php endif; ?>
                                                <?php if(!empty($row['phone'])): ?>
                                                    <a href="tel:<?= htmlspecialchars($row['phone']) ?>" class="text-gray-600 fs-7 text-hover-primary">
                                                        <?= htmlspecialchars($row['phone']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted fs-8">No phone</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if(!empty($row['barcode'])): ?>
                                                <div class="badge badge-light-success fs-7 fw-bold"><?= htmlspecialchars($row['barcode']) ?></div>
                                            <?php else: ?>
                                                <span class="text-muted fs-8">No barcode</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($row['company_id'])): ?>
                                                <span class="badge badge-light-primary fs-7"><?= htmlspecialchars($row['company_id']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted fs-8">No company</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($row['category_ids'])): ?>
                                                <span class="badge badge-light-info fs-7"><?= htmlspecialchars($row['category_ids']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted fs-8">No categories</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-10">
                                        <div class="text-center">
                                            <i class="ki-duotone ki-information-5 fs-2tx text-gray-400 mb-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            <div class="fw-bold fs-4 text-gray-600">No employees found</div>
                                            <div class="fs-6 text-gray-500">Sync employees from Odoo to get started</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; 

                            // Tutup koneksi
                            if ($result) {
                                mysqli_free_result($result);
                            }
                            mysqli_close($conn);
                            ?>
                        </tbody>
                    </table>
                </div>
                <!--end::Table container-->

                <!--begin::Pagination-->
                <div class="d-flex justify-content-between align-items-center flex-wrap mt-5" id="paginationInfoContainer" style="display: none !important;">
                    <div class="text-gray-600 fw-semibold fs-6">
                        Showing <span id="showingFrom">1</span> to <span id="showingTo">10</span> of <span id="totalRecords"><?= $total ?></span> entries
                    </div>
                    <div id="paginationContainer"></div>
                </div>
                <!--end::Pagination-->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<!--begin::Toast container-->
<div id="kt_docs_toast_stack" class="toast-container position-fixed top-0 end-0 p-3"></div>
<!--end::Toast container-->

<script>
// Script untuk menangani sync employees and show table
document.addEventListener('DOMContentLoaded', function() {
    // Setup search and pagination for employees table
    if (typeof setupSearch === 'function') {
        setupSearch('employeesTable', 10);
    }
    if (typeof setupPagination === 'function') {
        setupPagination('employeesTable', 10);
    }

    // Tombol sync employees
    document.getElementById('syncEmployeesBtn').addEventListener('click', function() {
        // Tampilkan loading state
        const submitBtn = document.getElementById('syncEmployeesBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Syncing...';
        submitBtn.disabled = true;

        // Show loader during sync
        document.getElementById('skeletonLoader').classList.remove('d-none');
        document.getElementById('employeesTable').closest('.table-responsive').style.display = 'none';

        // Kirim permintaan AJAX
        fetch('employee/add_employee.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: ''
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Success!', data.message || 'Employees synced successfully!');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showToast('error', 'Error!', data.message || 'Failed to sync employees');
                // Hide loader and show table on error
                document.getElementById('skeletonLoader').classList.add('d-none');
                document.getElementById('employeesTable').closest('.table-responsive').style.display = 'block';
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            showToast('error', 'Error!', 'Network error occurred while syncing employees');
            // Hide loader and show table on error
            document.getElementById('skeletonLoader').classList.add('d-none');
            document.getElementById('employeesTable').closest('.table-responsive').style.display = 'block';
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    // Toast function
    function showToast(type, title, message) {
        const toastContainer = document.getElementById('kt_docs_toast_stack');
        const toastId = 'toast_' + Date.now();
        
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        // Remove toast from DOM after it hides
        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }
});
</script>

<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>
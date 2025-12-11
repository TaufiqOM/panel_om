<div class="row g-5 g-xxl-10">
    <div class="col-xl-12 mb-5 mb-xxl-10">
        <div class="card card-flush h-xl-100">
            <div class="card-header py-7">
                <div class="card-title pt-3 mb-0 gap-4 gap-lg-10 gap-xl-15 nav nav-tabs border-bottom-0">
                    <div class="fs-4 fw-bold pb-3 border-bottom border-3 border-primary cursor-pointer"
                        data-kt-table-widget-3="tab" data-kt-table-widget-3-value="Show All">
                        All Plywood
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="card-toolbar d-flex align-items-center gap-3">
                        <div class="position-relative">
                            <span class="svg-icon svg-icon-2 svg-icon-gray-500 position-absolute top-50 translate-middle-y ms-3">
                                <i class="ki-duotone ki-magnifier fs-2 fs-lg-3 text-gray-800 position-absolute top-50 translate-middle-y me-5"></i>
                            </span>
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search plywood..." />
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body pt-1">
                <button class="btn btn-sm btn-primary btn-sync-plywood mb-5" id="syncPlywoodBtn">
                    <i class="ki-duotone ki-plus fs-4"></i> Sync Plywood from Odoo
                </button>

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
                            <tr>
                                <td><span class="placeholder col-8"></span></td>
                                <td><span class="placeholder col-6"></span></td>
                                <td><span class="placeholder col-5"></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <table id="plywoodTable" class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Plywood Name</th>
                            <th>Plywood Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT id, wood_engineered_code, wood_engineered_name FROM wood_engineered ORDER BY id";
                        $result = mysqli_query($conn, $sql);

                        if ($result && mysqli_num_rows($result) > 0):
                            $no = 1;
                        ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="min-w-50px"><?= $no++ ?></td>
                                    <td class="min-w-150px"><span class="badge badge-light-primary fs-7"><?= htmlspecialchars($row['wood_engineered_name']) ?></span></td>
                                    <td class="min-w-200px"><span class="fw-bold text-primary"><?= htmlspecialchars($row['wood_engineered_code']) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">Tidak ada data plywood ditemukan</td>
                            </tr>
                        <?php endif;

                        if ($result) {
                            mysqli_free_result($result);
                        }
                        mysqli_close($conn);
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('syncPlywoodBtn').addEventListener('click', function() {
        const btn = document.getElementById('syncPlywoodBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
        btn.disabled = true;

        fetch('plywood/add_plywood.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: ''
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Plywood berhasil disinkronkan!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });
});
</script>
<script src="../components/pagination.js"></script>
<script src="../components/search.js"></script>

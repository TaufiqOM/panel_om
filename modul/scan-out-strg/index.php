<?php
require __DIR__ . '/../../inc/config_odoo.php';
require __DIR__ . '/../../inc/config.php';

$username = $_SESSION['username'] ?? '';

// Handle scan_out action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan_out') {
    $production_code = trim($_POST['production_code'] ?? '');

    if (empty($production_code)) {
        echo json_encode(['success' => false, 'message' => 'Production code is required']);
        exit;
    }

    try {
        // Get data from production_lots and related tables
        $sql = "
            SELECT
                pl.production_code,
                pl.station_code,
                bl.customer_name,
                bl.sale_order_name as so_name,
                bl.sale_order_id,
                bl.sale_order_ref,
                bl.product_id as product_code
            FROM production_lots pl
            INNER JOIN barcode_item bi ON bi.barcode = pl.production_code
            INNER JOIN barcode_lot bl ON bl.id = bi.lot_id
            WHERE pl.production_code = ?
            AND pl.station_code = 'STR'  -- Assuming STR is the scan_out station
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $production_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Production code not found or not ready for scan_out']);
            exit;
        }

        $data = $result->fetch_assoc();
        $stmt->close();

        // Check if already exists in production_lots_strg
        $check_sql = "SELECT id FROM production_lots_strg WHERE production_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $production_code);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();

        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Production code already scanned out']);
            exit;
        }

        // Insert into production_lots_strg
        $insert_sql = "
            INSERT INTO production_lots_strg
            (customer_name, so_name, sale_order_id, sale_order_ref, product_code, production_code)
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            'ssisss',
            $data['customer_name'],
            $data['so_name'],
            $data['sale_order_id'],
            $data['sale_order_ref'],
            $data['product_code'],
            $data['production_code']
        );

        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Scan out successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to insert data']);
        }

        $insert_stmt->close();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

    exit;
}
?>

<!--begin::Main-->
<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
    <!--begin::Content wrapper-->
    <div class="d-flex flex-column flex-column-fluid">
        <!--begin::Content-->
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <!--begin::Content container-->
            <div id="kt_app_content_container" class="app-container container-fluid">
                <div class="row g-5 g-xxl-10">
                    <div class="col-xl-12">
                        <div class="card card-flush">
                            <div class="card-header py-7">
                                <div class="card-title pt-3 mb-0 gap-4 gap-lg-10 gap-xl-15">
                                    <div class="fs-4 fw-bold pb-3">
                                        Scan Out to Storage
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Production Code</label>
                                        <input type="text" id="productionCode" class="form-control" placeholder="Enter production code">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" id="scanOutBtn" class="btn btn-primary d-block">
                                            <i class="ki-duotone ki-plus fs-4 me-2"></i>Scan Out
                                        </button>
                                    </div>
                                </div>
                                <div id="resultMessage" class="mt-3" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Content container-->
        </div>
        <!--end::Content-->
    </div>
    <!--end::Content wrapper-->
</div>
<!--end::Main-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scanOutBtn = document.getElementById('scanOutBtn');
    const productionCodeInput = document.getElementById('productionCode');
    const resultMessage = document.getElementById('resultMessage');

    scanOutBtn.addEventListener('click', function() {
        const productionCode = productionCodeInput.value.trim();

        if (!productionCode) {
            showMessage('Production code is required', 'danger');
            return;
        }

        // Show loading state
        scanOutBtn.disabled = true;
        scanOutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

        // Send AJAX request
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=scan_out&production_code=' + encodeURIComponent(productionCode)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                productionCodeInput.value = '';
            } else {
                showMessage(data.message, 'danger');
            }
        })
        .catch(error => {
            showMessage('An error occurred: ' + error.message, 'danger');
        })
        .finally(() => {
            // Restore button state
            scanOutBtn.disabled = false;
            scanOutBtn.innerHTML = '<i class="ki-duotone ki-plus fs-4 me-2"></i>Scan Out';
        });
    });

    function showMessage(message, type) {
        resultMessage.className = `alert alert-${type}`;
        resultMessage.textContent = message;
        resultMessage.style.display = 'block';

        // Auto hide after 5 seconds
        setTimeout(() => {
            resultMessage.style.display = 'none';
        }, 5000);
    }

    // Allow Enter key to trigger scan out
    productionCodeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            scanOutBtn.click();
        }
    });
});
</script>

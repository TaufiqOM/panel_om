<?php
require __DIR__ . '/../../inc/config_odoo.php';
require __DIR__ . '/../../inc/config.php';

$username = $_SESSION['username'] ?? '';

// Get filter parameters from POST (after Swal popup) or GET (for direct access)
$selected_order = $_POST['order'] ?? $_GET['order'] ?? '';
$selected_customer = $_POST['customer'] ?? $_GET['customer'] ?? '';
$show_all_data = $_POST['show_all_data'] ?? $_GET['show_all_data'] ?? '0';

// Initialize data arrays
$production_summary = [
    'TO' => 0,    // Total Order from barcode_lot
    'OPN' => 0,   // Order yang masih di proses
    'AV' => 0,    // Available (RCV)
    'RAW' => 0,   // Assembly (ASM)
    'RPR' => 0,   // Sanding (OM4)
    'SND' => 0,   // Sanding (OM4)
    'RTR' => 0,   // Retro (CS2)
    'QCIN' => 0,  // QC IN (QCN)
    'CLR' => 0,   // Coloring (CS3)
    'QCL' => 0,   // QC Coloring (QCL)
    'FinP' => 0,  // Packing (PA1/PA2)
    'QFin' => 0,  // QC Finpack (QCF)
    'STR' => 0    // Storage (STR)
];

$production_data = [];
$available_orders = [];
$available_customers = [];

// Get available orders and customers for filter dropdowns
try {
    // Get unique orders from barcode_lot table
    $orders_query = "SELECT DISTINCT sale_order_name as so_name FROM barcode_lot WHERE sale_order_name IS NOT NULL AND sale_order_name != '' ORDER BY sale_order_name";
    $orders_result = $conn->query($orders_query);
    if ($orders_result) {
        while ($row = $orders_result->fetch_assoc()) {
            $available_orders[] = $row['so_name'];
        }
    }

    // Get unique customers from barcode_lot table
    $customers_query = "SELECT DISTINCT customer_name FROM barcode_lot WHERE customer_name IS NOT NULL AND customer_name != '' ORDER BY customer_name";
    $customers_result = $conn->query($customers_query);
    if ($customers_result) {
        while ($row = $customers_result->fetch_assoc()) {
            $available_customers[] = $row['customer_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching filter data: " . $e->getMessage());
}

// Only load data if filters are selected or show_all_data is true
$has_filters = (!empty($selected_order) || !empty($selected_customer)) || $show_all_data === '1';

if ($has_filters) {
    try {
        // ============================================
        // LOAD BARCODE ITEM BERDASARKAN FILTER
        // ============================================
        $where = [];

        if (!empty($selected_customer)) {
            $where[] = "bl.customer_name = '" . $conn->real_escape_string($selected_customer) . "'";
        }

        if (!empty($selected_order)) {
            $where[] = "bl.sale_order_name = '" . $conn->real_escape_string($selected_order) . "'";
        }

        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT 
                bl.customer_name,
                bl.sale_order_name,
                bl.product_name,
                bl.product_id,
                bl.qty_order,
                bi.barcode
            FROM barcode_item bi
            JOIN barcode_lot bl ON bl.id = bi.lot_id
            $where_sql
            ORDER BY bl.customer_name, bl.sale_order_name, bl.product_name
        ";

        $q = $conn->query($sql);

        if (!$q) {
            error_log("SQL ERROR: " . $conn->error);
            throw new Exception("Database query failed: " . $conn->error);
        }

        if ($q->num_rows == 0) {
            $production_data = [];
        } else {
            // ============================================
            // FUNCTION: AMBIL POSISI BARCODE
            // ============================================
            function get_barcode_position($conn, $barcode) {
                // Cek posisi aktif
                $sql1 = "
                    SELECT station_code 
                    FROM production_lots
                    WHERE production_code = '$barcode'
                    LIMIT 1
                ";
                
                $r1 = $conn->query($sql1);
                if ($r1 && $r1->num_rows > 0) {
                    return $r1->fetch_assoc()['station_code'];
                }

                // Cek history terbaru
                $sql2 = "
                    SELECT station_code 
                    FROM production_lots_history
                    WHERE production_code = '$barcode'
                    ORDER BY id DESC
                    LIMIT 1
                ";

                $r2 = $conn->query($sql2);
                if ($r2 && $r2->num_rows > 0) {
                    return $r2->fetch_assoc()['station_code'];
                }

                return "UNKNOWN";
            }

            // ============================================
            // BUILD DATA BERDASARKAN FILTER MODE
            // ============================================
            $raw_data = [];

            while ($row = $q->fetch_assoc()) {
                $customer = $row['customer_name'];
                $order = $row['sale_order_name'];
                $product = $row['product_name'];
                $product_id = $row['product_id'];
                $qty_order = $row['qty_order'];
                $barcode = $row['barcode'];

                $station = get_barcode_position($conn, $barcode);

                // Map station code to production stage
                $stage_key = 'UNKNOWN';
                switch ($station) {
                    case 'RCV': $stage_key = 'AV'; break;
                    case 'ASM': case 'OM1': case 'OM2': case 'OM3': $stage_key = 'RAW'; break;
                    case 'RPR': $stage_key = 'RPR'; break;
                    case 'SA4': $stage_key = 'SND'; break;
                    case 'CS2': $stage_key = 'RTR'; break;
                    case 'QCN': $stage_key = 'QCIN'; break;
                    case 'CS3': $stage_key = 'CLR'; break;
                    case 'QCL': $stage_key = 'QCL'; break;
                    case 'PA1': case 'PA2': $stage_key = 'FinP'; break;
                    case 'QCF': $stage_key = 'QFin'; break;
                    case 'STR': $stage_key = 'STR'; break;
                    default: $stage_key = 'UNKNOWN';
                }

                // ALL FILTER → Customer → Order → Product → Station → Qty
                if (empty($selected_customer) && empty($selected_order)) {
                    if (!isset($raw_data[$customer])) $raw_data[$customer] = [];
                    if (!isset($raw_data[$customer][$order])) $raw_data[$customer][$order] = [];
                    if (!isset($raw_data[$customer][$order][$product])) {
                        $raw_data[$customer][$order][$product] = [
                            'product_id' => $product_id,
                            'TO' => 0,
                            'AV' => 0, 'RAW' => 0, 'RPR' => 0, 'SND' => 0, 'RTR' => 0, 'QCIN' => 0,
                            'CLR' => 0, 'QCL' => 0, 'FinP' => 0, 'QFin' => 0, 'STR' => 0
                        ];
                    }
                    $raw_data[$customer][$order][$product]['TO'] += $qty_order;
                    if ($stage_key != 'UNKNOWN') {
                        $raw_data[$customer][$order][$product][$stage_key]++;
                    }
                }
                // ONLY CUSTOMER → Order → Product → Station → Qty
                elseif (!empty($selected_customer) && empty($selected_order)) {
                    if (!isset($raw_data[$order])) $raw_data[$order] = [];
                    if (!isset($raw_data[$order][$product])) {
                        $raw_data[$order][$product] = [
                            'product_id' => $product_id,
                            'TO' => 0,
                            'AV' => 0, 'RAW' => 0, 'RPR' => 0, 'SND' => 0, 'RTR' => 0, 'QCIN' => 0,
                            'CLR' => 0, 'QCL' => 0, 'FinP' => 0, 'QFin' => 0, 'STR' => 0
                        ];
                    }
                    $raw_data[$order][$product]['TO'] += $qty_order;
                    if ($stage_key != 'UNKNOWN') {
                        $raw_data[$order][$product][$stage_key]++;
                    }
                }
                // ONLY ORDER → Product → Station → Qty
                else {
                    if (!isset($raw_data[$product])) {
                        $raw_data[$product] = [
                            'product_id' => $product_id,
                            'TO' => 0,
                            'AV' => 0, 'RAW' => 0, 'RPR' => 0, 'SND' => 0, 'RTR' => 0, 'QCIN' => 0,
                            'CLR' => 0, 'QCL' => 0, 'FinP' => 0, 'QFin' => 0, 'STR' => 0
                        ];
                    }
                    $raw_data[$product]['TO'] += $qty_order;
                    if ($stage_key != 'UNKNOWN') {
                        $raw_data[$product][$stage_key]++;
                    }
                }
            }

            // ============================================
            // CALCULATE OPN (Order in Process) AND PREPARE FINAL DATA
            // ============================================
            
            // Get delivered items count per product
            $delivered_data = [];
            $delivered_query = "
                SELECT pld.so_name, pld.product_code, COUNT(*) as delivered_count
                FROM production_lots_delivery pld
                $where_sql
                GROUP BY pld.so_name, pld.product_code
            ";
            
            $delivered_result = $conn->query($delivered_query);
            if ($delivered_result) {
                while ($row = $delivered_result->fetch_assoc()) {
                    $delivered_data[$row['so_name']][$row['product_code']] = $row['delivered_count'];
                }
            }

            // Transform raw_data to production_data format
            $production_data = [];
            
            // ALL FILTER MODE
            if (empty($selected_customer) && empty($selected_order)) {
                foreach ($raw_data as $customer => $orders) {
                    foreach ($orders as $order => $products) {
                        foreach ($products as $product => $product_data) {
                            $delivered = $delivered_data[$order][$product_data['product_id']] ?? 0;
                            $opn = $product_data['TO'] - $delivered;
                            
                            if (!isset($production_data[$customer])) $production_data[$customer] = [];
                            if (!isset($production_data[$customer][$order])) $production_data[$customer][$order] = [];
                            
                            $production_data[$customer][$order][$product] = [
                                'product_id' => $product_data['product_id'],
                                'TO' => $product_data['TO'],
                                'OPN' => $opn,
                                'AV' => $product_data['AV'],
                                'RAW' => $product_data['RAW'],
                                'RPR' => $product_data['RPR'],
                                'SND' => $product_data['SND'],
                                'RTR' => $product_data['RTR'],
                                'QCIN' => $product_data['QCIN'],
                                'CLR' => $product_data['CLR'],
                                'QCL' => $product_data['QCL'],
                                'FinP' => $product_data['FinP'],
                                'QFin' => $product_data['QFin'],
                                'STR' => $product_data['STR']
                            ];
                            
                            // Update summary
                            $production_summary['TO'] += $product_data['TO'];
                            $production_summary['OPN'] += $opn;
                            $production_summary['AV'] += $product_data['AV'];
                            $production_summary['RAW'] += $product_data['RAW'];
                            $production_summary['RPR'] += $product_data['RPR'];
                            $production_summary['SND'] += $product_data['SND'];
                            $production_summary['RTR'] += $product_data['RTR'];
                            $production_summary['QCIN'] += $product_data['QCIN'];
                            $production_summary['CLR'] += $product_data['CLR'];
                            $production_summary['QCL'] += $product_data['QCL'];
                            $production_summary['FinP'] += $product_data['FinP'];
                            $production_summary['QFin'] += $product_data['QFin'];
                            $production_summary['STR'] += $product_data['STR'];
                        }
                    }
                }
            }
            // ONLY CUSTOMER MODE
            elseif (!empty($selected_customer) && empty($selected_order)) {
                foreach ($raw_data as $order => $products) {
                    foreach ($products as $product => $product_data) {
                        $delivered = $delivered_data[$order][$product_data['product_id']] ?? 0;
                        $opn = $product_data['TO'] - $delivered;
                        
                        if (!isset($production_data[$order])) $production_data[$order] = [];
                        
                        $production_data[$order][$product] = [
                            'product_id' => $product_data['product_id'],
                            'TO' => $product_data['TO'],
                            'OPN' => $opn,
                            'AV' => $product_data['AV'],
                            'RAW' => $product_data['RAW'],
                            'RPR' => $product_data['RPR'],
                            'SND' => $product_data['SND'],
                            'RTR' => $product_data['RTR'],
                            'QCIN' => $product_data['QCIN'],
                            'CLR' => $product_data['CLR'],
                            'QCL' => $product_data['QCL'],
                            'FinP' => $product_data['FinP'],
                            'QFin' => $product_data['QFin'],
                            'STR' => $product_data['STR']
                        ];
                        
                        // Update summary
                        $production_summary['TO'] += $product_data['TO'];
                        $production_summary['OPN'] += $opn;
                        $production_summary['AV'] += $product_data['AV'];
                        $production_summary['RAW'] += $product_data['RAW'];
                        $production_summary['RPR'] += $product_data['RPR'];
                        $production_summary['SND'] += $product_data['SND'];
                        $production_summary['RTR'] += $product_data['RTR'];
                        $production_summary['QCIN'] += $product_data['QCIN'];
                        $production_summary['CLR'] += $product_data['CLR'];
                        $production_summary['QCL'] += $product_data['QCL'];
                        $production_summary['FinP'] += $product_data['FinP'];
                        $production_summary['QFin'] += $product_data['QFin'];
                        $production_summary['STR'] += $product_data['STR'];
                    }
                }
            }
            // ONLY ORDER MODE
            else {
                foreach ($raw_data as $product => $product_data) {
                    $delivered = $delivered_data[$selected_order][$product_data['product_id']] ?? 0;
                    $opn = $product_data['TO'] - $delivered;
                    
                    $production_data[$product] = [
                        'product_id' => $product_data['product_id'],
                        'TO' => $product_data['TO'],
                        'OPN' => $opn,
                        'AV' => $product_data['AV'],
                        'RAW' => $product_data['RAW'],
                        'RPR' => $product_data['RPR'],
                        'SND' => $product_data['SND'],
                        'RTR' => $product_data['RTR'],
                        'QCIN' => $product_data['QCIN'],
                        'CLR' => $product_data['CLR'],
                        'QCL' => $product_data['QCL'],
                        'FinP' => $product_data['FinP'],
                        'QFin' => $product_data['QFin'],
                        'STR' => $product_data['STR']
                    ];
                    
                    // Update summary
                    $production_summary['TO'] += $product_data['TO'];
                    $production_summary['OPN'] += $opn;
                    $production_summary['AV'] += $product_data['AV'];
                    $production_summary['RAW'] += $product_data['RAW'];
                    $production_summary['RPR'] += $product_data['RPR'];
                    $production_summary['SND'] += $product_data['SND'];
                    $production_summary['RTR'] += $product_data['RTR'];
                    $production_summary['QCIN'] += $product_data['QCIN'];
                    $production_summary['CLR'] += $product_data['CLR'];
                    $production_summary['QCL'] += $product_data['QCL'];
                    $production_summary['FinP'] += $product_data['FinP'];
                    $production_summary['QFin'] += $product_data['QFin'];
                    $production_summary['STR'] += $product_data['STR'];
                }
            }

        }

    } catch (Exception $e) {
        error_log("Error fetching production data: " . $e->getMessage());
    }
}

// Prepare data for Odoo posting
$odoo_data = [];
?>

<!--begin::Main-->
<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
    <!--begin::Content wrapper-->
    <div class="d-flex flex-column flex-column-fluid">
        <!--begin::Content-->
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <!--begin::Content container-->
            <div id="kt_app_content_container" class="app-container container-fluid">
                <?php if (!$has_filters): ?>
                <!--begin::Filter Selection Required-->
                <div class="row g-5 g-xxl-10">
                    <div class="col-xl-12">
                        <div class="card card-flush">
                            <div class="card-body text-center py-20">
                                <div class="mb-10">
                                    <i class="ki-duotone ki-information fs-4x text-primary mb-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <h3 class="text-gray-800 fw-bold mb-4">Select Filters to View Data</h3>
                                    <p class="text-gray-600 fs-6 mb-8">Please select your preferred filters to load and display the production data efficiently.</p>
                                    <button type="button" class="btn btn-primary btn-lg" onclick="showFilterModal()">
                                        <i class="ki-duotone ki-filter fs-4 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Open Filter Selection
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Filter Selection Required-->
                <?php else: ?>
                <!--begin::Summary Cards-->
                <!-- ... (Summary cards remain the same) ... -->
                
                <!--begin::Production by Order Table-->
                <div class="row g-5 g-xxl-10">
                    <div class="col-xl-12 mb-5 mb-xxl-10">
                        <div class="card card-flush h-xl-100">
                            <!--begin::Card header-->
                            <div class="card-header py-7">
                                <div class="card-title pt-3 mb-0 gap-4 gap-lg-10 gap-xl-15">
                                    <div class="fs-4 fw-bold pb-3">
                                        <?php
                                        if (empty($selected_customer) && empty($selected_order)) {
                                            echo "Production Tracking - All Customers";
                                        } elseif (!empty($selected_customer) && empty($selected_order)) {
                                            echo "Production Tracking - Customer: " . htmlspecialchars($selected_customer);
                                        } else {
                                            echo "Production Tracking - Order: " . htmlspecialchars($selected_order);
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="card-toolbar">
                                    <div class="card-toolbar d-flex align-items-center gap-3">
                                        <button type="button" class="btn btn-sm btn-light-primary" onclick="showFilterModal()">
                                            <i class="ki-duotone ki-filter fs-4 me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Change Filters
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="postToOdoo()">
                                            <i class="ki-duotone ki-sync fs-4 me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Sync with Odoo
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!--end::Card header-->

                            <!--begin::Card body-->
                            <div class="card-body pt-1">
                                <!--begin::Table-->
                                <table id="productionTable" class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3">
                                    <thead>
                                        <tr>
                                            <?php if (empty($selected_customer) && empty($selected_order)): ?>
                                                <th>Customer</th>
                                                <th>Sales Order</th>
                                            <?php elseif (!empty($selected_customer) && empty($selected_order)): ?>
                                                <th>Sales Order</th>
                                            <?php endif; ?>
                                            <th>Product</th>
                                            <th>TO</th>
                                            <th>OPN</th>
                                            <th>AV</th>
                                            <th>RAW</th>
                                            <th>RPR</th>
                                            <th>SND</th>
                                            <th>RTR</th>
                                            <th>QCIN</th>
                                            <th>CLR</th>
                                            <th>QCL</th>
                                            <th>FinP</th>
                                            <th>QFin</th>
                                            <th>STR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($production_data)): ?>
                                            <?php 
                                            // ALL FILTER MODE: Customer → Order → Product
                                            if (empty($selected_customer) && empty($selected_order)): 
                                                foreach ($production_data as $customer => $orders): 
                                                    $customer_first = true;
                                                    foreach ($orders as $order => $products): 
                                                        $order_first = true;
                                                        foreach ($products as $product => $product_data): ?>
                                                            <tr>
                                                                <?php if ($customer_first && $order_first): ?>
                                                                    <td rowspan="<?php echo count($products); ?>">
                                                                        <div class="fw-bold"><?php echo htmlspecialchars($customer); ?></div>
                                                                    </td>
                                                                <?php endif; ?>
                                                                <?php if ($order_first): ?>
                                                                    <td rowspan="<?php echo count($products); ?>">
                                                                        <div class="fw-bold"><?php echo htmlspecialchars($order); ?></div>
                                                                    </td>
                                                                <?php endif; ?>
                                                                <td>
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($product); ?></div>
                                                                </td>
                                                                <td><span class="badge badge-light-primary fs-7 fw-semibold"><?php echo number_format($product_data['TO']); ?></span></td>
                                                                <td><span class="badge badge-light-warning fs-7 fw-semibold"><?php echo number_format($product_data['OPN']); ?></span></td>
                                                                <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['AV']); ?></span></td>
                                                                <td><span class="badge badge-light-danger fs-7 fw-semibold"><?php echo number_format($product_data['RAW']); ?></span></td>
                                                                <td><span class="badge badge-light-danger fs-7 fw-semibold"><?php echo number_format($product_data['RPR']); ?></span></td>
                                                                <td><span class="badge badge-light-warning fs-7 fw-semibold"><?php echo number_format($product_data['SND']); ?></span></td>
                                                                <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['RTR']); ?></span></td>
                                                                <td><span class="badge badge-light-success fs-7 fw-semibold"><?php echo number_format($product_data['QCIN']); ?></span></td>
                                                                <td><span class="badge badge-light-primary fs-7 fw-semibold"><?php echo number_format($product_data['CLR']); ?></span></td>
                                                                <td><span class="badge badge-light-secondary fs-7 fw-semibold"><?php echo number_format($product_data['QCL']); ?></span></td>
                                                                <td><span class="badge badge-light-dark fs-7 fw-semibold"><?php echo number_format($product_data['FinP']); ?></span></td>
                                                                <td><span class="badge badge-light-success fs-7 fw-semibold"><?php echo number_format($product_data['QFin']); ?></span></td>
                                                                <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['STR']); ?></span></td>
                                                            </tr>
                                                            <?php 
                                                            $customer_first = false;
                                                            $order_first = false;
                                                        endforeach; 
                                                    endforeach; 
                                                endforeach; 
                                            
                                            // ONLY CUSTOMER MODE: Order → Product
                                            elseif (!empty($selected_customer) && empty($selected_order)): 
                                                foreach ($production_data as $order => $products): 
                                                    $order_first = true;
                                                    foreach ($products as $product => $product_data): ?>
                                                        <tr>
                                                            <?php if ($order_first): ?>
                                                                <td rowspan="<?php echo count($products); ?>">
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($order); ?></div>
                                                                </td>
                                                            <?php endif; ?>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($product); ?></div>
                                                            </td>
                                                            <td><span class="badge badge-light-primary fs-7 fw-semibold"><?php echo number_format($product_data['TO']); ?></span></td>
                                                            <td><span class="badge badge-light-warning fs-7 fw-semibold"><?php echo number_format($product_data['OPN']); ?></span></td>
                                                            <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['AV']); ?></span></td>
                                                            <td><span class="badge badge-light-danger fs-7 fw-semibold"><?php echo number_format($product_data['RAW']); ?></span></td>
                                                            <td><span class="badge badge-light-danger fs-7 fw-semibold"><?php echo number_format($product_data['RPR']); ?></span></td>
                                                            <td><span class="badge badge-light-warning fs-7 fw-semibold"><?php echo number_format($product_data['SND']); ?></span></td>
                                                            <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['RTR']); ?></span></td>
                                                            <td><span class="badge badge-light-success fs-7 fw-semibold"><?php echo number_format($product_data['QCIN']); ?></span></td>
                                                            <td><span class="badge badge-light-primary fs-7 fw-semibold"><?php echo number_format($product_data['CLR']); ?></span></td>
                                                            <td><span class="badge badge-light-secondary fs-7 fw-semibold"><?php echo number_format($product_data['QCL']); ?></span></td>
                                                            <td><span class="badge badge-light-dark fs-7 fw-semibold"><?php echo number_format($product_data['FinP']); ?></span></td>
                                                            <td><span class="badge badge-light-success fs-7 fw-semibold"><?php echo number_format($product_data['QFin']); ?></span></td>
                                                            <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['STR']); ?></span></td>
                                                        </tr>
                                                        <?php 
                                                        $order_first = false;
                                                    endforeach; 
                                                endforeach; 
                                            
                                            // ONLY ORDER MODE: Product
                                            else: 
                                                foreach ($production_data as $product => $product_data): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($product); ?></div>
                                                        </td>
                                                        <td><span class="badge badge-light-primary fs-7 fw-semibold"><?php echo number_format($product_data['TO']); ?></span></td>
                                                        <td><span class="badge badge-light-warning fs-7 fw-semibold"><?php echo number_format($product_data['OPN']); ?></span></td>
                                                        <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['AV']); ?></span></td>
                                                        <td><span class="badge badge-light-danger fs-7 fw-semibold"><?php echo number_format($product_data['RAW']); ?></span></td>
                                                        <td><span class="badge badge-light-danger fs-7 fw-semibold"><?php echo number_format($product_data['RPR']); ?></span></td>
                                                        <td><span class="badge badge-light-warning fs-7 fw-semibold"><?php echo number_format($product_data['SND']); ?></span></td>
                                                        <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['RTR']); ?></span></td>
                                                        <td><span class="badge badge-light-success fs-7 fw-semibold"><?php echo number_format($product_data['QCIN']); ?></span></td>
                                                        <td><span class="badge badge-light-primary fs-7 fw-semibold"><?php echo number_format($product_data['CLR']); ?></span></td>
                                                        <td><span class="badge badge-light-secondary fs-7 fw-semibold"><?php echo number_format($product_data['QCL']); ?></span></td>
                                                        <td><span class="badge badge-light-dark fs-7 fw-semibold"><?php echo number_format($product_data['FinP']); ?></span></td>
                                                        <td><span class="badge badge-light-success fs-7 fw-semibold"><?php echo number_format($product_data['QFin']); ?></span></td>
                                                        <td><span class="badge badge-light-info fs-7 fw-semibold"><?php echo number_format($product_data['STR']); ?></span></td>
                                                    </tr>
                                                <?php endforeach; 
                                            endif; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="15" class="text-center text-gray-500 py-10">
                                                    <i class="ki-duotone ki-information fs-3x text-gray-400 mb-4"></i>
                                                    <div>No production data available</div>
                                                    <div class="text-gray-400 fs-7">Please adjust your filters to display data</div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <!--end::Table-->
                            </div>
                            <!--end::Card body-->
                        </div>
                    </div>
                </div>
                <!--end::Production by Order Table-->
                <?php endif; ?>
            </div>
            <!--end::Content container-->
        </div>
        <!--end::Content-->
    </div>
    <!--end::Content wrapper-->
</div>
<!--end::Main-->

<!-- Filter Modal and JavaScript remain the same -->
<!-- ... (Filter modal and JavaScript code remains unchanged) ... -->

<!--begin::Filter Modal-->
<div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Filters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="filterForm">
                <div class="modal-body">
                    <div class="row g-6">
                        <!-- Order Filter -->
                        <div class="col-md-6">
                            <label class="form-label">Select Order</label>
                            <select name="order" class="form-select">
                                <option value="">All Orders</option>
                                <?php foreach ($available_orders as $order): ?>
                                    <option value="<?php echo htmlspecialchars($order); ?>" <?php echo $selected_order === $order ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($order); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Customer Filter -->
                        <div class="col-md-6">
                            <label class="form-label">Select Customer</label>
                            <select name="customer" class="form-select">
                                <option value="">All Customers</option>
                                <?php foreach ($available_customers as $customer): ?>
                                    <option value="<?php echo htmlspecialchars($customer); ?>" <?php echo $selected_customer === $customer ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Show All Data Option -->
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_all_data" value="1" id="showAllData" <?php echo $show_all_data === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showAllData">
                                    Show all data (may take longer to load)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Filter Modal-->

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Show filter modal on page load if no filters are selected
    <?php if (!$has_filters): ?>
    showFilterModal();
    <?php endif; ?>

    // Initialize search functionality
    const searchInput = document.getElementById('orderSearch');
    const table = document.getElementById('productionTable');

    if (searchInput && table) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toUpperCase();
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                if (cells.length > 0) {
                    const soText = cells[0].textContent.toUpperCase();
                    const customerText = cells[1].textContent.toUpperCase();

                    if (soText.indexOf(filter) > -1 || customerText.indexOf(filter) > -1) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
            }
        });
    }
});

// Function to show filter modal
function showFilterModal() {
    const modal = new bootstrap.Modal(document.getElementById('filterModal'));
    modal.show();
}

// Function to post data to Odoo
function postToOdoo() {
    const odooData = <?php echo json_encode($odoo_data); ?>;

    if (odooData.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Data',
            text: 'No production data available to post to Odoo'
        });
        return;
    }

    Swal.fire({
        title: 'Posting to Odoo',
        text: `Posting ${odooData.length} order(s) to Odoo...`,
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    // Simulate API call to Odoo
    fetch('mo-periodik-information/api-post-data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(odooData)
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: `Successfully posted ${data.posted_count} orders to Odoo`
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to post data to Odoo'
                });
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Network error occurred while posting to Odoo'
            });
        });
}

// Function to view order details
function viewOrderDetails(soName) {
    // This would typically make an AJAX call to get detailed order information
    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const content = document.getElementById('orderDetailsContent');

    content.innerHTML = `
    <div class="text-center py-10">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-4">Loading order details...</div>
    </div>
`;

    modal.show();

    // Simulate loading order details
    setTimeout(() => {
        content.innerHTML = `
        <div class="alert alert-info">
            <h6>Order: ${soName}</h6>
            <p>Detailed production tracking information would be displayed here.</p>
            <p>This would include individual item tracking, timestamps, and station history.</p>
        </div>
    `;
    }, 1000);
}

</script>

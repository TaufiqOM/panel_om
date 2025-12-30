<?php
// No login required for this page
require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../inc/config_odoo.php';

// Function untuk mendapatkan gambar base64
function getImageBase64($imageData)
{
    if (empty($imageData)) return null;

    // Jika datanya belum base64 (masih blob biner)
    if (base64_encode(base64_decode($imageData, true)) !== $imageData) {
        $imageData = base64_encode($imageData);
    }

    // Deteksi tipe MIME berdasarkan header base64
    if (strpos($imageData, '/9j/') === 0 || strpos($imageData, 'data:image/jpeg') === 0) {
        $mime = 'image/jpeg';
    } elseif (strpos($imageData, 'iVBOR') === 0 || strpos($imageData, 'data:image/png') === 0) {
        $mime = 'image/png';
    } elseif (strpos($imageData, 'R0lGOD') === 0 || strpos($imageData, 'data:image/gif') === 0) {
        $mime = 'image/gif';
    } else {
        $mime = 'image/jpeg'; // default
    }

    // Jika sudah ada data:image prefix, return langsung
    if (strpos($imageData, 'data:image') === 0) {
        return $imageData;
    }

    return "data:$mime;base64,$imageData";
}

// Handle AJAX request to check code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_code') {
    header('Content-Type: application/json');
    try {
        $code = trim($_POST['code'] ?? '');
        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Code is required']);
            exit;
        }
        if (!isset($conn)) {
            throw new Exception('Database connection not available');
        }
        $stmt = $conn->prepare("SELECT id FROM shipping WHERE description = ?");
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("s", $code);
        if (!$stmt->execute()) {
            throw new Exception('Database execute failed: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(['success' => true, 'shipping_id' => $row['id']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Code not found in shipping records']);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX request to submit barcode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_barcode') {
    header('Content-Type: application/json');
    try {
        $barcode = trim($_POST['barcode'] ?? '');
        $shipping_id = intval($_POST['shipping_id'] ?? 0);
        if (empty($barcode)) {
            echo json_encode(['success' => false, 'message' => 'Barcode is required']);
            exit;
        }
        if ($shipping_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid shipping ID']);
            exit;
        }
        
        // Validasi: Cek apakah barcode sudah ada di shipping_manual_stuffing untuk shipping_id ini
        $stmt_duplicate = $conn->prepare("SELECT id FROM shipping_manual_stuffing WHERE id_shipping = ? AND production_code = ?");
        if (!$stmt_duplicate) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt_duplicate->bind_param("is", $shipping_id, $barcode);
        if (!$stmt_duplicate->execute()) {
            throw new Exception('Database execute failed: ' . $stmt_duplicate->error);
        }
        $result_duplicate = $stmt_duplicate->get_result();
        $is_duplicate = $result_duplicate->num_rows > 0;
        $stmt_duplicate->close();
        
        if ($is_duplicate) {
            echo json_encode(['success' => false, 'message' => 'Barcode sudah di scan']);
            exit;
        }
        
        // Cek apakah barcode ada di production_lots_strg
        $stmt_check = $conn->prepare("SELECT production_code FROM production_lots_strg WHERE production_code = ?");
        if (!$stmt_check) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt_check->bind_param("s", $barcode);
        if (!$stmt_check->execute()) {
            throw new Exception('Database execute failed: ' . $stmt_check->error);
        }
        $result_check = $stmt_check->get_result();
        $exists = $result_check->num_rows > 0;
        $stmt_check->close();
        
        if ($exists) {
            $stmt_insert = $conn->prepare("INSERT INTO shipping_manual_stuffing (id_shipping, production_code) VALUES (?, ?)");
            if (!$stmt_insert) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt_insert->bind_param("is", $shipping_id, $barcode);
            if (!$stmt_insert->execute()) {
                throw new Exception('Database execute failed: ' . $stmt_insert->error);
            }
            $stmt_insert->close();
            echo json_encode(['success' => true, 'message' => 'Berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product Tidak Ada Di Storage']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX request to delete item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_item') {
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $stmt_delete = $conn->prepare("DELETE FROM shipping_manual_stuffing WHERE id = ?");
        if (!$stmt_delete) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt_delete->bind_param("i", $id);
        if (!$stmt_delete->execute()) {
            throw new Exception('Database execute failed: ' . $stmt_delete->error);
        }
        $stmt_delete->close();
        echo json_encode(['success' => true, 'message' => 'Berhasil dihapus']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

$shipping_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$shipping_data = null;

if ($shipping_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM shipping WHERE id = ?");
    $stmt->bind_param("i", $shipping_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $shipping_data = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $shipping_data ? htmlspecialchars($shipping_data['description']) : 'Manual Stuffing'; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 700;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .info-box {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .info-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 13px;
            color: #333;
            font-weight: 600;
        }

        /* Scan Section */
        .scan-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .scan-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .scan-input {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #4CAF50;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 10px;
            outline: none;
        }

        .scan-input:focus {
            border-color: #45a049;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .scan-help {
            color: #666;
            font-size: 12px;
            margin-bottom: 15px;
        }

        .btn-submit {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
        }

        .btn-submit:hover {
            background: #45a049;
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        /* Counter */
        .counter {
            background: #4CAF50;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .counter-number {
            font-size: 40px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .counter-label {
            font-size: 14px;
            opacity: 0.9;
        }

        /* List */
        .list-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }

        .list-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .btn-refresh {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-refresh:hover {
            background: #1976D2;
        }

        .search-container {
            flex: 0 0 auto;
            width: 250px;
        }

        .search-input-wrapper {
            position: relative;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 8px 35px 8px 10px;
            font-size: 14px;
            border: 2px solid #ddd;
            border-radius: 6px;
            outline: none;
        }

        .search-input:focus {
            border-color: #2196F3;
        }

        .search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .search-clear:hover {
            background: #f0f0f0;
            color: #666;
        }

        .search-clear:active {
            transform: translateY(-50%) scale(0.9);
        }

        .list-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            flex-shrink: 0;
            background: #f8f9fa;
        }

        .item-image-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            flex-shrink: 0;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 24px;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item:hover {
            background: #f9f9f9;
        }

        .item-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .item-number {
            font-weight: bold;
            color: #666;
        }

        .item-code {
            flex: 1;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            color: #333;
            min-width: 0;
            overflow-wrap: break-word;
            word-break: break-all;
        }

        .item-product-name {
            flex: 1;
            font-size: 13px;
            color: #555;
            font-weight: 500;
            margin-top: 4px;
            padding-left: 0;
            min-width: 0;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .item-info {
            flex: 1;
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 4px;
            padding-left: 0;
            min-width: 0;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .item-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .item-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-delete {
            background: #f44336;
            color: white;
            border: none;
            padding: 3px 5px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            min-width: 24px;
            height: 24px;
            line-height: 1;
        }

        .btn-delete:hover {
            background: #d32f2f;
            transform: scale(1.05);
        }

        .btn-delete:active {
            transform: scale(0.95);
        }

        .btn-delete:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .no-data-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }

        .pagination-btn {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            min-width: 40px;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #1976D2;
            transform: translateY(-2px);
        }

        .pagination-btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .pagination-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .pagination-btn.active {
            background: #4CAF50;
        }

        .pagination-info {
            color: #666;
            font-size: 14px;
            margin: 0 10px;
        }

        /* Success Animation */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background: #4CAF50;
            color: white;
        }

        .toast.error {
            background: #f44336;
            color: white;
        }

        .toast-icon {
            font-size: 24px;
        }

        /* Mobile Responsive */
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .header {
                padding: 15px;
                margin-bottom: 10px;
            }

            .header h1 {
                font-size: 18px;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom-width: 2px;
            }

            .info-grid {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                margin-top: 12px;
            }

            .info-box {
                padding: 6px 4px;
                border-width: 1px;
            }

            .info-label {
                font-size: 8px;
                margin-bottom: 3px;
            }

            .info-value {
                font-size: 10px;
                word-break: break-word;
            }

            .counter {
                padding: 12px;
                margin-bottom: 10px;
            }

            .counter-number {
                font-size: 32px;
            }

            .counter-label {
                font-size: 12px;
            }

            .list-header {
                flex-wrap: wrap;
                gap: 8px;
            }

            .list-title {
                font-size: 16px;
            }

            .search-container {
                width: auto;
                flex: 1;
                min-width: 150px;
            }

            .search-input {
                font-size: 13px;
                padding: 6px 30px 6px 8px;
            }

            .search-clear {
                right: 8px;
                font-size: 18px;
                width: 18px;
                height: 18px;
            }

            .scan-section {
                padding: 15px;
                margin-bottom: 10px;
            }

            .scan-icon {
                font-size: 28px;
                margin-bottom: 10px;
            }

            .scan-input {
                font-size: 14px;
                padding: 10px;
                margin-bottom: 8px;
            }

            .scan-help {
                font-size: 11px;
                margin-bottom: 12px;
            }

            .btn-submit {
                padding: 10px 20px;
                font-size: 13px;
            }

            .counter-number {
                font-size: 36px;
            }

            .list-item {
                padding: 12px 10px;
                gap: 8px;
            }

            .item-image {
                width: 50px;
                height: 50px;
            }

            .item-image-placeholder {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .item-left {
                gap: 8px;
            }

            .item-number {
                font-size: 14px;
            }

            .item-code {
                font-size: 14px;
                flex: 1;
                min-width: 0;
            }

            .item-product-name {
                font-size: 12px;
            }

            .item-info {
                font-size: 11px;
                display: none; /* Sembunyikan info_to_buyer di mobile */
            }

            .item-content {
                flex: 1;
                min-width: 0;
            }

            .item-actions {
                flex-shrink: 0;
            }

            .btn-delete {
                min-width: 28px;
                height: 28px;
                padding: 4px;
                font-size: 12px;
                border-radius: 4px;
                /* Touch-friendly: larger tap target */
                -webkit-tap-highlight-color: rgba(244, 67, 54, 0.2);
            }

            .btn-delete:active {
                transform: scale(0.9);
                background: #c62828;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($shipping_data): ?>
            <!-- Header -->
            <div class="header">
                <h1><?php echo htmlspecialchars($shipping_data['description']); ?></h1>
                <div class="info-grid">
                    <div class="info-box">
                        <div class="info-label">Tanggal Kirim</div>
                        <div class="info-value">
                            <?php 
                            if (!empty($shipping_data['sheduled_date'])) {
                                $date = DateTime::createFromFormat('Y-m-d H:i:s', $shipping_data['sheduled_date']);
                                if (!$date) {
                                    $date = DateTime::createFromFormat('Y-m-d', $shipping_data['sheduled_date']);
                                }
                                if ($date) {
                                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                    $day = $date->format('d');
                                    $month = $months[(int)$date->format('m') - 1];
                                    $year = $date->format('Y');
                                    echo htmlspecialchars($day . '-' . $month . '-' . $year);
                                } else {
                                    echo htmlspecialchars($shipping_data['sheduled_date']);
                                }
                            } else {
                                echo '';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Tujuan</div>
                        <div class="info-value"><?php echo htmlspecialchars($shipping_data['ship_to']); ?></div>
                    </div>
                </div>
            </div>

            <?php
            // Pagination settings
            $items_per_page = 10;
            $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $offset = ($current_page - 1) * $items_per_page;
            
            // Search parameter
            $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
            $search_where = '';
            $search_param = '';
            
            if (!empty($search_term)) {
                $search_where = " AND sms.production_code LIKE ?";
                $search_param = "%{$search_term}%";
            }
            
            // Hitung total records untuk pagination (dengan search)
            $count_query = "
                SELECT COUNT(*) as total
                FROM shipping_manual_stuffing sms
                WHERE sms.id_shipping = ? {$search_where}
            ";
            $stmt_count = $conn->prepare($count_query);
            if (!empty($search_term)) {
                $stmt_count->bind_param("is", $shipping_id, $search_param);
            } else {
                $stmt_count->bind_param("i", $shipping_id);
            }
            $stmt_count->execute();
            $result_count = $stmt_count->get_result();
            $total_records = $result_count->fetch_assoc()['total'];
            $total_pages = ceil($total_records / $items_per_page);
            $stmt_count->close();
            
            // Ambil data stuffing dengan sale_order_line_id dari production_lots_strg (dengan pagination dan search)
            $query = "
                SELECT 
                    sms.id, 
                    sms.production_code,
                    pls.sale_order_line_id
                FROM shipping_manual_stuffing sms
                LEFT JOIN production_lots_strg pls ON pls.production_code = sms.production_code
                WHERE sms.id_shipping = ? {$search_where}
                ORDER BY sms.id DESC
                LIMIT ? OFFSET ?
            ";
            $stmt_stuffing = $conn->prepare($query);
            if (!empty($search_term)) {
                $stmt_stuffing->bind_param("isii", $shipping_id, $search_param, $items_per_page, $offset);
            } else {
                $stmt_stuffing->bind_param("iii", $shipping_id, $items_per_page, $offset);
            }
            $stmt_stuffing->execute();
            $result_stuffing = $stmt_stuffing->get_result();
            $stuffing_data = [];
            $username = $_SESSION['username'] ?? '';
            
            // Cache untuk info_to_buyer, product_name, dan product_image berdasarkan sale_order_line_id
            $order_line_cache = [];
            $product_image_cache = [];
            
            while ($row = $result_stuffing->fetch_assoc()) {
                $sale_order_line_id = $row['sale_order_line_id'] ?? null;
                $info_to_buyer = '';
                $product_name = '';
                $product_image = null;
                
                // Ambil info_to_buyer, product_name, dan product_id dari Odoo jika sale_order_line_id tersedia
                if ($sale_order_line_id && !isset($order_line_cache[$sale_order_line_id])) {
                    $order_lines = callOdooRead($username, "sale.order.line", [["id", "=", $sale_order_line_id]], ["info_to_buyer", "name", "product_id"]);
                    if ($order_lines && !empty($order_lines)) {
                        $info_to_buyer = $order_lines[0]['info_to_buyer'] ?? '';
                        $product_name = $order_lines[0]['name'] ?? '';
                        $product_id = is_array($order_lines[0]['product_id']) ? $order_lines[0]['product_id'][0] : ($order_lines[0]['product_id'] ?? null);
                        
                        $order_line_cache[$sale_order_line_id] = [
                            'info_to_buyer' => $info_to_buyer,
                            'product_name' => $product_name,
                            'product_id' => $product_id
                        ];
                    } else {
                        $order_line_cache[$sale_order_line_id] = [
                            'info_to_buyer' => '',
                            'product_name' => '',
                            'product_id' => null
                        ];
                    }
                } elseif ($sale_order_line_id && isset($order_line_cache[$sale_order_line_id])) {
                    $info_to_buyer = $order_line_cache[$sale_order_line_id]['info_to_buyer'];
                    $product_name = $order_line_cache[$sale_order_line_id]['product_name'];
                    $product_id = $order_line_cache[$sale_order_line_id]['product_id'];
                } else {
                    $product_id = null;
                }
                
                // Ambil product image dari Odoo jika product_id tersedia
                if ($product_id && !isset($product_image_cache[$product_id])) {
                    $product_data = callOdooRead($username, "product.product", [["id", "=", $product_id]], ["image_1920"]);
                    if ($product_data && !empty($product_data) && isset($product_data[0]['image_1920'])) {
                        $image_data = $product_data[0]['image_1920'];
                        $product_image = getImageBase64($image_data);
                        $product_image_cache[$product_id] = $product_image;
                    } else {
                        $product_image_cache[$product_id] = null;
                    }
                } elseif ($product_id && isset($product_image_cache[$product_id])) {
                    $product_image = $product_image_cache[$product_id];
                }
                
                $row['info_to_buyer'] = $info_to_buyer;
                $row['product_name'] = $product_name;
                $row['product_image'] = $product_image;
                $stuffing_data[] = $row;
            }
            $stmt_stuffing->close();
            ?>

            <!-- Counter -->
            <div class="counter">
                <div class="counter-number" id="totalCount"><?php echo $total_records; ?></div>
                <div class="counter-label">
                    <?php if (!empty($search_term)): ?>
                        Hasil Pencarian
                    <?php else: ?>
                        Total Item Terscan
                    <?php endif; ?>
                </div>
            </div>

            <!-- Scan Section -->
            <div class="scan-section">
                <div class="scan-icon">📦</div>
                <form id="barcodeForm">
                    <input type="hidden" name="shipping_id" value="<?php echo $shipping_id; ?>">
                    <input 
                        type="text" 
                        class="scan-input" 
                        id="barcodeInput" 
                        name="barcode" 
                        placeholder="Scan Barcode Di Sini" 
                        autofocus 
                        autocomplete="off"
                    >
                    <button type="submit" class="btn-submit">✓ SUBMIT</button>
                </form>
            </div>

            <!-- List -->
            <div class="list-section">
                <div class="list-header">
                    <div class="list-title">Daftar Item</div>
                    <div class="search-container">
                        <div class="search-input-wrapper">
                            <form method="GET" action="" id="searchForm" style="display: flex; width: 100%;">
                                <input type="hidden" name="id" value="<?php echo $shipping_id; ?>">
                                <input 
                                    type="text" 
                                    class="search-input" 
                                    id="searchInput" 
                                    name="search"
                                    value="<?php echo htmlspecialchars($search_term); ?>"
                                    placeholder="Cari barcode..."
                                    autocomplete="off"
                                >
                                <button type="button" class="search-clear" id="searchClear" title="Clear search">×</button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if (!empty($stuffing_data)): ?>
                    <div id="itemList">
                        <?php foreach ($stuffing_data as $index => $item): ?>
                            <div class="list-item" data-id="<?php echo $item['id']; ?>">
                                <div class="item-left">
                                    <span class="item-number"><?php echo $offset + $index + 1; ?>.</span>
                                    <?php if (!empty($item['product_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['product_image']); ?>" alt="Product" class="item-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="item-image-placeholder" style="display: none;">📦</div>
                                    <?php else: ?>
                                        <div class="item-image-placeholder">📦</div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-content">
                                    <span class="item-code"><?php echo htmlspecialchars($item['production_code']); ?></span>
                                    <?php if (!empty($item['product_name'])): ?>
                                        <span class="item-product-name">📦 <?php echo htmlspecialchars($item['product_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['info_to_buyer'])): ?>
                                        <span class="item-info">ℹ️ <?php echo htmlspecialchars($item['info_to_buyer']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="item-actions">
                                    <button class="btn-delete" onclick="deleteItem(<?php echo $item['id']; ?>, this)" title="Hapus">🗑️</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php
                            $search_param_url = !empty($search_term) ? '&search=' . urlencode($search_term) : '';
                            ?>
                            <button 
                                class="pagination-btn" 
                                onclick="window.location.href='?id=<?php echo $shipping_id; ?>&page=<?php echo max(1, $current_page - 1); ?><?php echo $search_param_url; ?>'"
                                <?php echo $current_page <= 1 ? 'disabled' : ''; ?>
                            >
                                ‹ Prev
                            </button>
                            
                            <?php
                            // Tampilkan maksimal 5 nomor halaman
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            // Jika di awal, pastikan tampil 5 halaman
                            if ($end_page - $start_page < 4) {
                                if ($start_page == 1) {
                                    $end_page = min($total_pages, $start_page + 4);
                                } else {
                                    $start_page = max(1, $end_page - 4);
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <button 
                                    class="pagination-btn <?php echo $i == $current_page ? 'active' : ''; ?>"
                                    onclick="window.location.href='?id=<?php echo $shipping_id; ?>&page=<?php echo $i; ?><?php echo $search_param_url; ?>'"
                                >
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            
                            <button 
                                class="pagination-btn" 
                                onclick="window.location.href='?id=<?php echo $shipping_id; ?>&page=<?php echo min($total_pages, $current_page + 1); ?><?php echo $search_param_url; ?>'"
                                <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>
                            >
                                Next ›
                            </button>
                            
                            <span class="pagination-info">
                                Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?>
                                <?php if (!empty($search_term)): ?>
                                    <br><small style="font-size: 11px; opacity: 0.7;">Hasil pencarian: "<?php echo htmlspecialchars($search_term); ?>"</small>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-data">
                        <div class="no-data-icon">📭</div>
                        <?php if (!empty($search_term)): ?>
                            <p>Tidak ada hasil untuk "<?php echo htmlspecialchars($search_term); ?>"</p>
                            <p style="margin-top: 10px; font-size: 14px;">
                                <a href="?id=<?php echo $shipping_id; ?>&page=1" style="color: #2196F3; text-decoration: underline;">Tampilkan semua item</a>
                            </p>
                        <?php else: ?>
                            <p>Belum ada item yang discan</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="toast" class="toast"></div>

            <script>
                // Toast notification function
                function showToast(message, type = 'success') {
                    const toast = document.getElementById('toast');
                    const icon = type === 'success' ? '✓' : '✕';
                    
                    toast.innerHTML = `<span class="toast-icon">${icon}</span><span>${message}</span>`;
                    toast.className = `toast ${type}`;
                    
                    // Show toast
                    setTimeout(() => {
                        toast.classList.add('show');
                    }, 100);
                    
                    // Hide toast after 2 seconds
                    setTimeout(() => {
                        toast.classList.remove('show');
                    }, 2000);
                }
                // Auto focus ke input
                function focusInput() {
                    // Jangan auto-focus jika user sedang mengetik di search input
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput === document.activeElement) {
                        return; // Skip auto-focus jika search input sedang aktif
                    }
                    document.getElementById('barcodeInput').focus();
                }

                // Focus setiap 5 detik
                setInterval(focusInput, 5000);

                // Form submit
                document.getElementById('barcodeForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'submit_barcode');
                    const input = document.getElementById('barcodeInput');

                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success toast
                            showToast('Berhasil ditambahkan!', 'success');
                            
                            // Play beep sound
                            const beep = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBDGH0fPTgjMGHm7A7+OZSA0PVqzn77BdGAg+ltryxnMpBSh+zPLaizsIGGS57OihUBELTKXh8bllHAU2jdXy0H0vBSl+zPDajTkHGWu/7eWdTQ0OVKzn8LJfGgk+ltrzxnQrBSh9y/HajzsIGWS46+mjUBEMTKXh8bllHAU1jdXy0H4wBSp/zPDajTkHGWu/7eWdTQ0OVKzn8LJfGgk9ltrzxnQrBSh9y/HajzsIGWS46+mjUBEMTKXh8bllHAU1jdXy0H4wBSp/zPDajTkHGWu/7eWdTQ0OVKzn8LJfGgk9ltrzxnQrBSh9y/HajzsIGWS46+mjUBEMTKXh8bllHAU1jdXy0H4wBSp/zPDajTkHGWu/7eWdTQ0OVKzn8LJfGgk9ltrzxnQrBSh9y/HajzsIGWS46+mjUBEMTKXh8bllHAU1jdXy0H4wBSp/zPDajTkHGWu/7eWdTQ0OVKzn8LJfGgk9ltrzxnQrBSh9y/HajzsIGWS46+mjUBEMTKXh8bllHAU1jdXy0H4wBSp/zPDajTkHGWu/7eWdTQ0OVKzn8LJfGgk9ltrzxnQrBSh9y/HajzsIGWS46+mjUBEMTKXh8bllHAU1jdXy0H4wBSp/zPDajTkHGWu/7eWdTQ0OVKzn8LJfGgk9ltrzxnQrBSh9y/HajzsIGWS46+mjUBEMTKXh8bllHAU1jdXy0H4wBSp/zPDajTkHGWu/7eWdTQ0OVKzn8LJfGgo=');
                            beep.play().catch(() => {});

                            // Clear input
                            input.value = '';
                            
                            // Reload after 1.5 seconds dengan mempertahankan search parameter
                            setTimeout(() => {
                                const urlParams = new URLSearchParams();
                                urlParams.set('id', '<?php echo $shipping_id; ?>');
                                <?php if (!empty($search_term)): ?>
                                urlParams.set('search', '<?php echo urlencode($search_term); ?>');
                                <?php endif; ?>
                                // Setelah submit barcode baru, tampilkan di halaman 1
                                urlParams.set('page', '1');
                                window.location.href = '?' + urlParams.toString();
                            }, 1500);
                        } else {
                            // Show error toast
                            showToast(data.message, 'error');
                            
                            // Clear input and refocus
                            input.value = '';
                            input.focus();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Terjadi kesalahan', 'error');
                        input.focus();
                    });
                });

                // Clear input dengan ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        document.getElementById('barcodeInput').value = '';
                        focusInput();
                    }
                });

                // Search functionality - submit form untuk server-side search
                let searchTimeout;
                document.getElementById('searchInput').addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const searchTerm = this.value.trim();
                    
                    // Debounce: tunggu 500ms setelah user berhenti mengetik
                    searchTimeout = setTimeout(function() {
                        const form = document.getElementById('searchForm');
                        const urlParams = new URLSearchParams();
                        urlParams.set('id', '<?php echo $shipping_id; ?>');
                        if (searchTerm) {
                            urlParams.set('search', searchTerm);
                        }
                        // Reset ke halaman 1 saat search
                        urlParams.set('page', '1');
                        window.location.href = '?' + urlParams.toString();
                    }, 500);
                });

                // Submit form dengan Enter
                document.getElementById('searchForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    const searchTerm = document.getElementById('searchInput').value.trim();
                    const urlParams = new URLSearchParams();
                    urlParams.set('id', '<?php echo $shipping_id; ?>');
                    if (searchTerm) {
                        urlParams.set('search', searchTerm);
                    }
                    urlParams.set('page', '1');
                    window.location.href = '?' + urlParams.toString();
                });

                // Clear button functionality
                document.getElementById('searchClear').addEventListener('click', function() {
                    clearTimeout(searchTimeout);
                    const urlParams = new URLSearchParams();
                    urlParams.set('id', '<?php echo $shipping_id; ?>');
                    urlParams.set('page', '1');
                    window.location.href = '?' + urlParams.toString();
                });

                // Delete item functionality
                function deleteItem(id, buttonElement) {
                    Swal.fire({
                        title: 'Hapus Item?',
                        text: 'Apakah Anda yakin ingin menghapus item ini?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#f44336',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Disable button during request
                            buttonElement.disabled = true;
                            
                            const formData = new FormData();
                            formData.append('action', 'delete_item');
                            formData.append('id', id);

                            fetch('', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showToast('Item berhasil dihapus', 'success');
                                    
                                    // Reload halaman dengan mempertahankan search dan page parameter
                                    setTimeout(() => {
                                        const urlParams = new URLSearchParams();
                                        urlParams.set('id', '<?php echo $shipping_id; ?>');
                                        <?php if (!empty($search_term)): ?>
                                        urlParams.set('search', '<?php echo urlencode($search_term); ?>');
                                        <?php endif; ?>
                                        urlParams.set('page', '<?php echo $current_page; ?>');
                                        window.location.href = '?' + urlParams.toString();
                                    }, 500);
                                } else {
                                    showToast(data.message || 'Gagal menghapus item', 'error');
                                    buttonElement.disabled = false;
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Terjadi kesalahan saat menghapus', 'error');
                                buttonElement.disabled = false;
                            });
                        }
                    });
                }
            </script>
        <?php endif; ?>
    </div>

    <?php if (!$shipping_data): ?>
    <script>
        function showCodePrompt() {
            Swal.fire({
                title: 'Masukkan Kode Pengiriman',
                input: 'text',
                inputPlaceholder: 'Ketik kode pengiriman',
                showCancelButton: false,
                allowOutsideClick: false,
                confirmButtonText: 'OK',
                confirmButtonColor: '#4CAF50',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Kode harus diisi!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=check_code&code=' + encodeURIComponent(result.value)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '?id=' + data.shipping_id;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Kode Salah',
                                text: 'Kode tidak ditemukan',
                                confirmButtonText: 'Coba Lagi',
                                confirmButtonColor: '#4CAF50'
                            }).then(() => {
                                showCodePrompt();
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan',
                            confirmButtonText: 'Coba Lagi'
                        }).then(() => {
                            showCodePrompt();
                        });
                    });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', showCodePrompt);
    </script>
    <?php endif; ?>
</body>
</html>
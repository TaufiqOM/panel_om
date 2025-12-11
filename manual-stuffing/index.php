<?php
// No login required for this page
require __DIR__ . '/../inc/config.php';

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
            margin-bottom: 20px;
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
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e9ecef;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        /* Scan Section */
        .scan-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .scan-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .scan-input {
            width: 100%;
            padding: 16px;
            font-size: 18px;
            border: 3px solid #4CAF50;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
            outline: none;
        }

        .scan-input:focus {
            border-color: #45a049;
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.1);
        }

        .scan-help {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-submit {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 16px 40px;
            font-size: 18px;
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
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .counter-number {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .counter-label {
            font-size: 16px;
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
            margin-bottom: 15px;
        }

        .search-input-wrapper {
            position: relative;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            font-size: 16px;
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
            align-items: center;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item:hover {
            background: #f9f9f9;
        }

        .item-number {
            font-weight: bold;
            color: #666;
            margin-right: 15px;
        }

        .item-code {
            flex: 1;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            color: #333;
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

            .header h1 {
                font-size: 22px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .scan-input {
                font-size: 16px;
            }

            .counter-number {
                font-size: 36px;
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
                        <div class="info-label">Nama Pengiriman</div>
                        <div class="info-value"><?php echo htmlspecialchars($shipping_data['name']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Tanggal Kirim</div>
                        <div class="info-value"><?php echo htmlspecialchars($shipping_data['sheduled_date']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Tujuan</div>
                        <div class="info-value"><?php echo htmlspecialchars($shipping_data['ship_to']); ?></div>
                    </div>
                </div>
            </div>

            <?php
            $stmt_stuffing = $conn->prepare("SELECT production_code FROM shipping_manual_stuffing WHERE id_shipping = ? ORDER BY id DESC");
            $stmt_stuffing->bind_param("i", $shipping_id);
            $stmt_stuffing->execute();
            $result_stuffing = $stmt_stuffing->get_result();
            $stuffing_data = [];
            while ($row = $result_stuffing->fetch_assoc()) {
                $stuffing_data[] = $row;
            }
            $stmt_stuffing->close();
            ?>

            <!-- Counter -->
            <div class="counter">
                <div class="counter-number" id="totalCount"><?php echo count($stuffing_data); ?></div>
                <div class="counter-label">Total Item Terscan</div>
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
                    <div class="scan-help">Arahkan scanner ke kotak di atas atau ketik manual</div>
                    <button type="submit" class="btn-submit">✓ SUBMIT</button>
                </form>
            </div>

            <!-- List -->
            <div class="list-section">
                <div class="list-header">
                    <div class="list-title">Daftar Item</div>
                    <button class="btn-refresh" onclick="location.reload()">🔄 Refresh</button>
                </div>

                <div class="search-container">
                    <div class="search-input-wrapper">
                        <input type="text" class="search-input" id="searchInput" placeholder="Cari barcode yang sudah terscan...">
                        <button type="button" class="search-clear" id="searchClear" title="Clear search">×</button>
                    </div>
                </div>

                <?php if (!empty($stuffing_data)): ?>
                    <div id="itemList">
                        <?php foreach ($stuffing_data as $index => $item): ?>
                            <div class="list-item">
                                <span class="item-number"><?php echo $index + 1; ?>.</span>
                                <span class="item-code"><?php echo htmlspecialchars($item['production_code']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <div class="no-data-icon">📭</div>
                        <p>Belum ada item yang discan</p>
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
                            
                            // Reload after 1.5 seconds
                            setTimeout(() => {
                                location.reload();
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

                // Search functionality
                function updateSearch() {
                    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                    const items = document.querySelectorAll('.list-item');
                    let visibleCount = 0;

                    items.forEach(item => {
                        const code = item.querySelector('.item-code').textContent.toLowerCase();
                        if (code.includes(searchTerm)) {
                            item.style.display = '';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    // Update counter
                    const counter = document.getElementById('totalCount');
                    if (searchTerm === '') {
                        counter.textContent = items.length;
                    } else {
                        counter.textContent = visibleCount;
                    }
                }

                document.getElementById('searchInput').addEventListener('input', updateSearch);

                // Clear button functionality
                document.getElementById('searchClear').addEventListener('click', function() {
                    document.getElementById('searchInput').value = '';
                    updateSearch();
                });
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
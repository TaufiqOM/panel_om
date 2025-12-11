<?php
session_start();
require_once '../inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input) || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or empty input']);
    exit;
}

$conn->begin_transaction();

try {
    $date = date('Ymd');
    $query_count = "SELECT COUNT(*) as count FROM wood_engineered_pbp WHERE wood_engineered_date = CURDATE() FOR UPDATE";
    $result = $conn->query($query_count);
    $count = $result->fetch_assoc()['count'] + 1;
    $pbpNumber = 'PBP-WE-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    $query_insert = "INSERT INTO wood_engineered_pbp (
                        wood_engineered_number, wood_engineered_date, wood_engineered_time, 
                        wood_engineered_code, wood_engineered_qty, 
                        so_number, product_code, employee_nik
                     ) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query_insert);
    if ($stmt === false) {
        throw new Exception("Gagal mempersiapkan statement: " . $conn->error);
    }

    $savedCount = 0;
    foreach ($input['items'] as $item) {
        $so_number = $item['so_number'] ?? '';
        $product_code = $item['product_code'] ?? '';

        // PERBAIKAN: Tipe data disesuaikan dengan urutan variabel
        // pbpNumber(s), code(s), qty(d), so_number(s), product_code(s), employee_nik(s)
        $stmt->bind_param("ssdsss",
            $pbpNumber,
            $item['code'],
            $item['qty'],
            $so_number,
            $product_code,
            $input['employee_nik']
        );
        
        if ($stmt->execute()) {
            $savedCount++;
        } else {
            throw new Exception("Gagal menyimpan item: " . ($item['code'] ?? 'N/A') . " - " . $stmt->error);
        }
    }
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'pbp_number' => $pbpNumber,
        'saved_count' => $savedCount,
        'item_count' => count($input['items']),
        'message' => 'Semua data berhasil disimpan.'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}

$conn->close();
?>
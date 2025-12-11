<?php
require_once '../../inc/config.php'; 

header('Content-Type: application/json');

// Hanya izinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Ambil data JSON dari body request
$input = json_decode(file_get_contents('php://input'), true);
$bom_id = $input['bom_id'] ?? '';

// Validasi bom_id
if (empty($bom_id) || !is_numeric($bom_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'BOM ID tidak valid.']);
    exit;
}

// Mulai transaksi untuk keamanan
$conn->begin_transaction();

try {
    // Siapkan statement untuk menghapus data
    $sql = "DELETE FROM bom_component WHERE bom_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Gagal mempersiapkan statement: " . $conn->error);
    }

    // Bind parameter dan eksekusi
    $stmt->bind_param("i", $bom_id);
    if (!$stmt->execute()) {
        throw new Exception("Gagal mengeksekusi penghapusan data: " . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    // Commit transaksi jika berhasil
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Berhasil mereset data. Sebanyak $affected_rows komponen untuk BOM ID $bom_id telah dihapus."
    ]);

} catch (Exception $e) {
    // Rollback jika terjadi error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>
<?php
// submit_materials.php
header('Content-Type: application/json');

include '../inc/config.php';

// Periksa koneksi
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Ambil data dari POST request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['materials']) || empty($data['materials'])) {
    echo json_encode(['success' => false, 'message' => 'No materials data received']);
    exit;
}

$materials = $data['materials'];
$successCount = 0;
$errors = [];

// Generate nomor PBP (contoh: PBP-2024-001)
function generatePbpNumber($conn) {
    $currentYear = date('Y');
    
    // Cari nomor terakhir untuk tahun ini
    $sql = "SELECT wood_solid_pbp_number FROM wood_solid_pbp 
            WHERE wood_solid_pbp_number LIKE ? 
            ORDER BY wood_solid_pbp_id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $likePattern = 'PBP-' . $currentYear . '-%';
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $lastNumber = $result->fetch_assoc()['wood_solid_pbp_number'];
        $lastSequence = intval(substr($lastNumber, -3));
        $newSequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newSequence = '001';
    }
    
    $stmt->close();
    return 'PBP-' . $currentYear . '-' . $newSequence;
}

// Data default (sesuaikan dengan kebutuhan)
$pbpNumber = generatePbpNumber($conn);
$soNumber = isset($data['so_number']) ? $data['so_number'] : 'SO-' . date('Ymd');
$productCode = isset($data['product_code']) ? $data['product_code'] : 'PROD-' . date('Ymd');
$operatorFullname = isset($data['employee_nik']) ? $data['employee_nik'] : 'Operator';
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

// Mulai transaction
$conn->begin_transaction();

try {
    foreach ($materials as $index => $material) {
        // Validasi data material
        if (empty($material['barcode'])) {
            $errors[] = "Material ke-" . ($index + 1) . ": Barcode kosong";
            continue;
        }
        
        // Cek apakah barcode sudah ada di database wood_solid_dtg
        $checkSql = "SELECT wood_solid_barcode FROM wood_solid_dtg WHERE wood_solid_barcode = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $material['barcode']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $errors[] = "Material ke-" . ($index + 1) . ": Barcode '{$material['barcode']}' tidak valid";
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();
        
        // Insert data ke tabel wood_solid_pbp
        $insertSql = "INSERT INTO wood_solid_pbp (
                        wood_solid_pbp_number, 
                        so_number, 
                        product_code, 
                        wood_solid_pbp_date, 
                        wood_solid_pbp_time, 
                        wood_solid_barcode, 
                        employee_nik
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param(
            "sssssss", 
            $pbpNumber,
            $soNumber,
            $productCode,
            $currentDate,
            $currentTime,
            $material['barcode'],
            $operatorFullname
        );
        
        if ($insertStmt->execute()) {
            $successCount++;
            
            // Optional: Update status barcode di tabel wood_solid_dtg (jika ada kolom status)
            // $updateSql = "UPDATE wood_solid_dtg SET status = 'processed' WHERE wood_solid_barcode = ?";
            // $updateStmt = $conn->prepare($updateSql);
            // $updateStmt->bind_param("s", $material['barcode']);
            // $updateStmt->execute();
            // $updateStmt->close();
            
        } else {
            $errors[] = "Material ke-" . ($index + 1) . ": Gagal menyimpan - " . $insertStmt->error;
        }
        
        $insertStmt->close();
    }
    
    // Commit transaction jika semua berhasil
    if (empty($errors)) {
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Berhasil menyimpan ' . $successCount . ' material',
            'pbp_number' => $pbpNumber,
            'saved_count' => $successCount
        ]);
    } else {
        // Rollback jika ada error
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => 'Sebagian data gagal disimpan',
            'saved_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors,
            'total_materials' => count($materials)
        ]);
    }
    
} catch (Exception $e) {
    // Rollback jika terjadi exception
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
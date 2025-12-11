<?php
session_start();
require_once 'session_manager.php';
include "../inc/config.php"; // Pastikan $conn ada dari file ini

header('Content-Type: application/json');

// Asumsi status token: 1 = Aktif/Belum dipakai, 0 = Tidak Aktif/Sudah dipakai
define('TOKEN_STATUS_ACTIVE', 1);
define('TOKEN_STATUS_USED', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? ''); // Ambil token
    $barcode = trim($_POST['barcode'] ?? '');
    
    // Validasi input dasar
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Token login tidak boleh kosong']);
        exit;
    }

    if (empty($barcode)) {
        echo json_encode(['success' => false, 'message' => 'Barcode karyawan tidak boleh kosong']);
        exit;
    }

    // 1. Validasi Token Terlebih Dahulu
    $tokenCheck = validateToken($token);
    if (!$tokenCheck['valid']) {
        echo json_encode(['success' => false, 'message' => $tokenCheck['message']]);
        exit;
    }

    // 2. Cek session yang sudah ada (jika token valid)
    $sessionCheck = SessionManager::checkEmployeeSession();
    if ($sessionCheck['logged_in']) {
        if ($sessionCheck['employee']['barcode'] === $barcode) {
            // Token valid, barcode sama dengan session, login berhasil
            echo json_encode(['success' => true, 'employee' => $sessionCheck['employee']]);
            // Token tidak perlu dinonaktifkan karena session sudah ada
            exit;
        }
    }
    
    // 3. Validasi barcode dari database
    $employee = validateEmployeeBarcode($barcode);
    
    if ($employee) {
        // 4. Nonaktifkan Token karena login berhasil
        // deactivateToken($token);

        // Buat session baru
        SessionManager::createEmployeeSession($employee);
        
        echo json_encode([
            'success' => true, 
            'employee' => $employee
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Barcode karyawan tidak valid atau tidak ditemukan'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
}

/**
 * Fungsi baru untuk memvalidasi token
 */
function validateToken($token_code) {
    global $conn;
    
    $sql = "SELECT token_status FROM token WHERE token_code = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['valid' => false, 'message' => 'Kesalahan database (prepare)'];
    }
    
    $stmt->bind_param("s", $token_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['valid' => false, 'message' => 'Token login tidak valid'];
    }
    
    $token = $result->fetch_assoc();
    $stmt->close();
    
    if ($token['token_status'] != TOKEN_STATUS_ACTIVE) {
        return ['valid' => false, 'message' => 'Token login sudah digunakan atau tidak aktif'];
    }
    
    return ['valid' => true, 'message' => 'Token valid'];
}

/**
 * Fungsi baru untuk menonaktifkan token setelah dipakai
 */
function deactivateToken($token_code) {
    global $conn;
    
    $sql = "UPDATE token SET token_status = ? WHERE token_code = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $status_used = TOKEN_STATUS_USED;
        $stmt->bind_param("is", $status_used, $token_code);
        $stmt->execute();
        $stmt->close();
    }
    // Tidak mengembalikan error, biarkan proses login lanjut
    // Bisa ditambahkan logging jika update gagal
}


/**
 * Fungsi validasi employee (Menggunakan Prepared Statement)
 */
function validateEmployeeBarcode($barcode) {
    global $conn;
    
    $sql = "SELECT id, id_employee, name, email, phone, barcode, image_1920 
            FROM employee 
            WHERE barcode = ?";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null; // Error prepare
    }
    
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        $stmt->close();
        
        // Handle image - convert blob to base64 jika ada
        $imageData = null;
        if ($employee['image_1920']) {
            $imageData = 'data:image/jpeg;base64,' . base64_encode($employee['image_1920']);
        }
        
        return [
            'id' => $employee['id'],
            'id_employee' => $employee['id_employee'],
            'name' => $employee['name'],
            'email' => $employee['email'],
            'phone' => $employee['phone'],
            'barcode' => $employee['barcode'],
            'image' => $imageData,
            'full_name' => $employee['name']
        ];
    }
    
    $stmt->close();
    return null;
}
?>
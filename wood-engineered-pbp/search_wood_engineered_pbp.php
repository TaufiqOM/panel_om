<?php
// Sertakan file konfigurasi database Anda
require_once '../inc/config.php';

// Atur header output menjadi JSON
header('Content-Type: application/json');

// 1. Validasi Metode Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed'
    ]);
    exit;
}

// 2. Ambil dan Validasi Input
$employee_nik = $_POST['employee_nik'] ?? '';

if (empty($employee_nik)) {
    echo json_encode([
        'success' => false, 
        'message' => 'NIK Karyawan wajib diisi.'
    ]);
    exit;
}

try {
    // 3. Siapkan dan Jalankan Query SQL
    // Query ini akan mengelompokkan data berdasarkan nomor PBP
    // dan menghitung jumlah item di setiap PBP.
    $sql = "SELECT
                wood_engineered_number,
                wood_engineered_date,
                wood_engineered_time,
                COUNT(wood_engineered_id) as item_count
            FROM
                wood_engineered_pbp
            WHERE
                employee_nik = ?
            GROUP BY
                wood_engineered_number, wood_engineered_date, wood_engineered_time
            ORDER BY
                wood_engineered_date DESC, wood_engineered_time DESC
            LIMIT 50"; // Batasi hasil untuk performa

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Gagal mempersiapkan query: " . $conn->error);
    }

    // Bind parameter untuk keamanan (mencegah SQL Injection)
    $stmt->bind_param("s", $employee_nik);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Ambil semua hasil sebagai array asosiatif
    $pbp_list = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $conn->close();

    // 4. Kirim Respon JSON Sukses
    echo json_encode([
        'success' => true,
        'pbp_list' => $pbp_list
    ]);

} catch (Exception $e) {
    // 5. Kirim Respon JSON Error jika terjadi masalah
    http_response_code(500); // Set kode status error server
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
    ]);
}
?>
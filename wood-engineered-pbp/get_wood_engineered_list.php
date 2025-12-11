<?php
// 1. Sertakan file konfigurasi database Anda
require_once '../inc/config.php';

// 2. Atur header output menjadi JSON
header('Content-Type: application/json');

try {
    // 3. Query untuk mengambil semua item dari tabel master
    // Sesuaikan nama tabel (wood_engineered_master) dan kolom jika perlu
    $sql = "SELECT 
                wood_engineered_code, 
                wood_engineered_name 
            FROM 
                wood_engineered
            ORDER BY 
                wood_engineered_code ASC";

    $result = $conn->query($sql);

    // Cek jika query gagal
    if ($result === false) {
        throw new Exception("Query ke database gagal dieksekusi: " . $conn->error);
    }

    // Ambil semua hasil data menjadi sebuah array
    $items = $result->fetch_all(MYSQLI_ASSOC);

    // Tutup koneksi database
    $conn->close();

    // 4. Kirim respon sukses dalam format JSON yang diharapkan oleh form
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);

} catch (Exception $e) {
    // 5. Jika terjadi error, kirim respon gagal dalam format JSON
    http_response_code(500); // Set kode status error server
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
    ]);
}
?>
<?php
// Pastikan path ke config.php sudah benar
require_once '../../inc/config.php'; 

header('Content-Type: application/json');

// 1. Hanya izinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 2. Ambil data JSON mentah dari body request
$input = json_decode(file_get_contents('php://input'), true);
$bom_id = $input['bom_id'] ?? '';
$components = $input['components'] ?? [];

// 3. Validasi bom_id
if (empty($bom_id) || !is_numeric($bom_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'BOM ID tidak valid.']);
    exit;
}

// 4. Mulai transaksi database untuk memastikan integritas data
$conn->begin_transaction();

try {
    // 5. Hapus semua komponen lama untuk BOM ID ini
    $delete_sql = "DELETE FROM bom_component WHERE bom_id = ?";
    $stmt_delete = $conn->prepare($delete_sql);
    if ($stmt_delete === false) throw new Exception("Gagal mempersiapkan statement hapus data: " . $conn->error);
    $stmt_delete->bind_param("i", $bom_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 6. Insert semua komponen baru dari data yang dikirim oleh JavaScript
    if (!empty($components)) {
        $sql_insert = "INSERT INTO bom_component (
                    bom_id, bom_component_number, bom_component_name, bom_component_bahan, bom_component_kayu,
                    bom_component_kayu_detail, bom_component_jenis, bom_component_qty, bom_component_panjang,
                    bom_component_lebar, bom_component_tebal, bom_component_description, bom_component_group
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert === false) throw new Exception("Gagal mempersiapkan statement insert data: " . $conn->error);

        foreach ($components as $item) {
            // Memberi nilai default jika ada data yang kosong atau null
            $bom_component_number = $item['bom_component_number'] ?? '';
            $bom_component_name = $item['bom_component_name'] ?? '';
            $bom_component_bahan = $item['bom_component_bahan'] ?? '';
            $bom_component_kayu = $item['bom_component_kayu'] ?? '';
            $bom_component_kayu_detail = $item['bom_component_kayu_detail'] ?? '';
            $bom_component_jenis = $item['bom_component_jenis'] ?? '';
            $bom_component_qty = (int)($item['bom_component_qty'] ?? 0);
            $bom_component_panjang = (float)($item['bom_component_panjang'] ?? 0);
            $bom_component_lebar = (float)($item['bom_component_lebar'] ?? 0);
            $bom_component_tebal = (float)($item['bom_component_tebal'] ?? 0);
            $bom_component_description = $item['bom_component_description'] ?? '';
            $bom_component_group = $item['bom_component_group'] ?? '';

            $stmt_insert->bind_param("issssssiddsss", // tipe data disesuaikan (i=integer, d=double/float, s=string)
                $bom_id, 
                $bom_component_number, $bom_component_name, $bom_component_bahan,
                $bom_component_kayu, $bom_component_kayu_detail, $bom_component_jenis,
                $bom_component_qty, $bom_component_panjang, $bom_component_lebar,
                $bom_component_tebal, $bom_component_description, $bom_component_group
            );
            $stmt_insert->execute();
        }
        $stmt_insert->close();
    }

    // 7. Jika semua berhasil, commit transaksi
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Semua perubahan berhasil disimpan.']);

} catch (Exception $e) {
    // 8. Jika ada error, batalkan semua perubahan (rollback)
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    // 9. Tutup koneksi
    if ($conn) $conn->close();
}
?>
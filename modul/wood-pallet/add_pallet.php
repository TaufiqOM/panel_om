<?php
// wood-pallet/add_pallet.php
include '../../inc/config.php'; // Sesuaikan dengan path yang benar

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = intval($_POST['count']);
    $prefix = $_POST['prefix'];
    
    // Validasi input
    if ($count < 1 || $count > 100) {
        echo json_encode(['success' => false, 'message' => 'Jumlah pallet tidak valid']);
        exit;
    }
    
    if (!preg_match('/^[0-9]{4}$/', $prefix)) {
        echo json_encode(['success' => false, 'message' => 'Format prefix tidak valid']);
        exit;
    }
    
    // Dapatkan kode pallet terakhir dengan prefix yang sama
    $sql = "SELECT wood_pallet_code FROM wood_pallet WHERE wood_pallet_code LIKE '$prefix-%' ORDER BY wood_pallet_id DESC LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    $last_number = 0;
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_code = $row['wood_pallet_code'];
        $last_number = intval(substr($last_code, 5)); // Ambil bagian angka setelah prefix-
    }
    
    // Generate kode pallet baru
    $values = [];
    $current_date = date('Y-m-d H:i:s');
    
    for ($i = 1; $i <= $count; $i++) {
        $new_number = $last_number + $i;
        $new_code = $prefix . '-' . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        $values[] = "(NULL, '$new_code', '$current_date')";
    }
    
    // Insert ke database
    $sql = "INSERT INTO wood_pallet (wood_pallet_id, wood_pallet_code, created_at) VALUES " . implode(', ', $values);
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Pallet berhasil ditambahkan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    
    mysqli_close($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid']);
}
?>
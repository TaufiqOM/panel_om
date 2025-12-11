<?php
// session_start();
require __DIR__ . '/../../inc/config_odoo.php';
require __DIR__ . '/../../inc/config.php';

$username = $_SESSION['username'] ?? 'system';

// Ambil data employee dari Odoo
$employees = callOdooRead(
    $username,
    "hr.employee",
    [],
    ["id", "name", "work_email", "work_phone", "barcode", "image_1920"]
);

// Ekstrak ID dari Odoo
$odoo_ids = array_column($employees, 'id');

// Ambil ID employee dari local DB
$result = $conn->query("SELECT id_employee FROM employee");
$local_ids = [];
while ($row = $result->fetch_assoc()) {
    $local_ids[] = $row['id_employee'];
}

// Cari ID yang perlu dihapus (ada di local tapi tidak di Odoo)
$ids_to_delete = array_diff($local_ids, $odoo_ids);

// Hapus employee yang tidak ada di Odoo
if (!empty($ids_to_delete)) {
    $placeholders = str_repeat('?,', count($ids_to_delete) - 1) . '?';
    $stmt_del = $conn->prepare("DELETE FROM employee WHERE id_employee IN ($placeholders)");
    $stmt_del->bind_param(str_repeat('i', count($ids_to_delete)), ...$ids_to_delete);
    if ($stmt_del->execute()) {
        echo "Deleted " . $stmt_del->affected_rows . " employees from local DB.<br>";
    } else {
        echo "Error deleting: " . $stmt_del->error . "<br>";
    }
    $stmt_del->close();
}

// Siapkan query UPSERT untuk insert/update
$stmt = $conn->prepare("
    INSERT INTO employee (id_employee, name, email, phone, barcode, image_1920, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        email = VALUES(email),
        phone = VALUES(phone),
        barcode = VALUES(barcode),
        image_1920 = VALUES(image_1920)
");

foreach ($employees as $emp) {
    $odoo_id = intval($emp['id']);
    $name    = $emp['name'] ?? '';
    $email   = $emp['work_email'] ?? '';
    $phone   = $emp['work_phone'] ?? '';
    $barcode = $emp['barcode'] ?? '';
    $image   = base64_decode($emp['image_1920'] ?? ''); // decode base64 → binary

    // bind parameter
    $null = NULL;
    $stmt->bind_param("issssb", $odoo_id, $name, $email, $phone, $barcode, $null);

    // kirim binary data untuk kolom ke-6 (index 5)
    $stmt->send_long_data(5, $image);

    if ($stmt->execute()) {
        echo "Sync: {$name}<br>";
    } else {
        echo "Error: " . $stmt->error . "<br>";
    }
}

$stmt->close();
$conn->close();

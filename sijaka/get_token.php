<?php
require __DIR__ . '/../inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = trim($_POST['token'] ?? '');
    $station = trim($_POST['station'] ?? '');

    if ($token === '') {
        echo json_encode(["status" => "error", "message" => "Token tidak boleh kosong"]);
        exit;
    }

    // Validasi token dari tabel token_sijaka
    $stmt = $conn->prepare("SELECT tk_code FROM token_sijaka WHERE tk_code = ? AND tk_status = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Token valid
        echo json_encode(["status" => "token_ok"]);
    } else {
        // Token tidak valid
        echo json_encode(["status" => "error", "message" => "Token tidak valid atau tidak aktif"]);
    }

    $stmt->close();
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid request"]);
?>
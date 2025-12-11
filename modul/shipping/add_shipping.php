<?php
require_once __DIR__ . '/../../inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$sheduled_date = $_POST['sheduled_date'] ?? null;
$description = trim($_POST['description'] ?? '');
$ship_to = trim($_POST['ship_to'] ?? '');

if (empty($name) || empty($ship_to)) {
    echo json_encode(['success' => false, 'message' => 'Name and Ship To are required']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO shipping (name, sheduled_date, description, ship_to) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $sheduled_date, $description, $ship_to);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Shipping batch added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add shipping batch']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

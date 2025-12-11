<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/csrf.php';

$csrf_token = $_POST['csrf_token'] ?? '';
if (!CSRF::validateToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT id FROM user_accounts WHERE username = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Email not found']);
    exit;
}

// Reset password to default
$default_password = 'password123';
$hashed = password_hash($default_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE user_accounts SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hashed, $email);
$stmt->execute();
$stmt->close();

// In real app, send email, but for now just return success
echo json_encode(['success' => true, 'message' => 'Password reset to default. Please contact admin for new password.']);
?>

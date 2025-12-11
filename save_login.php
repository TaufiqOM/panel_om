<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/config_odoo.php';
require_once __DIR__ . '/inc/csrf.php';

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!CSRF::validateToken($csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validation
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Username and password are required']);
    exit;
}

if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

// Check if user exists in DB
$stmt_check = $conn->prepare("SELECT uid, password, user_role FROM user_accounts WHERE username = ?");
$stmt_check->bind_param("s", $username);
$stmt_check->execute();
$result = $stmt_check->get_result();
$user = $result->fetch_assoc();
$stmt_check->close();

$uid = false;
$role = 'user';

require_once __DIR__ . '/inc/config_odoo.php';

if ($user) {
    // User exists, verify password
    $password_valid = false;
    if (password_verify($password, $user['password'])) {
        $password_valid = true;
    } else {
        // Try decrypt if encrypted
        $decrypted = decrypt_password($user['password']);
        if ($decrypted == $password) {
            $password_valid = true;
        }
    }

    if ($password_valid) {
        $uid = $user['uid'];
        $role = $user['user_role'];
    } else {
        // Password changed in Odoo? Try Odoo auth
        $connInfo = [
            'url' => 'https://om-omegamas.odoo.com/jsonrpc',
            'db'  => 'om-omegamas-main-17240508'
        ];

        $data = [
            "jsonrpc" => "2.0",
            "method"  => "call",
            "params"  => [
                "service" => "common",
                "method"  => "authenticate",
                "args"    => [$connInfo['db'], $username, $password, []]
            ],
            "id" => 1
        ];

        $ch = curl_init($connInfo['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            echo json_encode(['success' => false, 'error' => curl_error($ch)]);
            exit;
        }
        curl_close($ch);

        $decoded = json_decode($response, true);
        $uid = $decoded['result'] ?? false;

        if ($uid) {
            // Update password encrypted
            $encrypted_password = encrypt_password($password);
            $stmt_update = $conn->prepare("UPDATE user_accounts SET password = ?, uid = ? WHERE username = ?");
            $stmt_update->bind_param("sis", $encrypted_password, $uid, $username);
            $stmt_update->execute();
            $stmt_update->close();
        }
    }
} else {
    // User not in DB, authenticate with Odoo
    $connInfo = [
        'url' => 'https://om-omegamas.odoo.com/jsonrpc',
        'db'  => 'om-omegamas-main-17240508'
    ];

    $data = [
        "jsonrpc" => "2.0",
        "method"  => "call",
        "params"  => [
            "service" => "common",
            "method"  => "authenticate",
            "args"    => [$connInfo['db'], $username, $password, []]
        ],
        "id" => 1
    ];

    $ch = curl_init($connInfo['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        echo json_encode(['success' => false, 'error' => curl_error($ch)]);
        exit;
    }
    curl_close($ch);

    $decoded = json_decode($response, true);
    $uid = $decoded['result'] ?? false;
}

if ($uid) {
    $_SESSION['uid'] = $uid;
    $_SESSION['username'] = $username;

    // Determine role
    $role = (strpos($username, 'admin') !== false) ? 1 : 0;
    $_SESSION['user_role'] = $role;

    // Encrypt password
    $encrypted_password = encrypt_password($password);

    // Check if user exists in DB
    $stmt_check_exists = $conn->prepare("SELECT id FROM user_accounts WHERE username = ?");
    $stmt_check_exists->bind_param("s", $username);
    $stmt_check_exists->execute();
    $exists = $stmt_check_exists->get_result()->num_rows > 0;
    $stmt_check_exists->close();

    if ($exists) {
        // Update existing user
        $stmt = $conn->prepare("UPDATE user_accounts SET uid = ?, password = ?, user_role = ? WHERE username = ?");
        $stmt->bind_param("isss", $uid, $encrypted_password, $role, $username);
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO user_accounts (username, uid, password, user_role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("siss", $username, $uid, $encrypted_password, $role);
    }
    $stmt->execute();
    $stmt->close();

    // Insert login history
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt_history = $conn->prepare("INSERT INTO login_history (username, uid, ip_address, user_agent, success) VALUES (?, ?, ?, ?, 1)");
    $stmt_history->bind_param("siss", $username, $uid, $ip, $user_agent);
    $stmt_history->execute();
    $stmt_history->close();

    echo json_encode(['success' => true]);
} else {
    // Insert failed login history
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt_history = $conn->prepare("INSERT INTO login_history (username, uid, ip_address, user_agent, success) VALUES (?, 0, ?, ?, 0)");
    $stmt_history->bind_param("sss", $username, $ip, $user_agent);
    $stmt_history->execute();
    $stmt_history->close();

    echo json_encode(['success' => false]);
}

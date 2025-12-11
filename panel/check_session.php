<?php
session_start();
require_once 'session_manager.php';

header('Content-Type: application/json');

// Cek timeout session
if (!SessionManager::checkSessionTimeout()) {
    echo json_encode(['logged_in' => false, 'message' => 'Session expired']);
    exit;
}

$sessionCheck = SessionManager::checkEmployeeSession();

if ($sessionCheck['logged_in']) {
    echo json_encode([
        'logged_in' => true,
        'employee' => $sessionCheck['employee']
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>
<?php
session_start();
require_once 'session_manager.php';

SessionManager::destroyEmployeeSession();

echo json_encode(['success' => true, 'message' => 'Logout berhasil']);
?>
<?php
session_start();
require_once '../../inc/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../');
    exit;
}

// Fetch login history
$history = [];
$result = $conn->query("SELECT username, uid, login_time, ip_address, user_agent, success FROM login_history ORDER BY login_time DESC LIMIT 100");
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}
$result->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>Login History</h1>
    <table class="table">
        <thead>
            <tr>
                <th>Username</th>
                <th>UID</th>
                <th>Login Time</th>
                <th>IP Address</th>
                <th>User Agent</th>
                <th>Success</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $entry): ?>
                <tr>
                    <td><?php echo htmlspecialchars($entry['username']); ?></td>
                    <td><?php echo $entry['uid']; ?></td>
                    <td><?php echo $entry['login_time']; ?></td>
                    <td><?php echo htmlspecialchars($entry['ip_address']); ?></td>
                    <td><?php echo htmlspecialchars(substr($entry['user_agent'], 0, 50)); ?>...</td>
                    <td><?php echo $entry['success'] ? 'Yes' : 'No'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

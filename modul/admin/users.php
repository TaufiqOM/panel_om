<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/csrf.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../');
    exit;
}

$csrf_token = CSRF::generateToken();

// Handle form submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!CSRF::validateToken($csrf)) {
        $message = 'Invalid CSRF token';
    } else {
        if (isset($_POST['add_user'])) {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $role = $_POST['user_role'];
            if (!empty($username) && !empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO user_accounts (username, password, user_role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password), user_role = VALUES(user_role)");
                $stmt->bind_param("sss", $username, $hashed, $role);
                $stmt->execute();
                $stmt->close();
                $message = 'User added/updated successfully';
            }
        } elseif (isset($_POST['reset_password'])) {
            $user_id = $_POST['user_id'];
            $new_password = $_POST['new_password'];
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE user_accounts SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $user_id);
                $stmt->execute();
                $stmt->close();
                $message = 'Password reset successfully';
            }
        }
    }
}

// Fetch users
$users = [];
$result = $conn->query("SELECT id, username, user_role, created_at FROM user_accounts ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$result->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>User Management</h1>
    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <h2>Add/Edit User</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="mb-3">
            <label>Username (Email)</label>
            <input type="email" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Role</label>
            <select name="user_role" class="form-control">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" name="add_user" class="btn btn-primary">Add/Update User</button>
    </form>

    <h2 class="mt-5">Existing Users</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['user_role']); ?></td>
                    <td><?php echo $user['created_at']; ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="resetPassword(<?php echo $user['id']; ?>)">Reset Password</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal for reset password -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="reset_password" class="btn btn-primary">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetPassword(userId) {
    document.getElementById('reset_user_id').value = userId;
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}
</script>
</body>
</html>

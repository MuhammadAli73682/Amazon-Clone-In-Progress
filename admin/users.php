<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

// only admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$users = $pdo->query("SELECT id, full_name, email, user_type, shop_name, created_at FROM users ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/backend-header.php'; ?>
    <div class="container my-5">
        <h2 class="mb-4">All Users</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Type</th><th>Shop</th><th>Joined</th></tr>
                </thead>
                <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= ucfirst($u['user_type']) ?></td>
                    <td><?= htmlspecialchars($u['shop_name'] ?? '') ?></td>
                    <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
       <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</body>
</html>
</body>
</html>
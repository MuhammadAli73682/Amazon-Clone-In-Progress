<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$orders = $pdo->query(
    "SELECT o.id AS order_number, o.total_amount, o.created_at, o.status, o.shipping_address, u.full_name AS buyer_name
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     ORDER BY o.id DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Orders - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/backend-header.php'; ?>
    <div class="container my-5">
        <h2 class="mb-4">All Orders</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr><th>Order #</th><th>Buyer</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach($orders as $o): ?>
                <tr>
                    <td>#<?= $o['order_number'] ?></td>
                    <td><?= htmlspecialchars($o['buyer_name']) ?></td>
                    <td>$<?= number_format($o['total_amount'],2) ?></td>
                    <td><?= ucfirst($o['status']) ?></td>
                    <td><?= date('Y-m-d', strtotime($o['created_at'])) ?></td>
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
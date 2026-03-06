<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];

// fetch orders for this seller
$stmt = $pdo->prepare(
    "SELECT o.id AS order_id, o.status, o.created_at, o.shipping_address, u.full_name AS buyer_name,
            oi.product_id, oi.quantity, oi.price, p.name AS product_name
     FROM orders o
     JOIN order_items oi ON o.id = oi.order_id
     JOIN products p ON oi.product_id = p.id
     JOIN users u ON o.user_id = u.id
     WHERE oi.seller_id = ?
     ORDER BY o.created_at DESC"
);
$stmt->execute([$seller_id]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Orders - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/backend-header.php'; ?>
    <div class="container my-5">
        <h2 class="mb-4">Orders for Your Products</h2>
        <?php if(empty($orders)): ?>
            <div class="alert alert-info">No orders have been placed for your items yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Product</th>
                            <th>Buyer</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($orders as $o): ?>
                        <tr>
                            <td>#<?= $o['order_id'] ?></td>
                            <td><?= htmlspecialchars($o['product_name']) ?></td>
                            <td><?= htmlspecialchars($o['buyer_name']) ?></td>
                            <td><?= $o['quantity'] ?></td>
                            <td>$<?= number_format($o['price'] * $o['quantity'], 2) ?></td>
                            <td><?= htmlspecialchars(ucfirst($o['status'])) ?></td>
                            <td><?= date('Y-m-d', strtotime($o['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</body>
</html>
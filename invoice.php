<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

// fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();
if(!$order) {
    echo "Order not found.";
    exit;
}

// fetch items
$stmt2 = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt2->execute([$order_id]);
$items = $stmt2->fetchAll();

$siteName = 'ShopHub';
$logoUrl = 'https://via.placeholder.com/150x50?text=ShopHub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= $order_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin:20px; }
        .invoice-header { text-align:center; margin-bottom:30px; }
        .invoice-header img { max-height:50px; }
        .invoice-details th, .invoice-details td { padding:8px; }
        @media print {
            .no-print { display:none; }
        }
    </style>
</head>
<body>
    <div class="invoice-header">
        <img src="<?= $logoUrl ?>" alt="Logo">
        <h2><?= $siteName ?> Invoice</h2>
    </div>

    <table class="table table-bordered">
        <tr><th>Invoice #</th><td><?= $order_id ?></td></tr>
        <tr><th>Date</th><td><?= date('F j, Y', strtotime($order['created_at'])) ?></td></tr>
        <tr><th>Total</th><td>$<?= number_format($order['total_amount'],2) ?></td></tr>
        <tr><th>Status</th><td><?= ucfirst($order['status']) ?></td></tr>
    </table>

    <h5>Items</h5>
    <table class="table table-striped invoice-details">
        <thead>
            <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
        <?php foreach($items as $it):
            $sub = $it['price'] * $it['quantity'];
        ?>
            <tr>
                <td><?= htmlspecialchars($it['name']) ?></td>
                <td><?= $it['quantity'] ?></td>
                <td>$<?= number_format($it['price'],2) ?></td>
                <td>$<?= number_format($sub,2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h5>Shipping Address</h5>
    <p><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>

    <button class="btn btn-primary no-print" onclick="window.print()">Print</button>
</body>
</html>
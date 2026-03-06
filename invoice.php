<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order_number = $_GET['id'] ?? '';
$user_id = $_SESSION['user_id'];

// fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
$stmt->execute([$order_number, $user_id]);
$order = $stmt->fetch();

// also grab user contact info
$userInfo = ['email'=>'','phone'=>''];
if($order) {
    $u = $pdo->prepare("SELECT email, phone FROM users WHERE id = ?");
    $u->execute([$user_id]);
    $urow = $u->fetch();
    if($urow) {
        $userInfo = $urow;
    }
}
if(!$order) {
    echo "Order not found.";
    exit;
}

// fetch items
$stmt2 = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt2->execute([$order['id']]);
$items = $stmt2->fetchAll();

$siteName = 'ShopHub';
// logo color is orange; we'll render text rather than image
$logoUrl = ''; // not used when rendering styled text
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= $order_number ?> - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f0f0f0; padding:40px; font-family: Arial, sans-serif; color: #333; }
        .invoice-container { background:#fff; padding:40px; max-width:900px; margin:auto; box-shadow:0 0 15px rgba(0,0,0,0.05); }
        .brand-logo { font-size:2.5rem; color:#ff6600; font-weight:bold; }
        .header-flex { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; }
        .header-flex .right { text-align:right; }
        .section-title { font-size:1.1rem; font-weight:600; margin-bottom:10px; }
        .info-table td, .info-table th { padding:4px 8px; }
        .items-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
        .items-table th, .items-table td { border:1px solid #dee2e6; padding:8px; }
        .items-table th { background:#f8f9fa; }
        .totals { max-width:300px; margin-left:auto; }
        .totals tr td { padding:4px 8px; }
        .totals .label { text-align:right; }
        .amount { background:#ff6600; color:#fff; font-weight:600; }
        .notes { margin-top:30px; font-size:0.9rem; color:#555; }
        .no-print { margin-top:20px; }
        @media print { .no-print { display:none; } body { background:#fff; padding:0; } .invoice-container { box-shadow:none; } }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header-flex">
            <div class="brand-logo"><?= htmlspecialchars($siteName) ?></div>
            <div class="right">
                <div><strong>Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?></div>
                <div><strong>Invoice #</strong> <?= $order_number ?></div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="section-title">Supplier</div>
                <table class="info-table">
                    <tr><td>ShopHub Inc.</td></tr>
                    <tr><td>123 E-Commerce Ave</td></tr>
                    <tr><td>City, ZIP</td></tr>
                    <tr><td>Country</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="section-title">Customer</div>
                <table class="info-table">
                    <tr><td><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></td></tr>
                    <?php if(!empty($userInfo['email'])): ?>
                    <tr><td>Email: <?= htmlspecialchars($userInfo['email']) ?></td></tr>
                    <?php endif; ?>
                    <?php if(!empty($order['phone'])): ?>
                    <tr><td>Phone: <?= htmlspecialchars($order['phone']) ?></td></tr>
                    <?php elseif(!empty($userInfo['phone'])): ?>
                    <tr><td>Phone: <?= htmlspecialchars($userInfo['phone']) ?></td></tr>
                    <?php endif; ?>
                    <?php if(!empty($order['alt_phone'])): ?>
                    <tr><td>Alt Phone: <?= htmlspecialchars($order['alt_phone']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr><th>#</th><th>Product</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th></tr>
            </thead>
            <tbody>
            <?php $i=1; foreach($items as $it):
                $sub = $it['price'] * $it['quantity'];
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td class="text-center"><?= $it['quantity'] ?></td>
                    <td class="text-end">$<?= number_format($it['price'],2) ?></td>
                    <td class="text-end">$<?= number_format($sub,2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if(!empty($order['shipping_address'])): ?>
        <div class="notes">
            <strong>Shipping Address:</strong><br>
            <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
        </div>
        <?php endif; ?>

        <?php if(!empty($order['rider_instructions'])): ?>
        <div class="notes">
            <strong>Rider Instructions:</strong><br>
            <?= nl2br(htmlspecialchars($order['rider_instructions'])) ?>
        </div>
        <?php endif; ?>

        <table class="totals">
            <tr><td class="label">Subtotal:</td><td class="text-end">$<?= number_format($order['total_amount'],2) ?></td></tr>
            <tr><td class="label">Shipping:</td><td class="text-end">$5.00</td></tr>
            <tr><td class="label amount">Total:</td><td class="text-end amount">$<?= number_format($order['total_amount']+5,2) ?></td></tr>
        </table>

        <div class="notes">
            <strong>Payment Details:</strong><br>
            Cash on Delivery<br>
            <!-- more info can be added here -->
        </div>

        <div class="notes">
            <strong>Notes:</strong><br>
            Thank you for shopping with ShopHub!
        </div>
    </div><!-- end invoice-container -->

    <button class="btn btn-primary no-print" onclick="window.print()">Print</button>
</body>
</html>
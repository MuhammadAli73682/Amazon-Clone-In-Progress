<?php
session_start();
require_once 'config/database.php';

$order = null;
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_number = trim($_POST['order_number']);
    if($order_number === '' || !ctype_digit($order_number)) {
        $error = 'Please enter a valid order number.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch();

        if(!$order) {
            $error = 'No order found with that number.';
        } else {
            // if user is logged in, ensure they own it (or are admin)
            if(isset($_SESSION['user_id']) && $_SESSION['user_type'] !== 'admin' && $_SESSION['user_id'] != $order['user_id']) {
                $error = 'You are not authorized to view this order.';
                $order = null;
            } else {
                // fetch items for display
                $stmt2 = $pdo->prepare("SELECT oi.*, p.name, u.full_name AS seller_name FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN users u ON p.seller_id = u.id WHERE oi.order_id = ?");
                $stmt2->execute([$order['id']]);
                $order['items'] = $stmt2->fetchAll();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-5">
        <h2 class="mb-4">Track Your Order</h2>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="mb-4">
            <div class="mb-3">
                <label for="order_number" class="form-label">Order Number</label>
                <input type="text" class="form-control" id="order_number" name="order_number" required
                       value="<?= isset($_POST['order_number']) ? htmlspecialchars($_POST['order_number']) : '' ?>">
            </div>
            <button type="submit" class="btn btn-primary">Check Status</button>
        </form>

        <?php if($order): ?>
            <div class="order-card">
                <h5>Order #<?= $order['order_number'] ?></h5>
                <p class="text-muted">Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                <p><strong>Status:</strong>
                    <?php
                        switch($order['status']) {
                            case 'delivered': $badge = 'success'; break;
                            case 'cancelled': $badge = 'danger'; break;
                            case 'shipped':   $badge = 'primary'; break;
                            case 'processing':$badge = 'info'; break;
                            default:          $badge = 'warning';
                        }
                    ?>
                    <span class="status-badge bg-<?= $badge ?> text-white">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </p>
                <p><strong>Total:</strong> $<?= number_format($order['total_amount'], 2) ?></p>
                <?php if(!empty($order['items'])): ?>
                    <p><strong>Items:</strong></p>
                    <ul>
                    <?php foreach($order['items'] as $it): ?>
                        <li>
                            <?= htmlspecialchars($it['name']) ?> x<?= $it['quantity'] ?>
                            <?php if(!empty($it['seller_name'])): ?>(seller: <?= htmlspecialchars($it['seller_name']) ?>)<?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p><strong>Shipping Address:</strong><br><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
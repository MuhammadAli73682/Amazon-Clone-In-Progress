<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// allow user to cancel their own orders if they are not yet delivered/cancelled
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $order_id = intval($_POST['order_id']);
    if($_POST['action'] === 'cancel') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
        // Get order number for message
        $on_stmt = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
        $on_stmt->execute([$order_id]);
        $on = $on_stmt->fetch();
        $order_num = $on ? $on['order_number'] : $order_id;
        $cancel_message = "Order #$order_num has been cancelled.";
    }
}

// Get orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// attach items for each order
foreach($orders as &$order) {
    $stmt2 = $pdo->prepare("SELECT oi.*, p.name, u.full_name AS seller_name FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN users u ON p.seller_id = u.id WHERE oi.order_id = ?");
    $stmt2->execute([$order['id']]);
    $order['items'] = $stmt2->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <h2 class="mb-4">My Orders</h2>
        
        <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Order placed successfully!
            <?php if(isset($_GET['order_id'])): ?>
                Your order number is <strong>#<?= htmlspecialchars($_GET['order_id']) ?></strong>.
                &nbsp; <a href="invoice.php?id=<?= urlencode($_GET['order_id']) ?>" class="btn btn-sm btn-primary" target="_blank">Print Invoice</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if(!empty($cancel_message)): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($cancel_message) ?></div>
        <?php endif; ?>
        
        <?php if(empty($orders)): ?>
        <div class="alert alert-info">No orders yet. <a href="products.php">Start shopping</a></div>
        <?php else: ?>
        
        <?php foreach($orders as $order): ?>
        <div class="order-card">
            <div class="row">
                <div class="col-md-8">
                    <h5>Order #<?= $order['order_number'] ?></h5>
                    <p class="text-muted">Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
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
                    <p><strong>Shipping Address:</strong> <?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
                    <?php if(!empty($order['phone'])): ?>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                    <?php endif; ?>
                    <?php if(!empty($order['alt_phone'])): ?>
                        <p><strong>Alt Phone:</strong> <?= htmlspecialchars($order['alt_phone']) ?></p>
                    <?php endif; ?>
                    <?php if(!empty($order['rider_instructions'])): ?>
                        <p><strong>Rider Instructions:</strong> <?= nl2br(htmlspecialchars($order['rider_instructions'])) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
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
                    <?php if(in_array($order['status'], ['pending','processing','shipped'])): ?>
                        <form method="post" class="mt-2 d-inline-block">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" name="action" value="cancel" class="btn btn-sm btn-danger">Cancel Order</button>
                        </form>
                    <?php endif; ?>
                    <a href="track-order.php" class="btn btn-sm btn-secondary mt-2">Track</a>
                    <a href="invoice.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary mt-2" target="_blank">Invoice</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];

// handle status update requests from seller
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $action = $_POST['action'];
    // map our simple actions to real status values
    if($action === 'complete') {
        $new_status = 'delivered';
    } elseif($action === 'cancel') {
        $new_status = 'cancelled';
    }
    if(isset($new_status)) {
        // ensure the seller actually has an item in this order before updating
        $stmt = $pdo->prepare(
            "UPDATE orders o 
             JOIN order_items oi ON o.id = oi.order_id 
             SET o.status = ? 
             WHERE o.id = ? AND oi.seller_id = ?"
        );
        $stmt->execute([$new_status, $order_id, $seller_id]);
        // if the seller cancelled the order, return stock for the items they sold
        if($new_status === 'cancelled') {
            $restock = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ? AND seller_id = ?");
            $restock->execute([$order_id, $seller_id]);
            $items = $restock->fetchAll();
            foreach($items as $item) {
                $upd = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $upd->execute([$item['quantity'], $item['product_id']]);
            }
        }
        $update_message = "Order #$order_id updated to " . ucfirst($new_status);
        // redirect so refreshing the page doesn't resend the form
        header('Location: dashboard.php?msg=' . urlencode($update_message));
        exit;
    }
}

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE seller_id = ?");
$stmt->execute([$seller_id]);
$product_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ?");
$stmt->execute([$seller_id]);
$order_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT SUM(price * quantity) as revenue FROM order_items WHERE seller_id = ?");
$stmt->execute([$seller_id]);
$revenue = $stmt->fetch()['revenue'] ?? 0;

// Get recent orders
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, o.created_at, o.status, u.full_name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id 
    JOIN users u ON o.user_id = u.id 
    WHERE oi.seller_id = ? 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$stmt->execute([$seller_id]);
$recent_orders = $stmt->fetchAll();

// Get return requests addressed to this seller
$stmt = $pdo->prepare("SELECT r.*, p.name AS product_name FROM return_requests r LEFT JOIN products p ON r.product_id = p.id WHERE r.seller_id = ? ORDER BY r.created_at DESC LIMIT 10");
$stmt->execute([$seller_id]);
$return_requests = $stmt->fetchAll();
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container my-5">
        <h2 class="mb-4">Seller Dashboard</h2>
        <?php if(!empty($update_message) || !empty($_GET['msg'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($update_message ?? $_GET['msg']) ?></div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-box"></i>
                    <h3><?= $product_count ?></h3>
                    <p>Total Products</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-shopping-cart"></i>
                    <h3><?= $order_count ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>$<?= number_format($revenue, 2) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-12">
                <a href="products.php" class="btn btn-warning me-2">
                    <i class="fas fa-box"></i> Manage Products
                </a>
                <a href="add-product.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Product
                </a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0">Recent Orders</h4>
            </div>
            <div class="card-body">
                <?php if(empty($recent_orders)): ?>
                <p class="text-muted">No orders yet</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                $seen_orders = [];
                                foreach($recent_orders as $order): 
                                    $show_actions = !in_array($order['order_id'], $seen_orders);
                            ?>
                            <tr>
                                <td>#<?= $order['order_id'] ?></td>
                                <td><?= htmlspecialchars($order['name']) ?></td>
                                <td><?= htmlspecialchars($order['full_name']) ?></td>
                                <td><?= $order['quantity'] ?></td>
                                <td>$<?= number_format($order['price'] * $order['quantity'], 2) ?></td>
                                <td><?php
                                            // badge colour based on status
                                            switch($order['status']) {
                                                case 'delivered': $badge = 'success'; break;
                                                case 'cancelled': $badge = 'danger'; break;
                                                case 'shipped':   $badge = 'primary'; break;
                                                case 'processing':$badge = 'info'; break;
                                                default:          $badge = 'warning';
                                            }
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= ucfirst($order['status']) ?></span>
                                    </td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                <td>
                                        <?php if($show_actions && in_array($order['status'], ['pending','processing','shipped'])): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <button type="submit" name="action" value="complete" class="btn btn-sm btn-success">Complete</button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <button type="submit" name="action" value="cancel" class="btn btn-sm btn-danger">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php $seen_orders[] = $order['order_id']; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Return requests section -->
        <div class="card mt-5">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0">Return Requests</h4>
            </div>
            <div class="card-body">
                <?php if(empty($return_requests)): ?>
                    <p class="text-muted">No return requests for your products.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Order #</th>
                                    <th>Product</th>
                                    <th>Reason</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($return_requests as $req): ?>
                                    <tr>
                                        <td><?= $req['id'] ?></td>
                                        <td><?= htmlspecialchars($req['order_number']) ?></td>
                                        <td><?= htmlspecialchars($req['product_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($req['reason']) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($req['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

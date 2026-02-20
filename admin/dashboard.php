<?php
session_start();
require_once '../config/database.php';

// only accessible to admins
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// handle order status update from admin
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'] ?? '';
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if(in_array($new_status, $allowed)) {
        $upd = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $upd->execute([$new_status, $order_id]);
        $update_message = "Order #$order_id status updated to '" . htmlspecialchars($new_status) . "'.";
    } else {
        $update_message = 'Invalid status selected.';
    }
}
// gather statistics
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM products WHERE status = 'active'");
$live_products = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE user_type = 'buyer'");
$buyers_count = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE user_type = 'seller'");
$sellers_count = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders");
$total_orders = $stmt->fetch()['cnt'];

// fetch full lists for tabs
$all_products = $pdo->query("SELECT id, name, price, stock, status FROM products ORDER BY id DESC")->fetchAll();
$all_buyers = $pdo->query("SELECT id, full_name, email, created_at FROM users WHERE user_type='buyer' ORDER BY id DESC")->fetchAll();
$all_sellers = $pdo->query("SELECT id, full_name, email, shop_name, created_at FROM users WHERE user_type='seller' ORDER BY id DESC")->fetchAll();
// since the orders table has no `order_number` column we use the id as the number
$all_orders = $pdo->query("SELECT o.id AS order_number, o.total_amount, o.created_at, o.status, o.user_id, u.full_name AS buyer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.id DESC")->fetchAll();

// recent contact messages and returns
$stmt = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 10");
$contacts = $stmt->fetchAll();

// return requests along with seller name
$stmt = $pdo->query("SELECT r.*, u.full_name AS seller_name FROM return_requests r LEFT JOIN users u ON r.seller_id = u.id ORDER BY r.created_at DESC LIMIT 10");
$return_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container my-5">
        <?php if(!empty($update_message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($update_message) ?></div>
        <?php endif; ?>
        <h2 class="mb-4">Admin Dashboard</h2>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="fas fa-box"></i>
                    <h3><?= $live_products ?></h3>
                    <p>Live Products</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="fas fa-users"></i>
                    <h3><?= $buyers_count ?></h3>
                    <p>Buyers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="fas fa-store"></i>
                    <h3><?= $sellers_count ?></h3>
                    <p>Sellers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="fas fa-shopping-cart"></i>
                    <h3><?= $total_orders ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
        </div>

        <!-- tabs for data lists -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">Products</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="buyers-tab" data-bs-toggle="tab" data-bs-target="#buyers" type="button" role="tab">Buyers</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sellers-tab" data-bs-toggle="tab" data-bs-target="#sellers" type="button" role="tab">Sellers</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">Orders</button>
            </li>
        </ul>
        <div class="tab-content mb-5" id="adminTabsContent">
            <div class="tab-pane fade show active" id="products" role="tabpanel">
                <?php if(empty($all_products)): ?>
                    <p>No products available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach($all_products as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= number_format($p['price'],2) ?></td>
                                <td><?= $p['stock'] ?></td>
                                <td><?= htmlspecialchars($p['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="tab-pane fade" id="buyers" role="tabpanel">
                <?php if(empty($all_buyers)): ?>
                    <p>No buyers registered.</p>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php foreach($all_buyers as $b): ?>
                            <tr>
                                <td><?= $b['id'] ?></td>
                                <td><?= htmlspecialchars($b['full_name']) ?></td>
                                <td><?= htmlspecialchars($b['email']) ?></td>
                                <td><?= date('Y-m-d', strtotime($b['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="tab-pane fade" id="sellers" role="tabpanel">
                <?php if(empty($all_sellers)): ?>
                    <p>No sellers registered.</p>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Shop</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php foreach($all_sellers as $s): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td><?= htmlspecialchars($s['shop_name']) ?></td>
                                <td><?= date('Y-m-d', strtotime($s['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="tab-pane fade" id="orders" role="tabpanel">
                <?php if(empty($all_orders)): ?>
                    <p>No orders placed yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-striped">
                            <thead><tr><th>ID</th><th>Order #</th><th>Buyer</th><th>Total</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach($all_orders as $o): ?>
                                <tr>
                                    <td><?= $o['order_number'] ?></td>
                                    <td><?= htmlspecialchars($o['order_number']) ?></td>
                                    <td><?= htmlspecialchars($o['buyer_name'] ?? 'N/A') ?></td>
                                    <td><?= number_format($o['total_amount'],2) ?></td>
                                    <td><?= date('Y-m-d', strtotime($o['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($o['status']) ?></td>
                                    <td>
                                        <form method="post" class="d-flex align-items-center">
                                            <input type="hidden" name="order_id" value="<?= $o['order_number'] ?>">
                                            <select name="status" class="form-select form-select-sm me-2">
                                                <?php foreach(['pending','processing','shipped','delivered','cancelled'] as $st): ?>
                                                    <option value="<?= $st ?>" <?= $o['status'] == $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="update_order_status" value="1" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Recent contact messages -->
        <div class="mb-5">
            <h4>Recent Contact Messages</h4>
            <?php if(empty($contacts)): ?>
                <p>No messages yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>Name</th><th>Email</th><th>Message</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach($contacts as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['email']) ?></td>
                            <td><?= htmlspecialchars($c['message']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($c['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <!-- Recent return requests -->
        <div class="mb-5">
            <h4>Recent Return Requests</h4>
            <?php if(empty($return_requests)): ?>
                <p>No return requests yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>Order #</th><th>Product</th><th>Seller</th><th></th>Reason</th><th>Image</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach($return_requests as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['order_number']) ?></td>
                            <td><?= htmlspecialchars($r['product_name']) ?></td>
                            <td><?= htmlspecialchars($r['seller_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($r['reason']) ?></td>
                            <td><?php if($r['image']): ?><a href="<?= BASE_URL ?>/<?= htmlspecialchars($r['image']) ?>" target="_blank">View</a><?php endif; ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
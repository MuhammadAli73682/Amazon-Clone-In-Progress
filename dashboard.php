<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];
$low_stock_threshold = 5;

// handle status update requests from seller
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_fail();

    // update order status
    if(isset($_POST['order_id']) && isset($_POST['status'])) {
        $order_id = intval($_POST['order_id']);
        $new_status = $_POST['status'];
        // allowed statuses the seller can set (mirror admin options)
        $allowed_statuses = ['pending','processing','shipped','delivered','cancelled'];
        if(in_array($new_status, $allowed_statuses)) {
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
    // handle return request decisions
    if(isset($_POST['return_id'], $_POST['return_action'])) {
        $return_id = intval($_POST['return_id']);
        $action = $_POST['return_action']; // accept or decline
        $allowed = ['accepted','declined'];
        if(in_array($action, $allowed)) {
            // allow legacy rows where return_requests.seller_id is NULL but product_id points to this seller's product
            $upd = $pdo->prepare("
                UPDATE return_requests r
                LEFT JOIN products p ON p.id = r.product_id
                SET r.status = ?
                WHERE r.id = ?
                  AND (r.seller_id = ? OR (r.seller_id IS NULL AND p.seller_id = ?))
            ");
            $upd->execute([$action, $return_id, $seller_id, $seller_id]);
            $update_message = "Return request #$return_id " . ($action === 'accepted' ? 'accepted' : 'declined');
            header('Location: dashboard.php?msg=' . urlencode($update_message));
            exit;
        }
    }
}

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE seller_id = ?");
$stmt->execute([$seller_id]);
$product_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ?");
$stmt->execute([$seller_id]);
$order_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count,
        COALESCE(SUM(CASE WHEN stock > 0 AND stock <= ? THEN 1 ELSE 0 END), 0) AS low_stock_count,
        COALESCE(SUM(CASE WHEN stock > ? THEN 1 ELSE 0 END), 0) AS healthy_stock_count
    FROM products
    WHERE seller_id = ?
");
$stmt->execute([$low_stock_threshold, $low_stock_threshold, $seller_id]);
$inventory_summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'out_of_stock_count' => 0,
    'low_stock_count' => 0,
    'healthy_stock_count' => 0,
];

$stmt = $pdo->prepare("
    SELECT id, name, stock, status, price, currency
    FROM products
    WHERE seller_id = ? AND stock <= ?
    ORDER BY stock ASC, created_at DESC
    LIMIT 8
");
$stmt->execute([$seller_id, $low_stock_threshold]);
$low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue overview (exclude cancelled orders)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.price * oi.quantity), 0) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.seller_id = ? AND o.status != 'cancelled'
");
$stmt->execute([$seller_id]);
$revenue_total = (float)($stmt->fetch()['revenue'] ?? 0);

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.price * oi.quantity), 0) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.seller_id = ? AND o.status != 'cancelled' AND o.created_at >= ?
");
$todayStart = date('Y-m-d 00:00:00');
$stmt->execute([$seller_id, $todayStart]);
$revenue_today = (float)($stmt->fetch()['revenue'] ?? 0);

$d7 = date('Y-m-d H:i:s', strtotime('-7 days'));
$stmt->execute([$seller_id, $d7]);
$revenue_7d = (float)($stmt->fetch()['revenue'] ?? 0);

$d30 = date('Y-m-d H:i:s', strtotime('-30 days'));
$stmt->execute([$seller_id, $d30]);
$revenue_30d = (float)($stmt->fetch()['revenue'] ?? 0);

// Order notifications
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN x.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_orders,
        COALESCE(SUM(CASE WHEN x.status = 'processing' THEN 1 ELSE 0 END), 0) AS processing_orders,
        COALESCE(SUM(CASE WHEN x.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END), 0) AS new_24h
    FROM (
        SELECT DISTINCT o.id, o.status, o.created_at
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        WHERE oi.seller_id = ?
    ) x
");
$stmt->execute([$seller_id]);
$notif = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['pending_orders' => 0, 'processing_orders' => 0, 'new_24h' => 0];

// Sales analytics (last 14 days)
$stmt = $pdo->prepare("
    SELECT DATE(o.created_at) AS day, COALESCE(SUM(oi.price * oi.quantity), 0) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.seller_id = ? AND o.status != 'cancelled' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(o.created_at)
    ORDER BY day ASC
");
$stmt->execute([$seller_id]);
$salesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$salesMap = [];
foreach ($salesRows as $r) {
    $salesMap[$r['day']] = (float)$r['revenue'];
}
$salesLabels = [];
$salesValues = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $salesLabels[] = date('M j', strtotime($d));
    $salesValues[] = $salesMap[$d] ?? 0;
}

// Top products (last 30 days)
$stmt = $pdo->prepare("
    SELECT p.name, COALESCE(SUM(oi.quantity), 0) AS units, COALESCE(SUM(oi.price * oi.quantity), 0) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE oi.seller_id = ? AND o.status != 'cancelled' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY oi.product_id
    ORDER BY revenue DESC
    LIMIT 5
");
$stmt->execute([$seller_id]);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent orders
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, o.created_at, o.status, o.shipping_address, u.full_name 
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
// Include legacy rows where r.seller_id is NULL but product_id belongs to this seller.
$stmt = $pdo->prepare("
    SELECT
        r.*,
        COALESCE(p.name, r.product_name) AS product_name
    FROM return_requests r
    LEFT JOIN products p ON r.product_id = p.id
    WHERE (r.seller_id = ? OR (r.seller_id IS NULL AND p.seller_id = ?))
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->execute([$seller_id, $seller_id]);
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
    <?php include '../includes/backend-header.php'; ?>
    
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
                    <h3>$<?= number_format($revenue_total, 2) ?></h3>
                    <p>Total Earnings</p>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-triangle-exclamation"></i>
                    <h3><?= (int)($inventory_summary['low_stock_count'] ?? 0) ?></h3>
                    <p>Low Stock Items</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-box-open"></i>
                    <h3><?= (int)($inventory_summary['out_of_stock_count'] ?? 0) ?></h3>
                    <p>Out Of Stock</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-shield-heart"></i>
                    <h3><?= (int)($inventory_summary['healthy_stock_count'] ?? 0) ?></h3>
                    <p>Healthy Inventory</p>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-bell"></i>
                    <h3><?= (int)$notif['pending_orders'] ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-clock"></i>
                    <h3><?= (int)$notif['processing_orders'] ?></h3>
                    <p>Processing Orders</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <i class="fas fa-bolt"></i>
                    <h3><?= (int)$notif['new_24h'] ?></h3>
                    <p>New (24h)</p>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Inventory Health</h4>
                <span class="small text-white-50">Low stock threshold: <?= (int)$low_stock_threshold ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_products)): ?>
                    <p class="text-muted mb-0">Great news, all products are above the low-stock threshold.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_products as $lp): ?>
                                    <?php
                                        $stockQty = (int)$lp['stock'];
                                        $stockBadge = $stockQty <= 0 ? 'danger' : 'warning';
                                        $stockLabel = $stockQty <= 0 ? 'Out of stock' : 'Low stock';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($lp['name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $stockBadge ?>">
                                                <?= $stockQty ?> • <?= $stockLabel ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= ($lp['status'] ?? 'inactive') === 'active' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst($lp['status'] ?? 'inactive') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($lp['currency'] ?? 'USD') ?> <?= number_format((float)$lp['price'], 2) ?></td>
                                        <td>
                                            <a href="edit-product.php?id=<?= (int)$lp['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                Restock / Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Earnings Overview</h4>
                <span class="small text-white-50">Excludes cancelled orders</span>
            </div>
            <div class="card-body">
                <div class="row text-center g-3">
                    <div class="col-md-4">
                        <div class="p-3 border rounded">
                            <div class="text-muted">Today</div>
                            <div class="fs-4 fw-bold">$<?= number_format($revenue_today, 2) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded">
                            <div class="text-muted">Last 7 Days</div>
                            <div class="fs-4 fw-bold">$<?= number_format($revenue_7d, 2) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded">
                            <div class="text-muted">Last 30 Days</div>
                            <div class="fs-4 fw-bold">$<?= number_format($revenue_30d, 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4 g-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0">Sales Analytics (14 Days)</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="110"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0">Top Products (30 Days)</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_products)): ?>
                            <p class="text-muted mb-0">No sales yet.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($top_products as $tp): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="me-2">
                                            <div class="fw-semibold"><?= htmlspecialchars($tp['name']) ?></div>
                                            <div class="small text-muted"><?= (int)$tp['units'] ?> units</div>
                                        </div>
                                        <span class="badge bg-success">$<?= number_format((float)$tp['revenue'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-12">
                <a href="products.php" class="btn btn-warning me-2">
                    <i class="fas fa-box"></i> Manage Products
                </a>
                <a href="import-products.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-file-import"></i> Bulk Import (CSV)
                </a>
                <a href="export_products.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-file-export"></i> Export (CSV)
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
                                <th>Ship Address</th>
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
                                <td><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></td>
                                <td>
                                        <?php if($show_actions): ?>
                                        <form method="post" class="d-flex align-items-center">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <select name="status" class="form-select form-select-sm me-2">
                                                <?php foreach(['pending','processing','shipped','delivered','cancelled'] as $st): ?>
                                                    <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Update</button>
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
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($return_requests as $req): ?>
                                    <tr>
                                        <td><?= $req['id'] ?></td>
                                        <td><?= htmlspecialchars($req['order_number']) ?></td>
                                        <td><?= htmlspecialchars($req['product_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($req['reason']) ?></td>
                                        <td><?= ucfirst($req['status'] ?? 'pending') ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($req['created_at'])) ?></td>
                                        <td>
                                            <?php if(($req['status'] ?? 'pending') === 'pending'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                    <input type="hidden" name="return_id" value="<?= $req['id'] ?>">
                                                    <button type="submit" name="return_action" value="accepted" class="btn btn-sm btn-success">Accept</button>
                                                </form>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                    <input type="hidden" name="return_id" value="<?= $req['id'] ?>">
                                                    <button type="submit" name="return_action" value="declined" class="btn btn-sm btn-danger">Decline</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const el = document.getElementById('salesChart');
            if (!el || !window.Chart) return;

            const labels = <?= json_encode($salesLabels) ?>;
            const values = <?= json_encode($salesValues) ?>;

            new Chart(el, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: values,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.15)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: true } },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        })();
    </script>
</body>
</html>

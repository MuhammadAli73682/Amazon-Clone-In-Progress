<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    json_error('Authentication required', 401);
}
$user_id = $_SESSION['user_id'];

action:
$action = $_REQUEST['action'] ?? '';

// GET: list orders
if($_SERVER['REQUEST_METHOD'] === 'GET') {
    if($action == 'list') {
        $stmt = $pdo->prepare("SELECT o.*, u.full_name as buyer_name FROM orders o JOIN users u ON u.id=o.user_id WHERE o.user_id = ? ORDER BY o.created_at DESC");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll();
        echo json_encode(['orders' => $orders]);
        exit;
    }
    json_error('Invalid parameters', 400);
}

// ensure orders table has contact columns for API as well
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS alt_phone VARCHAR(20) NULL");
    $pdo->exec("ALTER TABLE orders ADD UNIQUE INDEX IF NOT EXISTS idx_orders_phone (phone)");
} catch(Exception $e) {}

// POST actions require CSRF
if(!csrf_validate($_POST['csrf_token'] ?? '')) {
    json_error('Invalid CSRF token',403);
}

if($action == 'create') {
    // build order from cart
    $stmt = $pdo->prepare("SELECT c.*, p.price FROM cart c JOIN products p ON p.id=c.product_id WHERE c.user_id = ?");
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll();
    if(!$items) {
        json_error('Cart is empty');
    }

    $total = 0;
    foreach($items as $i) {
        $total += $i['price'] * $i['quantity'];
    }

    // create order
    $rider = trim($_POST['rider_instructions'] ?? '');
    // validate phone inputs
    $phone = trim($_POST['phone'] ?? '');
    $alt = trim($_POST['alt_phone'] ?? '');
    if(!$phone) {
        json_error('Phone number is required');
    }
    if($alt && $alt === $phone) {
        json_error('Alternate number cannot match primary phone');
    }
    // enforce uniqueness across orders as well
    try {
        $dup = $pdo->prepare("SELECT id FROM orders WHERE phone = ?");
        $dup->execute([$phone]);
        if($dup->fetch()) {
            json_error('Primary phone number already used in another order');
        }

        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address, phone, alt_phone, rider_instructions) VALUES (?, ?, 'pending', ?, ?, ?, ?)");
        $stmt->execute([$user_id, $total, $_POST['shipping_address'] ?? '', $phone, $alt ?: null, $rider]);
    } catch(Exception $e) {
        json_error('Failed to create order: ' . $e->getMessage(), 500);
    }
    $orderId = $pdo->lastInsertId();

    // move items to order_items, decrement product stock
    foreach($items as $i) {
        $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, seller_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([$orderId, $i['product_id'], $i['quantity'], $i['price'], $i['seller_id']]);
        $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$i['quantity'], $i['product_id']]);
    }

    // clear cart
    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

    // notify admin by email (best-effort)
    try {
        $adminEmail = 'aliabid78555@gmail.com';
        $sub = "New Order #$orderId";
        $msg = "Order #$orderId was created by user ID $user_id.\nTotal: $" . number_format($total,2) . "\n" .
               "Phone: $phone\n" .
               "Shipping Address: " . ($_POST['shipping_address'] ?? '') . "\n";
        @mail($adminEmail, $sub, $msg);
    } catch(Exception $ex) {}
    echo json_encode(['success' => true, 'order_id' => $orderId]);
    exit;
}

json_error('Unknown action', 400);

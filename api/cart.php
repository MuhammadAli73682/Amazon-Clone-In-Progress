<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// helper to compute count from session cart
function sessionCartCount() {
    $count = 0;
    if(!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach($_SESSION['cart'] as $qty) {
            $count += intval($qty);
        }
    }
    return $count;
}

$user_id = $_SESSION['user_id'] ?? null;

// GET request - get cart count
if($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    
    if($action == 'count') {
        if($user_id) {
            $stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            echo json_encode(['count' => $result['count'] ?? 0]);
        } else {
            echo json_encode(['count' => sessionCartCount()]);
        }
    }
    exit;
}

// POST request
$action = $_POST['action'] ?? '';

if($action == 'add') {
    $product_id = $_POST['product_id'];

    if(!$user_id) {
        // guest: store cart items in session
        if(!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + 1;
    } else {
        // logged-in user: use database
        // Check if already in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch();
        
        if($existing) {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?");
            $stmt->execute([$existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $stmt->execute([$user_id, $product_id]);
        }
    }

    echo json_encode(['success' => true]);
}
elseif($action == 'update') {
    $cart_id = $_POST['cart_id'] ?? null;
    $product_id = $_POST['product_id'] ?? null;
    $quantity = max(1, intval($_POST['quantity']));
    
    if($user_id && $cart_id) {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$quantity, $cart_id, $user_id]);
    } elseif(!$user_id && $product_id) {
        if(isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] = $quantity;
        }
    }
    
    echo json_encode(['success' => true]);
}
elseif($action == 'remove') {
    $cart_id = $_POST['cart_id'] ?? null;
    $product_id = $_POST['product_id'] ?? null;
    
    if($user_id && $cart_id) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
    } elseif(!$user_id && $product_id) {
        if(isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    
    echo json_encode(['success' => true]);
}
?>

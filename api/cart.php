<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

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

// GET request - fetch cart info
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
    } elseif($action == 'list') {
        $items = [];
        if($user_id) {
            $stmt = $pdo->prepare("
                SELECT c.id, c.product_id, c.quantity,
                       p.name, p.price, p.image, p.stock,
                       u.shop_name
                FROM cart c
                JOIN products p ON p.id = c.product_id
                JOIN users u ON u.id = p.seller_id
                WHERE c.user_id = ?");
            $stmt->execute([$user_id]);
            $items = $stmt->fetchAll();
        } else {
            foreach($_SESSION['cart'] ?? [] as $pid => $qty) {
                $pstmt = $pdo->prepare("SELECT p.*, u.shop_name FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id = ?");
                $pstmt->execute([$pid]);
                if($p = $pstmt->fetch()) {
                    $items[] = [
                        'id' => null,
                        'product_id' => $p['id'],
                        'quantity' => $qty,
                        'name' => $p['name'],
                        'price' => $p['price'],
                        'image' => $p['image'],
                        'stock' => $p['stock'],
                        'shop_name' => $p['shop_name'],
                    ];
                }
            }
        }
        echo json_encode(['items' => $items]);
    } elseif($action == 'clear') {
        if($user_id) {
            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);
        } else {
            unset($_SESSION['cart']);
        }
        echo json_encode(['success' => true]);
    }
    exit;
}

// POST request
$action = $_POST['action'] ?? '';
if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    json_error('Invalid CSRF token', 403);
}

if($action == 'add') {
    $product_id = intval($_POST['product_id'] ?? 0);
    if($product_id <= 0) {
        json_error('Invalid product');
    }

    $stockStmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stockStmt->execute([$product_id]);
    $product = $stockStmt->fetch();
    if(!$product || intval($product['stock']) <= 0) {
        json_error('Product is out of stock');
    }

    if(!$user_id) {
        // guest: store cart items in session
        if(!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $newQty = intval($_SESSION['cart'][$product_id] ?? 0) + 1;
        $_SESSION['cart'][$product_id] = min($newQty, intval($product['stock']));
    } else {
        // logged-in user: use database
        // Check if already in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch();
        
        if($existing) {
            if(intval($existing['quantity']) >= intval($product['stock'])) {
                json_error('Maximum stock reached');
            }
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
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    
    if($user_id && $cart_id) {
        $stockStmt = $pdo->prepare("SELECT p.stock FROM cart c JOIN products p ON p.id = c.product_id WHERE c.id = ? AND c.user_id = ?");
        $stockStmt->execute([$cart_id, $user_id]);
        $stockRow = $stockStmt->fetch();
        if(!$stockRow) {
            json_error('Cart item not found', 404);
        }
        $quantity = min($quantity, max(1, intval($stockRow['stock'])));

        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$quantity, $cart_id, $user_id]);
    } elseif(!$user_id && $product_id) {
        $product_id = intval($product_id);
        $stockStmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stockStmt->execute([$product_id]);
        $stockRow = $stockStmt->fetch();
        if(isset($_SESSION['cart'][$product_id]) && $stockRow) {
            $quantity = min($quantity, max(1, intval($stockRow['stock'])));
            $_SESSION['cart'][$product_id] = $quantity;
        }
    }
    
    echo json_encode(['success' => true]);
}
elseif($action == 'remove') {
    $cart_id = $_POST['cart_id'] ?? null;
    $product_id = $_POST['product_id'] ?? null;
    
    if($user_id) {
        if($cart_id) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, $user_id]);
        } elseif($product_id) {
            // fallback: delete based on product id if cart row id not provided
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, intval($product_id)]);
        }
    } elseif(!$user_id && $product_id) {
        if(isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    
    echo json_encode(['success' => true]);
}
?>

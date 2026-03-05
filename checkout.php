<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// allow both logged-in users and guests
$user_id = $_SESSION['user_id'] ?? null;
// make sure orders table has necessary columns
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS alt_phone VARCHAR(20) NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS rider_instructions TEXT NULL");
    // unique constraint on phone to prevent duplicates
    $pdo->exec("ALTER TABLE orders ADD UNIQUE INDEX IF NOT EXISTS idx_orders_phone (phone)");
} catch(Exception $e) {}
// ensure users.phone uniqueness for account updates
try {
    $pdo->exec("ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS idx_users_phone (phone)");
} catch(Exception $e) {}

$email_prefill = '';
$phone_prefill = '';
$alt_prefill = '';
if($user_id) {
    $u = $pdo->prepare("SELECT email, phone FROM users WHERE id = ?");
    $u->execute([$user_id]);
    $userRow = $u->fetch();
    if($userRow) {
        $email_prefill = $userRow['email'];
        $phone_prefill = $userRow['phone'];
    // alt number not stored on profile
    }
}

// fetch cart items based on session or database
if($user_id) {
    // database cart for authenticated user
    $stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.seller_id, p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll();
} else {
    $cart_items = [];
    if(!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $session_cart = $_SESSION['cart'];
        $product_ids = array_keys($session_cart);
        if(!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $pdo->prepare("SELECT p.*, p.id as product_id, p.seller_id FROM products p WHERE p.id IN ($placeholders)");
            $stmt->execute($product_ids);
            $products = $stmt->fetchAll();
            foreach($products as $p) {
                $cart_items[] = [
                    'product_id' => $p['id'],
                    'quantity' => $session_cart[$p['id']],
                    'name' => $p['name'],
                    'price' => $p['price'],
                    'seller_id' => $p['seller_id'],
                    'stock' => $p['stock']
                ];
            }
        }
    }
}

if(empty($cart_items)) {
    header('Location: cart.php');
    exit;
}

// verify stock availability before placing order
foreach($cart_items as $item) {
    $available = isset($item['stock']) ? intval($item['stock']) : 0;
    if($item['quantity'] > $available) {
        $error = "The product '" . htmlspecialchars($item['name']) . "' is no longer available in the requested quantity.";
        break;
    }
}

if(isset($error)) {
    // prevent rendering checkout form with invalid cart
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    require_csrf_or_fail();

    // grab contact inputs early
    $phone = trim($_POST['phone'] ?? '');
    $alt_phone = trim($_POST['alt_phone'] ?? '');
    // preserve values for redisplay
    $phone_prefill = $phone;
    $alt_prefill = $alt_phone;
    $email_prefill = trim($_POST['email'] ?? $email_prefill);

    // if guest, collect personal info and create or lookup user
    if(!$user_id) {
        $full_name = trim($_POST['full_name']);
        $email = strtolower(trim($_POST['email']));
        $password = $_POST['password'] ?? '';
        if($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
            $error = 'Please enter a valid name, email and phone number.';
        }
        if($alt_phone && $alt_phone === $phone) {
            $error = 'Alternate number cannot be the same as primary.';
        }

        if(empty($error)) {
            // check for existing account
            $stmt = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            if($existing) {
                $user_id = $existing['id'];
                $full_name = $existing['full_name'];
                // update phone if missing and not duplicate
                if(!$existing['phone'] && $phone) {
                    // ensure phone uniqueness
                    $dup = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id <> ?");
                    $dup->execute([$phone, $user_id]);
                    if($dup->fetch()) {
                        $error = 'Primary phone number already in use by another account.';
                    } else {
                        $updPhone = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
                        $updPhone->execute([$phone, $user_id]);
                    }
                }
            } else {
                $hash = $password ? password_hash($password, PASSWORD_DEFAULT) : password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
                // check duplicate phone before insert
                $dup = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
                $dup->execute([$phone]);
                if($dup->fetch()) {
                    $error = 'Primary phone number already in use by another account.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, user_type, phone) VALUES (?, ?, ?, 'buyer', ?)");
                    $stmt->execute([$email, $hash, $full_name, $phone]);
                    $user_id = $pdo->lastInsertId();
                }
                $user_id = $pdo->lastInsertId();
            }

            // log them in for the session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_type'] = 'buyer';
            $_SESSION['full_name'] = $full_name;
        }
    }

    // build full address string
    $line1 = trim($_POST['address_line1'] ?? '');
    $line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $payment_method = $_POST['payment_method'];

    if($line1 === '' || $city === '' || $state === '' || $zip === '' || $country === '' || $phone === '' || !in_array($payment_method, ['cod', 'card'], true)) {
        $error = 'Please provide complete shipping information and payment method.';
    }
    if($alt_phone && $alt_phone === $phone) {
        $error = 'Alternate number cannot match primary phone.';
    }
    // duplicate phone across orders is not permitted
    if(empty($error)) {
        $dup = $pdo->prepare("SELECT id FROM orders WHERE phone = ?");
        $dup->execute([$phone]);
        if($dup->fetch()) {
            $error = 'Primary phone number already in use by another order.';
        }
    }

    $address = $line1;
    if($line2) { $address .= "\n" . $line2; }
    $address .= "\n" . $city . ', ' . $state . ' ' . $zip . "\n" . $country . "\nPhone: " . $phone;
    // previously we appended alt_phone to shipping string; now it's stored separately
    // but keep it in address for compatibility/legacy
    if($alt_phone) {
        $address .= "\nAlt phone: " . $alt_phone;
    }
    $rider_instructions = trim($_POST['rider_instructions'] ?? '');
    
    $total = 0;
    foreach($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    $total += 5; // shipping
    
    try {
        $pdo->beginTransaction();
        
        // Create order
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, payment_method, phone, alt_phone, rider_instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $total, $address, $payment_method, $phone, $alt_phone ?: null, $rider_instructions]);
        $order_id = $pdo->lastInsertId();
        
        // Create order items and update stock
        foreach($cart_items as $item) {
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, seller_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $item['product_id'], $item['seller_id'], $item['quantity'], $item['price']]);

            // decrement stock and mark inactive if none left
            $upd = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $upd->execute([$item['quantity'], $item['product_id']]);
            $inactive = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ? AND stock <= 0");
            $inactive->execute([$item['product_id']]);
        }
        
        // Clear cart (db or session)
        if($user_id && isset($_SESSION['cart'])) {
            unset($_SESSION['cart']);
        }
        if($user_id) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        
        $pdo->commit();
        
        header('Location: orders.php?success=1&order_id=' . $order_id);
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        // provide more info to user/developer
        $error = 'Order failed. Please try again.';
        // append underlying error if available (e.g. duplicate phone)
        $msg = $e->getMessage();
        if($msg) {
            $error .= ' (' . htmlspecialchars($msg) . ')';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <h2 class="mb-4">Checkout</h2>
        
        <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <p><a href="cart.php" class="btn btn-primary">Return to cart</a></p>
        <?php endif; ?>
        
        <?php if(empty($error)): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4>Contact Information</h4>
                            <?php if(!$user_id): ?>
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email_prefill) ?>" required <?= $user_id ? 'readonly' : '' ?>>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($phone_prefill) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Alternate phone (optional)</label>
                                <input type="tel" name="alt_phone" class="form-control" placeholder="secondary number" value="<?= htmlspecialchars($alt_prefill) ?>">
                                <small class="text-muted">Cannot be same as primary</small>
                            </div>
                            <?php if(!$user_id): ?>
                            <div class="mb-3">
                                <label class="form-label">Password (optional to create account)</label>
                                <input type="password" name="password" class="form-control">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4>Shipping Address</h4>
                            <div class="mb-3">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" name="address_line1" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" name="address_line2" class="form-control">
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" name="state" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">ZIP/Postal Code</label>
                                    <input type="text" name="zip" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" name="country" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Special instructions for rider</label>
                                <textarea name="rider_instructions" class="form-control" rows="2" placeholder="e.g. leave at gate"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h4>Payment Method</h4>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" value="cod" id="cod" checked>
                                <label class="form-check-label" for="cod">Cash on Delivery</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="card" id="card">
                                <label class="form-check-label" for="card">Credit/Debit Card</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h4>Order Summary</h4>
                            <hr>
                            <?php 
                            $total = 0;
                            foreach($cart_items as $item): 
                                $subtotal = $item['price'] * $item['quantity'];
                                $total += $subtotal;
                            ?>
                            <div class="d-flex justify-content-between mb-2">
                                <small><?= htmlspecialchars($item['name']) ?> x<?= $item['quantity'] ?></small>
                                <small>$<?= number_format($subtotal, 2) ?></small>
                            </div>
                            <?php endforeach; ?>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span>$<?= number_format($total, 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping:</span>
                                <span>$5.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Total:</strong>
                                <strong>$<?= number_format($total + 5, 2) ?></strong>
                            </div>
                            <button type="submit" class="btn btn-warning w-100">Place Order</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(function(){
            $('form').on('submit', function(){
                const phone = $('input[name=phone]').val().trim();
                const alt = $('input[name=alt_phone]').val().trim();
                if(alt && phone && alt === phone) {
                    alert('Alternate number must differ from primary phone.');
                    return false;
                }
            });
        });
    </script>
</body>
</html>

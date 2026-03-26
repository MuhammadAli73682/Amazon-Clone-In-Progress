<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function merge_session_cart_into_user_cart(PDO $pdo, int $userId): void
{
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return;
    }

    foreach ($_SESSION['cart'] as $productId => $qty) {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $upd = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
            $upd->execute([$qty, $existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $ins->execute([$userId, $productId, $qty]);
        }
    }

    unset($_SESSION['cart']);
}

function login_user_into_session(PDO $pdo, array $user): void
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];

    merge_session_cart_into_user_cart($pdo, (int)$user['id']);
}

function redirect_after_login(?string $userType): void
{
    if ($userType === 'seller') {
        header('Location: seller/dashboard.php');
    } elseif ($userType === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

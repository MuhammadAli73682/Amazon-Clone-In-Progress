<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/security.php';
$user_id   = $_SESSION['user_id']   ?? null;
$user_type = $_SESSION['user_type'] ?? null;
$full_name = $_SESSION['full_name'] ?? 'User';
$csrfToken = csrf_token();
?>
<script>window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;</script>

<nav class="navbar navbar-expand-lg navbar-dark bg-secondary sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>/<?= $user_type ?>/dashboard.php">
            <i class="fas fa-cogs"></i> Backend
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#backendNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="backendNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if($user_type === 'seller'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/seller/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/seller/products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/seller/orders.php">Orders</a></li>
                <?php elseif($user_type === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/orders.php">Orders</a></li>
                <?php elseif($user_type === 'buyer'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/orders.php">My Orders</a></li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/index.php" target="_blank">Visit Site</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="backendUser" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($full_name) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
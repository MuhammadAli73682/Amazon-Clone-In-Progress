<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Safe session variables
$user_id   = $_SESSION['user_id']   ?? null;
$user_type = $_SESSION['user_type'] ?? null;
$full_name = $_SESSION['full_name'] ?? 'User';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        
        <!-- Logo -->
        <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
            <i class="fas fa-shopping-bag"></i> ShopHub
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            
            <!-- Search Bar -->
            <form class="d-flex mx-auto search-form" action="<?= BASE_URL ?>/products.php" method="GET">
                <input 
                    class="form-control me-2" 
                    type="search" 
                    name="search" 
                    placeholder="Search products..." 
                    style="width: 500px;"
                >
                <button class="btn btn-warning" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            
            <!-- Right Menu -->
            <ul class="navbar-nav ms-auto">
                
                <!-- Wishlist -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/wishlist.php">
                        <i class="fas fa-heart"></i> Wishlist 
                        <span class="badge bg-warning" id="wishlist-count">0</span>
                    </a>
                </li>

                <!-- Cart -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/cart.php">
                        <i class="fas fa-shopping-cart"></i> Cart 
                        <span class="badge bg-warning" id="cart-count">0</span>
                    </a>
                </li>

                <?php if($user_id): ?>

                    <!-- Orders -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/orders.php">
                            <i class="fas fa-box"></i> Orders
                        </a>
                    </li>

                    <!-- Track Order -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/track-order.php">
                            <i class="fas fa-map-marker-alt"></i> Track Order
                        </a>
                    </li>

                    <!-- Seller Dashboard -->
                    <?php if($user_type === 'seller'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/seller/dashboard.php">
                            <i class="fas fa-store"></i> Seller Dashboard
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Admin Dashboard -->
                    <?php if($user_type === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($full_name) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/profile.php">
                                    Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </li>

                <?php else: ?>

                    <!-- Guest Links -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/register.php">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/track-order.php">
                            <i class="fas fa-map-marker-alt"></i> Track Order
                        </a>
                    </li>

                <?php endif; ?>
                
            </ul>
        </div>
    </div>
</nav>

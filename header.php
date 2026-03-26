<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/security.php';

// Safe session variables
$user_id   = $_SESSION['user_id']   ?? null;
$user_type = $_SESSION['user_type'] ?? null;
$full_name = $_SESSION['full_name'] ?? 'User';
$csrfToken = csrf_token();
?>
<script>
window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
</script>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
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
            <form class="d-flex mx-auto search-form" action="<?= BASE_URL ?>/products.php" method="GET" autocomplete="off">
                <input 
                    class="form-control me-2" 
                    type="search" 
                    name="search" 
                    id="globalSearch"
                    placeholder="Search products..." 
                >
                <button class="btn btn-warning" type="submit">
                    <i class="fas fa-search"></i>
                </button>
                <div id="searchSuggestions" class="list-group" style="display:none;"></div>
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

                    <!-- Chat Support -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/support-chat.php">
                            <i class="fas fa-comments"></i> Support
                        </a>
                    </li>

                    <!-- Backend shortcut -->
                    <li class="nav-item">
                        <?php if($user_type === 'seller'): ?>
                            <a class="nav-link" href="<?= BASE_URL ?>/seller/dashboard.php">Go to Seller</a>
                        <?php elseif($user_type === 'admin'): ?>
                            <a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">Go to Admin</a>
                        <?php endif; ?>
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

                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/support-chat.php">
                            <i class="fas fa-comments"></i> Support
                        </a>
                    </li>

                <?php endif; ?>
                
            </ul>
        </div>
    </div>
</nav>
<script>
(function() {
    const input = document.getElementById('globalSearch');
    const suggest = document.getElementById('searchSuggestions');
    if(!input || !suggest) return;

    let timer = null;
    function hide() { suggest.style.display = 'none'; suggest.innerHTML = ''; }
    function show() { suggest.style.display = 'block'; }

    function render(items) {
        if(!items || !items.length) return hide();
        const html = items.map(s => {
            const label = String(s.label || '');
            const id = Number(s.id || 0);
            const href = id ? ('product-detail.php?id=' + encodeURIComponent(String(id))) : ('products.php?search=' + encodeURIComponent(label));
            const meta = [
                (s.category ? String(s.category) : ''),
                (s.currency && (s.price !== undefined) ? (String(s.currency) + ' ' + String(Number(s.price).toFixed(2))) : '')
            ].filter(Boolean).join(' • ');
            return (
                '<a href="'+href+'" class="list-group-item list-group-item-action">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                        '<div class="me-2">' +
                            '<div class="fw-semibold">'+escapeHtml(label)+'</div>' +
                            (meta ? '<div class="small text-muted">'+escapeHtml(meta)+'</div>' : '') +
                        '</div>' +
                        '<i class="fas fa-arrow-right text-muted"></i>' +
                    '</div>' +
                '</a>'
            );
        }).join('');
        suggest.innerHTML = html;
        show();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    input.addEventListener('input', function() {
        if(timer) window.clearTimeout(timer);
        const val = input.value.trim();
        if(val.length < 2) return hide();
        timer = window.setTimeout(async function() {
            try {
                const url = 'api/search.php?term=' + encodeURIComponent(val);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                render(data && data.suggestions ? data.suggestions : []);
            } catch(e) {
                hide();
            }
        }, 250);
    });

    document.addEventListener('click', function(e) {
        const t = e.target;
        if(!t) return;
        if(t === input || suggest.contains(t)) return;
        hide();
    });
})();
</script>

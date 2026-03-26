<footer class="text-white mt-5 py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <h5>About ShopHub</h5>
                <p>Your trusted online marketplace for quality products from verified sellers worldwide.</p>
            </div>
            <div class="col-md-3">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="<?= BASE_URL ?>/products.php" class="text-white">All Products</a></li>
                    <li><a href="<?= BASE_URL ?>/register.php?type=seller" class="text-white">Become a Seller</a></li>
                    <li><a href="<?= BASE_URL ?>/help-center.php" class="text-white">Help Center</a></li>
                </ul>
            </div>
            <div class="col-md-3">
                <h5>Customer Service</h5>
                <ul class="list-unstyled">
                    <li><a href="<?= BASE_URL ?>/contact.php" class="text-white">Contact Us</a></li>
                    <li><a href="<?= BASE_URL ?>/returns.php" class="text-white">Returns</a></li>
                    <li><a href="<?= BASE_URL ?>/shipping-info.php" class="text-white">Shipping Info</a></li>
                </ul>
            </div>
            <div class="col-md-3">
                <h5>Follow Us</h5>
                <div class="social-links d-flex">
                    <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-2x"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-2x"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-instagram fa-2x"></i></a>
                </div>
            </div>
        </div>
        <hr class="bg-white">
        <div class="text-center">
            <p>&copy; 2026 ShopHub. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- App Toast (used by assets/js/main.js) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 2000;">
    <div id="appToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div id="appToastBody" class="toast-body">...</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

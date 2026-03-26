$(document).ready(function() {
    const csrfToken = window.CSRF_TOKEN || '';
    const toastEl = document.getElementById('appToast');
    const toastBody = document.getElementById('appToastBody');
    const toast = (toastEl && window.bootstrap && window.bootstrap.Toast) ? new window.bootstrap.Toast(toastEl, { delay: 2400 }) : null;

    function showToast(message, variant) {
        if(!message) return;
        if(!toast || !toastEl || !toastBody) {
            alert(message);
            return;
        }
        const v = (variant || 'dark').toLowerCase();
        toastEl.className = 'toast align-items-center border-0';
        if(v === 'success') toastEl.classList.add('text-bg-success');
        else if(v === 'danger' || v === 'error') toastEl.classList.add('text-bg-danger');
        else if(v === 'warning') toastEl.classList.add('text-bg-warning');
        else toastEl.classList.add('text-bg-dark');
        toastBody.textContent = message;
        toast.show();
    }

    // Update cart count on page load
    updateCartCount();

    // load full cart if cart page is open (AJAX friendly)
    if($('.cart-item').length === 0 && window.location.pathname.endsWith('cart.php')) {
        // page will render server side; but this check leaves room for SPA updates
        refreshCartItems();
    }
    
    // Add to cart functionality (delegated so it works for dynamically rendered cards)
    $(document).on('click', '.add-to-cart', function() {
        const productId = $(this).data('id');
        
        $.ajax({
            url: 'api/cart.php',
            method: 'POST',
            data: { action: 'add', product_id: productId, csrf_token: csrfToken },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    showToast('Added to cart', 'success');
                    updateCartCount();
                } else {
                    showToast(response.message || 'Unable to add to cart', 'danger');
                    if(response.redirect) {
                        window.location.href = 'login.php';
                    }
                }
            },
            error: function() {
                showToast('Error adding product to cart', 'danger');
            }
        });
    });
    
    // Add to wishlist (delegated for dynamic content)
    $(document).on('click', '.add-to-wishlist', function() {
        const productId = $(this).data('id');
        const $btn = $(this);
        const removing = $btn.hasClass('remove-mode');
        $.ajax({
            url: 'api/wishlist.php',
            method: 'POST',
            data: { action: removing ? 'remove' : 'add', product_id: productId, csrf_token: csrfToken },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    showToast(removing ? 'Removed from wishlist' : 'Added to wishlist', 'success');
                    if(removing) {
                        $btn.removeClass('btn-danger remove-mode').addClass('btn-outline-danger');
                        // optional text on product detail page
                        const $icon = $btn.find('i.fas.fa-heart');
                        if($icon.length) { /* keep */ }
                        if($btn.text().toLowerCase().includes('remove')) {
                            $btn.html('<i class="fas fa-heart"></i> Add to Wishlist');
                        }
                    } else {
                        $btn.removeClass('btn-outline-danger').addClass('btn-danger remove-mode');
                        if($btn.text().toLowerCase().includes('add')) {
                            $btn.html('<i class="fas fa-heart"></i> Remove from Wishlist');
                        }
                    }
                    updateCartCount();
                } else {
                    showToast(response.message || 'Please login to use wishlist', 'danger');
                    if(response.redirect) {
                        window.location.href = 'login.php';
                    }
                }
            }
        });
    });

    // Remove from wishlist (button in wishlist page)
    $('.remove-from-wishlist').click(function() {
        if(confirm('Remove this item from wishlist?')) {
            const productId = $(this).data('product-id');
            $.ajax({
                url: 'api/wishlist.php',
                method: 'POST',
                data: { action: 'remove', product_id: productId, csrf_token: csrfToken },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        location.reload();
                        updateCartCount();
                    }
                }
            });
        }
    });

    // Update cart quantity
    $('.update-quantity').change(function() {
        const cartId = $(this).data('cart-id');
        const productId = $(this).data('product-id');
        const quantity = $(this).val();
        
        $.ajax({
            url: 'api/cart.php',
            method: 'POST',
            data: { action: 'update', cart_id: cartId, product_id: productId, quantity: quantity, csrf_token: csrfToken },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    location.reload();
                }
            }
        });
    });
    
    // Remove from cart
    $('.remove-from-cart').click(function() {
        if(confirm('Remove this item from cart?')) {
            const cartId = $(this).data('cart-id');
            const productId = $(this).data('product-id');
            
            {
                let payload = { action: 'remove', csrf_token: csrfToken };
                if(cartId) payload.cart_id = cartId;
                if(productId) payload.product_id = productId;
                $.ajax({
                    url: 'api/cart.php',
                    method: 'POST',
                    data: payload,
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        }
                    }
                });
            }
        }
    });
    
    // Update cart count and optionally refresh list
    function updateCartCount() {
        // update cart count
        $.ajax({
            url: 'api/cart.php',
            method: 'GET',
            data: { action: 'count' },
            dataType: 'json',
            success: function(response) {
                if(response.count !== undefined) {
                    $('#cart-count').text(response.count);
                }
            }
        });
        // update wishlist count
        $.ajax({
            url: 'api/wishlist.php',
            method: 'GET',
            data: { action: 'count' },
            dataType: 'json',
            success: function(response) {
                if(response.count !== undefined) {
                    $('#wishlist-count').text(response.count);
                }
            }
        });
    }

    // refresh cart rows using API
    function refreshCartItems() {
        $.ajax({
            url: 'api/cart.php',
            method: 'GET',
            data: { action: 'list' },
            dataType: 'json',
            success: function(response) {
                if(response.items) {
                    const container = $('.container.my-5');
                    // building a simple table or list
                    let html = '<h2 class="mb-4">Shopping Cart</h2>';
                    if(response.items.length === 0) {
                        html += '<div class="alert alert-info">Your cart is empty. <a href="products.php">Continue shopping</a></div>';
                    } else {
                        html += '<table class="table"><thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Actions</th></tr></thead><tbody>';
                        response.items.forEach(function(item) {
                            html += '<tr>' +
                                    '<td>'+item.name+'</td>' +
                                    '<td>$'+parseFloat(item.price).toFixed(2)+'</td>' +
                                    '<td>'+item.quantity+'</td>' +
                                    '<td><button class="btn btn-sm btn-danger remove-from-cart" data-cart-id="'+item.id+'" data-product-id="'+item.product_id+'">Remove</button></td>' +
                                    '</tr>';
                        });
                        html += '</tbody></table>';
                        html += '<button id="clear-cart" class="btn btn-outline-secondary">Clear cart</button>';
                    }
                    container.html(html);
                    // rebind events
                    $('.remove-from-cart').click(function() {
                        if(confirm('Remove this item from cart?')) {
                            const cartId = $(this).data('cart-id');
                            const productId = $(this).data('product-id');
                            {
                                let payload = { action: 'remove', csrf_token: csrfToken };
                                if(cartId) payload.cart_id = cartId;
                                if(productId) payload.product_id = productId;
                                $.ajax({
                                    url: 'api/cart.php',
                                    method: 'POST',
                                    data: payload,
                                    dataType: 'json',
                                    success: function(resp) {
                                        if(resp.success) {
                                            refreshCartItems();
                                            updateCartCount();
                                        }
                                    }
                                });
                            }
                        }
                    });
                    $('#clear-cart').click(function() {
                        if(confirm('Clear entire cart?')) {
                            $.ajax({
                                url: 'api/cart.php',
                                method: 'POST',
                                data: { action: 'clear', csrf_token: csrfToken },
                                dataType: 'json',
                                success: function(resp) {
                                    if(resp.success) {
                                        refreshCartItems();
                                        updateCartCount();
                                    }
                                }
                            });
                        }
                    });
                }
            }
        });
    }

    // AI recommendations loader
    function loadAiRecommendations() {
        const $containers = $('[data-ai-reco]');
        if($containers.length === 0) return;

        $containers.each(function() {
            const $container = $(this);
            const productId = $container.data('product-id') || $container.data('productId') || '';
            const limit = $container.data('limit') || 8;
            const skCount = Math.max(1, Math.min(8, parseInt(limit, 10) || 8));

            // skeleton placeholders
            let sk = '';
            for(let i=0;i<skCount;i++) {
                sk +=
                    '<div class="col-md-3 col-sm-6 mb-4">' +
                        '<div class="skeleton-card">' +
                            '<div class="skeleton-img"></div>' +
                            '<div class="skeleton-body">' +
                                '<div class="skeleton-line w-70"></div>' +
                                '<div class="skeleton-line w-90"></div>' +
                                '<div class="skeleton-line w-50"></div>' +
                                '<div class="skeleton-pill"></div>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            }
            $container.html(sk);

            $.ajax({
                url: 'api/recommendations.php',
                method: 'GET',
                data: { product_id: productId, limit: limit },
                dataType: 'json',
                success: function(resp) {
                    if(!resp || !resp.items || !resp.items.length) {
                        $container.html('<div class="col-12 text-muted">No recommendations available right now.</div>');
                        return;
                    }

                    let html = '';
                    resp.items.forEach(function(p) {
                        const price = '$' + parseFloat(p.price || 0).toFixed(2);
                        const reason = p.reason ? ('<div class="text-muted small mb-1">'+escapeHtml(p.reason)+'</div>') : '';
                        html +=
                            '<div class="col-md-3 col-sm-6 mb-4">' +
                                '<div class="product-card">' +
                                    '<a href="product-detail.php?id='+encodeURIComponent(p.id)+'">' +
                                        '<img src="'+escapeAttr(p.image || '')+'" alt="'+escapeAttr(p.name || '')+'">' +
                                    '</a>' +
                                    '<div class="product-info">' +
                                        reason +
                                        '<h5>'+escapeHtml(p.name || '')+'</h5>' +
                                        '<p class="price">'+price+'</p>' +
                                        (p.shop_name ? '<p class="seller">by '+escapeHtml(p.shop_name)+'</p>' : '') +
                                        '<button class="btn btn-warning btn-sm add-to-cart" data-id="'+encodeURIComponent(p.id)+'">' +
                                            '<i class="fas fa-cart-plus"></i> Add to Cart' +
                                        '</button>' +
                                    '</div>' +
                                '</div>' +
                            '</div>';
                    });
                    $container.html(html);
                },
                error: function() {
                    $container.html('<div class="col-12 text-muted">Failed to load recommendations.</div>');
                }
            });
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttr(str) {
        // Basic attribute escaping (same as HTML escaping for our usage).
        return escapeHtml(str);
    }

    loadAiRecommendations();
});

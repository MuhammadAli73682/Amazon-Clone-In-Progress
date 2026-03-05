$(document).ready(function() {
    const csrfToken = window.CSRF_TOKEN || '';

    // Update cart count on page load
    updateCartCount();

    // load full cart if cart page is open (AJAX friendly)
    if($('.cart-item').length === 0 && window.location.pathname.endsWith('cart.php')) {
        // page will render server side; but this check leaves room for SPA updates
        refreshCartItems();
    }
    
    // Add to cart functionality
    $('.add-to-cart').click(function() {
        const productId = $(this).data('id');
        
        $.ajax({
            url: 'api/cart.php',
            method: 'POST',
            data: { action: 'add', product_id: productId, csrf_token: csrfToken },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    alert('Product added to cart!');
                    updateCartCount();
                } else {
                    alert(response.message || 'Please login to add items to cart');
                    if(response.redirect) {
                        window.location.href = 'login.php';
                    }
                }
            },
            error: function() {
                alert('Error adding product to cart');
            }
        });
    });
    
    // Add to wishlist
    $('.add-to-wishlist').click(function() {
        const productId = $(this).data('id');
        $.ajax({
            url: 'api/wishlist.php',
            method: 'POST',
            data: { action: 'add', product_id: productId, csrf_token: csrfToken },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    alert('Added to wishlist');
                    updateCartCount();
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
});

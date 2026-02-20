$(document).ready(function() {
    // Update cart count on page load
    updateCartCount();
    
    // Add to cart functionality
    $('.add-to-cart').click(function() {
        const productId = $(this).data('id');
        
        $.ajax({
            url: 'api/cart.php',
            method: 'POST',
            data: { action: 'add', product_id: productId },
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
            data: { action: 'add', product_id: productId },
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
                data: { action: 'remove', product_id: productId },
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
            data: { action: 'update', cart_id: cartId, product_id: productId, quantity: quantity },
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
            
            $.ajax({
                url: 'api/cart.php',
                method: 'POST',
                data: { action: 'remove', cart_id: cartId, product_id: productId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    }
                }
            });
        }
    });
    
    // Update cart count
    function updateCartCount() {
        // update cart
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
        // update wishlist
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
});

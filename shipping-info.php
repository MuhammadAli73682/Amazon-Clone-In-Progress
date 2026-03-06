<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Info - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container my-5">
        <h2 class="mb-4">Shipping Information</h2>
        <p>We strive to deliver your orders as quickly and efficiently as possible. Here are the details about our shipping policies:</p>
        
        <h4>Delivery Times</h4>
        <ul>
            <li>Standard Shipping: 5-7 business days</li>
            <li>Express Shipping: 2-3 business days</li>
            <li>Overnight Shipping: 1 business day (available in select areas)</li>
        </ul>
        
        <h4>Shipping Costs</h4>
        <ul>
            <li>Free shipping on orders over $50</li>
            <li>Standard shipping: $5.99</li>
            <li>Express shipping: $12.99</li>
        </ul>
        
        <h4>Tracking Your Order</h4>
        <p>Once your order ships, you'll receive a tracking number via email. You can track your package on our <a href="<?= BASE_URL ?>/track-order.php">Track Order</a> page.</p>
        
        <h4>International Shipping</h4>
        <p>We offer international shipping to select countries. Additional customs fees may apply.</p>
        
        <p>For more details or questions, contact our support team at <a href="<?= BASE_URL ?>/contact.php">Contact Us</a>.</p>
    </div>
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
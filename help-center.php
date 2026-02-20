<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container my-5">
        <h2 class="mb-4">Help Center</h2>
        <p>If you need assistance, please reach out via the following:</p>
        <ul>
            <li>Email: support@shophub.com</li>
            <li>Phone: +1 (800) 123-4567</li>
            <li>Address: 123 Commerce St, Suite 100, Cityville, Country</li>
        </ul>
        <p>For additional queries you can also use our <a href="<?= BASE_URL ?>/contact.php">contact form</a>.</p>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
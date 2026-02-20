-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 19, 2026 at 07:28 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ecommerce_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `added_at`) VALUES
(6, 11, 9, 1, '2026-02-18 12:56:43'),
(8, 14, 17, 1, '2026-02-18 13:00:31'),
(10, 6, 9, 2, '2026-02-19 06:02:55'),
(11, 8, 17, 1, '2026-02-19 06:16:39');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `shipping_address` text NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `status`, `shipping_address`, `payment_method`, `created_at`) VALUES
(1, 6, 24.99, 'pending', 'karachi', 'cod', '2026-02-18 06:11:01'),
(2, 6, 355.00, 'delivered', 'karachi', 'cod', '2026-02-18 06:18:51'),
(3, 6, 705.00, 'cancelled', 'karachi\r\n', 'cod', '2026-02-18 06:33:51'),
(4, 6, 134.99, 'pending', 'karachi', 'cod', '2026-02-18 07:14:23'),
(5, 13, 304.99, 'processing', 'hello', 'cod', '2026-02-19 05:23:33');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `seller_id`, `quantity`, `price`) VALUES
(1, 1, 9, 4, 1, 19.99),
(2, 2, 19, 11, 1, 350.00),
(3, 3, 19, 11, 2, 350.00),
(4, 4, 8, 3, 1, 129.99),
(5, 5, 2, 2, 1, 299.99);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `created_at`, `expires_at`, `used`) VALUES
(3, 'new@gmail.com', '842506', '2026-02-18 07:04:38', '2026-02-18 03:19:38', 0),
(7, 'aliabid78555@gmail.com', '$2y$10', '2026-02-18 13:06:26', '2026-02-18 09:21:26', 0);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `name`, `description`, `price`, `stock`, `category`, `image`, `status`, `created_at`) VALUES
(1, 2, 'Wireless Bluetooth Headphones', 'Premium noise-cancelling headphones with 30-hour battery life', 89.99, 50, 'Electronics', 'assets/images/products/headphones.jpg', 'active', '2026-02-17 13:24:32'),
(2, 2, 'Smart Watch Series 5', 'Fitness tracker with heart rate monitor and GPS', 299.99, 29, 'Electronics', 'assets/images/products/smartwatch.jpg', 'active', '2026-02-17 13:24:32'),
(3, 2, 'Laptop 15.6 inch', 'Intel i7, 16GB RAM, 512GB SSD, Full HD Display', 899.99, 20, 'Electronics', 'assets/images/products/laptop.jpg', 'active', '2026-02-17 13:24:32'),
(4, 2, 'Wireless Mouse', 'Ergonomic design with precision tracking', 24.99, 100, 'Electronics', 'assets/images/products/mouse.jpg', 'active', '2026-02-17 13:24:32'),
(5, 3, 'Men\'s Casual Shirt', 'Cotton blend, available in multiple colors', 34.99, 75, 'Fashion', 'assets/images/products/shirt.jpg', 'active', '2026-02-17 13:24:32'),
(6, 3, 'Women\'s Summer Dress', 'Floral print, lightweight fabric', 49.99, 60, 'Fashion', 'assets/images/products/dress.jpg', 'active', '2026-02-17 13:24:32'),
(7, 3, 'Running Shoes', 'Comfortable athletic shoes for daily wear', 79.99, 40, 'Fashion', 'assets/images/products/shoes.jpg', 'active', '2026-02-17 13:24:32'),
(8, 3, 'Leather Handbag', 'Genuine leather with multiple compartments', 129.99, 24, 'Fashion', 'assets/images/products/handbag.jpg', 'active', '2026-02-17 13:24:32'),
(9, 4, 'The Great Novel', 'Bestselling fiction book of the year', 19.99, 200, 'Books', 'assets/images/products/book1.jpg', 'active', '2026-02-17 13:24:32'),
(10, 4, 'Programming Guide', 'Complete guide to modern web development', 44.99, 150, 'Books', 'assets/images/products/book2.jpg', 'active', '2026-02-17 13:24:32'),
(11, 4, 'Cookbook Collection', '500 delicious recipes for home cooking', 29.99, 80, 'Books', 'assets/images/products/cookbook.jpg', 'active', '2026-02-17 13:24:32'),
(12, 5, 'Coffee Maker', 'Programmable 12-cup coffee maker', 59.99, 45, 'Home & Kitchen', 'assets/images/products/coffeemaker.jpg', 'active', '2026-02-17 13:24:32'),
(13, 5, 'Blender Pro', '1000W high-speed blender for smoothies', 79.99, 35, 'Home & Kitchen', 'assets/images/products/blender.jpg', 'active', '2026-02-17 13:24:32'),
(14, 5, 'Bed Sheet Set', 'Soft microfiber, queen size, 4-piece set', 39.99, 90, 'Home & Kitchen', 'assets/images/products/bedsheet.jpg', 'active', '2026-02-17 13:24:32'),
(15, 5, 'LED Desk Lamp', 'Adjustable brightness with USB charging port', 34.99, 70, 'Home & Kitchen', 'assets/images/products/lamp.jpg', 'active', '2026-02-17 13:24:32'),
(16, 5, 'Wireless Mouse', 'High quality gaming mouse', 1500.00, 0, NULL, NULL, 'active', '2026-02-18 06:15:21'),
(17, 5, 'Bluetooth Headphones', 'Noise cancelling headphones', 4500.00, 0, NULL, NULL, 'active', '2026-02-18 06:15:21'),
(18, 5, 'Mechanical Keyboard', 'RGB backlit keyboard', 6500.00, 0, NULL, NULL, 'active', '2026-02-18 06:15:21'),
(19, 11, 'Wireless Gaming Mouse', 'High precision 16000 DPI gaming mouse with RGB lighting.\r\nErgonomic design for long gaming sessions.\r\nRechargeable battery with 40 hours backup', 350.00, 2, 'Electronics', 'assets/images/products/default.jpg', 'inactive', '2026-02-18 06:16:58');

-- --------------------------------------------------------

--
-- Table structure for table `return_requests`
--

CREATE TABLE `return_requests` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `reason` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_id` int(11) DEFAULT NULL,
  `seller_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_requests`
--

INSERT INTO `return_requests` (`id`, `order_number`, `product_name`, `reason`, `image`, `created_at`, `product_id`, `seller_id`) VALUES
(1, '2', 'Wireless Gaming Mouse x1', 'maza nahi arha', NULL, '2026-02-18 07:28:57', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 17, 6, 3, 'very good product', '2026-02-19 05:55:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `user_type` enum('buyer','seller','admin') DEFAULT 'buyer',
  `shop_name` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `user_type`, `shop_name`, `phone`, `address`, `created_at`) VALUES
(1, 'admin@shophub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin', NULL, NULL, NULL, '2026-02-17 13:24:32'),
(2, 'seller1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Electronics', 'seller', 'TechWorld Store', '555-0101', NULL, '2026-02-17 13:24:32'),
(3, 'seller2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Fashion', 'seller', 'Fashion Hub', '555-0102', NULL, '2026-02-17 13:24:32'),
(4, 'seller3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Books', 'seller', 'Book Paradise', '555-0103', NULL, '2026-02-17 13:24:32'),
(5, 'seller4@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa Home', 'seller', 'Home Essentials', '555-0104', NULL, '2026-02-17 13:24:32'),
(6, 'buyer1@example.com', '123', 'Alice Johnson', 'buyer', NULL, NULL, '123 Main St, New York, NY 10001', '2026-02-17 13:24:32'),
(7, 'buyer2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Smith', 'buyer', NULL, NULL, '456 Oak Ave, Los Angeles, CA 90001', '2026-02-17 13:24:32'),
(8, 'ali@gmail.com', '123', 'Ali Admin', 'admin', NULL, NULL, NULL, '2026-02-18 05:58:26'),
(9, 'muhammad@gmail.com', '$2y$10$xyFtsThSEviSPwhPoujDCOyj9LBqlsxhTgONkBgf8lzo37o.5pJSe', 'Muhammad', 'buyer', '', '', NULL, '2026-02-18 06:07:21'),
(10, 'mr@gmail.com', '$2y$10$VVh69S87P48cXKXMGUlePeLqZDS31B0olwknj0ZelnW.QagOzxQka', 'mr', 'buyer', '', '', NULL, '2026-02-18 06:08:21'),
(11, 'seller@gmail.com', '123', 'Demo Seller', 'seller', NULL, NULL, NULL, '2026-02-18 06:14:31'),
(12, 'new@gmail.com', '$2y$10$UdSGa0ZdAxgQTdbaeHYnFOUmijibh3aohuUjRnrBLMmmjepEyiwaG', 'new', 'buyer', '', '', NULL, '2026-02-18 06:43:47'),
(13, 'aliabid78555@gmail.com', '$2y$10$AKG4cMlWVV0xBQsr3B3yae1biyC0O9iEY7s49mVyGsnRy1llOYAxS', 'aliabid', 'buyer', '', '', NULL, '2026-02-18 07:03:34'),
(14, 'hello@gmail.com', '$2y$10$e4kgIrRi6pMF5Z6.y4SMHugq85ueE3zGlQY7YVuMRd7kE/FVmAWam', 'aliabid', 'buyer', '', '', NULL, '2026-02-18 13:00:17');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `added_at`) VALUES
(2, 8, 5, '2026-02-19 06:16:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `return_requests`
--
ALTER TABLE `return_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_product` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `return_requests`
--
ALTER TABLE `return_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `return_requests`
--
ALTER TABLE `return_requests`
  ADD CONSTRAINT `return_requests_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `return_requests_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

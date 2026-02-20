CREATE DATABASE IF NOT EXISTS ecommerce_db;
USE ecommerce_db;

-- Users table (buyers and sellers)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    user_type ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer',
    shop_name VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT DEFAULT 0,
    category VARCHAR(100),
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id)
);

-- Cart table
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    seller_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (seller_id) REFERENCES users(id)
);

-- Reviews table
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Wishlist table to allow buyers to save products
CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY uniq_user_product (user_id, product_id)
);

-- Contact messages table
CREATE TABLE contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Return requests table
CREATE TABLE return_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_id INT DEFAULT NULL,
    seller_id INT DEFAULT NULL,
    reason TEXT NOT NULL,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (seller_id) REFERENCES users(id)
);

-- Password reset table
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0
);

-- Insert dummy admin user (password: admin123)
-- Note: Run generate_password.php to get a fresh hash if login fails
INSERT INTO users (email, password, full_name, user_type) VALUES
('admin@shophub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin');

-- Insert dummy sellers
INSERT INTO users (email, password, full_name, user_type, shop_name, phone) VALUES
('seller1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Electronics', 'seller', 'TechWorld Store', '555-0101'),
('seller2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Fashion', 'seller', 'Fashion Hub', '555-0102'),
('seller3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Books', 'seller', 'Book Paradise', '555-0103'),
('seller4@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa Home', 'seller', 'Home Essentials', '555-0104');

-- Insert dummy buyers
INSERT INTO users (email, password, full_name, user_type, address) VALUES
('buyer1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Johnson', 'buyer', '123 Main St, New York, NY 10001'),
('buyer2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Smith', 'buyer', '456 Oak Ave, Los Angeles, CA 90001');

-- Insert dummy products
INSERT INTO products (seller_id, name, description, price, stock, category, image) VALUES
(2, 'Wireless Bluetooth Headphones', 'Premium noise-cancelling headphones with 30-hour battery life', 89.99, 50, 'Electronics', 'assets/images/products/headphones.jpg'),
(2, 'Smart Watch Series 5', 'Fitness tracker with heart rate monitor and GPS', 299.99, 30, 'Electronics', 'assets/images/products/smartwatch.jpg'),
(2, 'Laptop 15.6 inch', 'Intel i7, 16GB RAM, 512GB SSD, Full HD Display', 899.99, 20, 'Electronics', 'assets/images/products/laptop.jpg'),
(2, 'Wireless Mouse', 'Ergonomic design with precision tracking', 24.99, 100, 'Electronics', 'assets/images/products/mouse.jpg'),
(3, 'Men\'s Casual Shirt', 'Cotton blend, available in multiple colors', 34.99, 75, 'Fashion', 'assets/images/products/shirt.jpg'),
(3, 'Women\'s Summer Dress', 'Floral print, lightweight fabric', 49.99, 60, 'Fashion', 'assets/images/products/dress.jpg'),
(3, 'Running Shoes', 'Comfortable athletic shoes for daily wear', 79.99, 40, 'Fashion', 'assets/images/products/shoes.jpg'),
(3, 'Leather Handbag', 'Genuine leather with multiple compartments', 129.99, 25, 'Fashion', 'assets/images/products/handbag.jpg'),
(4, 'The Great Novel', 'Bestselling fiction book of the year', 19.99, 200, 'Books', 'assets/images/products/book1.jpg'),
(4, 'Programming Guide', 'Complete guide to modern web development', 44.99, 150, 'Books', 'assets/images/products/book2.jpg'),
(4, 'Cookbook Collection', '500 delicious recipes for home cooking', 29.99, 80, 'Books', 'assets/images/products/cookbook.jpg'),
(5, 'Coffee Maker', 'Programmable 12-cup coffee maker', 59.99, 45, 'Home & Kitchen', 'assets/images/products/coffeemaker.jpg'),
(5, 'Blender Pro', '1000W high-speed blender for smoothies', 79.99, 35, 'Home & Kitchen', 'assets/images/products/blender.jpg'),
(5, 'Bed Sheet Set', 'Soft microfiber, queen size, 4-piece set', 39.99, 90, 'Home & Kitchen', 'assets/images/products/bedsheet.jpg'),
(5, 'LED Desk Lamp', 'Adjustable brightness with USB charging port', 34.99, 70, 'Home & Kitchen', 'assets/images/products/lamp.jpg');

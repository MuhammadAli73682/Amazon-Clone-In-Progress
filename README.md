# ShopHub - E-Commerce Web Application

Amazon-inspired full-featured e-commerce platform built with PHP, MySQL, Bootstrap, jQuery, HTML, and CSS.

## Features

### For Buyers
- Browse products by category
- Search functionality
- Product details with reviews
- Shopping cart management
- Checkout and order placement
- Order history tracking
- Order number generation and "Track Order" page for checking status
- Contact form, returns portal (with image upload) and help center for customer support
- User profile management

### For Sellers
- Seller dashboard with statistics
- Add/manage products
- View orders
- Track revenue
- Inventory management

### General Features
- User authentication (Login/Register)
- Multiple user types (Buyer, Seller, Admin)
- Responsive design
- Clean and modern UI
- Secure password hashing
- Session management

## Installation

1. **Database Setup**
   - Create a MySQL database named `ecommerce_db`
   - Import the `database.sql` file
   ```bash
   mysql -u root -p ecommerce_db < database.sql
   ```

2. **Configuration**
   - Update database credentials in `config/database.php` if needed
   ```php
   $host = 'localhost';
   $dbname = 'ecommerce_db';
   $username = 'root';
   $password = '';
   ```

3. **Web Server**
   - Place files in your web server directory (htdocs for XAMPP, www for WAMP)
   - Start Apache and MySQL
   - Access via `http://localhost/your-folder-name`

## Default Accounts

## Support Data
- New database tables `contacts` and `return_requests` store messages and return submissions.
- `return_requests` now optionally records `product_id` and `seller_id` (looked up by product name).
- Admin dashboard lists the most recent entries for each and shows seller name for returns.

Orders & tracking pages now display the names/quantities of products in each order.


### Admin
- Email: admin@shophub.com
- Password: admin123

### Sellers (4 demo accounts)
- seller1@example.com / admin123
- seller2@example.com / admin123
- seller3@example.com / admin123
- seller4@example.com / admin123

### Buyers (2 demo accounts)
- buyer1@example.com / admin123
- buyer2@example.com / admin123

## Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.3
- **Library**: jQuery 3.6
- **Icons**: Font Awesome 6.4

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser

> **Note:** To send password reset codes (and other notification emails),
> PHP's `mail()` function must be configured correctly. On local hosts like
> XAMPP/WAMP this often requires setting up an SMTP server or using a tool
> such as [MailHog](https://github.com/mailhog/MailHog), or switch to a
> library like PHPMailer and an external SMTP provider (Gmail, SendGrid,
> etc.). Otherwise the code will be displayed on screen for debugging.

## License

This project is open source and available for educational purposes.

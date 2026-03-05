# ShopHub - E-Commerce Web Application

Amazon-inspired full-featured e-commerce platform built with PHP, MySQL, Bootstrap, jQuery, HTML, and CSS.

## Features

### For Buyers
- Browse products by category
- Search functionality with pagination, filters, and autocomplete suggestions
- Product details with reviews
- Advanced REST APIs for products, cart, orders and authentication (JSON responses)
- Shopping cart management (add/update/remove/clear), persistent across sessions
- Checkout and order placement (API powered)
- Order history tracking, order item detail view and tracking
- Contact form, returns portal (with image upload) and help center for customer support
- User profile management

### For Sellers
- Seller dashboard with statistics
- Add/manage products
- View orders
- Track revenue
- Inventory management

### General Features
- User authentication (Login/Register) with AJAX API support and CSRF protection
- Login throttling and password hashing (bcrypt) for security
- Email verification and password reset workflow
- Multiple user types (Buyer, Seller, Admin)
- RESTful JSON APIs for core functionality (see /api/*)
- Cross-site request forgery (CSRF) tokens included on all forms
- Input sanitization and output escaping for XSS prevention
- Rate limiting on sensitive endpoints
- Responsive design
- Clean and modern UI with Bootstrap 5
- jQuery-powered dynamic components and modal dialogs
- Session management with persistent guest carts
- Autocomplete search suggestions and infinite scrolling
- Admin/seller dashboards with statistics, analytics and CRUD tools
- Export tools (CSV) and bulk product uploads
- Orders now include two separate contact columns (`phone`, `alt_phone`) as well as a free‑text “rider instructions” field for delivery notes.
- Checkout collects full address, email, primary phone, and optional secondary phone (must differ); primary phone is unique across orders (and also enforced on user accounts) to prevent duplicate contact numbers.


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

> **Database updates:**
> ```sql
> ALTER TABLE orders ADD COLUMN phone VARCHAR(20) DEFAULT NULL;
> ALTER TABLE orders ADD COLUMN alt_phone VARCHAR(20) DEFAULT NULL;
> ALTER TABLE orders ADD COLUMN rider_instructions TEXT DEFAULT NULL;
> ALTER TABLE orders ADD UNIQUE INDEX IF NOT EXISTS idx_orders_phone (phone);
> ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS idx_users_phone (phone);
> ```
> A unique index on `orders.phone` (and optionally `users.phone`) prevents duplicate primary contact numbers; checkout and the API perform validation and will notify users if a phone is already in use.

## Default Accounts

## Support Data
- New database tables `contacts` and `return_requests` store messages and return submissions.
- `return_requests` now includes `buyer_id` and a `status` field (pending/accepted/declined) and optionally records `product_id` and `seller_id` (looked up by product name).
- Admin and seller dashboards show shipping address on orders and allow updating status.
- Both dashboards display return requests for relevant sellers; admin and sellers can accept/decline requests. Decisions are visible to buyers when they are logged in.

**Migration note:**
If you already have a `return_requests` table, run the following SQL to add new columns:
```sql
ALTER TABLE return_requests 
  ADD buyer_id INT DEFAULT NULL, 
  ADD status VARCHAR(20) NOT NULL DEFAULT 'pending',
  ADD CONSTRAINT return_requests_ibfk_3 FOREIGN KEY (buyer_id) REFERENCES users(id);
```
Existing rows will default to `pending` and no buyer association.
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

<?php
// Temporary helper script to create/update the admin account.  It sets the
// password for admin@shophub.com to "admin123" (hashed) and also updates
// a handful of demo users.  Run it once from the browser/CLI if you can't
// log in.  The script now prints the stored ID and password hash so you can
// confirm the row actually exists and see what value is in the database.
//
// If login still fails after running, make sure you are using the same
// database (check config/database.php) and that there are no stray spaces
// around the email.  The login page accepts the plain text password with
// password_verify, so "admin123" should always work for this user.
require_once 'config/database.php';

$email = 'admin@shophub.com';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // enforce a single admin row: delete any existing admin accounts first so
    // we don't end up with duplicates that might confuse you when you log in.
    $pdo->exec("DELETE FROM users WHERE user_type = 'admin'");

    // now insert fresh
    $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, user_type) VALUES (?, ?, 'Admin User', 'admin')");
    $stmt->execute([$email, $hash]);
       echo "✓ Admin user recreated successfully!<br>";

    // optionally trim any trailing/leading spaces in the stored email just in case
    $stmt = $pdo->prepare("UPDATE users SET email = TRIM(email) WHERE user_type = 'admin'");
    $stmt->execute();
    // fetch and display the actual stored record for debugging
    $stmt = $pdo->prepare("SELECT id,email,password FROM users WHERE user_type = 'admin'");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if(count($rows)) {
        echo "<strong>Admin row(s) in database:</strong><br>";
        foreach($rows as $created) {
            echo "ID: " . $created['id'] . "<br>";
            echo "Email: " . htmlspecialchars($created['email']) . "<br>";
            echo "Password hash: " . htmlspecialchars($created['password']) . "<br>";
            echo "<hr>";
        }
        echo "(Use <code>admin@shophub.com</code> / <code>admin123</code> to log in.)<br>";
        echo "<em>If login still fails, verify the email exactly matches above and that you are
              connecting to the same database.</em><br>";
    }
    
    // Also fix seller passwords
    $sellers = [
        'seller1@example.com',
        'seller2@example.com', 
        'seller3@example.com',
        'seller4@example.com'
    ];
    
    foreach($sellers as $seller_email) {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hash, $seller_email]);
    }
    echo "<br>✓ All seller passwords also updated to: admin123<br>";
    
    // Fix buyer passwords
    $buyers = ['buyer1@example.com', 'buyer2@example.com'];
    foreach($buyers as $buyer_email) {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hash, $buyer_email]);
    }
    echo "✓ All buyer passwords also updated to: admin123<br>";
    
    echo "<br><strong>All accounts are now ready to use!</strong><br>";
    echo "<a href='login.php'>Go to Login Page</a>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

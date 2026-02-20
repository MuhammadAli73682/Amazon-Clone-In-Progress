<?php
// Generate password hash for admin123
echo "Password: admin123\n";
echo "Hash: " . password_hash('admin123', PASSWORD_DEFAULT) . "\n\n";

// Verify the hash
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
if(password_verify('admin123', $hash)) {
    echo "Hash verification: SUCCESS\n";
} else {
    echo "Hash verification: FAILED\n";
    echo "New hash: " . password_hash('admin123', PASSWORD_DEFAULT) . "\n";
}
?>

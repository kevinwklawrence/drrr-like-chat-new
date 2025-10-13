<?php
// setup_persistent_login.php - Add remember_token column for persistent login
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Persistent Login System Setup</h2>\n";
echo "<pre>\n";

try {
    // Check if remember_token column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
    
    if ($result->num_rows == 0) {
        echo "Adding remember_token column to users table...\n";
        $conn->query("ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) NULL AFTER password, ADD INDEX idx_remember_token (remember_token)");
        echo "✓ Added remember_token column\n";
    } else {
        echo "✓ remember_token column already exists\n";
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Persistent login system is ready!\n";
    echo "\nFeatures:\n";
    echo "- Users stay logged in after closing browser\n";
    echo "- Cookies last for 30 days\n";
    echo "- Manual logout clears remember token\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
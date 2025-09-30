<?php
// setup_dura_system.php - Setup Dura currency system
session_start();
include 'db_connect.php';

// Check if user is admin
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Dura Currency System Setup</h2>\n";
echo "<pre>\n";

try {
    // Check current users table structure
    $result = $conn->query("SHOW COLUMNS FROM users");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    echo "Current users table columns: " . implode(', ', $columns) . "\n\n";
    
    // Add dura column if not exists
    if (!in_array('dura', $columns)) {
        echo "Adding 'dura' column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN dura INT DEFAULT 0 NOT NULL AFTER is_admin");
        echo "✓ Added 'dura' column\n";
    } else {
        echo "✓ 'dura' column already exists\n";
    }
    
    // Add tokens column if not exists
    if (!in_array('tokens', $columns)) {
        echo "Adding 'tokens' column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN tokens INT DEFAULT 20 NOT NULL AFTER dura");
        echo "✓ Added 'tokens' column (default 20)\n";
    } else {
        echo "✓ 'tokens' column already exists\n";
    }
    
    // Add last_token_grant column if not exists
    if (!in_array('last_token_grant', $columns)) {
        echo "Adding 'last_token_grant' column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN last_token_grant TIMESTAMP NULL DEFAULT NULL AFTER tokens");
        echo "✓ Added 'last_token_grant' column\n";
    } else {
        echo "✓ 'last_token_grant' column already exists\n";
    }
    
    // Initialize all existing users with tokens
    echo "\nInitializing existing users...\n";
    $conn->query("UPDATE users SET tokens = 20 WHERE tokens IS NULL OR tokens = 0");
    $updated = $conn->affected_rows;
    echo "✓ Initialized $updated users with 20 tokens\n";
    
    // Create dura_transactions table for logging
    echo "\nCreating dura_transactions table...\n";
    $create_table = "CREATE TABLE IF NOT EXISTS dura_transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        from_user_id INT NOT NULL,
        to_user_id INT NOT NULL,
        amount INT NOT NULL,
        type ENUM('token', 'dura') NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_from_user (from_user_id),
        INDEX idx_to_user (to_user_id),
        INDEX idx_timestamp (timestamp)
    )";
    
    $conn->query($create_table);
    echo "✓ Created dura_transactions table\n";
    
    // Create shop_purchases table
    echo "\nCreating shop_purchases table...\n";
    $create_shop = "CREATE TABLE IF NOT EXISTS shop_purchases (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        item_id VARCHAR(50) NOT NULL,
        cost INT NOT NULL,
        currency ENUM('dura', 'tokens') NOT NULL,
        purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_item (item_id),
        INDEX idx_purchased (purchased_at)
    )";
    
    $conn->query($create_shop);
    echo "✓ Created shop_purchases table\n";
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Dura system is ready to use!\n";
    echo "\nSystem rules:\n";
    echo "- All users start with 0 Dura and 20 Tokens\n";
    echo "- Every 12 hours, users receive 10 Tokens (max 20)\n";
    echo "- 1 Token = 1 Dura when gifting\n";
    echo "- Users can also tip from their own Dura balance\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
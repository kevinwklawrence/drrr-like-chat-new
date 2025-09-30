<?php
// setup_inventory_system.php - Setup Inventory and Titles System
session_start();
include 'db_connect.php';

// Check if user is admin
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Inventory & Titles System Setup</h2>\n";
echo "<pre>\n";

try {
    // Create shop_items table
    echo "Creating shop_items table...\n";
    $create_shop_items = "CREATE TABLE IF NOT EXISTS shop_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        item_id VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        type ENUM('title', 'badge', 'effect', 'other') NOT NULL,
        rarity ENUM('common', 'rare', 'strange', 'legendary') DEFAULT 'common',
        cost INT NOT NULL,
        currency ENUM('dura', 'tokens') NOT NULL,
        icon VARCHAR(10),
        is_available TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_type (type),
        INDEX idx_rarity (rarity),
        INDEX idx_available (is_available)
    )";
    
    $conn->query($create_shop_items);
    echo "✓ Created shop_items table\n";
    
    // Create user_inventory table
    echo "\nCreating user_inventory table...\n";
    $create_inventory = "CREATE TABLE IF NOT EXISTS user_inventory (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        item_id VARCHAR(50) NOT NULL,
        acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_equipped TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_item (user_id, item_id),
        INDEX idx_user (user_id),
        INDEX idx_item (item_id),
        INDEX idx_equipped (is_equipped)
    )";
    
    $conn->query($create_inventory);
    echo "✓ Created user_inventory table\n";
    
    // Insert sample titles
    echo "\nInserting sample titles...\n";
    
    $sample_items = [
        ['vip_title', 'VIP', 'Exclusive VIP member', 'title', 'legendary', 1000000, 'dura', '👑'],
        ['veteran_title', 'Veteran', 'Long-time member', 'title', 'rare', 50000, 'dura', '🎖️'],
        ['newbie_title', 'Newbie', 'Just getting started', 'title', 'common', 100, 'tokens', '🌱'],
        ['chat_master', 'Chat Master', 'Master of conversation', 'title', 'strange', 25000, 'dura', '💬'],
        ['generous_title', 'Generous', 'Known for giving', 'title', 'rare', 10000, 'dura', '💝']
    ];
    
    foreach ($sample_items as $item) {
        $check = $conn->prepare("SELECT id FROM shop_items WHERE item_id = ?");
        $check->bind_param("s", $item[0]);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO shop_items (item_id, name, description, type, rarity, cost, currency, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssiss", $item[0], $item[1], $item[2], $item[3], $item[4], $item[5], $item[6], $item[7]);
            $stmt->execute();
            echo "  ✓ Added: {$item[1]} ({$item[4]})\n";
            $stmt->close();
        } else {
            echo "  - Skipped: {$item[1]} (already exists)\n";
        }
        $check->close();
    }
    
    // Remove old has_vip column if it exists (we're using inventory now)
    echo "\nChecking for legacy columns...\n";
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'has_vip'");
    if ($check->num_rows > 0) {
        echo "  Note: has_vip column exists but is deprecated. Using inventory system instead.\n";
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Inventory and Titles system is ready!\n";
    echo "\nSystem features:\n";
    echo "- Shop items stored in shop_items table\n";
    echo "- User inventory stored in user_inventory table\n";
    echo "- Multiple titles support\n";
    echo "- Rarity system: Common, Rare, Strange, Legendary\n";
    echo "- Equip/unequip functionality\n";
    echo "- 5 sample titles added to shop\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
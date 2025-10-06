<?php
// setup_bundles.php - Setup Bundle System
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Bundle System Setup</h2>\n";
echo "<pre>\n";

try {
    // Update shop_items type enum to include 'bundle'
    echo "Updating shop_items type enum...\n";
    $alter_type = "ALTER TABLE shop_items 
        MODIFY COLUMN type ENUM('title', 'avatar', 'color', 'badge', 'effect', 'special', 'bundle', 'other') NOT NULL";
    $conn->query($alter_type);
    echo "✓ Added 'bundle' to type enum\n";
    
    // Create bundle_items table
    echo "\nCreating bundle_items table...\n";
    $create_table = "CREATE TABLE IF NOT EXISTS bundle_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        bundle_id VARCHAR(50) NOT NULL,
        item_id VARCHAR(50) NOT NULL,
        FOREIGN KEY (bundle_id) REFERENCES shop_items(item_id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES shop_items(item_id) ON DELETE CASCADE,
        UNIQUE KEY unique_bundle_item (bundle_id, item_id),
        INDEX idx_bundle (bundle_id)
    )";
    $conn->query($create_table);
    echo "✓ Created bundle_items table\n";
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Bundle system is ready!\n";
    echo "\n=== HOW TO CREATE BUNDLES ===\n";
    echo "1. Create a bundle item in shop_items with type='bundle'\n";
    echo "2. Add items to the bundle in bundle_items table\n";
    echo "\nExample:\n";
    echo "-- Create bundle item\n";
    echo "INSERT INTO shop_items (item_id, name, description, type, rarity, cost, currency, icon)\n";
    echo "VALUES ('bundle_starter', 'Starter Pack', 'Get 2 items!', 'bundle', 'rare', 15000, 'dura', '📦');\n";
    echo "\n-- Add items to bundle\n";
    echo "INSERT INTO bundle_items (bundle_id, item_id) VALUES ('bundle_starter', 'newbie_title');\n";
    echo "INSERT INTO bundle_items (bundle_id, item_id) VALUES ('bundle_starter', 'color_blue');\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
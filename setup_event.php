<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Add 'special' Item Type</h2>\n";
echo "<pre>\n";

try {
    echo "Updating shop_items type enum to include 'special'...\n";
    
    $alter_type = "ALTER TABLE shop_items 
        MODIFY COLUMN type ENUM('title', 'avatar', 'badge', 'effect', 'special', 'other') NOT NULL";
    $conn->query($alter_type);
    
    echo "✓ Added 'special' to item type enum\n";
    
    echo "\n=== SUCCESS ===\n";
    echo "You can now create special items that trigger actions on purchase!\n";
    echo "\nExample special items:\n";
    echo "- VIP status\n";
    echo "- Username color change\n";
    echo "- Profile customization unlocks\n";
    echo "- One-time boosts or bonuses\n";
    
    echo "\n=== HOW TO CREATE SPECIAL ITEMS ===\n";
    echo "Special items should be marked as type='special'\n";
    echo "They cannot be equipped and trigger actions when purchased.\n";
    echo "\nExample SQL:\n";
    echo "INSERT INTO shop_items (item_id, name, description, type, rarity, cost, currency, icon)\n";
    echo "VALUES ('special_vip', 'VIP Status', 'Get VIP benefits', 'special', 'legendary', 100000, 'dura', '👑');\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
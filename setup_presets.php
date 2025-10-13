<?php
// setup_presets.php - Create profile presets table
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Profile Presets Setup</h2>\n<pre>\n";

try {
    // Create profile_presets table
    $conn->query("CREATE TABLE IF NOT EXISTS profile_presets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        preset_name VARCHAR(50) NOT NULL,
        avatar VARCHAR(255) NOT NULL,
        color VARCHAR(50) NOT NULL,
        avatar_hue INT DEFAULT 0 NOT NULL,
        avatar_saturation INT DEFAULT 100 NOT NULL,
        bubble_hue INT DEFAULT 0 NOT NULL,
        bubble_saturation INT DEFAULT 100 NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    )");
    echo "✓ Created profile_presets table\n";
    
    echo "\n✅ Profile Presets System Setup Complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
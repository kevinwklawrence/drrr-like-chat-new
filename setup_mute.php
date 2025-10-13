<?php
// setup_mute_system.php - Setup mute system
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Mute System Setup</h2>\n<pre>\n";

try {
    // Create muted_users table
    $conn->query("CREATE TABLE IF NOT EXISTS muted_users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        muter_user_id_string VARCHAR(255) NOT NULL,
        muted_user_id_string VARCHAR(255) NOT NULL,
        muted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_mute (muter_user_id_string, muted_user_id_string),
        INDEX idx_muter (muter_user_id_string),
        INDEX idx_muted (muted_user_id_string)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "✓ Created muted_users table\n";
    echo "\nMute system is ready!\n";
    echo "- Users can mute other users\n";
    echo "- Muted users' messages will be hidden\n";
    echo "- Muted users cannot whisper the muter\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
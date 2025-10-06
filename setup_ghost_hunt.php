<?php
// setup_ghost_hunt.php - Create ghost hunt table
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Admin only");
}

echo "<h2>Ghost Hunt System Setup</h2>\n<pre>\n";

try {
    // Create ghost_hunt_events table
    $sql = "CREATE TABLE IF NOT EXISTS ghost_hunt_events (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_id INT NOT NULL,
        ghost_phrase VARCHAR(255) NOT NULL,
        reward_amount INT DEFAULT 10,
        spawned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        claimed_by_user_id_string VARCHAR(255) DEFAULT NULL,
        claimed_at TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_room_active (room_id, is_active),
        INDEX idx_spawned (spawned_at),
        FOREIGN KEY (room_id) REFERENCES chatrooms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "✅ Created ghost_hunt_events table\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
    
    echo "\n✅ Ghost Hunt system ready!\n";
    echo "\nUsage:\n";
    echo "1. Ghost hunts spawn automatically every 5-15 minutes\n";
    echo "2. Users type the displayed phrase to claim reward\n";
    echo "3. First correct typer wins event currency\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
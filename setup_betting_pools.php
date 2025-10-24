<?php
// setup_betting_pools.php - Setup Betting Pools system
session_start();
include 'db_connect.php';

// Check if user is admin
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Betting Pools System Setup</h2>\n";
echo "<pre>\n";

try {
    // Create betting_pools table
    echo "Creating betting_pools table...\n";
    $create_pools_table = "CREATE TABLE IF NOT EXISTS betting_pools (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        created_by INT NULL,
        created_by_user_id_string VARCHAR(255) NOT NULL,
        created_by_username VARCHAR(255) NOT NULL,
        total_pool INT DEFAULT 0 NOT NULL,
        status ENUM('active', 'closed', 'completed') DEFAULT 'active' NOT NULL,
        winner_user_id_string VARCHAR(255) NULL,
        winner_username VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        closed_at TIMESTAMP NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_room (room_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    )";

    $conn->query($create_pools_table);
    echo "✓ Created betting_pools table\n";

    // Create betting_pool_bets table
    echo "\nCreating betting_pool_bets table...\n";
    $create_bets_table = "CREATE TABLE IF NOT EXISTS betting_pool_bets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        pool_id INT NOT NULL,
        user_id INT NULL,
        user_id_string VARCHAR(255) NOT NULL,
        username VARCHAR(255) NOT NULL,
        bet_amount INT NOT NULL,
        placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pool_id) REFERENCES betting_pools(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_pool (pool_id),
        INDEX idx_user_id_string (user_id_string),
        INDEX idx_placed_at (placed_at),
        UNIQUE KEY unique_user_per_pool (pool_id, user_id_string)
    )";

    $conn->query($create_bets_table);
    echo "✓ Created betting_pool_bets table\n";

    echo "\n=== SETUP COMPLETE ===\n";
    echo "Betting Pools system is ready to use!\n";
    echo "\nSystem features:\n";
    echo "- Hosts/Moderators/Admins can create betting pools in rooms\n";
    echo "- Users can place bets with their Dura into the pool\n";
    echo "- Bet amounts are displayed in the user list\n";
    echo "- Hosts/Moderators/Admins can select a winner who receives the total pool\n";
    echo "- Each user can only bet once per pool\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>

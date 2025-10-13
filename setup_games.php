<?php
// setup_halloween_games.php - Setup Pumpkin Smash, Trick-or-Treat, Shadow Duel
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Admin only");
}

echo "<h2>Halloween Games Setup</h2>\n<pre>\n";

try {
    // 1. PUMPKIN SMASH TABLE
    echo "Setting up Pumpkin Smash...\n";
    $pumpkin_sql = "CREATE TABLE IF NOT EXISTS pumpkin_smash_events (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_id INT NOT NULL,
        reward_amount INT DEFAULT 15,
        spawned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        claimed_by_user_id INT DEFAULT NULL,
        claimed_at TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_room_active (room_id, is_active),
        FOREIGN KEY (room_id) REFERENCES chatrooms(id) ON DELETE CASCADE,
        FOREIGN KEY (claimed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($pumpkin_sql)) {
        echo "✅ Created pumpkin_smash_events table\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
    
    // 2. TRICK-OR-TREAT TABLE (add column to users)
    echo "\nSetting up Trick-or-Treat Roulette...\n";
    $check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'last_trick_treat'");
    if ($check_col->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN last_trick_treat TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Added last_trick_treat column to users\n";
    } else {
        echo "✅ last_trick_treat column already exists\n";
    }
    
    // 3. SHADOW DUEL TABLES
    echo "\nSetting up Shadow Duel...\n";
    $duel_sql = "CREATE TABLE IF NOT EXISTS shadow_duels (
        id INT PRIMARY KEY AUTO_INCREMENT,
        challenger_id INT NOT NULL,
        opponent_id INT NOT NULL,
        bet_amount INT NOT NULL,
        room_id INT NOT NULL,
        state ENUM('pending', 'active', 'finished', 'cancelled') DEFAULT 'pending',
        challenger_move ENUM('slash', 'block', 'curse', NULL) DEFAULT NULL,
        opponent_move ENUM('slash', 'block', 'curse', NULL) DEFAULT NULL,
        challenger_score INT DEFAULT 0,
        opponent_score INT DEFAULT 0,
        current_round INT DEFAULT 1,
        winner_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 5 MINUTE),
        INDEX idx_challenger (challenger_id),
        INDEX idx_opponent (opponent_id),
        INDEX idx_state (state),
        INDEX idx_expires (expires_at),
        FOREIGN KEY (challenger_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (opponent_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (room_id) REFERENCES chatrooms(id) ON DELETE CASCADE,
        FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($duel_sql)) {
        echo "✅ Created shadow_duels table\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "\n🎃 Pumpkin Smash: Click-to-claim events spawn in rooms\n";
    echo "🍬 Trick-or-Treat: Daily spin for random rewards (5-100 currency)\n";
    echo "⚔️ Shadow Duel: PvP turn-based battles (rock/paper/scissors style)\n";
    echo "\nNext steps:\n";
    echo "1. Add pumpkin spawn cron: */8 * * * * php api/spawn_pumpkin.php\n";
    echo "2. Users can access Trick-or-Treat from their profile/shop\n";
    echo "3. Users can challenge each other to duels in chat rooms\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
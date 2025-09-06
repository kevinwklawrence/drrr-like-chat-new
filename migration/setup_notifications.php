<?php
// migration/setup_notifications.php - Database setup for enhanced notification system
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../db_connect.php';

echo "<h2>Enhanced Notification System Setup</h2>\n";
echo "<pre>\n";

if (!$conn || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

try {
    $conn->begin_transaction();
    
    echo "=== SETTING UP ENHANCED NOTIFICATION SYSTEM ===\n\n";
    
    // ===== STEP 1: ENSURE USER_MENTIONS TABLE EXISTS =====
    echo "STEP 1: Creating/updating user_mentions table...\n";
    
    $user_mentions_table = "CREATE TABLE IF NOT EXISTS user_mentions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_id INT NOT NULL,
        message_id INT NOT NULL,
        mentioned_user_id_string VARCHAR(255) NOT NULL,
        mentioned_by_user_id_string VARCHAR(255) NOT NULL,
        mention_type ENUM('mention', 'reply') DEFAULT 'mention',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_mentions_user (mentioned_user_id_string, room_id),
        INDEX idx_user_mentions_message (message_id),
        INDEX idx_user_mentions_unread (mentioned_user_id_string, is_read),
        INDEX idx_user_mentions_room (room_id),
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($user_mentions_table)) {
        echo "✓ user_mentions table created/verified\n";
    } else {
        throw new Exception("Failed to create user_mentions table: " . $conn->error);
    }
    
    // ===== STEP 2: ENSURE FRIENDS TABLE EXISTS =====
    echo "\nSTEP 2: Creating/updating friends table...\n";
    
    $friends_table = "CREATE TABLE IF NOT EXISTS friends (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        friend_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_friends_user (user_id),
        INDEX idx_friends_friend (friend_id),
        INDEX idx_friends_status (status),
        INDEX idx_friends_pending (friend_id, status),
        UNIQUE KEY unique_friendship (user_id, friend_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($friends_table)) {
        echo "✓ friends table created/verified\n";
    } else {
        throw new Exception("Failed to create friends table: " . $conn->error);
    }
    
    // ===== STEP 3: ENSURE PRIVATE_MESSAGES TABLE EXISTS =====
    echo "\nSTEP 3: Creating/updating private_messages table...\n";
    
    $private_messages_table = "CREATE TABLE IF NOT EXISTS private_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        color VARCHAR(50) DEFAULT 'blue',
        avatar_hue INT DEFAULT 0,
        avatar_saturation INT DEFAULT 100,
        bubble_hue INT DEFAULT 0,
        bubble_saturation INT DEFAULT 100,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pm_recipient (recipient_id),
        INDEX idx_pm_sender (sender_id),
        INDEX idx_pm_conversation (sender_id, recipient_id),
        INDEX idx_pm_unread (recipient_id, is_read),
        INDEX idx_pm_created (created_at),
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($private_messages_table)) {
        echo "✓ private_messages table created/verified\n";
    } else {
        throw new Exception("Failed to create private_messages table: " . $conn->error);
    }
    
    // ===== STEP 4: ADD ACCEPTING_WHISPERS COLUMN TO USERS =====
    echo "\nSTEP 4: Adding accepting_whispers column to users table...\n";
    
    $check_whispers_col = $conn->query("SHOW COLUMNS FROM users LIKE 'accepting_whispers'");
    if ($check_whispers_col->num_rows === 0) {
        if ($conn->query("ALTER TABLE users ADD COLUMN accepting_whispers TINYINT(1) DEFAULT 1")) {
            echo "✓ Added accepting_whispers column to users table\n";
        } else {
            throw new Exception("Failed to add accepting_whispers column: " . $conn->error);
        }
    } else {
        echo "✓ accepting_whispers column already exists\n";
    }
    
    // ===== STEP 5: ADD REPLY_TO COLUMN TO MESSAGES =====
    echo "\nSTEP 5: Adding reply_to column to messages table...\n";
    
    $check_reply_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'reply_to'");
    if ($check_reply_col->num_rows === 0) {
        if ($conn->query("ALTER TABLE messages ADD COLUMN reply_to INT NULL DEFAULT NULL, ADD INDEX idx_messages_reply (reply_to)")) {
            echo "✓ Added reply_to column to messages table\n";
        } else {
            throw new Exception("Failed to add reply_to column: " . $conn->error);
        }
    } else {
        echo "✓ reply_to column already exists\n";
    }
    
    // ===== STEP 6: ADD MENTIONS COLUMN TO MESSAGES =====
    echo "\nSTEP 6: Adding mentions column to messages table...\n";
    
    $check_mentions_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'mentions'");
    if ($check_mentions_col->num_rows === 0) {
        if ($conn->query("ALTER TABLE messages ADD COLUMN mentions JSON NULL DEFAULT NULL")) {
            echo "✓ Added mentions column to messages table\n";
        } else {
            echo "! Could not add mentions column (JSON may not be supported): " . $conn->error . "\n";
            // Try with TEXT instead
            if ($conn->query("ALTER TABLE messages ADD COLUMN mentions TEXT NULL DEFAULT NULL")) {
                echo "✓ Added mentions column as TEXT to messages table\n";
            } else {
                throw new Exception("Failed to add mentions column: " . $conn->error);
            }
        }
    } else {
        echo "✓ mentions column already exists\n";
    }
    
    // ===== STEP 7: CREATE INDEXES FOR PERFORMANCE =====
    echo "\nSTEP 7: Creating additional indexes for performance...\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_messages_timestamp ON messages(timestamp)",
        "CREATE INDEX IF NOT EXISTS idx_messages_room_timestamp ON messages(room_id, timestamp)",
        "CREATE INDEX IF NOT EXISTS idx_messages_user_string ON messages(user_id_string)",
        "CREATE INDEX IF NOT EXISTS idx_user_mentions_created ON user_mentions(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_friends_created ON friends(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_pm_timestamp ON private_messages(created_at)"
    ];
    
    foreach ($indexes as $index_sql) {
        if ($conn->query($index_sql)) {
            echo "✓ " . substr($index_sql, 0, 50) . "...\n";
        } else {
            echo "! Warning: Could not create index: " . $conn->error . "\n";
        }
    }
    
    // ===== STEP 8: CLEAN UP OLD DATA =====
    echo "\nSTEP 8: Cleaning up old notification data...\n";
    
    // Remove old read notifications older than 30 days
    $cleanup_mentions = "DELETE FROM user_mentions WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    if ($conn->query($cleanup_mentions)) {
        $affected = $conn->affected_rows;
        echo "✓ Cleaned up $affected old read mentions\n";
    } else {
        echo "! Warning: Could not clean up old mentions: " . $conn->error . "\n";
    }
    
    // Remove old read private messages older than 90 days
    $cleanup_pms = "DELETE FROM private_messages WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    if ($conn->query($cleanup_pms)) {
        $affected = $conn->affected_rows;
        echo "✓ Cleaned up $affected old read private messages\n";
    } else {
        echo "! Warning: Could not clean up old private messages: " . $conn->error . "\n";
    }
    
    $conn->commit();
    
    echo "\n=== SETUP COMPLETED SUCCESSFULLY ===\n";
    echo "Enhanced notification system is now ready!\n\n";
    
    echo "Features enabled:\n";
    echo "• Mention notifications\n";
    echo "• Reply notifications\n";
    echo "• Friend request notifications\n";
    echo "• Private message notifications\n";
    echo "• Mobile-responsive interface\n";
    echo "• Performance optimized with indexes\n";
    echo "• Automatic cleanup of old notifications\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Setup failed. Please check the error above and try again.\n";
}

if (isset($conn)) {
    $conn->close();
}

echo "</pre>\n";
?>

<?php
// api/cleanup_notifications.php - Maintenance script for notifications
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow admins or direct server access
if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    if (php_sapi_name() !== 'cli') {
        http_response_code(403);
        exit('Access denied');
    }
}

include '../db_connect.php';

try {
    // Clean up old read notifications (older than 30 days)
    $cleanup_mentions = $conn->prepare("DELETE FROM user_mentions WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $cleanup_mentions->execute();
    $mentions_cleaned = $cleanup_mentions->affected_rows;
    $cleanup_mentions->close();
    
    // Clean up old read private messages (older than 90 days)
    $cleanup_pms = $conn->prepare("DELETE FROM private_messages WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $cleanup_pms->execute();
    $pms_cleaned = $cleanup_pms->affected_rows;
    $cleanup_pms->close();
    
    // Clean up orphaned mentions (messages that no longer exist)
    $cleanup_orphaned = $conn->prepare("DELETE um FROM user_mentions um LEFT JOIN messages m ON um.message_id = m.id WHERE m.id IS NULL");
    $cleanup_orphaned->execute();
    $orphaned_cleaned = $cleanup_orphaned->affected_rows;
    $cleanup_orphaned->close();
    
    echo json_encode([
        'status' => 'success',
        'mentions_cleaned' => $mentions_cleaned,
        'private_messages_cleaned' => $pms_cleaned,
        'orphaned_cleaned' => $orphaned_cleaned,
        'message' => 'Notification cleanup completed'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Cleanup failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
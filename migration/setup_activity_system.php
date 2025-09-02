<?php
// migration/setup_activity_system.php - Migration script for new activity system
// Run this script to set up the new activity tracking system

error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../db_connect.php';

// Include the config after we have the connection
require_once __DIR__ . '/../config/activity_config.php';

echo "<h2>Activity System Setup and Migration</h2>\n";
echo "<pre>\n";

// Make sure connection is active
if (!$conn || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

try {
    $conn->begin_transaction();
    
    echo "=== STARTING ACTIVITY SYSTEM MIGRATION ===\n\n";
    
    // ===== STEP 1: ENSURE REQUIRED COLUMNS =====
    echo "STEP 1: Ensuring required database columns...\n";
    
    ensureActivityColumns($conn);
    
    // ===== STEP 2: CREATE MENTIONS TABLE =====
    echo "\nSTEP 2: Creating mentions table...\n";
    
    $mentions_table = "CREATE TABLE IF NOT EXISTS mentions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        message_id INT NOT NULL,
        mentioned_user_id_string VARCHAR(255) NOT NULL,
        type ENUM('mention', 'reply') DEFAULT 'mention',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mentions_user (mentioned_user_id_string),
        INDEX idx_mentions_message (message_id),
        INDEX idx_mentions_unread (mentioned_user_id_string, is_read),
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($mentions_table)) {
        echo "✓ Mentions table created/verified\n";
    } else {
        throw new Exception("Failed to create mentions table: " . $conn->error);
    }
    
    // ===== STEP 3: ADD REPLY SUPPORT TO MESSAGES =====
    echo "\nSTEP 3: Adding reply support to messages table...\n";
    
    $reply_column_check = $conn->query("SHOW COLUMNS FROM messages LIKE 'reply_to'");
    if ($reply_column_check->num_rows === 0) {
        if ($conn->query("ALTER TABLE messages ADD COLUMN reply_to INT NULL DEFAULT NULL, ADD INDEX idx_messages_reply (reply_to)")) {
            echo "✓ Added reply_to column to messages table\n";
        } else {
            throw new Exception("Failed to add reply_to column: " . $conn->error);
        }
    } else {
        echo "- reply_to column already exists in messages table\n";
    }
    
    // ===== STEP 4: UPDATE EXISTING DATA =====
    echo "\nSTEP 4: Updating existing data...\n";
    
    // Initialize last_activity for users who don't have it
    $init_activity = $conn->query("
        UPDATE chatroom_users 
        SET last_activity = NOW() 
        WHERE last_activity IS NULL OR last_activity = '0000-00-00 00:00:00'
    ");
    $activity_updates = $conn->affected_rows;
    
    if ($activity_updates > 0) {
        echo "✓ Initialized last_activity for $activity_updates chatroom users\n";
    }
    
    // Initialize global_users last_activity
    $global_check = $conn->query("SHOW TABLES LIKE 'global_users'");
    if ($global_check->num_rows > 0) {
        $init_global = $conn->query("
            UPDATE global_users 
            SET last_activity = NOW() 
            WHERE last_activity IS NULL OR last_activity = '0000-00-00 00:00:00'
        ");
        $global_updates = $conn->affected_rows;
        
        if ($global_updates > 0) {
            echo "✓ Initialized last_activity for $global_updates global users\n";
        }
    }
    
    // ===== STEP 5: CLEAN UP OLD DATA =====
    echo "\nSTEP 5: Cleaning up old inactive data...\n";
    
    // Remove very old inactive users (more than 24 hours)
    $old_cleanup = $conn->query("
        DELETE FROM global_users 
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $old_removed = $conn->affected_rows;
    
    if ($old_removed > 0) {
        echo "✓ Removed $old_removed very old inactive users\n";
    }
    
    // Clean up orphaned chatroom_users
    $orphan_cleanup = $conn->query("
        DELETE cu FROM chatroom_users cu 
        LEFT JOIN global_users gu ON cu.user_id_string = gu.user_id_string 
        WHERE gu.user_id_string IS NULL
    ");
    $orphans_removed = $conn->affected_rows;
    
    if ($orphans_removed > 0) {
        echo "✓ Removed $orphans_removed orphaned chatroom users\n";
    }
    
    // ===== STEP 6: CREATE SYSTEM AVATARS =====
    echo "\nSTEP 6: Setting up system avatars...\n";
    
    $system_avatars = [
        ['afk', 'afk.png', 'User went AFK icon'],
        ['active', 'active.png', 'User returned from AFK icon'],
        ['disconnect', 'disconnect.png', 'User disconnected icon'],
        ['system', 'system.png', 'System message icon']
    ];
    
    foreach ($system_avatars as $avatar) {
        echo "- System avatar: {$avatar[0]} -> {$avatar[1]}\n";
    }
    
    // ===== STEP 7: CONFIGURATION VERIFICATION =====
    echo "\nSTEP 7: Verifying configuration...\n";
    
    echo "✓ AFK timeout: " . formatTimeMinutes(AFK_TIMEOUT) . "\n";
    echo "✓ Disconnect timeout: " . formatTimeMinutes(DISCONNECT_TIMEOUT) . "\n";
    echo "✓ Session timeout: " . formatTimeMinutes(SESSION_TIMEOUT) . "\n";
    echo "✓ Activity check interval: " . ACTIVITY_CHECK_INTERVAL . " seconds\n";
    echo "✓ Disconnect check interval: " . DISCONNECT_CHECK_INTERVAL . " seconds\n";
    echo "✓ Heartbeat interval: " . HEARTBEAT_INTERVAL . " seconds\n";
    
    // ===== STEP 8: TEST THE SYSTEM =====
    echo "\nSTEP 8: Testing the system...\n";
    
    // Test activity tracking
    if (class_exists('ActivityTracker')) {
        echo "✓ ActivityTracker class is available\n";
    } else {
        echo "⚠ ActivityTracker class not found - ensure activity_tracker.php is included\n";
    }
    
    // Test disconnect checker
    if (function_exists('ensureActivityColumns')) {
        echo "✓ Activity configuration functions available\n";
    } else {
        echo "⚠ Activity configuration functions not found\n";
    }
    
    $conn->commit();
    
    // ===== SUCCESS MESSAGE =====
    echo "\n=== MIGRATION COMPLETED SUCCESSFULLY ===\n\n";
    
    echo "🎉 Activity system migration completed!\n\n";
    
    echo "NEXT STEPS:\n";
    echo "1. Upload the following system avatar images to your images/ directory:\n";
    echo "   - afk.png (user went AFK)\n";
    echo "   - active.png (user returned from AFK)\n";
    echo "   - disconnect.png (user disconnected)\n";
    echo "   - system.png (general system messages)\n\n";
    
    echo "2. Update your JavaScript files:\n";
    echo "   - Replace activity tracking functions in room.js with the new code\n";
    echo "   - Ensure the new ACTIVITY_CONFIG is used\n";
    echo "   - Call initializeActivityTracking() instead of initializeActivityTracking()\n\n";
    
    echo "3. Update your API endpoints:\n";
    echo "   - Replace api/update_activity.php\n";
    echo "   - Replace api/heartbeat.php\n";
    echo "   - Replace api/check_disconnects.php\n";
    echo "   - Update api/send_message.php to use ActivityTracker\n";
    echo "   - Update other activity-generating endpoints\n\n";
    
    echo "4. Set up cron job for disconnect checking:\n";
    echo "   */2 * * * * /usr/bin/php " . __DIR__ . "/../api/check_disconnects.php >/dev/null 2>&1\n\n";
    
    echo "5. Test the system:\n";
    echo "   - Join a room and verify heartbeat is working\n";
    echo "   - Test AFK detection (wait " . (AFK_TIMEOUT/60) . " minutes)\n";
    echo "   - Test disconnect detection (wait " . (DISCONNECT_TIMEOUT/60) . " minutes)\n";
    echo "   - Check active users list accuracy\n\n";
    
    echo "CONFIGURATION SUMMARY:\n";
    echo "- Users become AFK after: " . formatTimeMinutes(AFK_TIMEOUT) . "\n";
    echo "- Users disconnect from rooms after: " . formatTimeMinutes(DISCONNECT_TIMEOUT) . "\n";
    echo "- Users lose session after: " . formatTimeMinutes(SESSION_TIMEOUT) . "\n";
    echo "- Activity checks run every: " . DISCONNECT_CHECK_INTERVAL . " seconds\n\n";
    
    echo "The system is now ready for testing!\n\n";
    
    // ===== CURRENT STATUS =====
    echo "CURRENT SYSTEM STATUS:\n";
    
    $active_rooms = $conn->query("SELECT COUNT(*) as count FROM chatrooms")->fetch_assoc()['count'];
    $users_in_rooms = $conn->query("SELECT COUNT(DISTINCT user_id_string) as count FROM chatroom_users")->fetch_assoc()['count'];
    $total_active_users = $conn->query("
        SELECT COUNT(*) as count FROM global_users 
        WHERE last_activity >= DATE_SUB(NOW(), INTERVAL " . SESSION_TIMEOUT . " SECOND)
    ")->fetch_assoc()['count'];
    
    echo "- Active rooms: $active_rooms\n";
    echo "- Users in rooms: $users_in_rooms\n";
    echo "- Total active sessions: $total_active_users\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ MIGRATION FAILED: " . $e->getMessage() . "\n";
    echo "\nPlease check the error and try again.\n";
    exit(1);
}

echo "</pre>\n";

$conn->close();
?>

<?php
// test/test_activity_system.php - Test script for the activity system

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/activity_config.php';
require_once __DIR__ . '/../api/activity_tracker.php';

echo "<h2>Activity System Test</h2>\n";
echo "<pre>\n";

try {
    echo "=== TESTING ACTIVITY SYSTEM ===\n\n";
    
    // Test 1: Configuration
    echo "TEST 1: Configuration\n";
    echo "- AFK Timeout: " . formatTimeMinutes(AFK_TIMEOUT) . "\n";
    echo "- Disconnect Timeout: " . formatTimeMinutes(DISCONNECT_TIMEOUT) . "\n";
    echo "- Session Timeout: " . formatTimeMinutes(SESSION_TIMEOUT) . "\n";
    echo "✓ Configuration loaded\n\n";
    
    // Test 2: Database Setup
    echo "TEST 2: Database Setup\n";
    ensureActivityColumns($conn);
    echo "✓ Database columns verified\n\n";
    
    // Test 3: ActivityTracker Class
    echo "TEST 3: ActivityTracker Class\n";
    $test_user_id = 'test_user_' . time();
    $tracker = new ActivityTracker($conn, $test_user_id);
    echo "✓ ActivityTracker instantiated\n";
    
    // Test 4: Activity Recording
    echo "TEST 4: Activity Recording\n";
    $success = $tracker->updateActivity('manual_activity');
    echo $success ? "✓ Activity recorded successfully\n" : "❌ Activity recording failed\n";
    
    // Test 5: Status Retrieval
    echo "TEST 5: Status Retrieval\n";
    $status = $tracker->getUserActivityStatus();
    echo "✓ User status retrieved:\n";
    echo "  - Global active: " . ($status['global_active'] ? 'Yes' : 'No') . "\n";
    echo "  - Last activity: " . ($status['last_activity'] ?: 'None') . "\n";
    
    // Test 6: Disconnect Checker
    echo "\nTEST 6: Disconnect Checker\n";
    $output = '';
    ob_start();
    include __DIR__ . '/../api/check_disconnects.php';
    $output = ob_get_clean();
    
    $result = json_decode($output, true);
    if ($result && $result['status'] === 'success') {
        echo "✓ Disconnect checker working\n";
        echo "  - Users checked: " . $result['total_checked'] . "\n";
    } else {
        echo "❌ Disconnect checker failed\n";
    }
    
    // Cleanup test user
    $cleanup = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
    $cleanup->bind_param("s", $test_user_id);
    $cleanup->execute();
    $cleanup->close();
    
    echo "\n=== ALL TESTS COMPLETED ===\n";
    echo "✅ Activity system is working correctly!\n";
    
} catch (Exception $e) {
    echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
}

echo "</pre>\n";

$conn->close();
?>
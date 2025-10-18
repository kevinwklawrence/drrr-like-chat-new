<?php
/**
 * Automated Setup Script for SSE to Polling Conversion
 * This will automatically add event triggers to all necessary API files
 * 
 * WARNING: This modifies PHP files. Backup your files first!
 * Run from browser: http://yoursite.com/api/auto_setup_polling.php
 */

session_start();

// Security check - only admin can run this
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("❌ Admin access required");
}

echo "<html><head><title>Polling System Setup</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #00ff00; }
h1, h2, h3 { color: #00ff00; }
.success { color: #00ff00; }
.error { color: #ff0000; }
.info { color: #ffaa00; }
.section { margin: 20px 0; padding: 10px; border: 1px solid #333; background: #0a0a0a; }
pre { background: #000; padding: 10px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🚀 SSE to Polling Conversion - Automated Setup</h1>";

// Helper function to backup files
function backupFile($filepath) {
    if (file_exists($filepath)) {
        $backup = $filepath . '.backup_' . date('Y-m-d_H-i-s');
        if (copy($filepath, $backup)) {
            echo "<div class='success'>✅ Backed up: " . basename($filepath) . " → " . basename($backup) . "</div>";
            return true;
        } else {
            echo "<div class='error'>❌ Failed to backup: " . basename($filepath) . "</div>";
            return false;
        }
    }
    return true;
}

// Helper function to add code after a specific line
function addCodeAfterLine($filepath, $searchPattern, $codeToAdd, $description) {
    if (!file_exists($filepath)) {
        echo "<div class='error'>❌ File not found: $filepath</div>";
        return false;
    }
    
    $content = file_get_contents($filepath);
    
    // Check if code already exists
    if (strpos($content, 'createMessageEvent') !== false) {
        echo "<div class='info'>⚠️  Already modified: " . basename($filepath) . " (skipping)</div>";
        return true;
    }
    
    // Find the pattern and add code after it
    if (preg_match($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        $insertPosition = $matches[0][1] + strlen($matches[0][0]);
        
        $newContent = substr($content, 0, $insertPosition) . 
                     "\n\n" . $codeToAdd . "\n" . 
                     substr($content, $insertPosition);
        
        if (file_put_contents($filepath, $newContent)) {
            echo "<div class='success'>✅ Added event trigger: " . basename($filepath) . " - $description</div>";
            return true;
        } else {
            echo "<div class='error'>❌ Failed to modify: " . basename($filepath) . "</div>";
            return false;
        }
    } else {
        echo "<div class='error'>❌ Pattern not found in: " . basename($filepath) . " - $description</div>";
        return false;
    }
}

// ============================================================================
// PHASE 1: Database Setup
// ============================================================================
echo "<div class='section'><h2>📊 Phase 1: Database Setup</h2>";

include '../db_connect.php';

// Create message_events table
$sql1 = "CREATE TABLE IF NOT EXISTS message_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_event (room_id, id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB";

if ($conn->query($sql1)) {
    echo "<div class='success'>✅ Created/verified message_events table</div>";
} else {
    echo "<div class='error'>❌ Error creating message_events: " . $conn->error . "</div>";
}

// Create room_state_cache table
$sql2 = "CREATE TABLE IF NOT EXISTS room_state_cache (
    room_id INT NOT NULL,
    state_type VARCHAR(50) NOT NULL,
    state_hash VARCHAR(32) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id, state_type),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB";

if ($conn->query($sql2)) {
    echo "<div class='success'>✅ Created/verified room_state_cache table</div>";
} else {
    echo "<div class='error'>❌ Error creating room_state_cache: " . $conn->error . "</div>";
}

echo "</div>";

// ============================================================================
// PHASE 2: Helper Function Addition
// ============================================================================
echo "<div class='section'><h2>🔧 Phase 2: Adding Helper Functions</h2>";

$helperFunction = <<<'PHP'
// EVENT SYSTEM: Helper function for polling system
function createMessageEvent($conn, $room_id, $event_type, $event_data = null) {
    $stmt = $conn->prepare("INSERT INTO message_events (room_id, event_type, event_data, created_at) VALUES (?, ?, ?, NOW())");
    $data_json = $event_data ? json_encode($event_data) : null;
    $stmt->bind_param("iss", $room_id, $event_type, $data_json);
    $stmt->execute();
    $stmt->close();
}
PHP;

$filesToAddHelper = [
    'send_message.php',
    'room_whispers.php',
    'private_messages.php',
    'friends.php',
    'join_room.php',
    'leave_room.php',
    'toggle_afk.php',
    'ban_user_simple.php',
    'respond_knocks.php',
    'pass_host.php',
    'youtube_player.php',
    'youtube_sync.php'
];

foreach ($filesToAddHelper as $file) {
    $filepath = __DIR__ . '/' . $file;
    
    if (!file_exists($filepath)) {
        echo "<div class='info'>⚠️  File not found (skipping): $file</div>";
        continue;
    }
    
    backupFile($filepath);
    
    $content = file_get_contents($filepath);
    
    // Check if helper already exists
    if (strpos($content, 'function createMessageEvent') !== false) {
        echo "<div class='info'>⚠️  Helper already exists: $file (skipping)</div>";
        continue;
    }
    
    // Add helper function after the first <?php tag
    $newContent = preg_replace(
        '/(<\?php\s*\n)/i',
        "$1\n" . $helperFunction . "\n",
        $content,
        1
    );
    
    if (file_put_contents($filepath, $newContent)) {
        echo "<div class='success'>✅ Added helper function: $file</div>";
    } else {
        echo "<div class='error'>❌ Failed to add helper: $file</div>";
    }
}

echo "</div>";

// ============================================================================
// PHASE 3: Event Trigger Addition
// ============================================================================
echo "<div class='section'><h2>🎯 Phase 3: Adding Event Triggers</h2>";

// 3.1 - send_message.php
$trigger1 = <<<'PHP'
    // EVENT SYSTEM: Trigger message event for polling
    createMessageEvent($conn, $room_id, 'message', [
        'message_id' => $conn->insert_id,
        'type' => isset($type) ? $type : 'chat'
    ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/send_message.php',
    '/\$stmt->execute\(\);[\s\S]*?\$message_id\s*=\s*\$conn->insert_id;/i',
    $trigger1,
    'message sent'
);

// 3.2 - join_room.php
$trigger2 = <<<'PHP'
    // EVENT SYSTEM: Trigger user join event
    createMessageEvent($conn, $room_id, 'user_join', [
        'user_id_string' => $user_id_string
    ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/join_room.php',
    '/INSERT INTO chatroom_users[\s\S]*?\$stmt->execute\(\);/i',
    $trigger2,
    'user joined'
);

// 3.3 - leave_room.php
$trigger3 = <<<'PHP'
    // EVENT SYSTEM: Trigger user leave event
    createMessageEvent($conn, $room_id, 'user_leave', [
        'user_id_string' => $user_id_string
    ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/leave_room.php',
    '/DELETE FROM chatroom_users[\s\S]*?\$stmt->execute\(\);/i',
    $trigger3,
    'user left'
);

// 3.4 - room_whispers.php
$trigger4 = <<<'PHP'
            // EVENT SYSTEM: Trigger whisper event
            createMessageEvent($conn, $room_id, 'whisper', [
                'from' => $user_id_string,
                'to' => $recipient_user_id_string
            ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/room_whispers.php',
    '/if\s*\(\$stmt->execute\(\)\)\s*\{[\s]*error_log\("Whisper sent/i',
    $trigger4,
    'whisper sent'
);

// 3.5 - private_messages.php
$trigger5 = <<<'PHP'
        // EVENT SYSTEM: Trigger private message event (global room_id = 0)
        createMessageEvent($conn, 0, 'private_message', [
            'from' => $user_id,
            'to' => $recipient_id
        ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/private_messages.php',
    '/if\s*\(\$stmt->execute\(\)\)\s*\{[\s]*createMessageNotification/i',
    $trigger5,
    'private message sent'
);

// 3.6 - friends.php (accept case)
$trigger6 = <<<'PHP'
                // EVENT SYSTEM: Trigger friend update event
                createMessageEvent($conn, 0, 'friend_update', [
                    'action' => 'request_accepted',
                    'friend_id' => $friend_id
                ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/friends.php',
    "/case\s+'accept':[\s\S]*?UPDATE friends SET status = 'accepted'[\s\S]*?\$stmt->execute\(\);/i",
    $trigger6,
    'friend accepted'
);

// 3.7 - friends.php (add case)
$trigger7 = <<<'PHP'
            // EVENT SYSTEM: Trigger friend update event
            createMessageEvent($conn, 0, 'friend_update', [
                'action' => 'request_sent',
                'to_user' => $friend_id
            ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/friends.php',
    "/case\s+'add':[\s\S]*?INSERT INTO friends[\s\S]*?\$stmt->execute\(\);/i",
    $trigger7,
    'friend request sent'
);

// 3.8 - toggle_afk.php
$trigger8 = <<<'PHP'
    // EVENT SYSTEM: Trigger user update event
    createMessageEvent($conn, $room_id, 'user_update', [
        'user_id_string' => $user_id_string,
        'afk' => $new_afk_status,
        'manual' => $new_manual_afk
    ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/toggle_afk.php',
    '/UPDATE chatroom_users SET[\s\S]*?\$stmt->execute\(\);/i',
    $trigger8,
    'AFK toggled'
);

// 3.9 - ban_user_simple.php
$trigger9 = <<<'PHP'
        // EVENT SYSTEM: Trigger user leave event
        createMessageEvent($conn, $room_id, 'user_leave', [
            'user_id_string' => $user_id_string,
            'reason' => 'banned'
        ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/ban_user_simple.php',
    '/DELETE FROM chatroom_users WHERE room_id[\s\S]*?\$stmt->execute\(\);/i',
    $trigger9,
    'user banned'
);

// 3.10 - respond_knocks.php
$trigger10 = <<<'PHP'
        // EVENT SYSTEM: Trigger knock event
        createMessageEvent($conn, $room_id, 'knock', [
            'knock_id' => $knock_id,
            'response' => $response
        ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/respond_knocks.php',
    '/UPDATE room_knocks SET status[\s\S]*?\$stmt->execute\(\);/i',
    $trigger10,
    'knock responded'
);

// 3.11 - pass_host.php
$trigger11 = <<<'PHP'
        // EVENT SYSTEM: Trigger user update event
        createMessageEvent($conn, $room_id, 'user_update', [
            'action' => 'host_transferred',
            'new_host' => $target_user_id_string
        ]);
PHP;

addCodeAfterLine(
    __DIR__ . '/pass_host.php',
    '/UPDATE chatroom_users SET is_host = 1 WHERE[\s\S]*?\$stmt->execute\(\);/i',
    $trigger11,
    'host transferred'
);

// 3.12 - youtube_player.php (if exists)
if (file_exists(__DIR__ . '/youtube_player.php')) {
    $trigger12 = <<<'PHP'
        // EVENT SYSTEM: Trigger YouTube update event
        createMessageEvent($conn, $room_id, 'youtube_update', [
            'action' => $action
        ]);
PHP;

    addCodeAfterLine(
        __DIR__ . '/youtube_player.php',
        '/UPDATE room_player_sync[\s\S]*?\$stmt->execute\(\);/i',
        $trigger12,
        'YouTube updated'
    );
}

// 3.13 - youtube_sync.php (if exists)
if (file_exists(__DIR__ . '/youtube_sync.php')) {
    $trigger13 = <<<'PHP'
            // EVENT SYSTEM: Trigger YouTube update event
            createMessageEvent($conn, $room_id, 'youtube_update', [
                'action' => 'sync_update'
            ]);
PHP;

    addCodeAfterLine(
        __DIR__ . '/youtube_sync.php',
        '/UPDATE room_player_sync SET[\s\S]*?\$stmt->execute\(\);/i',
        $trigger13,
        'YouTube synced'
    );
}

echo "</div>";

// ============================================================================
// PHASE 4: Verification
// ============================================================================
echo "<div class='section'><h2>✅ Phase 4: Verification</h2>";

// Check if tables exist
$tables_check = $conn->query("SHOW TABLES LIKE 'message_events'");
if ($tables_check->num_rows > 0) {
    echo "<div class='success'>✅ message_events table exists</div>";
} else {
    echo "<div class='error'>❌ message_events table missing!</div>";
}

$tables_check2 = $conn->query("SHOW TABLES LIKE 'room_state_cache'");
if ($tables_check2->num_rows > 0) {
    echo "<div class='success'>✅ room_state_cache table exists</div>";
} else {
    echo "<div class='error'>❌ room_state_cache table missing!</div>";
}

// Check if helper functions were added
$filesWithHelper = 0;
foreach ($filesToAddHelper as $file) {
    $filepath = __DIR__ . '/' . $file;
    if (file_exists($filepath)) {
        $content = file_get_contents($filepath);
        if (strpos($content, 'function createMessageEvent') !== false) {
            $filesWithHelper++;
        }
    }
}

echo "<div class='success'>✅ Helper function added to $filesWithHelper files</div>";

// Count event triggers
$filesWithTriggers = 0;
$apiFiles = glob(__DIR__ . '/*.php');
foreach ($apiFiles as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'createMessageEvent($conn') !== false) {
        $filesWithTriggers++;
    }
}

echo "<div class='success'>✅ Event triggers found in $filesWithTriggers files</div>";

echo "</div>";

// ============================================================================
// PHASE 5: Next Steps
// ============================================================================
echo "<div class='section'><h2>📋 Phase 5: Next Steps</h2>";

echo "<h3>Completed:</h3>";
echo "<ul>";
echo "<li>✅ Database tables created</li>";
echo "<li>✅ Helper functions added</li>";
echo "<li>✅ Event triggers installed</li>";
echo "</ul>";

echo "<h3>Manual Steps Remaining:</h3>";
echo "<ol>";
echo "<li><strong>Create poll_room_data.php:</strong><br>";
echo "   - Copy from the artifact provided<br>";
echo "   - Place in api/ directory<br>";
echo "   - Test: <code>curl http://yoursite.com/api/poll_room_data.php?last_event_id=0</code></li>";

echo "<li><strong>Update room.js:</strong><br>";
echo "   - Backup current file: <code>cp room.js room.js.backup</code><br>";
echo "   - Replace SSE code with polling code from artifact<br>";
echo "   - Update initialization in <code>$(document).ready()</code></li>";

echo "<li><strong>Test the system:</strong><br>";
echo "   - Send a test message<br>";
echo "   - Check if event was created: <code>SELECT * FROM message_events ORDER BY id DESC LIMIT 1;</code><br>";
echo "   - Verify polling works in browser console</li>";

echo "<li><strong>Set up cleanup cron:</strong><br>";
echo "   - Create api/cleanup_events.php (see artifact)<br>";
echo "   - Add to crontab: <code>*/5 * * * * php /path/to/api/cleanup_events.php</code></li>";

echo "<li><strong>Monitor for 24 hours:</strong><br>";
echo "   - Check error logs<br>";
echo "   - Monitor event table size<br>";
echo "   - Test all features thoroughly</li>";
echo "</ol>";

echo "</div>";

// ============================================================================
// PHASE 6: Rollback Instructions
// ============================================================================
echo "<div class='section'><h2>🔄 Rollback Instructions</h2>";

echo "<p>If you need to rollback, your backup files are here:</p>";
echo "<pre>";
$backups = glob(__DIR__ . '/*.backup_*');
foreach ($backups as $backup) {
    echo basename($backup) . "\n";
}
echo "</pre>";

echo "<p><strong>To rollback a file:</strong></p>";
echo "<pre>cp api/send_message.php.backup_YYYY-MM-DD_HH-mm-ss api/send_message.php</pre>";

echo "<p><strong>To remove all changes:</strong></p>";
echo "<ol>";
echo "<li>Restore all .backup files</li>";
echo "<li>Optionally drop tables: <code>DROP TABLE message_events; DROP TABLE room_state_cache;</code></li>";
echo "</ol>";

echo "</div>";

echo "<div class='section' style='background: #002200; border-color: #00ff00;'>";
echo "<h2>🎉 Setup Complete!</h2>";
echo "<p>The automated setup has finished. Follow the manual steps above to complete the conversion.</p>";
echo "<p><strong>Total files modified:</strong> " . ($filesWithHelper + $filesWithTriggers) . "</p>";
echo "<p><strong>Backup files created:</strong> " . count($backups) . "</p>";
echo "</div>";

$conn->close();

echo "</body></html>";
?>
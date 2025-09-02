<?php
// config/activity_config.php - Global activity and AFK configuration
// Include this file in all activity-related scripts for consistent timing

// ===== GLOBAL TIMING CONFIGURATION =====
// All times are in seconds for consistency

// AFK Configuration
define('AFK_TIMEOUT', 20 * 60);        // 20 minutes = AFK
define('DISCONNECT_TIMEOUT', 80 * 60);  // 80 minutes total = disconnect from room (20 min + 60 min AFK)
define('SESSION_TIMEOUT', 60 * 60);     // 60 minutes = disconnect from site (lounge users)

// Activity Check Intervals
define('ACTIVITY_CHECK_INTERVAL', 30);   // 30 seconds between activity updates
define('DISCONNECT_CHECK_INTERVAL', 60); // 60 seconds between disconnect checks
define('HEARTBEAT_INTERVAL', 30);        // 30 seconds between heartbeats

// Grace periods (to prevent race conditions)
define('GRACE_PERIOD', 30);              // 30 seconds grace period for timing checks

// Debug mode
define('ACTIVITY_DEBUG_MODE', false);    // Set to true for detailed logging

// Logging function
function logActivity($message) {
    if (ACTIVITY_DEBUG_MODE) {
        $timestamp = date('Y-m-d H:i:s');
        error_log("ACTIVITY_SYSTEM [{$timestamp}]: {$message}");
    }
}

// Get human-readable time format
function formatTimeMinutes($seconds) {
    $minutes = round($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
    }
    $hours = floor($minutes / 60);
    $remainingMinutes = $minutes % 60;
    $result = $hours . ' hour' . ($hours != 1 ? 's' : '');
    if ($remainingMinutes > 0) {
        $result .= ' ' . $remainingMinutes . ' minute' . ($remainingMinutes != 1 ? 's' : '');
    }
    return $result;
}

// Ensure database tables have required columns
function ensureActivityColumns($conn) {
    logActivity("Ensuring required database columns exist...");
    
    // Check and add columns to chatroom_users table
    $required_columns = [
        'last_activity' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'is_afk' => 'TINYINT(1) DEFAULT 0',
        'afk_since' => 'TIMESTAMP NULL DEFAULT NULL',
        'manual_afk' => 'TINYINT(1) DEFAULT 0'
    ];
    
    foreach ($required_columns as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM chatroom_users LIKE '$column'");
        if ($check->num_rows === 0) {
            $sql = "ALTER TABLE chatroom_users ADD COLUMN $column $definition";
            if ($conn->query($sql)) {
                logActivity("Added column '$column' to chatroom_users");
            } else {
                logActivity("Failed to add column '$column': " . $conn->error);
            }
        }
    }
    
    // Check and add columns to global_users table
    $global_check = $conn->query("SHOW TABLES LIKE 'global_users'");
    if ($global_check->num_rows > 0) {
        $global_columns = [
            'last_activity' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'session_start' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ];
        
        foreach ($global_columns as $column => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM global_users LIKE '$column'");
            if ($check->num_rows === 0) {
                $sql = "ALTER TABLE global_users ADD COLUMN $column $definition";
                if ($conn->query($sql)) {
                    logActivity("Added column '$column' to global_users");
                } else {
                    logActivity("Failed to add column '$column': " . $conn->error);
                }
            }
        }
    }
    
    // Add useful indexes for performance
    $indexes = [
        'chatroom_users' => [
            'idx_last_activity' => 'last_activity',
            'idx_afk_status' => 'is_afk',
            'idx_user_room_activity' => 'user_id_string, room_id, last_activity'
        ],
        'global_users' => [
            'idx_global_last_activity' => 'last_activity',
            'idx_global_session' => 'user_id_string, last_activity'
        ]
    ];
    
    foreach ($indexes as $table => $table_indexes) {
        // Check if table exists
        $table_check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($table_check->num_rows === 0) continue;
        
        foreach ($table_indexes as $index_name => $columns) {
            $index_check = $conn->query("SHOW INDEX FROM $table WHERE Key_name = '$index_name'");
            if ($index_check->num_rows === 0) {
                $sql = "ALTER TABLE $table ADD INDEX $index_name ($columns)";
                if ($conn->query($sql)) {
                    logActivity("Added index '$index_name' to $table");
                }
            }
        }
    }
}

// Activity types that count as "active"
function getValidActivityTypes() {
    return [
        'message_send',      // Sending messages in room
        'room_join',         // Joining a room  
        'room_create',       // Creating a room
        'private_message',   // Sending private messages
        'whisper',           // Sending whispers
        'heartbeat',         // Regular heartbeat
        'interaction',       // Mouse/keyboard activity
        'page_focus',        // Page gained focus
        'window_focus',      // Window gained focus
        'system_start',      // System initialization
        'manual_activity'    // Manual activity update
    ];
}

// Check if an activity type is valid
function isValidActivityType($type) {
    return in_array($type, getValidActivityTypes());
}
?>
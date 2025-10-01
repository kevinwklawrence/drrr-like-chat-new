<?php
// fix_collation.php - Convert ALL tables to utf8mb4_unicode_ci (database default)
session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die('Admin only');
}

include 'db_connect.php';

echo "<h3>Converting ALL Tables to UTF8MB4_UNICODE_CI</h3>";

// Get database default collation
$db_charset = $conn->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
                            FROM information_schema.SCHEMATA 
                            WHERE SCHEMA_NAME = 'duranune_drrr_clone'");
if ($db_charset) {
    $db_info = $db_charset->fetch_assoc();
    echo "<p><strong>Database Default:</strong> {$db_info['DEFAULT_CHARACTER_SET_NAME']} / {$db_info['DEFAULT_COLLATION_NAME']}</p>";
}

// Tables that need fixing
$tables = [
    'user_mentions',
    'chatroom_users', 
    'room_whispers',
    'messages',
    'users',
    'chatrooms',
    'private_messages',
    'friends'
];

foreach ($tables as $table) {
    echo "<h4>Table: $table</h4>";
    
    // Check if table exists
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows == 0) {
        echo "<p>⚠️ Table does not exist, skipping...</p>";
        continue;
    }
    
    // Convert entire table to utf8mb4_unicode_ci
    $result = $conn->query("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if ($result) {
        echo "<p>✅ Converted $table to utf8mb4_unicode_ci</p>";
    } else {
        echo "<p>❌ Error converting $table: " . $conn->error . "</p>";
    }
    
    // Show column info
    $cols = $conn->query("SHOW FULL COLUMNS FROM `$table` WHERE Type LIKE '%char%' OR Type LIKE '%text%'");
    if ($cols) {
        while ($col = $cols->fetch_assoc()) {
            $status = (strpos($col['Collation'], 'utf8mb4_unicode_ci') !== false) ? '✅' : '⚠️';
            echo "<p>  $status {$col['Field']}: {$col['Collation']}</p>";
        }
    }
}

echo "<h4>Verification</h4>";
$verify = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'duranune_drrr_clone' 
    AND COLUMN_NAME IN ('user_id_string', 'mentioned_user_id_string', 'sender_user_id_string', 'recipient_user_id_string')
    AND COLLATION_NAME != 'utf8mb4_unicode_ci'
");

if ($verify && $verify->num_rows > 0) {
    echo "<p><strong>⚠️ Warning: These columns still have wrong collation:</strong></p>";
    while ($row = $verify->fetch_assoc()) {
        echo "<p>  - {$row['TABLE_NAME']}.{$row['COLUMN_NAME']}: {$row['COLLATION_NAME']}</p>";
    }
} else {
    echo "<p>✅ All user_id_string columns are using utf8mb4_unicode_ci</p>";
}

echo "<p>✅ <strong>Collation fix complete!</strong></p>";
echo "<p><a href='lounge.php'>Return to Lounge</a></p>";

$conn->close();
?>
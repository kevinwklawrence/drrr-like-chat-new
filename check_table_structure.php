<?php
// Check table structure to debug column issues
include 'db_connect.php';

echo "<h1>Database Table Structure Check</h1>";

$tables_to_check = ['chatrooms', 'chatroom_users', 'messages', 'room_knocks', 'users'];

foreach ($tables_to_check as $table) {
    echo "<h2>Table: $table</h2>";
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check->num_rows === 0) {
        echo "<p style='color: red;'>❌ Table '$table' does not exist!</p>";
        continue;
    }
    
    echo "<p style='color: green;'>✅ Table exists</p>";
    
    // Show columns
    $columns_query = $conn->query("SHOW COLUMNS FROM $table");
    if ($columns_query) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $columns_query->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>{$row['Field']}</strong></td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count columns
        $columns_query->data_seek(0);
        $column_count = $columns_query->num_rows;
        echo "<p><strong>Total columns:</strong> $column_count</p>";
        
        // For specific tables, show important notes
        if ($table === 'chatrooms') {
            echo "<h3>Important for Room Creation:</h3>";
            $important_columns = ['password', 'has_password', 'allow_knocking', 'room_keys'];
            foreach ($important_columns as $col) {
                $check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE '$col'");
                echo "<p>$col: " . ($check->num_rows > 0 ? '✅ EXISTS' : '❌ MISSING') . "</p>";
            }
        }
        
        if ($table === 'chatroom_users') {
            echo "<h3>Important for User Management:</h3>";
            $important_columns = ['user_id', 'user_id_string', 'username', 'guest_name', 'avatar', 'guest_avatar', 'is_host', 'ip_address'];
            foreach ($important_columns as $col) {
                $check = $conn->query("SHOW COLUMNS FROM chatroom_users LIKE '$col'");
                echo "<p>$col: " . ($check->num_rows > 0 ? '✅ EXISTS' : '❌ MISSING') . "</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>❌ Error getting columns: " . $conn->error . "</p>";
    }
    
    echo "<hr>";
}

// Show recent data samples
echo "<h2>Recent Data Samples</h2>";

echo "<h3>Recent Chatrooms:</h3>";
$recent_rooms = $conn->query("SELECT id, name, has_password, password, allow_knocking, created_at FROM chatrooms ORDER BY created_at DESC LIMIT 3");
if ($recent_rooms && $recent_rooms->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Has Password</th><th>Password Set</th><th>Allow Knocking</th><th>Created</th></tr>";
    while ($row = $recent_rooms->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>" . ($row['has_password'] ? 'YES' : 'NO') . "</td>";
        echo "<td>" . (!empty($row['password']) ? 'YES (' . strlen($row['password']) . ' chars)' : 'NO') . "</td>";
        echo "<td>" . (isset($row['allow_knocking']) ? ($row['allow_knocking'] ? 'YES' : 'NO') : 'N/A') . "</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No chatrooms found</p>";
}

echo "<h3>Recent Chatroom Users:</h3>";
$recent_users = $conn->query("SELECT room_id, user_id_string, username, guest_name, is_host, joined_at FROM chatroom_users ORDER BY joined_at DESC LIMIT 5");
if ($recent_users && $recent_users->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Room ID</th><th>User ID String</th><th>Username</th><th>Guest Name</th><th>Is Host</th><th>Joined</th></tr>";
    while ($row = $recent_users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['room_id']}</td>";
        echo "<td>{$row['user_id_string']}</td>";
        echo "<td>" . ($row['username'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['guest_name'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['is_host'] ? 'YES' : 'NO') . "</td>";
        echo "<td>" . ($row['joined_at'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No chatroom users found</p>";
}

echo "<h2>SQL Commands to Add Missing Columns</h2>";
echo "<p>If you're missing important columns, here are the SQL commands to add them:</p>";
echo "<pre>";
echo "-- Add missing chatrooms columns
ALTER TABLE chatrooms ADD COLUMN IF NOT EXISTS room_keys TEXT DEFAULT NULL;
ALTER TABLE chatrooms ADD COLUMN IF NOT EXISTS has_password TINYINT(1) DEFAULT 0;
ALTER TABLE chatrooms ADD COLUMN IF NOT EXISTS allow_knocking TINYINT(1) DEFAULT 1;

-- Add missing chatroom_users columns
ALTER TABLE chatroom_users ADD COLUMN IF NOT EXISTS user_id_string VARCHAR(255);
ALTER TABLE chatroom_users ADD COLUMN IF NOT EXISTS guest_avatar VARCHAR(255) DEFAULT 'default_avatar.jpg';
ALTER TABLE chatroom_users ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45);

-- Update has_password based on existing passwords
UPDATE chatrooms SET has_password = 1 WHERE password IS NOT NULL AND password != '';
UPDATE chatrooms SET has_password = 0 WHERE password IS NULL OR password = '';
";
echo "</pre>";
?>
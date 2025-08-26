<?php
// migration/add_afk_support.php - Database migration for AFK functionality
// Run this once to add AFK support to existing installations

include '../db_connect.php';

try {
    echo "Starting AFK feature migration...\n";
    
    // Check if columns already exist
    $existing_columns = [];
    $result = $conn->query("SHOW COLUMNS FROM chatroom_users");
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    $columns_to_add = [
        'is_afk' => 'TINYINT(1) DEFAULT 0',
        'afk_since' => 'TIMESTAMP NULL DEFAULT NULL',
        'manual_afk' => 'TINYINT(1) DEFAULT 0'
    ];
    
    $added_columns = [];
    
    foreach ($columns_to_add as $column_name => $column_definition) {
        if (!in_array($column_name, $existing_columns)) {
            $sql = "ALTER TABLE chatroom_users ADD COLUMN $column_name $column_definition";
            if ($conn->query($sql)) {
                echo "✓ Added column: $column_name\n";
                $added_columns[] = $column_name;
            } else {
                echo "✗ Failed to add column $column_name: " . $conn->error . "\n";
            }
        } else {
            echo "- Column $column_name already exists\n";
        }
    }
    
    // Add indices for better performance
    $indices_to_add = [
        'idx_chatroom_users_afk' => 'is_afk',
        'idx_chatroom_users_last_activity' => 'last_activity'
    ];
    
    foreach ($indices_to_add as $index_name => $column) {
        // Check if index exists
        $index_check = $conn->query("SHOW INDEX FROM chatroom_users WHERE Key_name = '$index_name'");
        if ($index_check->num_rows === 0) {
            $sql = "ALTER TABLE chatroom_users ADD INDEX $index_name ($column)";
            if ($conn->query($sql)) {
                echo "✓ Added index: $index_name\n";
            } else {
                echo "✗ Failed to add index $index_name: " . $conn->error . "\n";
            }
        } else {
            echo "- Index $index_name already exists\n";
        }
    }
    
    // Create system message avatars table if it doesn't exist
    $avatar_table_check = $conn->query("SHOW TABLES LIKE 'system_avatars'");
    if ($avatar_table_check->num_rows === 0) {
        $create_avatars_table = "
            CREATE TABLE system_avatars (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(50) NOT NULL UNIQUE,
                filename VARCHAR(100) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        if ($conn->query($create_avatars_table)) {
            echo "✓ Created system_avatars table\n";
            
            // Insert default AFK-related avatars
            $default_avatars = [
                ['afk', 'afk.png', 'User went AFK icon'],
                ['active', 'active.png', 'User returned from AFK icon'],
                ['disconnect', 'disconnect.png', 'User disconnected icon']
            ];
            
            foreach ($default_avatars as $avatar) {
                $stmt = $conn->prepare("INSERT IGNORE INTO system_avatars (name, filename, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $avatar[0], $avatar[1], $avatar[2]);
                if ($stmt->execute()) {
                    echo "✓ Added system avatar: {$avatar[0]}\n";
                }
                $stmt->close();
            }
        } else {
            echo "✗ Failed to create system_avatars table: " . $conn->error . "\n";
        }
    }
    
    // Update global_users table to support AFK if it exists
    $global_users_check = $conn->query("SHOW TABLES LIKE 'global_users'");
    if ($global_users_check->num_rows > 0) {
        $global_columns_result = $conn->query("SHOW COLUMNS FROM global_users");
        $global_columns = [];
        while ($row = $global_columns_result->fetch_assoc()) {
            $global_columns[] = $row['Field'];
        }
        
        if (!in_array('last_afk_activity', $global_columns)) {
            if ($conn->query("ALTER TABLE global_users ADD COLUMN last_afk_activity TIMESTAMP NULL DEFAULT NULL")) {
                echo "✓ Added last_afk_activity column to global_users\n";
            }
        }
    }
    
    echo "\n=== AFK Migration Summary ===\n";
    echo "Columns added: " . count($added_columns) . "\n";
    if (count($added_columns) > 0) {
        echo "Added columns: " . implode(', ', $added_columns) . "\n";
    }
    echo "\nAFK feature migration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Upload the afk.png, active.png system icons to your images/ directory\n";
    echo "2. Include css/afk.css in your room.php file\n";
    echo "3. Update your JavaScript files with the new AFK functions\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>
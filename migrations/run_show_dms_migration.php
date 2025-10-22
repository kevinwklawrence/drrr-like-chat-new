<?php
// Migration script to add show_dms_automatically column to users table
// Run this file once to apply the migration

require_once '../db_connect.php';

echo "Starting migration: Add show_dms_automatically setting...\n";

// Check if column already exists
$check_query = "SHOW COLUMNS FROM users LIKE 'show_dms_automatically'";
$result = $conn->query($check_query);

if ($result->num_rows > 0) {
    echo "Column 'show_dms_automatically' already exists. Skipping migration.\n";
} else {
    // Add the column
    $migration_query = "ALTER TABLE users
                       ADD COLUMN show_dms_automatically BOOLEAN DEFAULT FALSE
                       COMMENT 'Whether to automatically show DM modal when new messages arrive'";

    if ($conn->query($migration_query) === TRUE) {
        echo "Successfully added 'show_dms_automatically' column to users table.\n";

        // Set default value for existing users
        $update_query = "UPDATE users SET show_dms_automatically = FALSE WHERE show_dms_automatically IS NULL";
        if ($conn->query($update_query) === TRUE) {
            echo "Successfully set default values for existing users.\n";
        } else {
            echo "Error updating default values: " . $conn->error . "\n";
        }
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}

$conn->close();
echo "Migration complete!\n";
?>

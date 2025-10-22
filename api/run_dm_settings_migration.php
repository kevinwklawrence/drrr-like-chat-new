<?php
// API endpoint to run the show_dms_automatically migration
// Access this file via browser to apply the migration
session_start();

// Only allow admins to run migrations
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Only admins can run migrations.']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

$results = [];

// Check if column already exists
$check_query = "SHOW COLUMNS FROM users LIKE 'show_dms_automatically'";
$result = $conn->query($check_query);

if ($result->num_rows > 0) {
    $results[] = "Column 'show_dms_automatically' already exists. Skipping migration.";
} else {
    // Add the column
    $migration_query = "ALTER TABLE users
                       ADD COLUMN show_dms_automatically BOOLEAN DEFAULT FALSE
                       COMMENT 'Whether to automatically show DM modal when new messages arrive'";

    if ($conn->query($migration_query) === TRUE) {
        $results[] = "Successfully added 'show_dms_automatically' column to users table.";

        // Set default value for existing users
        $update_query = "UPDATE users SET show_dms_automatically = FALSE WHERE show_dms_automatically IS NULL";
        if ($conn->query($update_query) === TRUE) {
            $results[] = "Successfully set default values for existing users.";
        } else {
            $results[] = "Error updating default values: " . $conn->error;
        }
    } else {
        $results[] = "Error adding column: " . $conn->error;
    }
}

$conn->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Migration completed',
    'details' => $results
]);
?>

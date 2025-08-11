<?php
// api/sync_chatroom_colors.php - Sync colors from global_users to chatroom_users
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

try {
    // First, check if color column exists in chatroom_users
    $check_column = $conn->query("SHOW COLUMNS FROM chatroom_users LIKE 'color'");
    
    if ($check_column->num_rows == 0) {
        // Add color column if it doesn't exist
        $add_column = $conn->query("ALTER TABLE chatroom_users ADD COLUMN color VARCHAR(20) DEFAULT 'blue'");
        
        if (!$add_column) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add color column: ' . $conn->error]);
            exit;
        }
        
        error_log("SYNC_COLORS: Added color column to chatroom_users table");
    }
    
    // Sync colors from global_users to chatroom_users
    $sync_query = "
        UPDATE chatroom_users cu 
        INNER JOIN global_users gu ON cu.user_id_string = gu.user_id_string 
        SET cu.color = gu.color 
        WHERE gu.color IS NOT NULL AND gu.color != ''
    ";
    
    $result = $conn->query($sync_query);
    
    if ($result) {
        $affected_rows = $conn->affected_rows;
        
        // Also update any null colors to default blue
        $default_update = $conn->query("UPDATE chatroom_users SET color = 'blue' WHERE color IS NULL OR color = ''");
        $default_affected = $conn->affected_rows;
        
        error_log("SYNC_COLORS: Updated $affected_rows chatroom_users records from global_users, set $default_affected to default blue");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Colors synchronized successfully',
            'details' => [
                'synced_from_global_users' => $affected_rows,
                'set_to_default_blue' => $default_affected,
                'total_updated' => $affected_rows + $default_affected
            ]
        ]);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to sync colors: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    error_log("SYNC_COLORS: Error - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
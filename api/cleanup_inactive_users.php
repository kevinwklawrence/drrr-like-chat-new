<?php
// api/cleanup_inactive_users.php - Remove inactive users from global_users
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

include '../db_connect.php';

try {
    // Define inactive threshold: users inactive for more than 2 minutes
    $inactive_threshold = 2; // minutes
    
    // First, get count of users that will be cleaned up for logging
    $count_stmt = $conn->prepare("SELECT COUNT(*) as inactive_count FROM global_users WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $count_stmt->bind_param("i", $inactive_threshold);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $inactive_count = $count_row['inactive_count'];
    $count_stmt->close();
    
    // Delete inactive users from global_users
    $cleanup_stmt = $conn->prepare("DELETE FROM global_users WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $cleanup_stmt->bind_param("i", $inactive_threshold);
    $success = $cleanup_stmt->execute();
    $affected_rows = $cleanup_stmt->affected_rows;
    $cleanup_stmt->close();
    
    if ($success) {
        // Also clean up any orphaned records in chatroom_users where the user is no longer in global_users
        $cleanup_chatroom_stmt = $conn->prepare("
            DELETE cu FROM chatroom_users cu 
            LEFT JOIN global_users gu ON cu.user_id_string = gu.user_id_string 
            WHERE gu.user_id_string IS NULL
        ");
        $cleanup_chatroom_stmt->execute();
        $chatroom_affected = $cleanup_chatroom_stmt->affected_rows;
        $cleanup_chatroom_stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Cleanup completed',
            'cleaned_count' => $affected_rows,
            'chatroom_cleaned' => $chatroom_affected,
            'threshold_minutes' => $inactive_threshold,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Log cleanup if any users were removed
        if ($affected_rows > 0) {
            error_log("Cleaned up $affected_rows inactive users from global_users (threshold: {$inactive_threshold} minutes)");
        }
        if ($chatroom_affected > 0) {
            error_log("Cleaned up $chatroom_affected orphaned records from chatroom_users");
        }
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Cleanup failed']);
    }
    
} catch (Exception $e) {
    error_log("Cleanup error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Cleanup error: ' . $e->getMessage()]);
}
?>
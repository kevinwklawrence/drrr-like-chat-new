<?php
/* api/cleanup_inactive_users.php - Updated to preserve global_users
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

include '../db_connect.php';

try {
    
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
        'message' => 'Cleanup completed - global_users preserved',
        'cleaned_count' => 0,
        'chatroom_cleaned' => $chatroom_affected,
        'note' => 'Users now remain in global_users until logout',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    if ($chatroom_affected > 0) {
        error_log("Cleaned up $chatroom_affected orphaned records from chatroom_users");
    }
    
} catch (Exception $e) {
    error_log("Cleanup error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Cleanup error: ' . $e->getMessage()]);
}*/
?>
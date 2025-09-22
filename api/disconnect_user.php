<?php
// api/disconnect_user.php - New endpoint for immediate disconnect detection
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

include '../db_connect.php';

try {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
    
    if (empty($user_id_string)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user']);
        exit;
    }
    
    $conn->begin_transaction();
    
    // Remove from chatroom_users (if in any room)
    $remove_from_rooms = $conn->prepare("DELETE FROM chatroom_users WHERE user_id_string = ?");
    if ($remove_from_rooms) {
        $remove_from_rooms->bind_param("s", $user_id_string);
        $remove_from_rooms->execute();
        $rooms_affected = $remove_from_rooms->affected_rows;
        $remove_from_rooms->close();
    }
    
    // Remove from global_users
    $remove_from_global = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
    if ($remove_from_global) {
        $remove_from_global->bind_param("s", $user_id_string);
        $remove_from_global->execute();
        $global_affected = $remove_from_global->affected_rows;
        $remove_from_global->close();
    }
    
    $conn->commit();
    
    error_log("User disconnected via API: {$user_id_string} (rooms: {$rooms_affected}, global: {$global_affected})");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User disconnected successfully',
        'rooms_removed' => $rooms_affected,
        'global_removed' => $global_affected
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Disconnect API error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Disconnect failed']);
}

$conn->close();
?>
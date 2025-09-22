<?php
// api/heartbeat.php - Simple session validation endpoint
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'session_valid' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

include '../db_connect.php';

try {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
    $room_id = $_POST['room_id'] ?? null;
    
    // Check if user still exists in global_users
    $check_user = $conn->prepare("SELECT user_id_string FROM global_users WHERE user_id_string = ?");
    $check_user->bind_param("s", $user_id_string);
    $check_user->execute();
    $result = $check_user->get_result();
    $user_exists = $result->num_rows > 0;
    $check_user->close();
    
    if (!$user_exists) {
        // User was auto-logged out
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'session_valid' => false,
            'message' => 'Session expired'
        ]);
        exit;
    }
    
    // Update activity if user exists
    $update_activity = $conn->prepare("UPDATE global_users SET last_activity = NOW() WHERE user_id_string = ?");
    $update_activity->bind_param("s", $user_id_string);
    $update_activity->execute();
    $update_activity->close();
    
    // Also update room activity if in a room
    if ($room_id) {
        $update_room = $conn->prepare("UPDATE chatroom_users SET last_activity = NOW() WHERE user_id_string = ? AND room_id = ?");
        $update_room->bind_param("si", $user_id_string, $room_id);
        $update_room->execute();
        $update_room->close();
    }
    
    echo json_encode([
        'status' => 'success',
        'session_valid' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Heartbeat error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'session_valid' => true, // Assume valid on error to avoid false redirects
        'message' => 'Server error'
    ]);
}
?>
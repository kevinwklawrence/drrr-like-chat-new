<?php
// api/update_activity.php - Track user activity and handle AFK status
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not in room']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$activity_type = $_POST['activity_type'] ?? 'general'; // message, join, general, etc.

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Get current AFK status
    $status_stmt = $conn->prepare("SELECT is_afk, manual_afk, username, guest_name FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if (!$status_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $status_stmt->bind_param("is", $room_id, $user_id_string);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();
    
    if ($status_result->num_rows === 0) {
        echo json_encode(['status' => 'not_in_room', 'message' => 'User not found in room']);
        $status_stmt->close();
        exit;
    }
    
    $user_data = $status_result->fetch_assoc();
    $was_afk = (bool)$user_data['is_afk'];
    $was_manual_afk = (bool)$user_data['manual_afk'];
    $display_name = $user_data['username'] ?: $user_data['guest_name'] ?: 'Unknown User';
    $status_stmt->close();
    
    // Update activity and clear automatic AFK status
    if ($was_afk && !$was_manual_afk) {
        // User was auto-AFK, clear it
        $stmt = $conn->prepare("UPDATE chatroom_users SET last_activity = NOW(), is_afk = 0, afk_since = NULL, manual_afk = 0 WHERE room_id = ? AND user_id_string = ?");
        
        // Add system message for returning from AFK
        $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'active.png', 'system')");
        if ($msg_stmt) {
            $return_message = "$display_name is back from AFK.";
            $msg_stmt->bind_param("is", $room_id, $return_message);
            $msg_stmt->execute();
            $msg_stmt->close();
        }
        
    } else {
        // Just update activity (don't clear manual AFK)
        $stmt = $conn->prepare("UPDATE chatroom_users SET last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
    }
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $success = $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    if ($success && $affected_rows > 0) {
        $response = [
            'status' => 'success',
            'message' => 'Activity updated',
            'activity_type' => $activity_type,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Include AFK status change in response
        if ($was_afk && !$was_manual_afk) {
            $response['afk_status_changed'] = true;
            $response['was_afk'] = true;
            $response['now_afk'] = false;
        }
        
        echo json_encode($response);
    } else {
        // User might not be in room anymore
        echo json_encode([
            'status' => 'not_in_room',
            'message' => 'User not found in room'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Update activity error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update activity']);
}

$conn->close();
?>
<?php
// api/update_activity.php - Track user activity
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
    // Update last_activity for the user in this room
    $stmt = $conn->prepare("UPDATE chatroom_users SET last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $success = $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    if ($success && $affected_rows > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Activity updated',
            'activity_type' => $activity_type,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
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
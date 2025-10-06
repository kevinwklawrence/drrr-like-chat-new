<?php
// api/reset_user_activity.php - Reset user's inactivity timer
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not in room']);
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Reset inactivity timer and clear AFK status
    $stmt = $conn->prepare("UPDATE chatroom_users SET inactivity_seconds = 0, is_afk = 0, last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
    $stmt->bind_param("is", $room_id, $user_id_string);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Activity reset successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to reset activity'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Reset activity error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error'
    ]);
}

$conn->close();
?>
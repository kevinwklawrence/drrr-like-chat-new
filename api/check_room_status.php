<?php
// api/check_room_status.php - Verify user is still in room
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$room_id = $_SESSION['room_id'] ?? 0;

if (!$user_id_string || !$room_id) {
    echo json_encode(['status' => 'not_in_room', 'message' => 'No active session']);
    exit;
}

// Check if user is still in chatroom_users
$stmt = $conn->prepare("SELECT 1 FROM chatroom_users WHERE room_id = ? AND user_id_string = ? LIMIT 1");
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    // User is NOT in room - clear session
    unset($_SESSION['room_id']);
    echo json_encode([
        'status' => 'not_in_room', 
        'message' => 'You have been disconnected from the room',
        'redirect' => '/lounge'
    ]);
} else {
    echo json_encode(['status' => 'in_room']);
}

$conn->close();
?>
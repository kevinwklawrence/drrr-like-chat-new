<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if ($room_id <= 0) {
    error_log("Invalid room_id in get_messages.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

error_log("Fetching messages for room_id=$room_id"); // Debug

$stmt = $conn->prepare("
    SELECT m.id, m.user_id, m.guest_name, m.message, m.avatar, m.type, m.timestamp, u.username, u.is_admin, cu.ip_address 
    FROM messages m 
    LEFT JOIN users u ON m.user_id = u.id 
    LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id 
        AND (m.user_id = cu.user_id OR m.guest_name = cu.guest_name) 
    WHERE m.room_id = ? 
    ORDER BY m.timestamp
");
if (!$stmt) {
    error_log("Prepare failed in get_messages.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

error_log("Retrieved " . count($messages) . " messages for room_id=$room_id"); // Debug
echo json_encode($messages);
?>
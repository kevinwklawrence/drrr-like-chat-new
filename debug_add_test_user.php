<?php
// Debug helper to add a fake test user to a room
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

if ($room_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

// Create a fake test user
$fake_ip = '192.168.1.' . rand(100, 200);
$fake_user_id = 'TEST_' . substr(md5($fake_ip . time()), 0, 12);
$fake_name = 'TestUser' . rand(100, 999);

$stmt = $conn->prepare("INSERT INTO chatroom_users (room_id, user_id, guest_name, guest_avatar, user_id_string, is_host, ip_address) VALUES (?, NULL, ?, 'f1.png', ?, 0, ?)");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("isss", $room_id, $fake_name, $fake_user_id, $fake_ip);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => "Added test user: $fake_name ($fake_user_id)"]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add test user: ' . $stmt->error]);
}
$stmt->close();
?>
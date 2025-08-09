<?php
// Debug helper to clear test users from a room (except the current user)
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

// Get current user's user_id_string
$current_user_id_string = '';
if (isset($_SESSION['user']['user_id'])) {
    $current_user_id_string = $_SESSION['user']['user_id'];
}

if (empty($current_user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'No current user session']);
    exit;
}

// Delete all users except the current user
$stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string != ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("is", $room_id, $current_user_id_string);
if ($stmt->execute()) {
    $deleted_count = $stmt->affected_rows;
    echo json_encode(['status' => 'success', 'message' => "Removed $deleted_count other users from room"]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to clear users: ' . $stmt->error]);
}
$stmt->close();
?>
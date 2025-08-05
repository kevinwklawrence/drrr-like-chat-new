<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in leave_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
if ($room_id <= 0) {
    error_log("Invalid room_id in leave_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;
$name = $user_id ? $_SESSION['user']['username'] : $guest_name;
$avatar = $user_id ? $_SESSION['user']['avatar'] : ($_SESSION['user']['avatar'] ?? null);

error_log("Leaving room: room_id=$room_id, user_id=$user_id, guest_name=$guest_name"); // Debug

$stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND (user_id = ? OR guest_name = ?)");
if (!$stmt) {
    error_log("Prepare failed in leave_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iis", $room_id, $user_id, $guest_name);
if (!$stmt->execute()) {
    error_log("Execute failed in leave_room.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    exit;
}
$stmt->close();

// Insert system message for leave
$system_message = "$name has left the room.";
$stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, type) VALUES (?, ?, ?, ?, ?, 'system')");
if (!$stmt) {
    error_log("Prepare failed for system message in leave_room.php: " . $conn->error);
} else {
    $stmt->bind_param("iisss", $room_id, $user_id, $guest_name, $system_message, $avatar);
    if (!$stmt->execute()) {
        error_log("Execute failed for system message in leave_room.php: " . $stmt->error);
    }
    $stmt->close();
}

unset($_SESSION['room_id']);

// Trigger cleanup of empty rooms
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/cleanup_rooms.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if ($response === false) {
    error_log("cURL error in leave_room.php: " . curl_error($ch));
} else {
    error_log("Cleanup triggered: " . $response);
}
curl_close($ch);

echo json_encode(['status' => 'success']);
?>
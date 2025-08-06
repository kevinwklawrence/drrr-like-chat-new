<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in send_message.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($room_id <= 0 || empty($message)) {
    error_log("Missing room_id or message in send_message.php: room_id=$room_id, message=$message");
    echo json_encode(['status' => 'error', 'message' => 'Room ID and message are required']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;
$avatar = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['avatar'] : ($_SESSION['user']['avatar'] ?? null);

// Get the user_id string for both registered users and guests
$user_id_string = '';
if ($_SESSION['user']['type'] === 'user') {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
} else {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
}

error_log("Inserting message: room_id=$room_id, user_id=$user_id, guest_name=$guest_name, avatar=$avatar, user_id_string=$user_id_string, message=$message");

$stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, user_id_string) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    error_log("Prepare failed in send_message.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iissss", $room_id, $user_id, $guest_name, $message, $avatar, $user_id_string);
if (!$stmt->execute()) {
    error_log("Execute failed in send_message.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    exit;
}
$stmt->close();

echo json_encode(['status' => 'success']);
?>
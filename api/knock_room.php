<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in knock_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['type'])) {
    error_log("No valid user session in knock_room.php");
    echo json_encode(['status' => 'error', 'message' => 'No valid user session']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$knock_message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($room_id <= 0) {
    error_log("Invalid room_id in knock_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

// Get user information
$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$knocker_name = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['username'] : $guest_name;

if (empty($user_id_string)) {
    error_log("Missing user_id_string in knock_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

error_log("Knock request: room_id=$room_id, user_id_string=$user_id_string, knocker_name=$knocker_name, message=$knock_message");

// Check if room exists and is password protected
$stmt = $conn->prepare("SELECT id, name, password, host_user_id FROM chatrooms WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed in knock_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("Room not found in knock_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Room not found']);
    $stmt->close();
    exit;
}
$room = $result->fetch_assoc();
$stmt->close();

// Check if room has a password
if (empty($room['password'])) {
    error_log("Room is not password protected in knock_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Room is not password protected']);
    exit;
}

// Check if user already has a pending knock for this room
$stmt = $conn->prepare("SELECT id FROM room_knocks WHERE room_id = ? AND user_id_string = ? AND status = 'pending'");
if (!$stmt) {
    error_log("Prepare failed for duplicate knock check in knock_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    error_log("User already has pending knock: room_id=$room_id, user_id_string=$user_id_string");
    echo json_encode(['status' => 'error', 'message' => 'You already have a pending knock for this room']);
    $stmt->close();
    exit;
}
$stmt->close();

// Check if user is already in the room
$stmt = $conn->prepare("SELECT id FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    error_log("Prepare failed for room membership check in knock_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    error_log("User already in room: room_id=$room_id, user_id_string=$user_id_string");
    echo json_encode(['status' => 'error', 'message' => 'You are already in this room']);
    $stmt->close();
    exit;
}
$stmt->close();

// Create knock record
$stmt = $conn->prepare("INSERT INTO room_knocks (room_id, user_id, guest_name, user_id_string, knock_message, status) VALUES (?, ?, ?, ?, ?, 'pending')");
if (!$stmt) {
    error_log("Prepare failed for knock insert in knock_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iisss", $room_id, $user_id, $guest_name, $user_id_string, $knock_message);
if (!$stmt->execute()) {
    error_log("Execute failed for knock insert in knock_room.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$knock_id = $conn->insert_id;
$stmt->close();

error_log("Knock created: knock_id=$knock_id, room_id=$room_id, knocker=$knocker_name");

echo json_encode([
    'status' => 'success', 
    'message' => 'Knock sent to room host!',
    'knock_id' => $knock_id
]);
?>
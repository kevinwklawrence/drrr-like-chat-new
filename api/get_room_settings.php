<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['type'])) {
    echo json_encode(['status' => 'error', 'message' => 'No valid user session']);
    exit;
}

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

if ($room_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

// Get user_id_string from session
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

// Check if user is the host of the room
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    error_log("Prepare failed in get_room_settings.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0 || $result->fetch_assoc()['is_host'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'Only hosts can view room settings']);
    $stmt->close();
    exit;
}
$stmt->close();

// Get room settings
$stmt = $conn->prepare("SELECT name, description, capacity, background, permanent FROM chatrooms WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed for room settings query in get_room_settings.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Room not found']);
    $stmt->close();
    exit;
}
$room_settings = $result->fetch_assoc();
$stmt->close();

error_log("Retrieved room settings for room_id=$room_id");
echo json_encode([
    'status' => 'success', 
    'settings' => [
        'name' => $room_settings['name'],
        'description' => $room_settings['description'],
        'capacity' => $room_settings['capacity'],
        'background' => $room_settings['background'],
        'permanent' => $room_settings['permanent'],
        'has_password' => false // We don't return the actual password for security
    ]
]);
?>
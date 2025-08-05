<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in update_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;
$background = isset($_POST['background']) ? trim($_POST['background']) : '';
$permanent = isset($_POST['permanent']) ? (int)$_POST['permanent'] : 0;

if ($room_id <= 0 || empty($name) || $capacity <= 0) {
    error_log("Invalid input in update_room.php: room_id=$room_id, name=$name, capacity=$capacity");
    echo json_encode(['status' => 'error', 'message' => 'Room ID, name, and valid capacity are required']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;

// Check if user is a host
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND (user_id = ? OR guest_name = ?)");
if (!$stmt) {
    error_log("Prepare failed in update_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iis", $room_id, $user_id, $guest_name);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0 || $result->fetch_assoc()['is_host'] != 1) {
    error_log("User not a host in update_room.php: room_id=$room_id, user_id=$user_id, guest_name=$guest_name");
    echo json_encode(['status' => 'error', 'message' => 'Only hosts can update room settings']);
    $stmt->close();
    exit;
}
$stmt->close();

// Update room settings
$hashed_password = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
$stmt = $conn->prepare("UPDATE chatrooms SET name = ?, password = ?, capacity = ?, background = ?, permanent = ? WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed in update_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("ssisii", $name, $hashed_password, $capacity, $background, $permanent, $room_id);
if (!$stmt->execute()) {
    error_log("Execute failed in update_room.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

error_log("Room settings updated: room_id=$room_id, name=$name, permanent=$permanent"); // Debug
echo json_encode(['status' => 'success']);
?>
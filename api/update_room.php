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
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;
$background = isset($_POST['background']) ? trim($_POST['background']) : '';
$permanent = isset($_POST['permanent']) ? (int)$_POST['permanent'] : 0;

if ($room_id <= 0 || empty($name) || $capacity <= 0) {
    error_log("Invalid input in update_room.php: room_id=$room_id, name=$name, capacity=$capacity");
    echo json_encode(['status' => 'error', 'message' => 'Room ID, name, and valid capacity are required']);
    exit;
}

$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    error_log("Missing user_id_string in update_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

// Check if user is a host
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    error_log("Prepare failed in update_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0 || $result->fetch_assoc()['is_host'] != 1) {
    error_log("User not a host in update_room.php: room_id=$room_id, user_id_string=$user_id_string");
    echo json_encode(['status' => 'error', 'message' => 'Only hosts can update room settings']);
    $stmt->close();
    exit;
}
$stmt->close();

// Update room settings
$hashed_password = null;
if (!empty($password)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
}

// If password is empty, keep existing password (don't change it)
if (empty($password)) {
    $stmt = $conn->prepare("UPDATE chatrooms SET name = ?, description = ?, capacity = ?, background = ?, permanent = ? WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed in update_room.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ssissi", $name, $description, $capacity, $background, $permanent, $room_id);
} else {
    $stmt = $conn->prepare("UPDATE chatrooms SET name = ?, description = ?, password = ?, capacity = ?, background = ?, permanent = ? WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed in update_room.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("sssisii", $name, $description, $hashed_password, $capacity, $background, $permanent, $room_id);
}

if (!$stmt->execute()) {
    error_log("Execute failed in update_room.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

// Insert system message about room update
$host_name = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['username'] : $_SESSION['user']['name'];
$system_message = "$host_name has updated the room settings.";
$avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;

$stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, user_id_string, type) VALUES (?, ?, ?, ?, ?, ?, 'system')");
if ($stmt) {
    $stmt->bind_param("iissss", $room_id, $user_id, $guest_name, $system_message, $avatar, $user_id_string);
    if (!$stmt->execute()) {
        error_log("Execute failed for system message in update_room.php: " . $stmt->error);
    }
    $stmt->close();
}

error_log("Room settings updated: room_id=$room_id, name=$name, permanent=$permanent, host=$host_name");
echo json_encode(['status' => 'success', 'message' => 'Room settings updated successfully']);
?>
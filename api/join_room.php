<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in join_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($room_id <= 0) {
    error_log("Invalid room_id in join_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

error_log("Joining room: room_id=$room_id"); // Debug

$stmt = $conn->prepare("SELECT password, capacity, (SELECT COUNT(*) FROM chatroom_users WHERE room_id = ?) AS current_users FROM chatrooms WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed in join_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("ii", $room_id, $room_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("Room not found in join_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Room not found']);
    exit;
}
$room = $result->fetch_assoc();
$stmt->close();

if ($room['current_users'] >= $room['capacity']) {
    error_log("Room full in join_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Room is full']);
    exit;
}

if ($room['password'] && !password_verify($password, $room['password'])) {
    error_log("Incorrect password in join_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;
$guest_avatar = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['avatar'] : null;

// Get the user_id string for both registered users and guests
$user_id_string = '';
if ($_SESSION['user']['type'] === 'user') {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
} else {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

error_log("Session debug in join_room.php: " . json_encode($_SESSION['user']));
error_log("User_id_string in join_room.php: $user_id_string");

if (empty($user_id_string)) {
    error_log("Missing user_id_string in join_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session - missing user_id. Please log in again.']);
    exit;
}

error_log("Inserting user: room_id=$room_id, user_id=$user_id, guest_name=$guest_name, guest_avatar=$guest_avatar, user_id_string=$user_id_string, ip_address=$ip_address");

// Prevent duplicate entries - use user_id_string as primary identifier
$stmt = $conn->prepare("SELECT id, is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    error_log("Prepare failed for duplicate check in join_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $existing_user = $result->fetch_assoc();
    error_log("User already in room: room_id=$room_id, user_id_string=$user_id_string, existing_is_host=" . $existing_user['is_host']);
    echo json_encode(['status' => 'error', 'message' => 'User already in room']);
    $stmt->close();
    exit;
}
$stmt->close();

// Check if this user should be the host (if they are the room's designated host)
$should_be_host = 0;
$stmt = $conn->prepare("SELECT host_user_id FROM chatrooms WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $room_data = $result->fetch_assoc();
        if ($room_data['host_user_id'] === $user_id_string) {
            $should_be_host = 1;
            error_log("User is the designated host for this room: $user_id_string");
        }
    }
    $stmt->close();
}

$stmt = $conn->prepare("INSERT INTO chatroom_users (room_id, user_id, guest_name, guest_avatar, user_id_string, is_host, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    error_log("Prepare failed in join_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iissssi", $room_id, $user_id, $guest_name, $guest_avatar, $user_id_string, $should_be_host, $ip_address);
if (!$stmt->execute()) {
    error_log("Execute failed in join_room.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    exit;
}
$stmt->close();

error_log("User joined room: room_id=$room_id, user_id_string=$user_id_string, is_host=$should_be_host");

// Insert system message for join
$name = $user_id ? $_SESSION['user']['username'] : $guest_name;
$avatar = $user_id ? $_SESSION['user']['avatar'] : $guest_avatar;
$system_message = $should_be_host ? "$name has joined the room and is now the host." : "$name has joined the room.";
$stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, user_id_string, type) VALUES (?, ?, ?, ?, ?, ?, 'system')");
if (!$stmt) {
    error_log("Prepare failed for system message in join_room.php: " . $conn->error);
} else {
    $stmt->bind_param("iissss", $room_id, $user_id, $guest_name, $system_message, $avatar, $user_id_string);
    if (!$stmt->execute()) {
        error_log("Execute failed for system message in join_room.php: " . $stmt->error);
    }
    $stmt->close();
}

$_SESSION['room_id'] = $room_id;
error_log("Set session room_id: $room_id"); // Debug
echo json_encode(['status' => 'success']);
?>
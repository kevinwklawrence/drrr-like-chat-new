<?php
ob_start();
session_start();
include '../db_connect.php';

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

error_log("Starting create_room.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in create_room.php");
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['type'])) {
    error_log("No valid user session in create_room.php: " . json_encode($_SESSION));
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'No valid user session']);
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 10;
$background = isset($_POST['background']) ? trim($_POST['background']) : '';
$permanent = isset($_POST['permanent']) ? (int)$_POST['permanent'] : 0;

if (empty($name) || $capacity <= 0) {
    error_log("Invalid room data in create_room.php: name=$name, capacity=$capacity");
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Room name and valid capacity are required']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? ($_SESSION['user']['id'] ?? null) : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? ($_SESSION['user']['name'] ?? null) : null;
$guest_avatar = ($_SESSION['user']['type'] === 'guest') ? ($_SESSION['user']['avatar'] ?? 'default_avatar.jpg') : null;

if (!$user_id && !$guest_name) {
    error_log("Invalid session in create_room.php: no user_id or guest_name");
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

$hashed_password = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

$stmt = $conn->prepare("INSERT INTO chatrooms (name, password, description, capacity, background, created_by, permanent) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    error_log("Prepare failed in create_room.php: " . $conn->error);
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("sssisii", $name, $hashed_password, $description, $capacity, $background, $user_id, $permanent);
if (!$stmt->execute()) {
    error_log("Execute failed in create_room.php: " . $stmt->error);
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$room_id = $conn->insert_id;
$stmt->close();

error_log("Created room: id=$room_id, name=$name, permanent=$permanent, created_by=$user_id");

// Check for duplicate user in chatroom_users
$stmt = $conn->prepare("SELECT id FROM chatroom_users WHERE room_id = ? AND (user_id = ? OR guest_name = ?)");
if (!$stmt) {
    error_log("Prepare failed for duplicate check in create_room.php: " . $conn->error);
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iis", $room_id, $user_id, $guest_name);
if (!$stmt->execute()) {
    error_log("Execute failed for duplicate check in create_room.php: " . $stmt->error);
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}
if ($stmt->get_result()->num_rows > 0) {
    error_log("User already in room: room_id=$room_id, user_id=$user_id, guest_name=$guest_name");
} else {
    // Auto-join the user to the room
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO chatroom_users (room_id, user_id, guest_name, guest_avatar, ip_address) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare failed for auto-join in create_room.php: " . $conn->error);
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("iisss", $room_id, $user_id, $guest_name, $guest_avatar, $ip_address);
    if (!$stmt->execute()) {
        error_log("Execute failed for auto-join in create_room.php: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();
    error_log("User joined room: room_id=$room_id, user_id=$user_id, guest_name=$guest_name");
}

// Insert system message
$name = $user_id ? ($_SESSION['user']['username'] ?? 'Unknown') : $guest_name;
$avatar = $user_id ? ($_SESSION['user']['avatar'] ?? 'default_avatar.jpg') : $guest_avatar;
$system_message = "$name has joined the room.";
$stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, type) VALUES (?, ?, ?, ?, ?, 'system')");
if (!$stmt) {
    error_log("Prepare failed for system message in create_room.php: " . $conn->error);
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iisss", $room_id, $user_id, $guest_name, $system_message, $avatar);
if (!$stmt->execute()) {
    error_log("Execute failed for system message in create_room.php: " . $stmt->error);
}
$stmt->close();

$_SESSION['room_id'] = $room_id;
error_log("Set session room_id: $room_id");
ob_end_clean();
echo json_encode(['status' => 'success', 'room_id' => $room_id]);
exit;
?>
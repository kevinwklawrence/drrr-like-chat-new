<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in manage_host.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$target_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$target_guest_name = isset($_POST['guest_name']) ? trim($_POST['guest_name']) : null;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'grant' or 'revoke'

if ($room_id <= 0 || $action !== 'grant' && $action !== 'revoke' || ($target_user_id === null && $target_guest_name === null)) {
    error_log("Invalid input in manage_host.php: room_id=$room_id, action=$action, user_id=$target_user_id, guest_name=$target_guest_name");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID, action, or target user']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;

// Check if current user is a host
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND (user_id = ? OR guest_name = ?)");
if (!$stmt) {
    error_log("Prepare failed in manage_host.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iis", $room_id, $user_id, $guest_name);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0 || $result->fetch_assoc()['is_host'] != 1) {
    error_log("User not a host in manage_host.php: room_id=$room_id, user_id=$user_id, guest_name=$guest_name");
    echo json_encode(['status' => 'error', 'message' => 'Only hosts can manage host privileges']);
    $stmt->close();
    exit;
}
$stmt->close();

// Update host status
$is_host = ($action === 'grant') ? 1 : 0;
$stmt = $conn->prepare("UPDATE chatroom_users SET is_host = ? WHERE room_id = ? AND (user_id = ? OR guest_name = ?)");
if (!$stmt) {
    error_log("Prepare failed in manage_host.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iiis", $is_host, $room_id, $target_user_id, $target_guest_name);
if (!$stmt->execute()) {
    error_log("Execute failed in manage_host.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

// Insert system message
$name = $user_id ? $_SESSION['user']['username'] : $guest_name;
$target_name = $target_user_id ? ($conn->query("SELECT username FROM users WHERE id = $target_user_id")->fetch_assoc()['username'] ?? 'Unknown') : $target_guest_name;
$system_message = $action === 'grant' ? "$target_name has been granted host privileges by $name." : "$target_name has had host privileges revoked by $name.";
$avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
$stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, type) VALUES (?, ?, ?, ?, ?, 'system')");
if ($stmt) {
    $stmt->bind_param("iisss", $room_id, $user_id, $guest_name, $system_message, $avatar);
    if (!$stmt->execute()) {
        error_log("Execute failed for system message in manage_host.php: " . $stmt->error);
    }
    $stmt->close();
}

error_log("Host privileges updated: room_id=$room_id, target_user_id=$target_user_id, target_guest_name=$target_guest_name, action=$action"); // Debug
echo json_encode(['status' => 'success']);
?>
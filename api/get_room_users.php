<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if ($room_id <= 0) {
    error_log("Invalid room_id in get_room_users.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

error_log("Fetching users for room_id=$room_id"); // Debug

$stmt = $conn->prepare("
    SELECT cu.user_id, cu.guest_name, cu.guest_avatar, cu.user_id_string, cu.is_host, u.username, u.is_admin, u.avatar 
    FROM chatroom_users cu 
    LEFT JOIN users u ON cu.user_id = u.id 
    WHERE cu.room_id = ?
");
if (!$stmt) {
    error_log("Prepare failed in get_room_users.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

error_log("Retrieved " . count($users) . " users for room_id=$room_id"); // Debug
echo json_encode($users);
?>
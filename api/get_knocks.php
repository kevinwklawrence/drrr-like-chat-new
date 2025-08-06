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
    error_log("Prepare failed in get_knocks.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0 || $result->fetch_assoc()['is_host'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'Only hosts can view knocks']);
    $stmt->close();
    exit;
}
$stmt->close();

// Get pending knocks for this room
$stmt = $conn->prepare("
    SELECT rk.id, rk.user_id, rk.guest_name, rk.user_id_string, rk.knock_message, rk.created_at, u.username
    FROM room_knocks rk
    LEFT JOIN users u ON rk.user_id = u.id
    WHERE rk.room_id = ? AND rk.status = 'pending'
    ORDER BY rk.created_at ASC
");
if (!$stmt) {
    error_log("Prepare failed for knocks query in get_knocks.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$knocks = [];
while ($row = $result->fetch_assoc()) {
    $knocks[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'guest_name' => $row['guest_name'],
        'user_id_string' => $row['user_id_string'],
        'display_name' => $row['username'] ?: $row['guest_name'],
        'message' => $row['knock_message'],
        'created_at' => $row['created_at'],
        'user_type' => $row['user_id'] ? 'user' : 'guest'
    ];
}
$stmt->close();

error_log("Retrieved " . count($knocks) . " pending knocks for room_id=$room_id");
echo json_encode(['status' => 'success', 'knocks' => $knocks]);
?>
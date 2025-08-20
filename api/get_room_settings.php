<?php
// api/get_room_settings.php - Updated version with all new features
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

// Check what columns exist in the chatrooms table
$columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
$available_columns = [];
while ($row = $columns_query->fetch_assoc()) {
    $available_columns[] = $row['Field'];
}

// Build the SELECT query based on available columns
$select_fields = ['name', 'description', 'capacity'];

// Add optional fields if they exist
$optional_fields = [
    'background', 'permanent', 'has_password', 'allow_knocking', 'theme', 
    'youtube_enabled', 'is_rp', 'friends_only', 'invite_only', 'members_only',
    'disappearing_messages', 'message_lifetime_minutes', 'invite_code'
];

foreach ($optional_fields as $field) {
    if (in_array($field, $available_columns)) {
        $select_fields[] = $field;
    }
}

$sql = "SELECT " . implode(', ', $select_fields) . " FROM chatrooms WHERE id = ?";

// Get room settings
$stmt = $conn->prepare($sql);
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

// Ensure all expected fields exist with default values
$settings = [
    'name' => $room_settings['name'],
    'description' => $room_settings['description'] ?? '',
    'capacity' => (int)$room_settings['capacity'],
    'background' => $room_settings['background'] ?? '',
    'permanent' => isset($room_settings['permanent']) ? (bool)$room_settings['permanent'] : false,
    'has_password' => isset($room_settings['has_password']) ? (bool)$room_settings['has_password'] : false,
    'allow_knocking' => isset($room_settings['allow_knocking']) ? (bool)$room_settings['allow_knocking'] : true,
    'theme' => $room_settings['theme'] ?? 'default',
    'youtube_enabled' => isset($room_settings['youtube_enabled']) ? (bool)$room_settings['youtube_enabled'] : false,
    'is_rp' => isset($room_settings['is_rp']) ? (bool)$room_settings['is_rp'] : false,
    'friends_only' => isset($room_settings['friends_only']) ? (bool)$room_settings['friends_only'] : false,
    'invite_only' => isset($room_settings['invite_only']) ? (bool)$room_settings['invite_only'] : false,
    'members_only' => isset($room_settings['members_only']) ? (bool)$room_settings['members_only'] : false,
    'disappearing_messages' => isset($room_settings['disappearing_messages']) ? (bool)$room_settings['disappearing_messages'] : false,
    'message_lifetime_minutes' => isset($room_settings['message_lifetime_minutes']) ? (int)$room_settings['message_lifetime_minutes'] : 0,
    'invite_code' => $room_settings['invite_code'] ?? null
];

error_log("Retrieved room settings for room_id=$room_id: " . json_encode($settings));
echo json_encode([
    'status' => 'success', 
    'settings' => $settings
]);
?>
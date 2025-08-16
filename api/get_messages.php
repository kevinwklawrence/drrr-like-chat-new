<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if ($room_id <= 0) {
    error_log("Invalid room_id in get_messages.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

error_log("Fetching messages for room_id=$room_id"); // Debug

// Check what columns exist in both tables
$msg_columns_query = $conn->query("SHOW COLUMNS FROM messages");
$msg_columns = [];
while ($row = $msg_columns_query->fetch_assoc()) {
    $msg_columns[] = $row['Field'];
}

$cu_columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
$cu_columns = [];
while ($row = $cu_columns_query->fetch_assoc()) {
    $cu_columns[] = $row['Field'];
}

$users_columns_query = $conn->query("SHOW COLUMNS FROM users");
$users_columns = [];
while ($row = $users_columns_query->fetch_assoc()) {
    $users_columns[] = $row['Field'];
}

// Build select fields
$select_fields = [
    'm.id', 
    'm.user_id', 
    'm.guest_name', 
    'm.message', 
    'm.avatar',
    'm.type', 
    'm.timestamp'
];

// Add stored customization fields from messages table
if (in_array('color', $msg_columns)) {
    $select_fields[] = 'm.color';
}
if (in_array('avatar_hue', $msg_columns)) {
    $select_fields[] = 'm.avatar_hue';
}
if (in_array('avatar_saturation', $msg_columns)) {
    $select_fields[] = 'm.avatar_saturation';
}

// Add user fields if they exist
if (in_array('username', $users_columns)) {
    $select_fields[] = 'u.username';
}
if (in_array('is_admin', $users_columns)) {
    $select_fields[] = 'u.is_admin';
}

// Add chatroom_users fields
if (in_array('ip_address', $cu_columns)) {
    $select_fields[] = 'cu.ip_address';
}
if (in_array('is_host', $cu_columns)) {
    $select_fields[] = 'cu.is_host';
}
if (in_array('guest_avatar', $cu_columns)) {
    $select_fields[] = 'cu.guest_avatar';
}
if (in_array('user_id_string', $cu_columns)) {
    $select_fields[] = 'cu.user_id_string';
}

$sql = "SELECT " . implode(', ', $select_fields) . "
        FROM messages m 
        LEFT JOIN users u ON m.user_id = u.id 
        LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id 
            AND (
                (m.user_id IS NOT NULL AND m.user_id = cu.user_id) OR 
                (m.user_id IS NULL AND m.guest_name = cu.guest_name) OR
                (m.user_id IS NULL AND m.user_id_string = cu.user_id_string)
            )
        WHERE m.room_id = ? 
        ORDER BY m.timestamp ASC";

error_log("Messages query: " . $sql); // Debug

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed in get_messages.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];

while ($row = $result->fetch_assoc()) {
    error_log("Raw message row: " . print_r($row, true)); // Debug
    
    // Use stored values from when the message was sent (no lookups needed)
    $avatar_hue = (int)($row['avatar_hue'] ?? 0);
    $avatar_saturation = (int)($row['avatar_saturation'] ?? 100);
    $user_color = $row['color'] ?? 'blue';
    
    // Process the message data to ensure compatibility with frontend
    $processed_message = [
        'id' => $row['id'],
        'message' => $row['message'],
        'timestamp' => $row['timestamp'],
        'type' => $row['type'],
        
        // User identification
        'user_id' => $row['user_id'],
        'user_id_string' => $row['user_id_string'] ?? '',
        
        // Display information
        'username' => $row['username'] ?? null,
        'guest_name' => $row['guest_name'],
        'display_name' => $row['username'] ?? $row['guest_name'] ?? 'Unknown',
        
        // Avatar information
        'avatar' => $row['avatar'] ?? $row['guest_avatar'] ?? 'default_avatar.jpg',
        'guest_avatar' => $row['guest_avatar'] ?? null,
        
        // STORED avatar customization (preserved from when message was sent)
        'avatar_hue' => $avatar_hue,
        'avatar_saturation' => $avatar_saturation,
        'user_avatar_hue' => $avatar_hue,  // For compatibility
        'user_avatar_saturation' => $avatar_saturation,  // For compatibility
        
        // STORED color information (preserved from when message was sent)
        'color' => $user_color,
        'user_color' => $user_color,  // For compatibility
        
        // Permissions and roles
        'is_admin' => (bool)($row['is_admin'] ?? false),
        'is_host' => (bool)($row['is_host'] ?? false),
        
        // Admin information
        'ip_address' => $row['ip_address'] ?? null
    ];
    
    error_log("Processed message: " . print_r($processed_message, true)); // Debug
    $messages[] = $processed_message;
}

$stmt->close();

error_log("Retrieved " . count($messages) . " messages for room_id=$room_id"); // Debug
echo json_encode($messages);
?>
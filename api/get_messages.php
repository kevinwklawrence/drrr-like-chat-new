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

// Pagination parameters
$limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 150) : 100;
$offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;
$load_older = isset($_GET['load_older']) ? (bool)$_GET['load_older'] : false;

error_log("Fetching messages for room_id=$room_id, limit=$limit, offset=$offset, load_older=" . ($load_older ? 'true' : 'false'));

// Check what columns exist in tables
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
if (in_array('bubble_hue', $msg_columns)) {
    $select_fields[] = 'm.bubble_hue';
}
if (in_array('bubble_saturation', $msg_columns)) {
    $select_fields[] = 'm.bubble_saturation';
}
if (in_array('user_id_string', $msg_columns)) {
    $select_fields[] = 'm.user_id_string';
}

// Add reply and mention fields
if (in_array('reply_to_message_id', $msg_columns)) {
    $select_fields[] = 'm.reply_to_message_id';
}
if (in_array('mentions', $msg_columns)) {
    $select_fields[] = 'm.mentions';
}

// Add user fields if they exist
if (in_array('username', $users_columns)) {
    $select_fields[] = 'u.username';
}
if (in_array('is_admin', $users_columns)) {
    $select_fields[] = 'u.is_admin';
}
if (in_array('is_moderator', $users_columns)) {
    $select_fields[] = 'u.is_moderator';
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
if (in_array('user_id_string', $cu_columns) && !in_array('m.user_id_string', $select_fields)) {
    $select_fields[] = 'cu.user_id_string';
}

// Add reply message fields if reply functionality exists
$reply_fields = [];
if (in_array('reply_to_message_id', $msg_columns)) {
    $reply_fields = [
        'rm.color as reply_original_color',
        'rm.id as reply_original_id',
        'rm.message as reply_original_message',
        'rm.user_id_string as reply_original_user_id_string',
        'rm.guest_name as reply_original_guest_name',
        'rm.avatar as reply_original_avatar',
        'rm.avatar_hue as reply_original_avatar_hue',
        'rm.avatar_saturation as reply_original_avatar_saturation',
        'rm.bubble_hue as reply_original_bubble_hue',
        'rm.bubble_saturation as reply_original_bubble_saturation',
        'ru.username as reply_original_registered_username',
        'ru.avatar as reply_original_registered_avatar',
        'rcu.username as reply_original_chatroom_username'
    ];
    $select_fields = array_merge($select_fields, $reply_fields);
}

// Get total count for pagination info
$count_sql = "SELECT COUNT(*) as total FROM messages WHERE room_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $room_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_count = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Build the main query
$sql = "SELECT " . implode(', ', $select_fields) . "
        FROM messages m 
        LEFT JOIN users u ON m.user_id = u.id 
        LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id 
            AND (
                (m.user_id IS NOT NULL AND m.user_id = cu.user_id) OR 
                (m.user_id IS NULL AND m.guest_name = cu.guest_name) OR
                (m.user_id IS NULL AND m.user_id_string = cu.user_id_string)
            )";

// Add reply message join if column exists
if (in_array('reply_to_message_id', $msg_columns)) {
    $sql .= " LEFT JOIN messages rm ON m.reply_to_message_id = rm.id
              LEFT JOIN users ru ON rm.user_id = ru.id
              LEFT JOIN chatroom_users rcu ON rm.room_id = rcu.room_id 
                AND (
                    (rm.user_id IS NOT NULL AND rm.user_id = rcu.user_id) OR 
                    (rm.user_id IS NULL AND rm.guest_name = rcu.guest_name) OR
                    (rm.user_id IS NULL AND rm.user_id_string = rcu.user_id_string)
                )";
}

$sql .= " WHERE m.room_id = ?";

// For pagination, always order by timestamp DESC and use LIMIT/OFFSET
// The frontend will handle reversing the order if needed
$sql .= " ORDER BY m.timestamp DESC LIMIT ? OFFSET ?";

error_log("Messages query: " . $sql);
error_log("Query params: room_id=$room_id, limit=$limit, offset=$offset");

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed in get_messages.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iii", $room_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];

// Process messages
while ($row = $result->fetch_assoc()) {
    $avatar_hue = (int)($row['avatar_hue'] ?? 0);
    $avatar_saturation = (int)($row['avatar_saturation'] ?? 100);
    $user_color = $row['color'] ?? 'blue';
    $bubble_hue = (int)($row['bubble_hue'] ?? 0);
    $bubble_saturation = (int)($row['bubble_saturation'] ?? 100);
    
    $message_data = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'guest_name' => $row['guest_name'],
        'message' => $row['message'],
        'avatar' => $row['avatar'],
        'type' => $row['type'],
        'timestamp' => $row['timestamp'],
        'color' => $user_color,
        'avatar_hue' => $avatar_hue,
        'avatar_saturation' => $avatar_saturation,
        'bubble_hue' => $bubble_hue,
        'bubble_saturation' => $bubble_saturation,
        'username' => $row['username'] ?? null,
        'is_admin' => $row['is_admin'] ?? false,
        'is_moderator' => $row['is_moderator'] ?? false,
        'ip_address' => $row['ip_address'] ?? null,
        'is_host' => $row['is_host'] ?? false,
        'guest_avatar' => $row['guest_avatar'] ?? null,
        'user_id_string' => $row['user_id_string'] ?? null
    ];
    
    // Add reply data if present
    if (in_array('reply_to_message_id', $msg_columns) && !empty($row['reply_to_message_id'])) {
        $message_data['reply_to_message_id'] = $row['reply_to_message_id'];
        $message_data['reply_original_color'] = $row['reply_original_color'] ?? null;
        $message_data['reply_original_id'] = $row['reply_original_id'] ?? null;
        $message_data['reply_original_message'] = $row['reply_original_message'] ?? null;
        $message_data['reply_original_user_id_string'] = $row['reply_original_user_id_string'] ?? null;
        $message_data['reply_original_guest_name'] = $row['reply_original_guest_name'] ?? null;
        $message_data['reply_original_avatar'] = $row['reply_original_avatar'] ?? null;
        $message_data['reply_original_avatar_hue'] = $row['reply_original_avatar_hue'] ?? 0;
        $message_data['reply_original_avatar_saturation'] = $row['reply_original_avatar_saturation'] ?? 100;
        $message_data['reply_original_bubble_hue'] = $row['reply_original_bubble_hue'] ?? 0;
        $message_data['reply_original_bubble_saturation'] = $row['reply_original_bubble_saturation'] ?? 100;
        $message_data['reply_original_registered_username'] = $row['reply_original_registered_username'] ?? null;
        $message_data['reply_original_registered_avatar'] = $row['reply_original_registered_avatar'] ?? null;
        $message_data['reply_original_chatroom_username'] = $row['reply_original_chatroom_username'] ?? null;
    }
    
    // Add mentions if present
    if (in_array('mentions', $msg_columns)) {
        $message_data['mentions'] = $row['mentions'] ?? null;
    }
    
    $messages[] = $message_data;
}

$stmt->close();

// IMPORTANT: Always reverse so messages display in chronological order
// Database gives us newest first (DESC), but we want oldest first for display
// This ensures: oldest messages at top, newest messages at bottom
$messages = array_reverse($messages);

// Debug: Log message order
if (count($messages) > 0) {
    $first_msg_time = $messages[0]['timestamp'];
    $last_msg_time = $messages[count($messages)-1]['timestamp'];
    error_log("Message order check - First: $first_msg_time, Last: $last_msg_time");
}

// Calculate pagination info
$has_more_newer = $offset > 0;
$has_more_older = ($offset + $limit) < $total_count;

// Debug logging
error_log("Messages fetched: " . count($messages));
error_log("Total count: $total_count, Offset: $offset, Limit: $limit");
error_log("Has more older: " . ($has_more_older ? 'true' : 'false'));
error_log("Has more newer: " . ($has_more_newer ? 'true' : 'false'));

echo json_encode([
    'status' => 'success',
    'messages' => $messages,
    'pagination' => [
        'total_count' => $total_count,
        'current_offset' => $offset,
        'limit' => $limit,
        'has_more_newer' => $has_more_newer,
        'has_more_older' => $has_more_older,
        'loaded_count' => count($messages),
        'load_older' => $load_older
    ]
]);

$conn->close();
?>
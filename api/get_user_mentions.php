<?php
// api/get_mentions.php - Get mentions and reply notifications for current user
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

// Try multiple possible paths for db_connect.php
$db_paths = [
    '../db_connect.php',
    './db_connect.php',
    dirname(__DIR__) . '/db_connect.php',
    $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php'
];

$db_connected = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Get unread mentions for the current user in this room
    $stmt = $conn->prepare("
        SELECT 
            um.id,
            um.message_id,
            um.mention_type,
            um.created_at,
            m.message,
            m.timestamp as message_timestamp,
            m.user_id_string as sender_user_id_string,
            m.username as sender_username,
            m.guest_name as sender_guest_name,
            m.avatar as sender_avatar,
            u.username as sender_registered_username,
            u.avatar as sender_registered_avatar
        FROM user_mentions um
        LEFT JOIN messages m ON um.message_id = m.id
        LEFT JOIN users u ON m.user_id = u.id
        WHERE um.room_id = ? 
        AND um.mentioned_user_id_string = ? 
        AND um.is_read = FALSE
        ORDER BY um.created_at DESC
        LIMIT 10
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mentions = [];
    while ($row = $result->fetch_assoc()) {
        $sender_name = $row['sender_registered_username'] ?: ($row['sender_username'] ?: ($row['sender_guest_name'] ?: 'Unknown'));
        $sender_avatar = $row['sender_registered_avatar'] ?: ($row['sender_avatar'] ?: 'default_avatar.jpg');
        
        $mentions[] = [
            'id' => $row['id'],
            'message_id' => $row['message_id'],
            'type' => $row['mention_type'],
            'message' => $row['message'],
            'sender_name' => $sender_name,
            'sender_avatar' => $sender_avatar,
            'sender_user_id_string' => $row['sender_user_id_string'],
            'timestamp' => $row['message_timestamp'],
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'mentions' => $mentions,
        'unread_count' => count($mentions)
    ]);
    
} catch (Exception $e) {
    error_log("Get mentions error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to get mentions: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
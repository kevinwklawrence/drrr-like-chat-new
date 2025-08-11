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

// Updated query to include color data and better field mapping
$stmt = $conn->prepare("
    SELECT 
        m.id, 
        m.user_id, 
        m.guest_name, 
        m.message, 
        m.avatar,
        m.type, 
        m.timestamp,
        u.username, 
        u.is_admin,
        u.color as user_color,
        cu.ip_address,
        cu.is_host,
        cu.color as chatroom_color,
        cu.guest_avatar,
        cu.user_id_string,
        -- Create unified fields for the frontend
        COALESCE(u.username, m.guest_name, cu.guest_name) as display_name,
        COALESCE(m.avatar, u.avatar, cu.guest_avatar, 'default_avatar.jpg') as avatar_url,
        COALESCE(u.color, cu.color, 'blue') as color,
        CASE 
            WHEN m.user_id IS NOT NULL THEN CONCAT('user_', m.user_id)
            WHEN cu.user_id_string IS NOT NULL THEN cu.user_id_string
            ELSE CONCAT('guest_', m.guest_name)
        END as user_id_string
    FROM messages m 
    LEFT JOIN users u ON m.user_id = u.id 
    LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id 
        AND (
            (m.user_id IS NOT NULL AND m.user_id = cu.user_id) OR 
            (m.user_id IS NULL AND m.guest_name = cu.guest_name)
        )
    WHERE m.room_id = ? 
    ORDER BY m.timestamp ASC
");

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
    // Process the message data to ensure compatibility with frontend
    $processed_message = [
        'id' => $row['id'],
        'message' => $row['message'],
        'timestamp' => $row['timestamp'],
        'type' => $row['type'],
        
        // User identification
        'user_id' => $row['user_id'],
        'user_id_string' => $row['user_id_string'],
        
        // Display information
        'username' => $row['username'],
        'guest_name' => $row['guest_name'],
        'display_name' => $row['display_name'],
        
        // Avatar information
        'avatar' => $row['avatar_url'],
        'guest_avatar' => $row['guest_avatar'],
        
        // Color information (this is the key addition)
        'color' => $row['color'],
        
        // Permissions and roles
        'is_admin' => (bool)$row['is_admin'],
        'is_host' => (bool)$row['is_host'],
        
        // Admin information
        'ip_address' => $row['ip_address']
    ];
    
    $messages[] = $processed_message;
}

$stmt->close();

error_log("Retrieved " . count($messages) . " messages for room_id=$room_id"); // Debug
echo json_encode($messages);
?>
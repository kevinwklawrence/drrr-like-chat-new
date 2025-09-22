<?php
// api/get_inactivity_status.php - Get current user's inactivity time
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/inactivity_config.php';

$user_id = $_SESSION['user']['user_id'] ?? '';
$room_id = $_SESSION['room_id'] ?? 0;

if (!$user_id || !$room_id) {
    echo json_encode(['status' => 'error', 'message' => 'Not in room']);
    exit;
}

$stmt = $conn->prepare("SELECT cu.inactivity_seconds, cu.is_host, c.youtube_enabled 
                        FROM chatroom_users cu 
                        JOIN chatrooms c ON cu.room_id = c.id 
                        WHERE cu.room_id = ? AND cu.user_id_string = ?");
$stmt->bind_param("is", $room_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $timeout = getDisconnectTimeout($room_id, $data['is_host'], $conn);
    
    echo json_encode([
        'status' => 'success',
        'seconds' => (int)$data['inactivity_seconds'],
        'timeout' => $timeout,
        'is_host' => (bool)$data['is_host'],
        'youtube_enabled' => (bool)$data['youtube_enabled']
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
}

$stmt->close();
$conn->close();
?>
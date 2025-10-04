<?php
// api/sse_friends_status.php - SSE for friend notifications and room status
session_start();

if (!isset($_SESSION['user'])) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Not authorized']) . "\n\n";
    exit;
}

$user_type = $_SESSION['user']['type'] ?? 'guest';
$user_id = ($user_type === 'user' && isset($_SESSION['user']['id'])) ? (int)$_SESSION['user']['id'] : null;
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$in_room = isset($_SESSION['room_id']);

session_write_close();

while (ob_get_level()) ob_end_clean();
ob_implicit_flush(1);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

include '../db_connect.php';

echo "data: " . json_encode(['type' => 'connected']) . "\n\n";
flush();

$max_duration = 300; // 5 minutes
$start_time = time();
$last_friend_hash = '';
$last_status_hash = '';

while ((time() - $start_time) < $max_duration && connection_status() == CONNECTION_NORMAL) {
    try {
        $updates = [];
        
        // Check friend notifications (only for registered users)
        if ($user_id) {
            $friend_stmt = $conn->prepare("
                SELECT 
                    fn.id,
                    fn.type,
                    fn.from_user_id,
                    fn.message,
                    fn.created_at,
                    fn.is_read,
                    u.username as from_user,
                    u.avatar as from_avatar
                FROM friend_notifications fn
                LEFT JOIN users u ON fn.from_user_id = u.id
                WHERE fn.to_user_id = ?
                AND fn.is_read = 0
                ORDER BY fn.created_at DESC
                LIMIT 20
            ");
            
            $friend_stmt->bind_param("i", $user_id);
            $friend_stmt->execute();
            $friend_result = $friend_stmt->get_result();
            
            $friend_notifications = [];
            while ($row = $friend_result->fetch_assoc()) {
                $friend_notifications[] = $row;
            }
            $friend_stmt->close();
            
            $friend_hash = md5(json_encode($friend_notifications));
            
            if ($friend_hash !== $last_friend_hash) {
                $updates['friend_notifications'] = [
                    'notifications' => $friend_notifications,
                    'count' => count($friend_notifications)
                ];
                $last_friend_hash = $friend_hash;
            }
        }
        
        // Check room status (if user is in a room)
        if ($in_room) {
            $status_stmt = $conn->prepare("
                SELECT 1 FROM chatroom_users 
                WHERE user_id_string = ? 
                LIMIT 1
            ");
            
            $status_stmt->bind_param("s", $user_id_string);
            $status_stmt->execute();
            $status_result = $status_stmt->get_result();
            
            $room_status = ($status_result->num_rows > 0) ? 'in_room' : 'not_in_room';
            $status_stmt->close();
            
            $status_hash = md5($room_status);
            
            if ($status_hash !== $last_status_hash) {
                $updates['room_status'] = [
                    'status' => $room_status
                ];
                $last_status_hash = $status_hash;
            }
        }
        
        // Send updates if any
        if (!empty($updates)) {
            echo "data: " . json_encode([
                'type' => 'status_update',
                'updates' => $updates
            ]) . "\n\n";
            flush();
        }
        
    } catch (Exception $e) {
        error_log("Friends/Status SSE Error: " . $e->getMessage());
    }
    
    if ((time() - $start_time) % 30 == 0) {
        echo "data: " . json_encode(['type' => 'heartbeat']) . "\n\n";
        flush();
    }
    
    sleep(5);
    
    if ((time() - $start_time) >= $max_duration) {
        echo "data: " . json_encode(['type' => 'reconnect']) . "\n\n";
        flush();
        break;
    }
}

$conn->close();
?>
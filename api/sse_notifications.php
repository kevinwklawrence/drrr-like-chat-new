<?php
// api/sse_notifications.php - SSE for general notifications
session_start();

if (!isset($_SESSION['user'])) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Not authorized']) . "\n\n";
    exit;
}

$user_type = $_SESSION['user']['type'] ?? 'guest';
$user_id = ($user_type === 'user' && isset($_SESSION['user']['id'])) ? (int)$_SESSION['user']['id'] : null;

if (!$user_id) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Only registered users']) . "\n\n";
    exit;
}

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
$last_hash = '';

while ((time() - $start_time) < $max_duration && connection_status() == CONNECTION_NORMAL) {
    try {
        // Get notifications
        $stmt = $conn->prepare("
            SELECT n.*, u.username as sender_name, u.avatar as sender_avatar
            FROM notifications n
            LEFT JOIN users u ON n.from_user_id = u.id
            WHERE n.user_id = ?
            AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT 20
        ");
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        
        $current_hash = md5(json_encode($notifications));
        
        if ($current_hash !== $last_hash) {
            echo "data: " . json_encode([
                'type' => 'notifications_update',
                'notifications' => $notifications,
                'count' => count($notifications)
            ]) . "\n\n";
            flush();
            
            $last_hash = $current_hash;
        }
        
    } catch (Exception $e) {
        error_log("Notification SSE Error: " . $e->getMessage());
    }
    
    if ((time() - $start_time) % 30 == 0) {
        echo "data: " . json_encode(['type' => 'heartbeat']) . "\n\n";
        flush();
    }
    
    sleep(3);
    
    if ((time() - $start_time) >= $max_duration) {
        echo "data: " . json_encode(['type' => 'reconnect']) . "\n\n";
        flush();
        break;
    }
}

$conn->close();
?>
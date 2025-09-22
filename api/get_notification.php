<?php
// api/get_notifications.php - Unified notification system
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

// Database connection
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
$user_id = $_SESSION['user']['id'] ?? 0;

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    $notifications = [];
    
    // 1. GET MENTION AND REPLY NOTIFICATIONS
    $mention_stmt = $conn->prepare("
        SELECT 
            um.id,
            um.message_id,
            um.mention_type as type,
            um.created_at,
            m.message,
            m.timestamp as message_timestamp,
            m.user_id_string as sender_user_id_string,
            m.username as sender_username,
            m.guest_name as sender_guest_name,
            m.avatar as sender_avatar,
            u.username as sender_registered_username,
            u.avatar as sender_registered_avatar,
            'mention' as notification_type
        FROM user_mentions um
        LEFT JOIN messages m ON um.message_id = m.id
        LEFT JOIN users u ON m.user_id = u.id
        WHERE um.room_id = ? 
        AND um.mentioned_user_id_string = ? 
        AND um.is_read = FALSE
        ORDER BY um.created_at DESC
        LIMIT 5
    ");
    
    if ($mention_stmt) {
        $mention_stmt->bind_param("is", $room_id, $user_id_string);
        $mention_stmt->execute();
        $mention_result = $mention_stmt->get_result();
        
        while ($row = $mention_result->fetch_assoc()) {
            $sender_name = $row['sender_registered_username'] ?: ($row['sender_username'] ?: ($row['sender_guest_name'] ?: 'Unknown'));
            $sender_avatar = $row['sender_registered_avatar'] ?: ($row['sender_avatar'] ?: 'default_avatar.jpg');
            
            $notifications[] = [
                'id' => 'mention_' . $row['id'],
                'type' => $row['type'], // 'mention' or 'reply'
                'notification_type' => 'mention',
                'message_id' => $row['message_id'],
                'title' => $row['type'] === 'reply' ? 'Reply to your message' : 'Mentioned you',
                'message' => $row['message'],
                'sender_name' => $sender_name,
                'sender_avatar' => $sender_avatar,
                'timestamp' => $row['created_at'],
                'action_data' => ['mention_id' => $row['id']]
            ];
        }
        $mention_stmt->close();
    }
    
    // 2. GET FRIEND REQUEST NOTIFICATIONS (only for registered users)
    if ($_SESSION['user']['type'] === 'user' && $user_id > 0) {
        $friend_stmt = $conn->prepare("
            SELECT 
                f.id,
                f.created_at,
                u.username,
                u.avatar,
                u.avatar_hue,
                u.avatar_saturation
            FROM friends f
            JOIN users u ON f.user_id = u.id
            WHERE f.friend_id = ? 
            AND f.status = 'pending'
            ORDER BY f.created_at DESC
            LIMIT 5
        ");
        
        if ($friend_stmt) {
            $friend_stmt->bind_param("i", $user_id);
            $friend_stmt->execute();
            $friend_result = $friend_stmt->get_result();
            
            while ($row = $friend_result->fetch_assoc()) {
                $notifications[] = [
                    'id' => 'friend_' . $row['id'],
                    'type' => 'friend_request',
                    'notification_type' => 'friend_request',
                    'title' => 'Friend Request',
                    'message' => $row['username'] . ' wants to be your friend',
                    'sender_name' => $row['username'],
                    'sender_avatar' => $row['avatar'] ?: 'default_avatar.jpg',
                    'timestamp' => $row['created_at'],
                    'action_data' => ['friend_id' => $row['id']]
                ];
            }
            $friend_stmt->close();
        }
        
        // 3. GET PRIVATE MESSAGE NOTIFICATIONS
        $pm_stmt = $conn->prepare("
            SELECT 
                pm.id,
                pm.created_at,
                pm.message,
                pm.is_read,
                u.username,
                u.avatar,
                u.avatar_hue,
                u.avatar_saturation,
                COUNT(*) as unread_count
            FROM private_messages pm
            JOIN users u ON pm.sender_id = u.id
            WHERE pm.recipient_id = ? 
            AND pm.is_read = FALSE
            GROUP BY pm.sender_id
            ORDER BY pm.created_at DESC
            LIMIT 5
        ");
        
        if ($pm_stmt) {
            $pm_stmt->bind_param("i", $user_id);
            $pm_stmt->execute();
            $pm_result = $pm_stmt->get_result();
            
            while ($row = $pm_result->fetch_assoc()) {
                $message_preview = strlen($row['message']) > 50 ? 
                    substr($row['message'], 0, 50) . '...' : 
                    $row['message'];
                
                $notifications[] = [
                    'id' => 'pm_' . $row['id'],
                    'type' => 'private_message',
                    'notification_type' => 'private_message',
                    'title' => 'Private Message',
                    'message' => ($row['unread_count'] > 1 ? 
                        $row['unread_count'] . ' new messages from ' : 
                        'New message from ') . $row['username'],
                    'message_preview' => $message_preview,
                    'sender_name' => $row['username'],
                    'sender_avatar' => $row['avatar'] ?: 'default_avatar.jpg',
                    'timestamp' => $row['created_at'],
                    'unread_count' => $row['unread_count'],
                    'action_data' => ['sender_id' => $row['id']]
                ];
            }
            $pm_stmt->close();
        }
    }
    
    // Sort all notifications by timestamp
    usort($notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    echo json_encode([
        'status' => 'success',
        'notifications' => $notifications,
        'total_count' => count($notifications)
    ]);
    
} catch (Exception $e) {
    error_log("Get notifications error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to get notifications: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>

<?php
// api/handle_notification_action.php - Handle notification actions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Database connection
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

$action = $_POST['action'] ?? '';
$notification_type = $_POST['notification_type'] ?? '';
$notification_id = $_POST['notification_id'] ?? '';

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$user_id = $_SESSION['user']['id'] ?? 0;
$room_id = (int)($_SESSION['room_id'] ?? 0);

try {
    switch ($action) {
        case 'mark_read':
            if ($notification_type === 'mention') {
                $mention_id = str_replace('mention_', '', $notification_id);
                $stmt = $conn->prepare("
                    UPDATE user_mentions 
                    SET is_read = TRUE 
                    WHERE id = ? 
                    AND room_id = ? 
                    AND mentioned_user_id_string = ?
                ");
                $stmt->bind_param("iis", $mention_id, $room_id, $user_id_string);
                $stmt->execute();
                $stmt->close();
                
            } elseif ($notification_type === 'private_message') {
                $sender_id = str_replace('pm_', '', $notification_id);
                $stmt = $conn->prepare("
                    UPDATE private_messages 
                    SET is_read = TRUE 
                    WHERE sender_id = ? 
                    AND recipient_id = ? 
                    AND is_read = FALSE
                ");
                $stmt->bind_param("ii", $sender_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
            echo json_encode(['status' => 'success', 'message' => 'Notification marked as read']);
            break;
            
        case 'accept_friend':
    $friend_request_id = str_replace('friend_', '', $notification_id);
    
    // FIXED: Remove the incorrect friend_id filter
    $stmt = $conn->prepare("SELECT user_id, friend_id FROM friends WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $friend_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Friend request not found']);
        exit;
    }
    
    $request_data = $result->fetch_assoc();
    $sender_id = $request_data['user_id'];
    $receiver_id = $request_data['friend_id'];
    $stmt->close();
    
    // FIXED: Verify the current user is the intended recipient
    if ($receiver_id != $user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Not authorized to accept this request']);
        exit;
    }
    
    // Update the original request
    $stmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?");
    $stmt->bind_param("i", $friend_request_id);
    $stmt->execute();
    $stmt->close();
    
    // Add reverse friendship
    $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted') ON DUPLICATE KEY UPDATE status = 'accepted'");
    $stmt->bind_param("ii", $user_id, $sender_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'message' => 'Friend request accepted']);
    break;

case 'reject_friend':
    $friend_request_id = str_replace('friend_', '', $notification_id);
    
    // FIXED: First verify the request exists and user is authorized
    $stmt = $conn->prepare("SELECT user_id, friend_id FROM friends WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $friend_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Friend request not found']);
        exit;
    }
    
    $request_data = $result->fetch_assoc();
    $receiver_id = $request_data['friend_id'];
    $stmt->close();
    
    // FIXED: Verify the current user is the intended recipient
    if ($receiver_id != $user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Not authorized to reject this request']);
        exit;
    }
    
    // Delete the request
    $stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
    $stmt->bind_param("i", $friend_request_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'message' => 'Friend request rejected']);
    break;
            
        case 'mark_all_read':
            // Mark all mentions as read
            $stmt = $conn->prepare("
                UPDATE user_mentions 
                SET is_read = TRUE 
                WHERE room_id = ? 
                AND mentioned_user_id_string = ? 
                AND is_read = FALSE
            ");
            $stmt->bind_param("is", $room_id, $user_id_string);
            $stmt->execute();
            $stmt->close();
            
            // Mark all private messages as read (for registered users)
            if ($user_id > 0) {
                $stmt = $conn->prepare("
                    UPDATE private_messages 
                    SET is_read = TRUE 
                    WHERE recipient_id = ? 
                    AND is_read = FALSE
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }
            
            echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read']);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Handle notification action error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to handle notification: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
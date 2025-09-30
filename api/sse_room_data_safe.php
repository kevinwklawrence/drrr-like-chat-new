<?php
// api/sse_room_data.php - OPTIMIZED Combined SSE endpoint
session_start();

// Read session data
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Not authorized']) . "\n\n";
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$user_type = $_SESSION['user']['type'] ?? 'guest';
$user_id = ($user_type === 'user' && isset($_SESSION['user']['id'])) ? (int)$_SESSION['user']['id'] : null;

// CRITICAL: Release session lock immediately
session_write_close();

// Disable all output buffering
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(1);

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

include '../db_connect.php';
require_once '../input_sanitizer.php';

require_once __DIR__ . '/../config/inactivity_config.php';

$message_limit = isset($_GET['message_limit']) ? min(max((int)$_GET['message_limit'], 1), 100) : 50;
$check_youtube = isset($_GET['check_youtube']) ? (bool)$_GET['check_youtube'] : false;

// Send connection confirmation
echo "data: " . json_encode(['type' => 'connected', 'room_id' => $room_id]) . "\n\n";
if (ob_get_level() > 0) ob_flush();
flush();

// Main loop
$max_duration = 300;
$start_time = time();

while ((time() - $start_time) < $max_duration && connection_status() == CONNECTION_NORMAL) {
    $all_data = ['type' => 'room_data', 'timestamp' => time()];
    
    try {
        // 1. MESSAGES
        $messages_stmt = $conn->prepare("
    SELECT m.*, 
           u.username, u.is_admin, u.is_moderator,
           cu.ip_address, cu.is_host, cu.guest_avatar,
           rm.id as reply_original_id,
           rm.message as reply_original_message,
           rm.user_id_string as reply_original_user_id_string,
           rm.guest_name as reply_original_guest_name,
           rm.avatar as reply_original_avatar,
           rm.avatar_hue as reply_original_avatar_hue,
           rm.avatar_saturation as reply_original_avatar_saturation,
           rm.bubble_hue as reply_original_bubble_hue,
           rm.bubble_saturation as reply_original_bubble_saturation,
           rm.color as reply_original_color,
           ru.username as reply_original_registered_username,
           rcu.username as reply_original_chatroom_username
    FROM messages m 
    LEFT JOIN users u ON m.user_id = u.id 
    LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id 
        AND (
            (m.user_id IS NOT NULL AND m.user_id = cu.user_id) OR 
            (m.user_id IS NULL AND m.guest_name = cu.guest_name) OR
            (m.user_id IS NULL AND m.user_id_string = cu.user_id_string)
        )
    LEFT JOIN messages rm ON m.reply_to_message_id = rm.id
    LEFT JOIN users ru ON rm.user_id = ru.id
    LEFT JOIN chatroom_users rcu ON rm.room_id = rcu.room_id 
        AND (
            (rm.user_id IS NOT NULL AND rm.user_id = rcu.user_id) OR 
            (rm.user_id IS NULL AND rm.guest_name = rcu.guest_name) OR
            (rm.user_id IS NULL AND rm.user_id_string = rcu.user_id_string)
        )
    WHERE m.room_id = ? 
    ORDER BY m.id DESC 
    LIMIT ?
");
$messages_stmt->bind_param("ii", $room_id, $message_limit);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages = [];
while ($msg = $messages_result->fetch_assoc()) { $messages[] = $msg; }
$messages_stmt->close();
$all_data['messages'] = ['status' => 'success', 'messages' => array_reverse($messages)];
        
        // 2. USERS
        $users_stmt = $conn->prepare("SELECT * FROM chatroom_users WHERE room_id = ? ORDER BY is_host DESC, joined_at ASC");
        $users_stmt->bind_param("i", $room_id);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
        $users = [];
        while ($user = $users_result->fetch_assoc()) { $users[] = $user; }
        $users_stmt->close();
        $all_data['users'] = $users;
        
        // 3. MENTIONS (for all users)
        $mentions_stmt = $conn->prepare("
            SELECT 
                um.id, um.message_id, um.mention_type, um.created_at,
                m.message, m.timestamp as message_timestamp,
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
            AND um.is_read = 0
            ORDER BY um.created_at DESC LIMIT 50
        ");
        $mentions_stmt->bind_param("is", $room_id, $user_id_string);
        $mentions_stmt->execute();
        $mentions_result = $mentions_stmt->get_result();
        $mentions = [];
        while ($row = $mentions_result->fetch_assoc()) {
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
        $mentions_stmt->close();
        $all_data['mentions'] = ['status' => 'success', 'mentions' => $mentions, 'unread_count' => count($mentions)];
        
        // 4. WHISPERS - OPTIMIZED (single query)
        $whispers_stmt = $conn->prepare("
            SELECT DISTINCT 
                CASE WHEN sender_user_id_string = ? THEN recipient_user_id_string ELSE sender_user_id_string END as other_user_id_string,
                cu.username, cu.guest_name, cu.avatar, cu.guest_avatar,
                (SELECT message FROM room_whispers rw2 
                 WHERE rw2.room_id = ? 
                 AND ((rw2.sender_user_id_string = ? AND rw2.recipient_user_id_string = other_user_id_string) 
                   OR (rw2.sender_user_id_string = other_user_id_string AND rw2.recipient_user_id_string = ?))
                 ORDER BY rw2.created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM room_whispers rw3 
                 WHERE rw3.room_id = ? 
                 AND rw3.sender_user_id_string = other_user_id_string 
                 AND rw3.recipient_user_id_string = ? 
                 AND rw3.is_read = 0) as unread_count
            FROM room_whispers rw
            JOIN chatroom_users cu ON cu.room_id = ? 
                AND cu.user_id_string = CASE WHEN rw.sender_user_id_string = ? THEN rw.recipient_user_id_string ELSE rw.sender_user_id_string END
            WHERE rw.room_id = ? 
            AND (rw.sender_user_id_string = ? OR rw.recipient_user_id_string = ?)
        ");
        $whispers_stmt->bind_param("sissisisiss", $user_id_string, $room_id, $user_id_string, $user_id_string, 
                                   $room_id, $user_id_string, $room_id, $user_id_string, $room_id, 
                                   $user_id_string, $user_id_string);
        $whispers_stmt->execute();
        $whispers_result = $whispers_stmt->get_result();
        $conversations = [];
        while ($row = $whispers_result->fetch_assoc()) {
            $conversations[] = [
                'other_user_id_string' => $row['other_user_id_string'],
                'username' => $row['username'],
                'guest_name' => $row['guest_name'],
                'avatar' => $row['avatar'] ?: $row['guest_avatar'] ?: 'default_avatar.jpg',
                'last_message' => $row['last_message'],
                'unread_count' => $row['unread_count']
            ];
        }
        $whispers_stmt->close();
        $all_data['whispers'] = ['status' => 'success', 'conversations' => $conversations];
        
        // 5. PRIVATE MESSAGES (registered users only)
        if ($user_type === 'user' && $user_id) {
            $pm_stmt = $conn->prepare("
                SELECT CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END as other_user_id,
                    u.username, u.avatar, u.avatar_hue, u.avatar_saturation,
                    (SELECT message FROM private_messages pm2 WHERE 
                     (pm2.sender_id = ? AND pm2.recipient_id = other_user_id) OR 
                     (pm2.sender_id = other_user_id AND pm2.recipient_id = ?)
                     ORDER BY pm2.created_at DESC LIMIT 1) as last_message,
                    (SELECT COUNT(*) FROM private_messages pm3 WHERE 
                     pm3.sender_id = other_user_id AND pm3.recipient_id = ? AND pm3.is_read = 0) as unread_count
                FROM private_messages pm
                JOIN users u ON u.id = (CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END)
                WHERE pm.sender_id = ? OR pm.recipient_id = ?
                GROUP BY other_user_id
                ORDER BY (SELECT MAX(created_at) FROM private_messages pm4 WHERE 
                         (pm4.sender_id = ? AND pm4.recipient_id = other_user_id) OR 
                         (pm4.sender_id = other_user_id AND pm4.recipient_id = ?)) DESC
            ");
            $pm_stmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
            $pm_stmt->execute();
            $pm_result = $pm_stmt->get_result();
            $pm_conversations = [];
            while ($row = $pm_result->fetch_assoc()) {
                $pm_conversations[] = $row;
            }
            $pm_stmt->close();
            $all_data['private_messages'] = ['status' => 'success', 'conversations' => $pm_conversations];
        }
        
        // 6. FRIENDS (registered users only)
        if ($user_type === 'user' && $user_id) {
            $friends_stmt = $conn->prepare("
                SELECT CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END as friend_user_id,
                       u.username, u.avatar, u.avatar_hue, u.avatar_saturation, f.status,
                       CASE WHEN f.user_id = ? THEN 'sent' ELSE 'received' END as request_type
                FROM friends f 
                JOIN users u ON (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END = u.id)
                WHERE (f.user_id = ? OR f.friend_id = ?)
                GROUP BY CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END
                ORDER BY MIN(f.created_at) DESC
            ");
            $friends_stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
            $friends_stmt->execute();
            $friends_result = $friends_stmt->get_result();
            $friends = [];
            while ($row = $friends_result->fetch_assoc()) {
                $friends[] = $row;
            }
            $friends_stmt->close();
            $all_data['friends'] = ['status' => 'success', 'friends' => $friends];
        }
        
        // 7. ROOM DATA
        $room_stmt = $conn->prepare("SELECT * FROM chatrooms WHERE id = ?");
        $room_stmt->bind_param("i", $room_id);
        $room_stmt->execute();
        $room_result = $room_stmt->get_result();
        $room_data = $room_result->num_rows > 0 ? $room_result->fetch_assoc() : null;
        $room_stmt->close();
        if ($room_data) {
            $all_data['room_data'] = ['status' => 'success', 'room' => $room_data];
        }
        
        // 8. KNOCKS (hosts only)
        $is_host_stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
        $is_host_stmt->bind_param("is", $room_id, $user_id_string);
        $is_host_stmt->execute();
        $is_host_result = $is_host_stmt->get_result();
        $is_host = ($is_host_result->num_rows > 0 && $is_host_result->fetch_assoc()['is_host'] == 1);
        $is_host_stmt->close();
        
        if ($is_host) {
            $knocks_stmt = $conn->prepare("
                SELECT k.id, k.user_id_string, k.created_at,
                       COALESCE(u.username, k.guest_name) as display_name, 
                       COALESCE(u.avatar, 'default_avatar.jpg') as avatar 
                FROM room_knocks k 
                LEFT JOIN users u ON k.user_id = u.id 
                WHERE k.room_id = ? AND k.status = 'pending' 
                ORDER BY k.created_at DESC
            ");
            $knocks_stmt->bind_param("i", $room_id);
            $knocks_stmt->execute();
            $knocks_result = $knocks_stmt->get_result();
            $knocks = [];
            while ($row = $knocks_result->fetch_assoc()) {
                $knocks[] = [
                    'id' => $row['id'],
                    'user_id_string' => $row['user_id_string'],
                    'display_name' => $row['display_name'],
                    'avatar' => $row['avatar'] ?: 'default_avatar.jpg',
                    'created_at' => $row['created_at']
                ];
            }
            $knocks_stmt->close();
            $all_data['knocks'] = $knocks;
        }
        
        // 9. YOUTUBE (if enabled)
        if ($check_youtube && isset($room_data['youtube_enabled']) && $room_data['youtube_enabled']) {
            $yt_stmt = $conn->prepare("
                SELECT current_video_id, current_time, is_playing, last_sync_time, sync_token 
                FROM room_player_sync WHERE room_id = ?
            ");
            $yt_stmt->bind_param("i", $room_id);
            $yt_stmt->execute();
            $yt_result = $yt_stmt->get_result();
            
            if ($yt_result->num_rows > 0) {
                $sync_data = $yt_result->fetch_assoc();
                
                $queue_stmt = $conn->prepare("SELECT * FROM youtube_queue WHERE room_id = ? ORDER BY position ASC");
                $queue_stmt->bind_param("i", $room_id);
                $queue_stmt->execute();
                $queue_result = $queue_stmt->get_result();
                $queue = [];
                while ($q = $queue_result->fetch_assoc()) { $queue[] = $q; }
                $queue_stmt->close();
                
                $sugg_stmt = $conn->prepare("SELECT * FROM youtube_suggestions WHERE room_id = ? ORDER BY created_at DESC LIMIT 10");
                $sugg_stmt->bind_param("i", $room_id);
                $sugg_stmt->execute();
                $sugg_result = $sugg_stmt->get_result();
                $suggestions = [];
                while ($s = $sugg_result->fetch_assoc()) { $suggestions[] = $s; }
                $sugg_stmt->close();
                
                $all_data['youtube'] = [
                    'status' => 'success',
                    'sync_data' => [
                        'enabled' => true,
                        'video_id' => $sync_data['current_video_id'],
                        'current_time' => (float)$sync_data['current_time'],
                        'is_playing' => (bool)$sync_data['is_playing'],
                        'last_sync_time' => $sync_data['last_sync_time'],
                        'sync_token' => $sync_data['sync_token']
                    ],
                    'queue_data' => [
                        'queue' => $queue,
                        'suggestions' => $suggestions,
                        'current_playing' => $sync_data['current_video_id']
                    ]
                ];
            }
            $yt_stmt->close();
        }
        
        // 10. SETTINGS CHECK
        $settings_stmt = $conn->prepare("
            SELECT id, UNIX_TIMESTAMP(created_at) as timestamp
            FROM room_events 
            WHERE room_id = ? AND event_type = 'settings_update' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ORDER BY created_at DESC LIMIT 1
        ");
        $settings_stmt->bind_param("i", $room_id);
        $settings_stmt->execute();
        $settings_result = $settings_stmt->get_result();
        
        if ($settings_result->num_rows > 0) {
            $event = $settings_result->fetch_assoc();
            $all_data['settings_check'] = [
                'status' => 'success',
                'settings_changed' => true,
                'event_id' => $event['id'],
                'timestamp' => $event['timestamp']
            ];
        } else {
            $all_data['settings_check'] = ['status' => 'success', 'settings_changed' => false];
        }
        $settings_stmt->close();
        
        // 11. INACTIVITY STATUS
        $inactivity_stmt = $conn->prepare("
            SELECT cu.inactivity_seconds, cu.is_host, c.youtube_enabled 
            FROM chatroom_users cu 
            JOIN chatrooms c ON cu.room_id = c.id 
            WHERE cu.room_id = ? AND cu.user_id_string = ?
        ");
        $inactivity_stmt->bind_param("is", $room_id, $user_id_string);
        $inactivity_stmt->execute();
        $inactivity_result = $inactivity_stmt->get_result();
        
        if ($inactivity_result->num_rows > 0) {
            $data = $inactivity_result->fetch_assoc();
            $timeout = getDisconnectTimeout($room_id, $data['is_host'], $conn);
            
            $all_data['inactivity_status'] = [
                'status' => 'success',
                'seconds' => (int)$data['inactivity_seconds'],
                'timeout' => $timeout,
                'is_host' => (bool)$data['is_host'],
                'youtube_enabled' => (bool)$data['youtube_enabled']
            ];
        } else {
            $all_data['inactivity_status'] = ['status' => 'error', 'message' => 'User not found'];
        }
        $inactivity_stmt->close();
        
    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
        $all_data['error'] = $e->getMessage();
    }
    
    // Send data
    echo "data: " . json_encode($all_data) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
    
    // Wait before next iteration
    sleep(2);
}

$conn->close();
?>
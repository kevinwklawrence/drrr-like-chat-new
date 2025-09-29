<?php
// api/sse_room_data.php - Combined SSE endpoint for all room data
session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Not authorized']) . "\n\n";
    exit;
}

include '../db_connect.php';
require_once __DIR__ . '/../config/inactivity_config.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$user_type = $_SESSION['user']['type'] ?? 'guest';
$user_id = ($user_type === 'user' && isset($_SESSION['user']['id'])) ? (int)$_SESSION['user']['id'] : null;

// Get request parameters
$message_limit = isset($_GET['message_limit']) ? min(max((int)$_GET['message_limit'], 1), 100) : 50;
$check_youtube = isset($_GET['check_youtube']) ? (bool)$_GET['check_youtube'] : false;

// Send connection confirmation
echo "data: " . json_encode(['type' => 'connected', 'room_id' => $room_id]) . "\n\n";
flush();

// Check what columns exist
$msg_columns = [];
$msg_query = $conn->query("SHOW COLUMNS FROM messages");
while ($row = $msg_query->fetch_assoc()) { $msg_columns[] = $row['Field']; }

$cu_columns = [];
$cu_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
while ($row = $cu_query->fetch_assoc()) { $cu_columns[] = $row['Field']; }

$users_columns = [];
$users_query = $conn->query("SHOW COLUMNS FROM users");
while ($row = $users_query->fetch_assoc()) { $users_columns[] = $row['Field']; }

// Main loop
$max_duration = 300; // 5 minutes
$start_time = time();

while ((time() - $start_time) < $max_duration && connection_status() == CONNECTION_NORMAL) {
    $all_data = ['type' => 'room_data', 'timestamp' => time()];
    
    try {
        // 1. MESSAGES
        $messages_stmt = $conn->prepare("SELECT * FROM messages WHERE room_id = ? ORDER BY id DESC LIMIT ?");
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
        
        // 3. MENTIONS (only for registered users)
        if ($user_type === 'user' && $user_id) {
            $mentions_stmt = $conn->prepare("
                SELECT m.*, u.username, u.avatar, u.avatar_hue, u.avatar_saturation 
                FROM user_mentions m
                JOIN users u ON m.sender_id = u.id
                WHERE m.recipient_id = ? AND m.is_read = 0
                ORDER BY m.created_at DESC LIMIT 50
            ");
            $mentions_stmt->bind_param("i", $user_id);
            $mentions_stmt->execute();
            $mentions_result = $mentions_stmt->get_result();
            $mentions = [];
            while ($mention = $mentions_result->fetch_assoc()) { $mentions[] = $mention; }
            $mentions_stmt->close();
            $all_data['mentions'] = ['status' => 'success', 'mentions' => $mentions];
        }
        
        // 4. WHISPERS (room whispers)
        $whispers_stmt = $conn->prepare("
            SELECT DISTINCT 
                CASE WHEN sender_user_id_string = ? THEN recipient_user_id_string ELSE sender_user_id_string END as other_user_id_string
            FROM room_whispers 
            WHERE room_id = ? AND (sender_user_id_string = ? OR recipient_user_id_string = ?)
        ");
        $whispers_stmt->bind_param("siss", $user_id_string, $room_id, $user_id_string, $user_id_string);
        $whispers_stmt->execute();
        $whispers_result = $whispers_stmt->get_result();
        
        $conversations = [];
        while ($row = $whispers_result->fetch_assoc()) {
            $other_user_id = $row['other_user_id_string'];
            
            $user_stmt = $conn->prepare("SELECT username, guest_name, avatar, guest_avatar FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
            $user_stmt->bind_param("is", $room_id, $other_user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                
                $msg_stmt = $conn->prepare("SELECT message FROM room_whispers WHERE room_id = ? AND ((sender_user_id_string = ? AND recipient_user_id_string = ?) OR (sender_user_id_string = ? AND recipient_user_id_string = ?)) ORDER BY created_at DESC LIMIT 1");
                $msg_stmt->bind_param("issss", $room_id, $user_id_string, $other_user_id, $other_user_id, $user_id_string);
                $msg_stmt->execute();
                $msg_result = $msg_stmt->get_result();
                $last_message = $msg_result->num_rows > 0 ? $msg_result->fetch_assoc()['message'] : '';
                $msg_stmt->close();
                
                $unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM room_whispers WHERE room_id = ? AND sender_user_id_string = ? AND recipient_user_id_string = ? AND is_read = 0");
                $unread_stmt->bind_param("iss", $room_id, $other_user_id, $user_id_string);
                $unread_stmt->execute();
                $unread_result = $unread_stmt->get_result();
                $unread_count = $unread_result->fetch_assoc()['unread_count'];
                $unread_stmt->close();
                
                $conversations[] = [
                    'other_user_id_string' => $other_user_id,
                    'username' => $user_data['username'],
                    'guest_name' => $user_data['guest_name'],
                    'avatar' => $user_data['avatar'] ?: $user_data['guest_avatar'] ?: 'default_avatar.jpg',
                    'last_message' => $last_message,
                    'unread_count' => $unread_count
                ];
            }
            $user_stmt->close();
        }
        $whispers_stmt->close();
        $all_data['whispers'] = ['status' => 'success', 'conversations' => $conversations];
        
        // 5. PRIVATE MESSAGES (only for registered users)
        if ($user_type === 'user' && $user_id) {
            $pm_stmt = $conn->prepare("
                SELECT DISTINCT 
                    CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END as other_user_id,
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
        
        // 6. FRIENDS (only for registered users)
        if ($user_type === 'user' && $user_id) {
            $friends_stmt = $conn->prepare("
                SELECT CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END as friend_user_id,
                       u.username, u.avatar, u.avatar_hue, u.avatar_saturation, f.status,
                       CASE WHEN f.user_id = ? THEN 'sent' ELSE 'received' END as request_type
                FROM friends f 
                JOIN users u ON (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END = u.id)
                WHERE (f.user_id = ? OR f.friend_id = ?)
                GROUP BY CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END, u.username, u.avatar, u.avatar_hue, u.avatar_saturation, f.status
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
        
        // 8. KNOCKS (only for hosts)
        $is_host_stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
        $is_host_stmt->bind_param("is", $room_id, $user_id_string);
        $is_host_stmt->execute();
        $is_host_result = $is_host_stmt->get_result();
        $is_host = ($is_host_result->num_rows > 0 && $is_host_result->fetch_assoc()['is_host'] == 1);
        $is_host_stmt->close();
        
        if ($is_host) {
            $knocks_stmt = $conn->prepare("SELECT k.*, COALESCE(u.username, k.guest_name) as display_name, COALESCE(u.avatar, k.guest_avatar, 'default_avatar.jpg') as avatar FROM room_knocks k LEFT JOIN users u ON k.user_id = u.id WHERE k.room_id = ? AND k.status = 'pending' ORDER BY k.created_at DESC");
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
        
        // 9. YOUTUBE DATA (if enabled)
        if ($check_youtube && isset($room_data['youtube_enabled']) && $room_data['youtube_enabled']) {
            $yt_stmt = $conn->prepare("SELECT current_video_id, current_time, is_playing, last_sync_time, sync_token FROM room_player_sync WHERE room_id = ?");
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
        
        // 10. ROOM SETTINGS CHECK
        $settings_stmt = $conn->prepare("
            SELECT id, UNIX_TIMESTAMP(created_at) as timestamp
            FROM room_events 
            WHERE room_id = ? 
            AND event_type = 'settings_update' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ORDER BY created_at DESC
            LIMIT 1
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
            $all_data['settings_check'] = [
                'status' => 'success',
                'settings_changed' => false
            ];
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
            $all_data['inactivity_status'] = [
                'status' => 'error',
                'message' => 'User not found'
            ];
        }
        $inactivity_stmt->close();
        
    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
        $all_data['error'] = $e->getMessage();
    }
    
    // Send data
    echo "data: " . json_encode($all_data) . "\n\n";
    flush();
    
    // Wait before next iteration
    sleep(3);
}

$conn->close();
?>
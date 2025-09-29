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
        $select_fields = ['m.id', 'm.user_id', 'm.guest_name', 'm.message', 'm.avatar', 'm.type', 'm.timestamp'];
        if (in_array('color', $msg_columns)) $select_fields[] = 'm.color';
        if (in_array('avatar_hue', $msg_columns)) $select_fields[] = 'm.avatar_hue';
        if (in_array('avatar_saturation', $msg_columns)) $select_fields[] = 'm.avatar_saturation';
        if (in_array('bubble_hue', $msg_columns)) $select_fields[] = 'm.bubble_hue';
        if (in_array('bubble_saturation', $msg_columns)) $select_fields[] = 'm.bubble_saturation';
        if (in_array('user_id_string', $msg_columns)) $select_fields[] = 'm.user_id_string';
        if (in_array('reply_to_message_id', $msg_columns)) $select_fields[] = 'm.reply_to_message_id';
        if (in_array('mentions', $msg_columns)) $select_fields[] = 'm.mentions';
        if (in_array('username', $users_columns)) $select_fields[] = 'u.username';
        if (in_array('is_admin', $users_columns)) $select_fields[] = 'u.is_admin';
        if (in_array('is_moderator', $users_columns)) $select_fields[] = 'u.is_moderator';
        if (in_array('ip_address', $cu_columns)) $select_fields[] = 'cu.ip_address';
        if (in_array('is_host', $cu_columns)) $select_fields[] = 'cu.is_host';
        if (in_array('guest_avatar', $cu_columns)) $select_fields[] = 'cu.guest_avatar';
        if (in_array('user_id_string', $cu_columns)) $select_fields[] = 'cu.user_id_string';
        
        $reply_fields = [];
        if (in_array('reply_to_message_id', $msg_columns)) {
            $reply_fields = [
                'rm.color as reply_original_color', 'rm.id as reply_original_id',
                'rm.message as reply_original_message', 'rm.user_id_string as reply_original_user_id_string',
                'rm.guest_name as reply_original_guest_name', 'rm.avatar as reply_original_avatar',
                'rm.avatar_hue as reply_original_avatar_hue', 'rm.avatar_saturation as reply_original_avatar_saturation',
                'rm.bubble_hue as reply_original_bubble_hue', 'rm.bubble_saturation as reply_original_bubble_saturation',
                'ru.username as reply_original_registered_username', 'ru.avatar as reply_original_registered_avatar',
                'rcu.username as reply_original_chatroom_username'
            ];
            $select_fields = array_merge($select_fields, $reply_fields);
        }
        
        $msg_sql = "SELECT " . implode(', ', $select_fields) . "
            FROM messages m 
            LEFT JOIN users u ON m.user_id = u.id 
            LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id 
                AND ((m.user_id IS NOT NULL AND m.user_id = cu.user_id) OR 
                     (m.user_id IS NULL AND m.guest_name = cu.guest_name) OR
                     (m.user_id IS NULL AND m.user_id_string = cu.user_id_string))";
        
        if (in_array('reply_to_message_id', $msg_columns)) {
            $msg_sql .= " LEFT JOIN messages rm ON m.reply_to_message_id = rm.id
                         LEFT JOIN users ru ON rm.user_id = ru.id
                         LEFT JOIN chatroom_users rcu ON rm.room_id = rcu.room_id 
                            AND ((rm.user_id IS NOT NULL AND rm.user_id = rcu.user_id) OR 
                                 (rm.user_id IS NULL AND rm.guest_name = rcu.guest_name) OR
                                 (rm.user_id IS NULL AND rm.user_id_string = rcu.user_id_string))";
        }
        
        $msg_sql .= " WHERE m.room_id = ? ORDER BY m.timestamp DESC LIMIT ? OFFSET 0";
        
        $msg_stmt = $conn->prepare($msg_sql);
        $msg_stmt->bind_param("ii", $room_id, $message_limit);
        $msg_stmt->execute();
        $msg_result = $msg_stmt->get_result();
        
        $messages = [];
        while ($row = $msg_result->fetch_assoc()) {
            $message_data = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'guest_name' => $row['guest_name'],
                'message' => $row['message'],
                'avatar' => $row['avatar'],
                'type' => $row['type'],
                'timestamp' => $row['timestamp'],
                'color' => $row['color'] ?? 'blue',
                'avatar_hue' => (int)($row['avatar_hue'] ?? 0),
                'avatar_saturation' => (int)($row['avatar_saturation'] ?? 100),
                'bubble_hue' => (int)($row['bubble_hue'] ?? 0),
                'bubble_saturation' => (int)($row['bubble_saturation'] ?? 100),
                'username' => $row['username'] ?? null,
                'is_admin' => $row['is_admin'] ?? false,
                'is_moderator' => $row['is_moderator'] ?? false,
                'ip_address' => $row['ip_address'] ?? null,
                'is_host' => $row['is_host'] ?? false,
                'guest_avatar' => $row['guest_avatar'] ?? null,
                'user_id_string' => $row['user_id_string'] ?? null
            ];
            
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
                $message_data['reply_original_username'] = $row['reply_original_registered_username'] ?? ($row['reply_original_chatroom_username'] ?? null);
            }
            
            if (in_array('mentions', $msg_columns)) {
                $message_data['mentions'] = $row['mentions'];
            }
            
            $messages[] = $message_data;
        }
        $msg_stmt->close();
        $all_data['messages'] = ['status' => 'success', 'messages' => $messages];
        
        // 2. USERS
        $user_fields = ['cu.user_id', 'cu.user_id_string', 'cu.guest_name', 'cu.avatar as guest_avatar', 'cu.is_host', 'cu.last_activity'];
        if (in_array('is_afk', $cu_columns)) $user_fields[] = 'cu.is_afk'; else $user_fields[] = '0 as is_afk';
        if (in_array('manual_afk', $cu_columns)) $user_fields[] = 'cu.manual_afk'; else $user_fields[] = '0 as manual_afk';
        if (in_array('afk_since', $cu_columns)) $user_fields[] = 'cu.afk_since'; else $user_fields[] = 'NULL as afk_since';
        if (in_array('username', $cu_columns)) $user_fields[] = 'cu.username'; else $user_fields[] = 'NULL as cu_username';
        if (in_array('avatar_hue', $cu_columns)) $user_fields[] = 'cu.avatar_hue'; else $user_fields[] = '0 as avatar_hue';
        if (in_array('avatar_saturation', $cu_columns)) $user_fields[] = 'cu.avatar_saturation'; else $user_fields[] = '100 as avatar_saturation';
        if (in_array('color', $cu_columns)) $user_fields[] = 'cu.color'; else $user_fields[] = "'blue' as color";
        if (in_array('username', $users_columns)) $user_fields[] = 'u.username as registered_username'; else $user_fields[] = 'NULL as registered_username';
        if (in_array('avatar', $users_columns)) $user_fields[] = 'u.avatar as registered_avatar'; else $user_fields[] = 'NULL as registered_avatar';
        if (in_array('is_admin', $users_columns)) $user_fields[] = 'u.is_admin'; else $user_fields[] = '0 as is_admin';
        if (in_array('is_moderator', $users_columns)) $user_fields[] = 'u.is_moderator'; else $user_fields[] = '0 as is_moderator';
        
        $users_sql = "SELECT " . implode(', ', $user_fields) . " FROM chatroom_users cu LEFT JOIN users u ON cu.user_id = u.id WHERE cu.room_id = ?";
        $users_stmt = $conn->prepare($users_sql);
        $users_stmt->bind_param("i", $room_id);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
        
        $users = [];
        while ($row = $users_result->fetch_assoc()) {
            $display_name = $row['registered_username'] ?: ($row['cu_username'] ?: ($row['guest_name'] ?: 'Unknown'));
            $avatar = $row['registered_avatar'] ?: ($row['guest_avatar'] ?: 'default_avatar.jpg');
            
            $users[] = [
                'user_id' => $row['user_id'],
                'user_id_string' => $row['user_id_string'],
                'guest_name' => $row['guest_name'],
                'username' => $row['registered_username'],
                'display_name' => $display_name,
                'avatar' => $avatar,
                'is_host' => (bool)$row['is_host'],
                'is_afk' => (bool)$row['is_afk'],
                'manual_afk' => (bool)$row['manual_afk'],
                'afk_since' => $row['afk_since'],
                'last_activity' => $row['last_activity'],
                'avatar_hue' => (int)$row['avatar_hue'],
                'avatar_saturation' => (int)$row['avatar_saturation'],
                'color' => $row['color'],
                'is_admin' => (bool)$row['is_admin'],
                'is_moderator' => (bool)$row['is_moderator']
            ];
        }
        $users_stmt->close();
        $all_data['users'] = $users;
        
        // 3. MENTIONS
        $mentions_stmt = $conn->prepare("
            SELECT um.id, um.message_id, um.mention_type, um.created_at, m.message, m.timestamp as message_timestamp,
                   m.user_id_string as sender_user_id_string, m.username as sender_username, m.guest_name as sender_guest_name,
                   m.avatar as sender_avatar, u.username as sender_registered_username, u.avatar as sender_registered_avatar
            FROM user_mentions um
            LEFT JOIN messages m ON um.message_id = m.id
            LEFT JOIN users u ON m.user_id = u.id
            WHERE um.room_id = ? AND um.mentioned_user_id_string = ? AND um.is_read = FALSE
            ORDER BY um.created_at DESC LIMIT 10
        ");
        
        if ($mentions_stmt) {
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
        }
        
        // 4. WHISPERS (ROOM WHISPERS)
        $whispers_stmt = $conn->prepare("
            SELECT DISTINCT CASE WHEN sender_user_id_string = ? THEN recipient_user_id_string ELSE sender_user_id_string END as other_user_id_string
            FROM room_whispers WHERE room_id = ? AND (sender_user_id_string = ? OR recipient_user_id_string = ?)
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
        
        if ($room_result->num_rows > 0) {
            $room_data = $room_result->fetch_assoc();
            $all_data['room_data'] = [
                'id' => $room_data['id'],
                'name' => $room_data['name'],
                'description' => $room_data['description'],
                'capacity' => $room_data['capacity'],
                'background' => $room_data['background'],
                'theme' => $room_data['theme'] ?? 'default',
                'has_password' => (bool)$room_data['has_password'],
                'youtube_enabled' => (bool)($room_data['youtube_enabled'] ?? false),
                'disappearing_messages' => (bool)($room_data['disappearing_messages'] ?? false),
                'message_lifetime_minutes' => (int)($room_data['message_lifetime_minutes'] ?? 0),
                'host_user_id_string' => $room_data['host_user_id_string'] ?? ''
            ];
        }
        $room_stmt->close();
        
        // 8. KNOCKS (only for hosts)
        $knocks_stmt = $conn->prepare("
            SELECT rk.*, c.name as room_name 
            FROM room_knocks rk 
            JOIN chatrooms c ON rk.room_id = c.id 
            JOIN chatroom_users cu ON c.id = cu.room_id 
            WHERE cu.user_id_string = ? 
            AND cu.is_host = 1 
            AND rk.status = 'pending'
            AND rk.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY rk.created_at DESC
        ");
        
        if ($knocks_stmt) {
            $knocks_stmt->bind_param("s", $user_id_string);
            $knocks_stmt->execute();
            $knocks_result = $knocks_stmt->get_result();
            
            $knocks = [];
            while ($row = $knocks_result->fetch_assoc()) {
                $knocks[] = [
                    'id' => (int)$row['id'],
                    'room_id' => (int)$row['room_id'],
                    'room_name' => $row['room_name'],
                    'user_id_string' => $row['user_id_string'],
                    'username' => $row['username'],
                    'guest_name' => $row['guest_name'],
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
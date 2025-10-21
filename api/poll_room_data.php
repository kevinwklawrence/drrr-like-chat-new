<?php
// api/poll_room_data.php - Event-driven AJAX polling (replaces SSE)
session_start();

set_time_limit(3);
ini_set('max_execution_time', 3);

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$user_type = $_SESSION['user']['type'] ?? 'guest';
$user_id = ($user_type === 'user' && isset($_SESSION['user']['id'])) ? (int)$_SESSION['user']['id'] : null;

session_write_close();

include '../db_connect.php';
require_once __DIR__ . '/../config/inactivity_config.php';

function hasUsersChanged($conn, $room_id) {
    $stmt = $conn->prepare("
        SELECT user_id_string, is_afk, manual_afk, is_host
        FROM chatroom_users 
        WHERE room_id = ?
        ORDER BY user_id_string
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    
    $currentHash = md5(json_encode($users));
    
    $stmt = $conn->prepare("
        SELECT state_hash 
        FROM room_state_cache 
        WHERE room_id = ? AND state_type = 'users'
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lastHash = $result->num_rows > 0 ? $result->fetch_assoc()['state_hash'] : null;
    $stmt->close();
    
    if ($currentHash !== $lastHash) {
        $stmt = $conn->prepare("
            INSERT INTO room_state_cache (room_id, state_type, state_hash, updated_at)
            VALUES (?, 'users', ?, NOW())
            ON DUPLICATE KEY UPDATE state_hash = VALUES(state_hash), updated_at = NOW()
        ");
        $stmt->bind_param("is", $room_id, $currentHash);
        $stmt->execute();
        $stmt->close();
        
        return true;
    }
    
    return false;
}

$message_limit = isset($_GET['message_limit']) ? min(max((int)$_GET['message_limit'], 1), 100) : 50;
$check_youtube = isset($_GET['check_youtube']) ? (bool)$_GET['check_youtube'] : false;
$last_event_id = isset($_GET['last_event_id']) ? (int)$_GET['last_event_id'] : 0;

try {
    // Check for new events
    $event_check = $conn->prepare("
        SELECT id FROM message_events 
        WHERE id > ? AND (room_id = ? OR room_id = 0)
        LIMIT 1
    ");
    $event_check->bind_param("ii", $last_event_id, $room_id);
    $event_check->execute();
    $event_check->store_result();
    
    if ($event_check->num_rows === 0) {
        $event_check->close();
        echo json_encode(['status' => 'no_events', 'last_event_id' => $last_event_id]);
        $conn->close();
        exit;
    }
    $event_check->close();
    
    // Fetch all new events
    $event_stmt = $conn->prepare("
        SELECT id, event_type, event_data, created_at
        FROM message_events 
        WHERE id > ? AND (room_id = ? OR room_id = 0)
        ORDER BY id ASC
        LIMIT 50
    ");
    $event_stmt->bind_param("ii", $last_event_id, $room_id);
    $event_stmt->execute();
    $event_result = $event_stmt->get_result();
    
    $events = [];
    $new_last_event_id = $last_event_id;
    
    while ($event = $event_result->fetch_assoc()) {
        $events[] = $event;
        $new_last_event_id = max($new_last_event_id, (int)$event['id']);
    }
    $event_stmt->close();
    
    $all_data = [
        'status' => 'success',
        'type' => 'room_data', 
        'timestamp' => time(),
        'last_event_id' => $new_last_event_id
    ];
    
    $event_types = array_unique(array_column($events, 'event_type'));

    // Sound events
    $sound_events = [];
    $start_time = time() - 5;
    $new_events_for_sounds = array_filter($events, function($event) use ($start_time) {
        return isset($event['created_at']) && strtotime($event['created_at']) >= $start_time;
    });

    if (!empty($new_events_for_sounds)) {
        $new_event_types = array_unique(array_column($new_events_for_sounds, 'event_type'));
        
        if (in_array('message', $new_event_types)) {
            $has_system = false;
            foreach ($new_events_for_sounds as $event) {
                if ($event['event_type'] === 'message') {
                    $event_data = json_decode($event['event_data'], true);
                    if (isset($event_data['type']) && in_array($event_data['type'], ['system', 'announcement'])) {
                        $has_system = true;
                        break;
                    }
                }
            }
            $sound_events[$has_system ? 'system_message' : 'new_message'] = true;
        }
        
        if (in_array('mention', $new_event_types)) $sound_events['new_mention'] = true;
        if (in_array('whisper', $new_event_types)) $sound_events['new_whisper'] = true;
        if (in_array('private_message', $new_event_types)) $sound_events['new_private_message'] = true;
    }

    if (!empty($sound_events)) $all_data['sound_events'] = $sound_events;
    
    // MESSAGES - Only fetch if there's a message event
    if (in_array('message', $event_types)) {
        $messages_stmt = $conn->prepare("
            SELECT m.*, u.username, u.is_admin, u.is_moderator,
                   cu.ip_address, cu.is_host, cu.guest_avatar,
                   rm.id as reply_original_id, rm.message as reply_original_message,
                   rm.user_id_string as reply_original_user_id_string,
                   rm.guest_name as reply_original_guest_name,
                   rm.avatar as reply_original_avatar, rm.avatar_hue as reply_original_avatar_hue,
                   rm.avatar_saturation as reply_original_avatar_saturation,
                   rm.bubble_hue as reply_original_bubble_hue,
                   rm.bubble_saturation as reply_original_bubble_saturation,
                   rm.color as reply_original_color,
                   ru.username as reply_original_registered_username,
                   ru.avatar as reply_original_registered_avatar,
                   rcu.username as reply_original_chatroom_username
            FROM messages m
            LEFT JOIN users u ON m.user_id = u.id
            LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id AND m.user_id_string = cu.user_id_string
            LEFT JOIN messages rm ON m.reply_to_message_id = rm.id
            LEFT JOIN users ru ON rm.user_id = ru.id
            LEFT JOIN chatroom_users rcu ON rm.room_id = rcu.room_id 
                AND ((rm.user_id IS NOT NULL AND rm.user_id = rcu.user_id) OR 
                     (rm.user_id IS NULL AND rm.guest_name = rcu.guest_name) OR
                     (rm.user_id IS NULL AND rm.user_id_string = rcu.user_id_string))
            WHERE m.room_id = ?
            AND m.timestamp >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
            GROUP BY m.id
            ORDER BY m.id DESC 
            LIMIT ?
        ");
        $messages_stmt->bind_param("ii", $room_id, $message_limit);
        $messages_stmt->execute();
        $messages_result = $messages_stmt->get_result();
        
        $messages = [];
        $message_user_ids = [];
        while ($msg = $messages_result->fetch_assoc()) {
            if (empty($msg['id']) || !isset($msg['message'])) continue;
            
            $msg = array_change_key_case($msg, CASE_LOWER);
            $msg['ip_address'] = $msg['ip_address'] ?? null;
            $messages[] = $msg;
            
            if ($msg['user_id']) {
                $message_user_ids[] = (int)$msg['user_id'];
            }
        }
        $messages_stmt->close();
        
        // Batch fetch titles for all message users
        $titles_map = [];
        if (!empty($message_user_ids)) {
            $user_ids_str = implode(',', array_map('intval', array_unique($message_user_ids)));
            $titles_stmt = $conn->query("
                SELECT ui.user_id, si.name, si.rarity, si.icon
                FROM user_inventory ui
                INNER JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id IN ($user_ids_str) 
                AND ui.is_equipped = 1 
                AND si.type = 'title'
                ORDER BY ui.user_id, FIELD(si.rarity, 'legendary', 'strange', 'rare', 'common')
                LIMIT 250
            ");
            
            while ($title = $titles_stmt->fetch_assoc()) {
                $uid = $title['user_id'];
                if (!isset($titles_map[$uid])) $titles_map[$uid] = [];
                if (count($titles_map[$uid]) < 5) {
                    $titles_map[$uid][] = [
                        'name' => $title['name'],
                        'rarity' => $title['rarity'],
                        'icon' => $title['icon']
                    ];
                }
            }
        }
        
        // Attach titles to messages
        foreach ($messages as &$msg) {
            $msg['equipped_titles'] = $msg['user_id'] && isset($titles_map[$msg['user_id']]) 
                ? $titles_map[$msg['user_id']] 
                : [];
        }
        unset($msg);
        
        $all_data['messages'] = ['status' => 'success', 'messages' => array_reverse($messages)];
    }
    
    // USERS (only send if actually changed OR on specific events)
    $should_send_users = false;
    
    if (in_array('message', $event_types) || 
        in_array('user_update', $event_types) ||
        in_array('user_join', $event_types) ||
        in_array('user_leave', $event_types)) {
        if (hasUsersChanged($conn, $room_id)) {
            $should_send_users = true;
        }
    }

    if ($should_send_users) {
        $users_stmt = $conn->prepare("
            SELECT cu.user_id, cu.user_id_string, cu.guest_name, cu.avatar as guest_avatar, 
                   cu.is_host, cu.last_activity, cu.is_afk, cu.manual_afk, cu.afk_since, 
                   cu.username, cu.avatar_hue, cu.avatar_saturation, cu.color,
                   u.username as registered_username, u.avatar as registered_avatar, 
                   u.is_admin, u.is_moderator
            FROM chatroom_users cu 
            LEFT JOIN users u ON cu.user_id = u.id 
            WHERE cu.room_id = ?
        ");
        $users_stmt->bind_param("i", $room_id);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();

        $users = [];
        $user_ids = [];
        while ($user = $users_result->fetch_assoc()) {
            $users[] = $user;
            if ($user['user_id']) {
                $user_ids[] = (int)$user['user_id'];
            }
        }
        $users_stmt->close();
        
        // Batch fetch titles for all users
        $titles_map = [];
        if (!empty($user_ids)) {
            $user_ids_str = implode(',', array_map('intval', array_unique($user_ids)));
            $titles_stmt = $conn->query("
                SELECT ui.user_id, si.name, si.rarity, si.icon
                FROM user_inventory ui
                INNER JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id IN ($user_ids_str) 
                AND ui.is_equipped = 1 
                AND si.type = 'title'
                ORDER BY ui.user_id, FIELD(si.rarity, 'legendary', 'strange', 'rare', 'common')
                LIMIT 250
            ");
            
            while ($title = $titles_stmt->fetch_assoc()) {
                $uid = $title['user_id'];
                if (!isset($titles_map[$uid])) $titles_map[$uid] = [];
                if (count($titles_map[$uid]) < 5) {
                    $titles_map[$uid][] = [
                        'name' => $title['name'],
                        'rarity' => $title['rarity'],
                        'icon' => $title['icon']
                    ];
                }
            }
        }
        
        // Attach titles to users
        foreach ($users as &$user) {
            $user['equipped_titles'] = $user['user_id'] && isset($titles_map[$user['user_id']]) 
                ? $titles_map[$user['user_id']] 
                : [];
        }
        unset($user);
        
        $all_data['users'] = $users;
    }
    
    // MENTIONS
    if (in_array('mention', $event_types)) {
        $mentions_stmt = $conn->prepare("
            SELECT um.*, m.message, UNIX_TIMESTAMP(m.timestamp) * 1000 as timestamp, 
                   m.user_id_string as sender_user_id_string,
                   u.username as sender_username, cu.guest_name as sender_guest_name
            FROM user_mentions um
            JOIN messages m ON um.message_id = m.id
            LEFT JOIN users u ON m.user_id = u.id
            LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id AND m.user_id_string = cu.user_id_string
            WHERE um.room_id = ? AND um.mentioned_user_id_string = ? AND um.is_read = 0
            ORDER BY um.created_at DESC
            LIMIT 10
        ");
        $mentions_stmt->bind_param("is", $room_id, $user_id_string);
        $mentions_stmt->execute();
        $mentions_result = $mentions_stmt->get_result();
        
        $mentions = [];
        while ($mention = $mentions_result->fetch_assoc()) {
            $mentions[] = $mention;
        }
        $mentions_stmt->close();
        $all_data['mentions'] = ['status' => 'success', 'mentions' => $mentions];
    }
    
    // WHISPERS - Optimized to eliminate N+1 query problem
    if (in_array('whisper', $event_types)) {
        $whisper_stmt = $conn->prepare("
            SELECT
                CASE WHEN rw.sender_user_id_string = ? THEN rw.recipient_user_id_string ELSE rw.sender_user_id_string END as other_user_id_string,
                cu.username,
                cu.guest_name,
                SUM(CASE WHEN rw.sender_user_id_string = CASE WHEN rw.sender_user_id_string = ? THEN rw.recipient_user_id_string ELSE rw.sender_user_id_string END
                    AND rw.recipient_user_id_string = ?
                    AND rw.is_read = 0 THEN 1 ELSE 0 END) as unread_count
            FROM room_whispers rw
            LEFT JOIN chatroom_users cu ON cu.room_id = rw.room_id
                AND cu.user_id_string = CASE WHEN rw.sender_user_id_string = ? THEN rw.recipient_user_id_string ELSE rw.sender_user_id_string END
            WHERE rw.room_id = ? AND (rw.sender_user_id_string = ? OR rw.recipient_user_id_string = ?)
            GROUP BY other_user_id_string, cu.username, cu.guest_name
        ");
        $whisper_stmt->bind_param("sssssss", $user_id_string, $user_id_string, $user_id_string, $user_id_string, $room_id, $user_id_string, $user_id_string);
        $whisper_stmt->execute();
        $whisper_result = $whisper_stmt->get_result();

        $whispers = [];
        while ($row = $whisper_result->fetch_assoc()) {
            $whispers[] = [
                'other_user_id_string' => $row['other_user_id_string'],
                'username' => $row['username'],
                'guest_name' => $row['guest_name'],
                'unread_count' => (int)$row['unread_count']
            ];
        }
        $whisper_stmt->close();
        $all_data['whispers'] = ['status' => 'success', 'conversations' => $whispers];
    }
    
    // PRIVATE MESSAGES - Optimized to eliminate N+1 query problem
    if (in_array('private_message', $event_types) && $user_id) {
        $pm_stmt = $conn->prepare("
            SELECT
                CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END as other_user_id,
                u.username,
                u.avatar,
                u.avatar_hue,
                u.avatar_saturation,
                (SELECT pm2.message FROM private_messages pm2
                 WHERE (pm2.sender_id = ? AND pm2.recipient_id = CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END)
                    OR (pm2.recipient_id = ? AND pm2.sender_id = CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END)
                 ORDER BY pm2.created_at DESC LIMIT 1) as last_message,
                SUM(CASE WHEN pm.sender_id = CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END
                    AND pm.recipient_id = ?
                    AND pm.is_read = 0 THEN 1 ELSE 0 END) as unread_count
            FROM private_messages pm
            LEFT JOIN users u ON u.id = CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END
            WHERE pm.sender_id = ? OR pm.recipient_id = ?
            GROUP BY other_user_id, u.username, u.avatar, u.avatar_hue, u.avatar_saturation
        ");
        $pm_stmt->bind_param("iiiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
        $pm_stmt->execute();
        $pm_result = $pm_stmt->get_result();

        $pms = [];
        while ($row = $pm_result->fetch_assoc()) {
            $pms[] = [
                'other_user_id' => (int)$row['other_user_id'],
                'username' => $row['username'],
                'avatar' => $row['avatar'],
                'avatar_hue' => $row['avatar_hue'],
                'avatar_saturation' => $row['avatar_saturation'],
                'last_message' => $row['last_message'] ?? '',
                'unread_count' => (int)$row['unread_count']
            ];
        }
        $pm_stmt->close();
        $all_data['private_messages'] = ['status' => 'success', 'conversations' => $pms];
    }
    
    // FRIENDS
    if (in_array('friend_update', $event_types) && $user_id) {
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
    
    // ROOM DATA
    if (in_array('room_update', $event_types) || in_array('settings_update', $event_types)) {
        $room_stmt = $conn->prepare("SELECT * FROM chatrooms WHERE id = ?");
        $room_stmt->bind_param("i", $room_id);
        $room_stmt->execute();
        $room_result = $room_stmt->get_result();
        $room_data = $room_result->num_rows > 0 ? $room_result->fetch_assoc() : null;
        $room_stmt->close();
        if ($room_data) {
            $all_data['room_data'] = ['status' => 'success', 'room' => $room_data];
        }
    }
    
    // KNOCKS
    if (in_array('knock', $event_types)) {
        $is_host_stmt = $conn->prepare("
            SELECT is_host FROM chatroom_users 
            WHERE room_id = ? AND user_id_string = ?
        ");
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
                $knocks[] = $row;
            }
            $knocks_stmt->close();
            $all_data['knocks'] = $knocks;
        }
    }
    
    // YOUTUBE
    if ($check_youtube && in_array('youtube_update', $event_types)) {
        $yt_check_stmt = $conn->prepare("SELECT youtube_enabled FROM chatrooms WHERE id = ?");
        $yt_check_stmt->bind_param("i", $room_id);
        $yt_check_stmt->execute();
        $yt_check_result = $yt_check_stmt->get_result();
        $yt_enabled = false;
        if ($yt_check_result->num_rows > 0) {
            $yt_enabled = (bool)$yt_check_result->fetch_assoc()['youtube_enabled'];
        }
        $yt_check_stmt->close();
        
        if ($yt_enabled) {
            $yt_stmt = $conn->prepare("
                SELECT current_video_id, current_time, is_playing, last_sync_time, sync_token 
                FROM room_player_sync WHERE room_id = ?
            ");
            $yt_stmt->bind_param("i", $room_id);
            $yt_stmt->execute();
            $yt_result = $yt_stmt->get_result();
            
            if ($yt_result->num_rows > 0) {
                $sync_data = $yt_result->fetch_assoc();
                
                $adjusted_time = $sync_data['current_time'];
                if ($sync_data['is_playing']) {
                    $time_diff = time() - strtotime($sync_data['last_sync_time']);
                    $adjusted_time += $time_diff;
                }
                
                $queue_stmt = $conn->prepare("SELECT * FROM youtube_queue WHERE room_id = ? ORDER BY position ASC");
                $queue_stmt->bind_param("i", $room_id);
                $queue_stmt->execute();
                $queue_result = $queue_stmt->get_result();
                $queue = [];
                while ($q = $queue_result->fetch_assoc()) {
                    $queue[] = $q;
                }
                $queue_stmt->close();
                
                $sugg_stmt = $conn->prepare("SELECT * FROM youtube_suggestions WHERE room_id = ? ORDER BY created_at DESC LIMIT 10");
                $sugg_stmt->bind_param("i", $room_id);
                $sugg_stmt->execute();
                $sugg_result = $sugg_stmt->get_result();
                $suggestions = [];
                while ($s = $sugg_result->fetch_assoc()) {
                    $suggestions[] = $s;
                }
                $sugg_stmt->close();
                
                $all_data['youtube'] = [
                    'status' => 'success',
                    'sync_data' => [
                        'enabled' => true,
                        'video_id' => $sync_data['current_video_id'],
                        'current_time' => round($adjusted_time, 2),
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
    }
    
    // GHOST HUNT
    if (in_array('ghost_spawn', $event_types)) {
        $ghost_stmt = $conn->prepare("
            SELECT id, ghost_phrase, reward_amount, spawned_at 
            FROM ghost_hunt_events 
            WHERE room_id = ? AND is_active = 1
            ORDER BY spawned_at DESC LIMIT 1
        ");
        $ghost_stmt->bind_param("i", $room_id);
        $ghost_stmt->execute();
        $ghost_result = $ghost_stmt->get_result();
        
        if ($ghost_result->num_rows > 0) {
            $ghost_data = $ghost_result->fetch_assoc();
            $all_data['ghost_hunt'] = [
                'status' => 'success',
                'active' => true,
                'event' => $ghost_data
            ];
        } else {
            $all_data['ghost_hunt'] = ['status' => 'success', 'active' => false];
        }
        $ghost_stmt->close();
    }
    
    // SETTINGS CHECK
    if (in_array('settings_update', $event_types)) {
        $settings_stmt = $conn->prepare("
            SELECT id, UNIX_TIMESTAMP(created_at) as timestamp
            FROM room_events 
            WHERE room_id = ? AND event_type = 'settings_update' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
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
        }
        $settings_stmt->close();
    }
    
    // INACTIVITY STATUS (always send - NOT conditional)
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
    }
    $inactivity_stmt->close();
    
    echo json_encode($all_data);
    
} catch (Exception $e) {
    error_log("Poll error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error'
    ]);
}

$conn->close();
?>
<?php
// api/sse_room_data.php - Event-driven SSE with shared hosting optimizations
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Not authorized']) . "\n\n";
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$user_type = $_SESSION['user']['type'] ?? 'guest';
$user_id = ($user_type === 'user' && isset($_SESSION['user']['id'])) ? (int)$_SESSION['user']['id'] : null;

session_write_close(); // Already in your code - keeping it

while (ob_get_level()) ob_end_clean(); // Already in your code - keeping it
ob_implicit_flush(1);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

include '../db_connect.php';
require_once __DIR__ . '/../config/inactivity_config.php';

$message_limit = isset($_GET['message_limit']) ? min(max((int)$_GET['message_limit'], 1), 100) : 50;
$check_youtube = isset($_GET['check_youtube']) ? (bool)$_GET['check_youtube'] : false;
$last_event_id = isset($_GET['last_event_id']) ? (int)$_GET['last_event_id'] : 0;

echo "data: " . json_encode([
    'type' => 'connected', 
    'room_id' => $room_id,
    'last_event_id' => $last_event_id
]) . "\n\n";
flush();

// ===================== ONLY 3 CRITICAL CHANGES HERE =====================
$max_duration = 30; // CHANGED FROM 55
$start_time = time();
$last_event_id = 0;
$iteration = 0;
$max_iterations = 60; // CHANGED FROM 110
$consecutive_empty = 0; // ADDED
// ========================================================================

while ((time() - $start_time) < $max_duration && connection_status() == CONNECTION_NORMAL) {
    $has_events = false;
    
    try {
        $event_stmt = $conn->prepare("
            SELECT id, event_type, event_data, created_at
            FROM message_events 
            WHERE id > ? AND (room_id = ? OR user_id_string = ?)
            ORDER BY id ASC
            LIMIT 50
        ");
        
        if (!$event_stmt) {
            error_log("SSE prepare failed: " . $conn->error);
            break;
        }
        
        $event_stmt->bind_param("iis", $last_event_id, $room_id, $user_id_string);
        $event_stmt->execute();
        $event_result = $event_stmt->get_result();
        
        if ($event_result->num_rows > 0) {
            $has_events = true;
            $consecutive_empty = 0; // ADDED
            $all_data = ['type' => 'room_data', 'timestamp' => time()];
            
            $event_types = [];
            $new_events = [];
            while ($event = $event_result->fetch_assoc()) {
                $last_event_id = max($last_event_id, $event['id']);
                $event_types[] = $event['event_type'];
                $new_events[] = $event;
            }
            $event_types = array_unique($event_types);
            
            // Sound events tracking
            $sound_events = [];
            $new_events_for_sounds = [];
            foreach ($new_events as $event) {
                if ($event['created_at'] > date('Y-m-d H:i:s', strtotime('-5 seconds'))) {
                    $new_events_for_sounds[] = $event;
                }
            }
            
            if (!empty($new_events_for_sounds)) {
                $new_event_types = array_unique(array_column($new_events_for_sounds, 'event_type'));
                
                $has_system_message = false;
                if (in_array('message', $new_event_types)) {
                    foreach ($new_events_for_sounds as $event) {
                        if ($event['event_type'] === 'message') {
                            $event_data = json_decode($event['event_data'], true);
                            if (isset($event_data['type']) && 
                                ($event_data['type'] === 'system' || $event_data['type'] === 'announcement')) {
                                $has_system_message = true;
                                break;
                            }
                        }
                    }
                    
                    if ($has_system_message) {
                        $sound_events['system_message'] = true;
                    } else {
                        $sound_events['new_message'] = true;
                    }
                }
                
                if (in_array('mention', $new_event_types)) $sound_events['new_mention'] = true;
                if (in_array('whisper', $new_event_types)) $sound_events['new_whisper'] = true;
                if (in_array('private_message', $new_event_types)) $sound_events['new_private_message'] = true;
            }
            
            if (!empty($sound_events)) {
                $all_data['sound_events'] = $sound_events;
            }
            
            // 1. MESSAGES
            if (in_array('message', $event_types)) {
                $messages_stmt = $conn->prepare("
                    SELECT m.*, u.username, u.is_admin, u.is_moderator,
                           cu.ip_address, cu.is_host, cu.guest_avatar
                    FROM messages m 
                    LEFT JOIN users u ON m.user_id = u.id 
                    LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id 
                        AND ((m.user_id IS NOT NULL AND m.user_id = cu.user_id) OR 
                             (m.user_id IS NULL AND m.guest_name = cu.guest_name) OR
                             (m.user_id IS NULL AND m.user_id_string = cu.user_id_string))
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
            }
            
            // 2. USERS
            if (in_array('user_join', $event_types) || in_array('user_leave', $event_types) || in_array('user_update', $event_types)) {
                $users_stmt = $conn->prepare("
                    SELECT cu.*, u.username, u.display_name, u.avatar_hue, u.avatar_saturation,
                           u.custom_av, u.title, u.is_admin, u.is_moderator
                    FROM chatroom_users cu
                    LEFT JOIN users u ON cu.user_id = u.id
                    WHERE cu.room_id = ?
                    ORDER BY cu.is_host DESC, cu.last_activity DESC
                ");
                $users_stmt->bind_param("i", $room_id);
                $users_stmt->execute();
                $users_result = $users_stmt->get_result();
                $users = [];
                while ($user = $users_result->fetch_assoc()) { $users[] = $user; }
                $users_stmt->close();
                $all_data['users'] = $users;
            }
            
            // 3. MENTIONS
            if (in_array('mention', $event_types)) {
                $mentions_stmt = $conn->prepare("
                    SELECT um.id, um.message_id, um.mention_type, um.created_at,
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
            }
            
            // 4. WHISPERS
            if (in_array('whisper', $event_types)) {
                $whispers_stmt = $conn->prepare("
                    SELECT DISTINCT
                        CASE WHEN rw.sender_user_id_string = ? THEN rw.recipient_user_id_string 
                             ELSE rw.sender_user_id_string END as other_user_id_string
                    FROM room_whispers rw
                    WHERE rw.room_id = ? AND (rw.sender_user_id_string = ? OR rw.recipient_user_id_string = ?)
                ");
                $whispers_stmt->bind_param("siss", $user_id_string, $room_id, $user_id_string, $user_id_string);
                $whispers_stmt->execute();
                $whispers_result = $whispers_stmt->get_result();
                $whisper_conversations = [];
                while ($row = $whispers_result->fetch_assoc()) {
                    $whisper_conversations[] = ['other_user_id_string' => $row['other_user_id_string']];
                }
                $whispers_stmt->close();
                $all_data['whispers'] = ['status' => 'success', 'conversations' => $whisper_conversations];
            }
            
            // 5. PRIVATE MESSAGES (registered users only)
            if (in_array('private_message', $event_types) && $user_id) {
                $pm_stmt = $conn->prepare("
                    SELECT DISTINCT 
                        CASE WHEN pm.sender_id = ? THEN pm.recipient_id 
                             ELSE pm.sender_id END as other_user_id
                    FROM private_messages pm
                    WHERE pm.sender_id = ? OR pm.recipient_id = ?
                ");
                $pm_stmt->bind_param("iii", $user_id, $user_id, $user_id);
                $pm_stmt->execute();
                $pm_result = $pm_stmt->get_result();
                $pms = [];
                while ($row = $pm_result->fetch_assoc()) {
                    $pms[] = ['other_user_id' => $row['other_user_id']];
                }
                $pm_stmt->close();
                $all_data['private_messages'] = ['status' => 'success', 'conversations' => $pms];
            }
            
            // 6. FRIENDS (registered users only)
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
                while ($row = $friends_result->fetch_assoc()) { $friends[] = $row; }
                $friends_stmt->close();
                $all_data['friends'] = ['status' => 'success', 'friends' => $friends];
            }
            
            // 7. ROOM DATA
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
            
            // 8. KNOCKS (hosts only)
            if (in_array('knock', $event_types)) {
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
                    while ($row = $knocks_result->fetch_assoc()) { $knocks[] = $row; }
                    $knocks_stmt->close();
                    $all_data['knocks'] = $knocks;
                }
            }
            
            // 9. YOUTUBE (if enabled and requested)
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
            
            // 10. GHOST HUNT EVENTS
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
                    $all_data['ghost_hunt'] = ['status' => 'success', 'active' => true, 'event' => $ghost_data];
                } else {
                    $all_data['ghost_hunt'] = ['status' => 'success', 'active' => false];
                }
                $ghost_stmt->close();
            }
            
            // 11. SETTINGS CHECK
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
            
            // 12. INACTIVITY STATUS (always send)
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
            
            echo "data: " . json_encode($all_data) . "\n\n";
            flush();
        } else {
            $consecutive_empty++; // ADDED
        }
        
        $event_stmt->close();
        
    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
    }
    
    if (!$has_events && $iteration % 10 == 0) {
        echo "data: " . json_encode([
            'type' => 'heartbeat', 
            'timestamp' => time(),
            'last_event_id' => $last_event_id
        ]) . "\n\n";
        flush();
    }
    
    $iteration++;
    
    // ===================== CRITICAL CHANGES HERE =====================
    if ($has_events) {
        usleep(50000); // 0.05s - CHANGED
    } else if ($consecutive_empty < 5) {
        usleep(150000); // 0.15s - CHANGED
    } else {
        usleep(300000); // 0.3s - CHANGED
    }
    
    if ($consecutive_empty >= 20) { // ADDED: Early exit
        break;
    }
    // =================================================================
    
    if ($iteration >= $max_iterations || (time() - $start_time) >= ($max_duration - 2)) {
        echo "data: " . json_encode(['type' => 'reconnect']) . "\n\n";
        flush();
        break;
    }
}

echo "data: " . json_encode([
    'type' => 'reconnect',
    'last_event_id' => $last_event_id
]) . "\n\n";
flush();

$conn->close();
?>
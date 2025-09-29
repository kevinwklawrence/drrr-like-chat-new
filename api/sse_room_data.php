<?php
// api/sse_room_data.php - Event-driven SSE
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Not authorized']) . "\n\n";
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$user_type = $_SESSION['user']['type'] ?? 'guest';
$user_id = ($user_type === 'user' && isset($_SESSION['user']['id'])) ? (int)$_SESSION['user']['id'] : null;

session_write_close();

while (ob_get_level()) ob_end_clean();
ob_implicit_flush(1);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

include '../db_connect.php';
require_once __DIR__ . '/../config/inactivity_config.php';

$message_limit = isset($_GET['message_limit']) ? min(max((int)$_GET['message_limit'], 1), 100) : 50;
$check_youtube = isset($_GET['check_youtube']) ? (bool)$_GET['check_youtube'] : false;

echo "data: " . json_encode(['type' => 'connected', 'room_id' => $room_id]) . "\n\n";
flush();

$max_duration = 300;
$start_time = time();
$last_event_id = 0;
$iteration = 0;
$max_iterations = 60;

while ((time() - $start_time) < $max_duration && connection_status() == CONNECTION_NORMAL) {
    $has_events = false;
    
    try {
        // Check for new events since last check
        $event_stmt = $conn->prepare("
            SELECT id, event_type, event_data 
            FROM message_events 
            WHERE id > ? AND (room_id = ? OR room_id = 0)
            ORDER BY id ASC
        ");
        $event_stmt->bind_param("ii", $last_event_id, $room_id);
        $event_stmt->execute();
        $event_result = $event_stmt->get_result();
        
        $events = [];
        while ($event = $event_result->fetch_assoc()) {
            $events[] = $event;
            $last_event_id = max($last_event_id, (int)$event['id']);
        }
        $event_stmt->close();
        
        // If we have events, fetch and send the updated data
        if (!empty($events)) {
            $has_events = true;
            $all_data = ['type' => 'room_data', 'timestamp' => time()];
            
            // Determine what data needs to be fetched based on event types
            $event_types = array_unique(array_column($events, 'event_type'));
            
            // MESSAGES
            if (in_array('message', $event_types)) {
                $messages_stmt = $conn->prepare("
    SELECT m.id, m.user_id, m.user_id_string, m.guest_name, m.message, 
           m.avatar, m.type, m.color, m.avatar_hue, m.avatar_saturation,
           m.bubble_hue, m.bubble_saturation, m.reply_to_message_id, m.mentions,
           m.is_system, m.room_id,
           UNIX_TIMESTAMP(m.timestamp) * 1000 as timestamp,
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
            }
            
            // USERS (send with every event)
            $users_stmt = $conn->prepare("SELECT * FROM chatroom_users WHERE room_id = ?");
            $users_stmt->bind_param("i", $room_id);
            $users_stmt->execute();
            $users_result = $users_stmt->get_result();
            $users = [];
            while ($u = $users_result->fetch_assoc()) { $users[] = $u; }
            $users_stmt->close();
            $all_data['users'] = $users;
            
            // MENTIONS
            if (in_array('mention', $event_types)) {
                $mentions_stmt = $conn->prepare("
    SELECT um.*, m.message, UNIX_TIMESTAMP(m.timestamp) * 1000 as timestamp, m.user_id_string as sender_user_id_string,
           u.username as sender_username, cu.guest_name as sender_guest_name
    FROM user_mentions um
    JOIN messages m ON um.message_id = m.id
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id AND m.user_id_string = cu.user_id_string
    WHERE um.room_id = ? AND um.mentioned_user_id_string = ? AND um.is_read = 0
    ORDER BY um.created_at DESC
");
                $mentions_stmt->bind_param("is", $room_id, $user_id_string);
                $mentions_stmt->execute();
                $mentions_result = $mentions_stmt->get_result();
                $mentions = [];
                while ($mention = $mentions_result->fetch_assoc()) { $mentions[] = $mention; }
                $mentions_stmt->close();
                $all_data['mentions'] = ['status' => 'success', 'mentions' => $mentions];
            }
            
            // WHISPERS
            if (in_array('whisper', $event_types)) {
                $whisper_stmt = $conn->prepare("
                    SELECT DISTINCT 
                        CASE WHEN sender_user_id_string = ? THEN recipient_user_id_string ELSE sender_user_id_string END as other_user_id_string
                    FROM room_whispers 
                    WHERE room_id = ? AND (sender_user_id_string = ? OR recipient_user_id_string = ?)
                ");
                $whisper_stmt->bind_param("siss", $user_id_string, $room_id, $user_id_string, $user_id_string);
                $whisper_stmt->execute();
                $whisper_result = $whisper_stmt->get_result();
                
                $whispers = [];
                while ($row = $whisper_result->fetch_assoc()) {
                    $other_user_id = $row['other_user_id_string'];
                    
                    $user_stmt = $conn->prepare("SELECT username, guest_name FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
                    $user_stmt->bind_param("is", $room_id, $other_user_id);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();
                    
                    if ($user_result->num_rows > 0) {
                        $user_data = $user_result->fetch_assoc();
                        
                        $count_stmt = $conn->prepare("
                            SELECT COUNT(*) as count FROM room_whispers 
                            WHERE room_id = ? AND sender_user_id_string = ? AND recipient_user_id_string = ? AND is_read = 0
                        ");
                        $count_stmt->bind_param("iss", $room_id, $other_user_id, $user_id_string);
                        $count_stmt->execute();
                        $count_result = $count_stmt->get_result();
                        $unread_count = $count_result->fetch_assoc()['count'];
                        $count_stmt->close();
                        
                        $whispers[] = [
                            'other_user_id_string' => $other_user_id,
                            'username' => $user_data['username'],
                            'guest_name' => $user_data['guest_name'],
                            'unread_count' => (int)$unread_count
                        ];
                    }
                    $user_stmt->close();
                }
                $whisper_stmt->close();
                $all_data['whispers'] = ['status' => 'success', 'conversations' => $whispers];
            }
            
            // PRIVATE MESSAGES
if (in_array('private_message', $event_types) && $user_id) {
    $pm_stmt = $conn->prepare("
        SELECT DISTINCT 
            CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END as other_user_id
        FROM private_messages 
        WHERE sender_id = ? OR recipient_id = ?
    ");
    $pm_stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $pm_stmt->execute();
    $pm_result = $pm_stmt->get_result();
    
    $pms = [];
    while ($row = $pm_result->fetch_assoc()) {
        $other_user_id = $row['other_user_id'];
        
        $user_stmt = $conn->prepare("SELECT username, avatar, avatar_hue, avatar_saturation FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $other_user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            
            // Get last message
            $last_msg_stmt = $conn->prepare("
                SELECT message FROM private_messages 
                WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
                ORDER BY created_at DESC LIMIT 1
            ");
            $last_msg_stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
            $last_msg_stmt->execute();
            $last_msg_result = $last_msg_stmt->get_result();
            $last_message = $last_msg_result->num_rows > 0 ? $last_msg_result->fetch_assoc()['message'] : null;
            $last_msg_stmt->close();
            
            // Get unread count
            $count_stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM private_messages 
                WHERE sender_id = ? AND recipient_id = ? AND is_read = 0
            ");
            $count_stmt->bind_param("ii", $other_user_id, $user_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $unread_count = $count_result->fetch_assoc()['count'];
            $count_stmt->close();
            
            $pms[] = [
                'other_user_id' => $other_user_id,
                'username' => $user_data['username'],
                'avatar' => $user_data['avatar'],
                'avatar_hue' => (int)($user_data['avatar_hue'] ?? 0),
                'avatar_saturation' => (int)($user_data['avatar_saturation'] ?? 100),
                'last_message' => $last_message,
                'unread_count' => (int)$unread_count
            ];
        }
        $user_stmt->close();
    }
    $pm_stmt->close();
    $all_data['private_messages'] = ['status' => 'success', 'conversations' => $pms];
}
            
            // FRIENDS
            if (in_array('friend', $event_types) && $user_id) {
                $friends_stmt = $conn->prepare("
                    SELECT DISTINCT CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END as friend_user_id,
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
            
            // ROOM DATA
            if (in_array('room_update', $event_types)) {
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
            
            // YOUTUBE (only if enabled and requested)
            if ($check_youtube && in_array('youtube_update', $event_types)) {
                // First check if YouTube is enabled for this room
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
                        
                        // Calculate adjusted time if playing
                        $adjusted_time = $sync_data['current_time'];
                        if ($sync_data['is_playing']) {
                            $time_diff = time() - strtotime($sync_data['last_sync_time']);
                            $adjusted_time += $time_diff;
                        }
                        
                        // Get queue
                        $queue_stmt = $conn->prepare("
                            SELECT *, 
                                CASE 
                                    WHEN status = 'suggested' THEN 0
                                    WHEN status = 'queued' THEN queue_position
                                    WHEN status = 'playing' THEN -1
                                    ELSE 999
                                END as sort_order
                            FROM room_queue 
                            WHERE room_id = ? AND status IN ('suggested', 'queued', 'playing')
                            ORDER BY sort_order ASC, id ASC
                        ");
                        $queue_stmt->bind_param("i", $room_id);
                        $queue_stmt->execute();
                        $queue_result = $queue_stmt->get_result();
                        
                        $suggestions = [];
                        $queue = [];
                        $current_playing = null;
                        
                        while ($q = $queue_result->fetch_assoc()) {
                            $item = [
                                'id' => $q['id'],
                                'video_id' => $q['video_id'],
                                'video_title' => $q['video_title'],
                                'video_duration' => $q['video_duration'],
                                'video_thumbnail' => $q['video_thumbnail'],
                                'suggested_by_user_id_string' => $q['suggested_by_user_id_string'],
                                'suggested_by_name' => $q['suggested_by_name'],
                                'suggested_at' => $q['suggested_at'],
                                'queue_position' => $q['queue_position'],
                                'status' => $q['status']
                            ];
                            
                            if ($q['status'] === 'suggested') {
                                $suggestions[] = $item;
                            } elseif ($q['status'] === 'queued') {
                                $queue[] = $item;
                            } elseif ($q['status'] === 'playing') {
                                $current_playing = $item;
                            }
                        }
                        $queue_stmt->close();
                        
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
                                'current_playing' => $current_playing
                            ]
                        ];
                    }
                    $yt_stmt->close();
                }
            }
            
            // SETTINGS CHECK
            if (in_array('settings_update', $event_types)) {
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
            }
            
            // INACTIVITY STATUS (send with every update)
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
            
            // Send the data
            echo "data: " . json_encode($all_data) . "\n\n";
            flush();
        }
        
    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
    }
    
    // Send heartbeat every 30 seconds
    if ($iteration % 30 == 0) {
        echo "data: " . json_encode(['type' => 'heartbeat']) . "\n\n";
        flush();
    }
    
    $iteration++;
    
    // Short sleep to prevent hammering database
    // When events detected, sleep less; when no events, sleep more
    sleep($has_events ? 0.5 : 1);
    
    // Reset connection after max iterations
    if ($iteration >= $max_iterations) {
        echo "data: " . json_encode(['type' => 'reconnect']) . "\n\n";
        flush();
        break;
    }
}

$conn->close();
?>
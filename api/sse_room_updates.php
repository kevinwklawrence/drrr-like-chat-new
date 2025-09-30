<?php
// api/sse_room_updates.php - Server-Sent Events endpoint for all room data
session_start();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Not authorized']) . "\n\n";
    flush();
    exit;
}

include '../db_connect.php';
require_once '../input_sanitizer.php';


$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$user_id = $_SESSION['user']['id'] ?? 0;
$user_type = $_SESSION['user']['type'] ?? 'guest';

// Send initial connection confirmation
echo "data: " . json_encode(['type' => 'connected', 'room_id' => $room_id]) . "\n\n";
flush();

// Tracking variables for change detection
$last_message_id = 0;
$last_user_count = 0;
$last_mention_count = 0;
$last_whisper_hash = '';
$last_friend_hash = '';
$last_pm_hash = '';
$last_youtube_hash = '';
$last_knock_hash = '';
$last_notification_hash = '';
$last_friend_notification_count = 0;
$last_room_status_hash = '';

$iteration = 0;
$max_iterations = 120; // 10 minutes (5 second intervals)

while ($iteration < $max_iterations && connection_status() == CONNECTION_NORMAL) {
    try {
        $updates = [];
        $has_changes = false;
        
        // ============================================
        // 1. MESSAGES
        // ============================================
        $msg_stmt = $conn->prepare("
            SELECT m.*, u.username, u.display_name, u.avatar_hue, u.avatar_saturation
            FROM messages m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.room_id = ?
            ORDER BY m.timestamp DESC
            LIMIT 50
        ");
        $msg_stmt->bind_param("i", $room_id);
        $msg_stmt->execute();
        $msg_result = $msg_stmt->get_result();
        
        $messages = [];
        $max_msg_id = 0;
        while ($row = $msg_result->fetch_assoc()) {
            $messages[] = $row;
            if ($row['id'] > $max_msg_id) $max_msg_id = $row['id'];
        }
        $msg_stmt->close();
        
        if ($max_msg_id != $last_message_id) {
            $updates['messages'] = array_reverse($messages);
            $last_message_id = $max_msg_id;
            $has_changes = true;
        }
        
        // ============================================
        // 2. USERS
        // ============================================
        $users_stmt = $conn->prepare("
            SELECT cu.*, u.username, u.display_name, u.avatar_hue, u.avatar_saturation,
                   u.custom_av, u.title, u.is_admin, u.is_moderator
            FROM chatroom_users cu
            LEFT JOIN users u ON cu.user_id = u.id
            WHERE cu.room_id = ?
            ORDER BY cu.is_host DESC, cu.username ASC, cu.guest_name ASC
        ");
        $users_stmt->bind_param("i", $room_id);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
        
        $users = [];
        while ($row = $users_result->fetch_assoc()) {
            $users[] = $row;
        }
        $users_stmt->close();
        
        $user_count = count($users);
        if ($user_count != $last_user_count) {
            $updates['users'] = $users;
            $last_user_count = $user_count;
            $has_changes = true;
        }
        
        // ============================================
        // 3. MENTIONS
        // ============================================
        $mentions_stmt = $conn->prepare("
            SELECT m.*, msg.message, msg.timestamp,
                   msg.user_id_string as sender_user_id_string,
                   msg.username as sender_username,
                   msg.guest_name as sender_guest_name,
                   msg.avatar as sender_avatar
            FROM mentions m
            LEFT JOIN messages msg ON m.message_id = msg.id
            WHERE m.mentioned_user_id_string = ?
            AND msg.room_id = ?
            AND m.is_read = 0
            ORDER BY m.created_at DESC
        ");
        $mentions_stmt->bind_param("si", $user_id_string, $room_id);
        $mentions_stmt->execute();
        $mentions_result = $mentions_stmt->get_result();
        
        $mentions = [];
        while ($row = $mentions_result->fetch_assoc()) {
            $mentions[] = $row;
        }
        $mentions_stmt->close();
        
        $mention_count = count($mentions);
        if ($mention_count != $last_mention_count) {
            $updates['mentions'] = [
                'mentions' => $mentions,
                'unread_count' => $mention_count
            ];
            $last_mention_count = $mention_count;
            $has_changes = true;
        }
        
        // ============================================
        // 4. WHISPERS
        // ============================================
        $whispers_stmt = $conn->prepare("
            SELECT DISTINCT
                CASE 
                    WHEN rw.sender_user_id_string = ? THEN rw.recipient_user_id_string
                    ELSE rw.sender_user_id_string
                END as other_user_id_string,
                CASE
                    WHEN rw.sender_user_id_string = ? THEN rw.recipient_username
                    ELSE rw.sender_username
                END as username,
                CASE
                    WHEN rw.sender_user_id_string = ? THEN rw.recipient_guest_name
                    ELSE rw.sender_guest_name
                END as guest_name,
                MAX(rw.timestamp) as last_message_time,
                SUM(CASE WHEN rw.recipient_user_id_string = ? AND rw.is_read = 0 THEN 1 ELSE 0 END) as unread_count
            FROM room_whispers rw
            WHERE rw.room_id = ?
            AND (rw.sender_user_id_string = ? OR rw.recipient_user_id_string = ?)
            GROUP BY other_user_id_string
            ORDER BY last_message_time DESC
        ");
        $whispers_stmt->bind_param("ssssisss", 
            $user_id_string, $user_id_string, $user_id_string, $user_id_string,
            $room_id, $user_id_string, $user_id_string
        );
        $whispers_stmt->execute();
        $whispers_result = $whispers_stmt->get_result();
        
        $conversations = [];
        while ($row = $whispers_result->fetch_assoc()) {
            $conversations[] = $row;
        }
        $whispers_stmt->close();
        
        $whisper_hash = md5(json_encode($conversations));
        if ($whisper_hash != $last_whisper_hash) {
            $updates['whispers'] = ['conversations' => $conversations];
            $last_whisper_hash = $whisper_hash;
            $has_changes = true;
        }
        
        // ============================================
        // 5. FRIENDS (registered users only)
        // ============================================
        if ($user_type === 'user' && $user_id > 0) {
            $friends_stmt = $conn->prepare("
                SELECT f.*, u.username as friend_username, u.avatar as friend_avatar,
                       u.display_name as friend_display_name
                FROM friends f
                JOIN users u ON f.friend_id = u.id
                WHERE f.user_id = ? AND f.status = 'accepted'
            ");
            $friends_stmt->bind_param("i", $user_id);
            $friends_stmt->execute();
            $friends_result = $friends_stmt->get_result();
            
            $friends = [];
            while ($row = $friends_result->fetch_assoc()) {
                $friends[] = $row;
            }
            $friends_stmt->close();
            
            $friend_hash = md5(json_encode($friends));
            if ($friend_hash != $last_friend_hash) {
                $updates['friends'] = ['friends' => $friends];
                $last_friend_hash = $friend_hash;
                $has_changes = true;
            }
        }
        
        // ============================================
        // 6. PRIVATE MESSAGES (registered users only)
        // ============================================
        if ($user_type === 'user' && $user_id > 0) {
            $pm_stmt = $conn->prepare("
                SELECT DISTINCT
                    CASE 
                        WHEN pm.sender_id = ? THEN pm.recipient_id
                        ELSE pm.sender_id
                    END as other_user_id,
                    u.username,
                    u.avatar,
                    u.display_name,
                    MAX(pm.timestamp) as last_message_time,
                    SUM(CASE WHEN pm.recipient_id = ? AND pm.is_read = 0 THEN 1 ELSE 0 END) as unread_count
                FROM private_messages pm
                JOIN users u ON u.id = CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END
                WHERE pm.sender_id = ? OR pm.recipient_id = ?
                GROUP BY other_user_id
                ORDER BY last_message_time DESC
            ");
            $pm_stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
            $pm_stmt->execute();
            $pm_result = $pm_stmt->get_result();
            
            $pm_conversations = [];
            while ($row = $pm_result->fetch_assoc()) {
                $pm_conversations[] = $row;
            }
            $pm_stmt->close();
            
            $pm_hash = md5(json_encode($pm_conversations));
            if ($pm_hash != $last_pm_hash) {
                $updates['private_messages'] = ['conversations' => $pm_conversations];
                $last_pm_hash = $pm_hash;
                $has_changes = true;
            }
        }
        
        // ============================================
        // 7. YOUTUBE (if enabled)
        // ============================================
        $youtube_check = $conn->prepare("SELECT youtube_enabled FROM chatrooms WHERE id = ?");
        $youtube_check->bind_param("i", $room_id);
        $youtube_check->execute();
        $yt_result = $youtube_check->get_result();
        $youtube_enabled = $yt_result->fetch_assoc()['youtube_enabled'] ?? 0;
        $youtube_check->close();
        
        if ($youtube_enabled) {
            $yt_stmt = $conn->prepare("
                SELECT * FROM youtube_queue 
                WHERE room_id = ? 
                ORDER BY position ASC
            ");
            $yt_stmt->bind_param("i", $room_id);
            $yt_stmt->execute();
            $yt_queue_result = $yt_stmt->get_result();
            
            $queue = [];
            while ($row = $yt_queue_result->fetch_assoc()) {
                $queue[] = $row;
            }
            $yt_stmt->close();
            
            $youtube_hash = md5(json_encode($queue));
            if ($youtube_hash != $last_youtube_hash) {
                $updates['youtube'] = [
                    'queue' => $queue,
                    'now_playing' => $queue[0] ?? null
                ];
                $last_youtube_hash = $youtube_hash;
                $has_changes = true;
            }
        }
        
        // ============================================
        // 8. FRIEND NOTIFICATIONS (registered users only)
        // ============================================
        if ($user_type === 'user' && $user_id > 0) {
            // Check if friend_notifications table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'friend_notifications'");
            if ($table_check && $table_check->num_rows > 0) {
                $fn_stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM friend_notifications 
                    WHERE user_id = ? AND is_read = 0
                ");
                $fn_stmt->bind_param("i", $user_id);
                $fn_stmt->execute();
                $fn_result = $fn_stmt->get_result();
                $fn_count = $fn_result->fetch_assoc()['count'];
                $fn_stmt->close();
                
                if ($fn_count != $last_friend_notification_count) {
                    $updates['friend_notifications'] = [
                        'count' => $fn_count,
                        'notifications' => []
                    ];
                    $last_friend_notification_count = $fn_count;
                    $has_changes = true;
                }
            }
        }
        
        // ============================================
        // 9. GENERAL NOTIFICATIONS
        // ============================================
        $notif_stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM user_mentions 
            WHERE room_id = ? 
            AND mentioned_user_id_string = ? 
            AND is_read = 0
        ");
        $notif_stmt->bind_param("is", $room_id, $user_id_string);
        $notif_stmt->execute();
        $notif_result = $notif_stmt->get_result();
        $notif_count = $notif_result->fetch_assoc()['count'];
        $notif_stmt->close();
        
        $notification_hash = md5($notif_count);
        if ($notification_hash != $last_notification_hash) {
            $updates['general_notifications'] = [
                'count' => $notif_count,
                'notifications' => []
            ];
            $last_notification_hash = $notification_hash;
            $has_changes = true;
        }
        
        // ============================================
        // 10. ROOM STATUS
        // ============================================
        $status_stmt = $conn->prepare("
            SELECT status FROM chatrooms WHERE id = ?
        ");
        $status_stmt->bind_param("i", $room_id);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        $room_status = $status_result->fetch_assoc()['status'] ?? 'active';
        $status_stmt->close();
        
        $room_status_hash = md5($room_status);
        if ($room_status_hash != $last_room_status_hash) {
            $updates['room_status'] = ['status' => $room_status];
            $last_room_status_hash = $room_status_hash;
            $has_changes = true;
        }
        
        // ============================================
        // 11. KNOCKS (if user is host)
        // ============================================
        $host_check = $conn->prepare("
            SELECT is_host FROM chatroom_users 
            WHERE room_id = ? AND user_id_string = ?
        ");
        $host_check->bind_param("is", $room_id, $user_id_string);
        $host_check->execute();
        $host_result = $host_check->get_result();
        $is_host = $host_result->num_rows > 0 && $host_result->fetch_assoc()['is_host'];
        $host_check->close();
        
        if ($is_host) {
            $knock_stmt = $conn->prepare("
                SELECT * FROM room_knocks 
                WHERE room_id = ? 
                AND status = 'pending'
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at DESC
            ");
            $knock_stmt->bind_param("i", $room_id);
            $knock_stmt->execute();
            $knock_result = $knock_stmt->get_result();
            
            $knocks = [];
            while ($row = $knock_result->fetch_assoc()) {
                $knocks[] = $row;
            }
            $knock_stmt->close();
            
            $knock_hash = md5(json_encode($knocks));
            if ($knock_hash != $last_knock_hash) {
                $updates['knocks'] = $knocks;
                $last_knock_hash = $knock_hash;
                $has_changes = true;
            }
        }
        
        // ============================================
        // SEND UPDATES IF ANY CHANGES
        // ============================================
        if ($has_changes) {
            echo "data: " . json_encode([
                'type' => 'room_update',
                'updates' => $updates
            ]) . "\n\n";
            flush();
        }
        
    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
    }
    
    // Send heartbeat every 30 seconds (every 6 iterations)
    if ($iteration % 6 == 0) {
        echo "data: " . json_encode(['type' => 'heartbeat']) . "\n\n";
        flush();
    }
    
    $iteration++;
    sleep(5);
    
    // Request reconnection after max iterations
    if ($iteration >= $max_iterations) {
        echo "data: " . json_encode(['type' => 'reconnect']) . "\n\n";
        flush();
        break;
    }
}

$conn->close();
?>
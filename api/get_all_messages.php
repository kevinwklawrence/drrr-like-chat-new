<?php
// api/get_all_messages.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Check if user is moderator or admin
$user_id = $_SESSION['user']['id'];
$is_authorized = false;

$stmt = $conn->prepare("SELECT is_moderator, is_admin FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_authorized = ($user_data['is_moderator'] == 1 || $user_data['is_admin'] == 1);
    }
    $stmt->close();
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Only moderators and admins can access this data']);
    exit;
}

// Get query parameters
$limit = min((int)($_GET['limit'] ?? 100), 500); // Max 500 messages per request
$offset = (int)($_GET['offset'] ?? 0);
$message_type = $_GET['type'] ?? 'all'; // 'all', 'chat', 'whispers', 'private'
$search = $_GET['search'] ?? '';
$user_filter = $_GET['user_filter'] ?? '';
$room_filter = $_GET['room_filter'] ?? '';

$messages = [];

try {
    // Get regular chat messages
    if ($message_type === 'all' || $message_type === 'chat') {
        $chat_query = "
            SELECT 
                'chat' as message_type,
                m.id,
                m.room_id,
                c.name as room_name,
                m.user_id,
                m.user_id_string,
                m.guest_name,
                m.message,
                m.timestamp,
                m.avatar,
                m.color,
                m.avatar_hue,
                m.avatar_saturation,
                m.bubble_hue,
                m.bubble_saturation,
                m.is_system,
                m.type as msg_type,
                u.username,
                u.is_admin,
                cu.ip_address,
                cu.is_host
            FROM messages m
            LEFT JOIN chatrooms c ON m.room_id = c.id
            LEFT JOIN users u ON m.user_id = u.id
            LEFT JOIN chatroom_users cu ON m.room_id = cu.room_id AND m.user_id_string = cu.user_id_string
        ";
        
        $conditions = [];
        $params = [];
        $param_types = "";
        
        if (!empty($search)) {
            $conditions[] = "m.message LIKE ?";
            $params[] = "%$search%";
            $param_types .= "s";
        }
        
        if (!empty($user_filter)) {
            $conditions[] = "(u.username LIKE ? OR m.guest_name LIKE ? OR m.user_id_string LIKE ?)";
            $params[] = "%$user_filter%";
            $params[] = "%$user_filter%";
            $params[] = "%$user_filter%";
            $param_types .= "sss";
        }
        
        if (!empty($room_filter)) {
            $conditions[] = "c.name LIKE ?";
            $params[] = "%$room_filter%";
            $param_types .= "s";
        }
        
        if (!empty($conditions)) {
            $chat_query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $chat_query .= " ORDER BY m.timestamp DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $param_types .= "ii";
        
        $stmt = $conn->prepare($chat_query);
        if ($stmt) {
            if (!empty($params)) {
                $stmt->bind_param($param_types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            $stmt->close();
        }
    }
    
    // Get whisper messages
    if ($message_type === 'all' || $message_type === 'whispers') {
        $whisper_query = "
            SELECT 
                'whisper' as message_type,
                rw.id,
                rw.room_id,
                c.name as room_name,
                NULL as user_id,
                rw.sender_user_id_string as user_id_string,
                NULL as guest_name,
                CONCAT('WHISPER TO ', rw.recipient_user_id_string, ': ', rw.message) as message,
                rw.created_at as timestamp,
                rw.sender_avatar as avatar,
                rw.color,
                rw.avatar_hue,
                rw.avatar_saturation,
                rw.bubble_hue,
                rw.bubble_saturation,
                0 as is_system,
                'whisper' as msg_type,
                rw.sender_name as username,
                0 as is_admin,
                NULL as ip_address,
                0 as is_host,
                rw.recipient_user_id_string
            FROM room_whispers rw
            LEFT JOIN chatrooms c ON rw.room_id = c.id
        ";
        
        $whisper_conditions = [];
        $whisper_params = [];
        $whisper_param_types = "";
        
        if (!empty($search)) {
            $whisper_conditions[] = "rw.message LIKE ?";
            $whisper_params[] = "%$search%";
            $whisper_param_types .= "s";
        }
        
        if (!empty($user_filter)) {
            $whisper_conditions[] = "(rw.sender_name LIKE ? OR rw.sender_user_id_string LIKE ? OR rw.recipient_user_id_string LIKE ?)";
            $whisper_params[] = "%$user_filter%";
            $whisper_params[] = "%$user_filter%";
            $whisper_params[] = "%$user_filter%";
            $whisper_param_types .= "sss";
        }
        
        if (!empty($room_filter)) {
            $whisper_conditions[] = "c.name LIKE ?";
            $whisper_params[] = "%$room_filter%";
            $whisper_param_types .= "s";
        }
        
        if (!empty($whisper_conditions)) {
            $whisper_query .= " WHERE " . implode(" AND ", $whisper_conditions);
        }
        
        $whisper_query .= " ORDER BY rw.created_at DESC LIMIT ? OFFSET ?";
        $whisper_params[] = $limit;
        $whisper_params[] = $offset;
        $whisper_param_types .= "ii";
        
        $stmt = $conn->prepare($whisper_query);
        if ($stmt) {
            if (!empty($whisper_params)) {
                $stmt->bind_param($whisper_param_types, ...$whisper_params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            $stmt->close();
        }
    }
    
    // Get private messages
    if ($message_type === 'all' || $message_type === 'private') {
        $pm_query = "
            SELECT 
                'private' as message_type,
                pm.id,
                NULL as room_id,
                'Private Message' as room_name,
                pm.sender_id as user_id,
                CAST(pm.sender_id AS CHAR) as user_id_string,
                NULL as guest_name,
                CONCAT('PM TO ', r.username, ': ', pm.message) as message,
                pm.created_at as timestamp,
                s.avatar,
                pm.color,
                pm.avatar_hue,
                pm.avatar_saturation,
                pm.bubble_hue,
                pm.bubble_saturation,
                0 as is_system,
                'private' as msg_type,
                s.username,
                s.is_admin,
                NULL as ip_address,
                0 as is_host,
                r.username as recipient_username
            FROM private_messages pm
            LEFT JOIN users s ON pm.sender_id = s.id
            LEFT JOIN users r ON pm.recipient_id = r.id
        ";
        
        $pm_conditions = [];
        $pm_params = [];
        $pm_param_types = "";
        
        if (!empty($search)) {
            $pm_conditions[] = "pm.message LIKE ?";
            $pm_params[] = "%$search%";
            $pm_param_types .= "s";
        }
        
        if (!empty($user_filter)) {
            $pm_conditions[] = "(s.username LIKE ? OR r.username LIKE ?)";
            $pm_params[] = "%$user_filter%";
            $pm_params[] = "%$user_filter%";
            $pm_param_types .= "ss";
        }
        
        if (!empty($pm_conditions)) {
            $pm_query .= " WHERE " . implode(" AND ", $pm_conditions);
        }
        
        $pm_query .= " ORDER BY pm.created_at DESC LIMIT ? OFFSET ?";
        $pm_params[] = $limit;
        $pm_params[] = $offset;
        $pm_param_types .= "ii";
        
        $stmt = $conn->prepare($pm_query);
        if ($stmt) {
            if (!empty($pm_params)) {
                $stmt->bind_param($pm_param_types, ...$pm_params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            $stmt->close();
        }
    }
    
    // Sort all messages by timestamp
    usort($messages, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Limit to requested amount after sorting
    $messages = array_slice($messages, 0, $limit);
    
    // Log moderator action
    $log_stmt = $conn->prepare("INSERT INTO moderator_logs (moderator_id, moderator_username, action_type, details) VALUES (?, ?, 'message_review', ?)");
    if ($log_stmt) {
        $details = "Reviewed messages - Type: $message_type, Limit: $limit, Search: $search";
        $log_stmt->bind_param("iss", $user_id, $_SESSION['user']['username'], $details);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    echo json_encode([
        'status' => 'success',
        'messages' => $messages,
        'count' => count($messages),
        'filters' => [
            'type' => $message_type,
            'search' => $search,
            'user_filter' => $user_filter,
            'room_filter' => $room_filter
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get all messages error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve messages: ' . $e->getMessage()]);
}

$conn->close();
?>
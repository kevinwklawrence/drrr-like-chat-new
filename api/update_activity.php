<?php
// api/update_activity.php - Updated activity tracking endpoint
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/activity_config.php';
require_once __DIR__ . '/activity_tracker.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not in room']);
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$activity_type = $_POST['activity_type'] ?? 'general';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    $tracker = new ActivityTracker($conn, $user_id_string, $room_id);
    $success = $tracker->updateActivity($activity_type);
    
    if ($success) {
        $status = $tracker->getUserActivityStatus();
        
        $response = [
            'status' => 'success',
            'message' => 'Activity updated',
            'activity_type' => $activity_type,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_status' => $status
        ];
        
        // Check if user was returned from AFK
        if (!$status['is_afk'] && $activity_type !== 'heartbeat') {
            $response['afk_status_changed'] = true;
            $response['returned_from_afk'] = true;
        }
        
        echo json_encode($response);
    } else {
        // Check if user is still in room
        $check_stmt = $conn->prepare("SELECT 1 FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
        $check_stmt->bind_param("is", $room_id, $user_id_string);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $check_stmt->close();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'not_in_room', 'message' => 'User not found in room']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update activity']);
        }
    }
    
} catch (Exception $e) {
    logActivity("Update activity error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update activity']);
}

$conn->close();
?>

<?php
// api/heartbeat.php - Updated heartbeat endpoint
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/activity_config.php';
require_once __DIR__ . '/activity_tracker.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$username = $_SESSION['user']['username'] ?? null;
$guest_name = $_SESSION['user']['name'] ?? null;
$avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
$is_admin = $_SESSION['user']['is_admin'] ?? 0;
$color = $_SESSION['user']['color'] ?? 'black';
$avatar_hue = $_SESSION['user']['avatar_hue'] ?? 0;
$avatar_saturation = $_SESSION['user']['avatar_saturation'] ?? 100;
$ip_address = $_SERVER['REMOTE_ADDR'];
$room_id = isset($_SESSION['room_id']) ? (int)$_SESSION['room_id'] : null;

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Ensure required columns exist
    ensureActivityColumns($conn);
    
    // First update global_users with current session data
    $sql = "INSERT INTO global_users (
        user_id_string, username, guest_name, avatar, guest_avatar, is_admin, ip_address, color, avatar_hue, avatar_saturation, last_activity
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) 
    ON DUPLICATE KEY UPDATE 
        username = VALUES(username),
        guest_name = VALUES(guest_name),
        avatar = VALUES(avatar),
        guest_avatar = VALUES(guest_avatar),
        is_admin = VALUES(is_admin),
        ip_address = VALUES(ip_address),
        color = VALUES(color),
        avatar_hue = VALUES(avatar_hue),
        avatar_saturation = VALUES(avatar_saturation),
        last_activity = NOW()";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare global users update: ' . $conn->error);
    }
    
    $stmt->bind_param("sssssissii", 
        $user_id_string, $username, $guest_name, $avatar, $avatar, 
        $is_admin, $ip_address, $color, $avatar_hue, $avatar_saturation
    );
    
    $success = $stmt->execute();
    $stmt->close();
    
    if (!$success) {
        throw new Exception('Failed to update global users');
    }
    
    // If user is in a room, use the activity tracker
    if ($room_id) {
        $tracker = new ActivityTracker($conn, $user_id_string, $room_id);
        $tracker->updateActivity('heartbeat');
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Heartbeat updated',
        'timestamp' => date('Y-m-d H:i:s'),
        'in_room' => $room_id !== null
    ]);
    
} catch (Exception $e) {
    logActivity("Heartbeat error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update heartbeat']);
}

$conn->close();
?>

<?php
// api/send_message.php - Updated to track message sending activity
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/activity_config.php';
require_once __DIR__ . '/activity_tracker.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not in room']);
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$message = trim($_POST['message'] ?? '');
$reply_to = isset($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;

if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit;
}

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Update activity first - this will clear AFK if user was auto-AFK
    $tracker = new ActivityTracker($conn, $user_id_string, $room_id);
    $was_afk_before = $tracker->getUserActivityStatus()['is_afk'];
    $tracker->updateActivity('message_send');
    $is_afk_after = $tracker->getUserActivityStatus()['is_afk'];
    
    // Process message content (mentions, etc.)
    $processed_message = processMessageContent($message, $conn, $room_id);
    
    // Insert the message
    $insert_sql = "INSERT INTO messages (room_id, user_id_string, message, timestamp, reply_to) VALUES (?, ?, ?, NOW(), ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    
    if (!$insert_stmt) {
        throw new Exception('Failed to prepare message insert: ' . $conn->error);
    }
    
    $insert_stmt->bind_param("issi", $room_id, $user_id_string, $processed_message, $reply_to);
    
    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to insert message: ' . $conn->error);
    }
    
    $message_id = $conn->insert_id;
    $insert_stmt->close();
    
    // If this was a reply, create mention notification
    if ($reply_to) {
        createReplyNotification($conn, $reply_to, $message_id, $user_id_string);
    }
    
    // Process @mentions in the message
    processMentions($conn, $message_id, $processed_message, $user_id_string, $room_id);
    
    $conn->commit();
    
    $response = [
        'status' => 'success',
        'message' => 'Message sent successfully',
        'message_id' => $message_id,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Include AFK status change if applicable
    if ($was_afk_before && !$is_afk_after) {
        $response['afk_cleared'] = true;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    logActivity("Send message error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
}

// Helper functions (simplified versions)
function processMessageContent($message, $conn, $room_id) {
    // Process @mentions
    $message = preg_replace_callback(
        '/\B@(\w+)/u',
        function($matches) use ($conn, $room_id) {
            $username = $matches[1];
            
            // Find user in room
            $stmt = $conn->prepare("
                SELECT user_id_string 
                FROM chatroom_users 
                WHERE room_id = ? AND (username = ? OR guest_name = ?)
            ");
            $stmt->bind_param("iss", $room_id, $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return '<span class="mention" data-user="' . $row['user_id_string'] . '">@' . $username . '</span>';
            }
            
            $stmt->close();
            return '@' . $username;
        },
        $message
    );
    
    return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
}

function createReplyNotification($conn, $original_message_id, $reply_message_id, $sender_user_id) {
    // Get original message sender
    $stmt = $conn->prepare("SELECT user_id_string FROM messages WHERE id = ?");
    $stmt->bind_param("i", $original_message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $original_sender = $result->fetch_assoc()['user_id_string'];
        
        // Don't notify if replying to yourself
        if ($original_sender !== $sender_user_id) {
            $mention_stmt = $conn->prepare("
                INSERT INTO mentions (message_id, mentioned_user_id_string, type) 
                VALUES (?, ?, 'reply')
            ");
            $mention_stmt->bind_param("is", $reply_message_id, $original_sender);
            $mention_stmt->execute();
            $mention_stmt->close();
        }
    }
    
    $stmt->close();
}

function processMentions($conn, $message_id, $message, $sender_user_id, $room_id) {
    // Extract mentions from processed message
    preg_match_all('/data-user="([^"]+)"/', $message, $matches);
    
    foreach ($matches[1] as $mentioned_user_id) {
        // Don't mention yourself
        if ($mentioned_user_id !== $sender_user_id) {
            $mention_stmt = $conn->prepare("
                INSERT INTO mentions (message_id, mentioned_user_id_string, type) 
                VALUES (?, ?, 'mention')
            ");
            $mention_stmt->bind_param("is", $message_id, $mentioned_user_id);
            $mention_stmt->execute();
            $mention_stmt->close();
        }
    }
}

$conn->close();
?>
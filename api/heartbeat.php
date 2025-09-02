<?php
// api/heartbeat.php - Updated heartbeat endpoint with ghost mode support
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$username = $_SESSION['user']['username'] ?? null;
$guest_name = $_SESSION['user']['name'] ?? null;
$avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
$is_admin = $_SESSION['user']['is_admin'] ?? 0;
$is_moderator = $_SESSION['user']['is_moderator'] ?? 0;
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
    // Check if user is in ghost mode (for registered users only)
    $ghost_mode = false;
    if ($_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['id'])) {
        $ghost_check = $conn->prepare("SELECT ghost_mode FROM users WHERE id = ?");
        if ($ghost_check) {
            $ghost_check->bind_param("i", $_SESSION['user']['id']);
            $ghost_check->execute();
            $ghost_result = $ghost_check->get_result();
            if ($ghost_result->num_rows > 0) {
                $ghost_data = $ghost_result->fetch_assoc();
                $ghost_mode = (bool)$ghost_data['ghost_mode'];
                
                // Update session with current ghost mode status
                $_SESSION['user']['ghost_mode'] = $ghost_mode;
            }
            $ghost_check->close();
        }
    }
    
    // GHOST MODE: Only update global_users if user is NOT in ghost mode
    if (!$ghost_mode) {
        $sql = "INSERT INTO global_users (
            user_id_string, username, guest_name, avatar, guest_avatar, is_admin, is_moderator, ip_address, color, avatar_hue, avatar_saturation, last_activity
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
            username = VALUES(username),
            guest_name = VALUES(guest_name),
            avatar = VALUES(avatar),
            guest_avatar = VALUES(guest_avatar),
            is_admin = VALUES(is_admin),
            is_moderator = VALUES(is_moderator),
            ip_address = VALUES(ip_address),
            color = VALUES(color),
            avatar_hue = VALUES(avatar_hue),
            avatar_saturation = VALUES(avatar_saturation),
            last_activity = NOW()";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare global users update: ' . $conn->error);
        }
        
        $stmt->bind_param("sssssiisssii", 
            $user_id_string, $username, $guest_name, $avatar, $avatar, 
            $is_admin, $is_moderator, $ip_address, $color, $avatar_hue, $avatar_saturation
        );
        
        $success = $stmt->execute();
        $stmt->close();
        
        if (!$success) {
            throw new Exception('Failed to update global users');
        }
    } else {
        // If user is in ghost mode, remove them from global_users to appear offline
        $delete_stmt = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $user_id_string);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    }
    
    // If user is in a room, update room activity (this is always done regardless of ghost mode)
    if ($room_id) {
        $room_activity_sql = "UPDATE chatroom_users SET last_activity = NOW() WHERE room_id = ? AND user_id_string = ?";
        $room_stmt = $conn->prepare($room_activity_sql);
        if ($room_stmt) {
            $room_stmt->bind_param("is", $room_id, $user_id_string);
            $room_stmt->execute();
            $room_stmt->close();
        }
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Heartbeat updated',
        'timestamp' => date('Y-m-d H:i:s'),
        'in_room' => $room_id !== null,
        'ghost_mode' => $ghost_mode
    ]);
    
} catch (Exception $e) {
    error_log("Heartbeat error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update heartbeat']);
}

$conn->close();
?>
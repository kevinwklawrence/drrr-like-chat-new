<?php
// api/toggle_ghost_mode.php - Toggle ghost mode for moderators and admins
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$user_id = $_SESSION['user']['id'];
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

// Check if user is admin or moderator
$stmt = $conn->prepare("SELECT is_admin, is_moderator, ghost_mode FROM users WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    $stmt->close();
    exit;
}

$user_data = $result->fetch_assoc();
$stmt->close();

$is_admin = (bool)$user_data['is_admin'];
$is_moderator = (bool)$user_data['is_moderator'];
$current_ghost_mode = (bool)$user_data['ghost_mode'];

if (!$is_admin && !$is_moderator) {
    echo json_encode(['status' => 'error', 'message' => 'Only moderators and admins can use ghost mode']);
    exit;
}

// Toggle ghost mode
$new_ghost_mode = !$current_ghost_mode;

try {
    $conn->begin_transaction();
    
    // Update ghost mode in users table
    $stmt = $conn->prepare("UPDATE users SET ghost_mode = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $new_ghost_mode, $user_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update ghost mode: ' . $stmt->error);
    }
    $stmt->close();
    
    // Update session
    $_SESSION['user']['ghost_mode'] = $new_ghost_mode;
    
    // If going into ghost mode, remove from global_users to appear offline
    if ($new_ghost_mode) {
        $stmt = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
        if ($stmt) {
            $stmt->bind_param("s", $current_user_id_string);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // If disabling ghost mode, add back to global_users
        $username = $_SESSION['user']['username'] ?? '';
        $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
        $color = $_SESSION['user']['color'] ?? 'blue';
        $avatar_hue = $_SESSION['user']['avatar_hue'] ?? 0;
        $avatar_saturation = $_SESSION['user']['avatar_saturation'] ?? 100;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $stmt = $conn->prepare("INSERT INTO global_users (user_id_string, username, avatar, color, avatar_hue, avatar_saturation, is_admin, is_moderator, ip_address, last_activity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE username = VALUES(username), avatar = VALUES(avatar), color = VALUES(color), avatar_hue = VALUES(avatar_hue), avatar_saturation = VALUES(avatar_saturation), is_admin = VALUES(is_admin), is_moderator = VALUES(is_moderator), ip_address = VALUES(ip_address), last_activity = NOW()");
        if ($stmt) {
            $stmt->bind_param("ssssiiiss", $current_user_id_string, $username, $avatar, $color, $avatar_hue, $avatar_saturation, $is_admin, $is_moderator, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'ghost_mode' => $new_ghost_mode,
        'message' => $new_ghost_mode ? 'Ghost mode activated - you are now invisible' : 'Ghost mode deactivated - you are now visible'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Ghost mode toggle error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to toggle ghost mode: ' . $e->getMessage()]);
}

$conn->close();
?>
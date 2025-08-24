<?php
// api/site_ban_user.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Check if user is moderator or admin
$moderator_id = $_SESSION['user']['id'];
$is_authorized = false;

$stmt = $conn->prepare("SELECT is_moderator, is_admin, username FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $moderator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_authorized = ($user_data['is_moderator'] == 1 || $user_data['is_admin'] == 1);
        $moderator_username = $user_data['username'];
    }
    $stmt->close();
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Only moderators and admins can ban users']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$target_user_id = $_POST['user_id'] ?? null;
$target_user_id_string = $_POST['user_id_string'] ?? '';
$target_username = $_POST['username'] ?? '';
$target_ip = $_POST['ip_address'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$duration = $_POST['duration'] ?? 'permanent'; // 'permanent', or seconds

// Validate input
if (empty($target_user_id_string) && empty($target_ip)) {
    echo json_encode(['status' => 'error', 'message' => 'User ID string or IP address required']);
    exit;
}

// Don't allow banning yourself
if ($target_user_id == $moderator_id) {
    echo json_encode(['status' => 'error', 'message' => 'Cannot ban yourself']);
    exit;
}

// Don't allow moderators to ban other moderators/admins (only admins can)
if ($target_user_id && !$user_data['is_admin']) {
    $check_stmt = $conn->prepare("SELECT is_moderator, is_admin FROM users WHERE id = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("i", $target_user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $target_data = $check_result->fetch_assoc();
            if ($target_data['is_moderator'] || $target_data['is_admin']) {
                echo json_encode(['status' => 'error', 'message' => 'Only admins can ban other moderators or admins']);
                $check_stmt->close();
                exit;
            }
        }
        $check_stmt->close();
    }
}

try {
    $conn->begin_transaction();
    
    // Calculate ban expiry
    $ban_until = null;
    if ($duration !== 'permanent' && is_numeric($duration)) {
        $ban_until = date('Y-m-d H:i:s', time() + (int)$duration);
    }
    
    // Get IP address if not provided
    if (empty($target_ip) && !empty($target_user_id_string)) {
        $ip_stmt = $conn->prepare("SELECT ip_address FROM global_users WHERE user_id_string = ? LIMIT 1");
        if ($ip_stmt) {
            $ip_stmt->bind_param("s", $target_user_id_string);
            $ip_stmt->execute();
            $ip_result = $ip_stmt->get_result();
            if ($ip_result->num_rows > 0) {
                $ip_data = $ip_result->fetch_assoc();
                $target_ip = $ip_data['ip_address'];
            }
            $ip_stmt->close();
        }
    }
    
    // Get username if not provided
    if (empty($target_username) && $target_user_id) {
        $name_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        if ($name_stmt) {
            $name_stmt->bind_param("i", $target_user_id);
            $name_stmt->execute();
            $name_result = $name_stmt->get_result();
            if ($name_result->num_rows > 0) {
                $name_data = $name_result->fetch_assoc();
                $target_username = $name_data['username'];
            }
            $name_stmt->close();
        }
    }
    
    // Insert site ban
    $ban_stmt = $conn->prepare("
        INSERT INTO site_bans (user_id, user_id_string, username, ip_address, banned_by_id, banned_by_username, reason, ban_until) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            banned_by_id = VALUES(banned_by_id),
            banned_by_username = VALUES(banned_by_username),
            reason = VALUES(reason),
            ban_until = VALUES(ban_until),
            timestamp = CURRENT_TIMESTAMP
    ");
    
    if (!$ban_stmt) {
        throw new Exception('Failed to prepare ban statement: ' . $conn->error);
    }
    
    $ban_stmt->bind_param("isssssss", $target_user_id, $target_user_id_string, $target_username, $target_ip, $moderator_id, $moderator_username, $reason, $ban_until);
    
    if (!$ban_stmt->execute()) {
        throw new Exception('Failed to insert ban: ' . $ban_stmt->error);
    }
    $ban_stmt->close();
    
    // Remove user from all rooms if they're currently online
    if (!empty($target_user_id_string)) {
        $remove_stmt = $conn->prepare("DELETE FROM chatroom_users WHERE user_id_string = ?");
        if ($remove_stmt) {
            $remove_stmt->bind_param("s", $target_user_id_string);
            $remove_stmt->execute();
            $remove_stmt->close();
        }
        
        // Remove from global_users
        $global_stmt = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
        if ($global_stmt) {
            $global_stmt->bind_param("s", $target_user_id_string);
            $global_stmt->execute();
            $global_stmt->close();
        }
    }
    
    // Log moderator action
    $log_details = "Site banned user: " . ($target_username ?: $target_user_id_string) . " (IP: $target_ip)";
    if ($reason) $log_details .= " Reason: $reason";
    if ($ban_until) $log_details .= " Until: $ban_until";
    
    $log_stmt = $conn->prepare("INSERT INTO moderator_logs (moderator_id, moderator_username, action_type, target_user_id, target_username, target_ip, details) VALUES (?, ?, 'site_ban', ?, ?, ?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("ississ", $moderator_id, $moderator_username, $target_user_id, $target_username, $target_ip, $log_details);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    $conn->commit();
    
    $ban_message = "User " . ($target_username ?: $target_user_id_string) . " has been site-banned";
    if ($duration === 'permanent') {
        $ban_message .= " permanently";
    } else {
        $minutes = round((int)$duration / 60);
        $ban_message .= " for " . $minutes . " minute" . ($minutes != 1 ? "s" : "");
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => $ban_message,
        'ban_details' => [
            'user_id' => $target_user_id,
            'username' => $target_username,
            'ip_address' => $target_ip,
            'reason' => $reason,
            'ban_until' => $ban_until,
            'permanent' => ($duration === 'permanent')
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Site ban error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to ban user: ' . $e->getMessage()]);
}

$conn->close();
?>
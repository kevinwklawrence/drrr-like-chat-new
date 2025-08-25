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
$moderator_username = '';
$is_admin = false;

$stmt = $conn->prepare("SELECT is_moderator, is_admin, username FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $moderator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_authorized = ($user_data['is_moderator'] == 1 || $user_data['is_admin'] == 1);
        $moderator_username = $user_data['username'];
        $is_admin = ($user_data['is_admin'] == 1);
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

// Get ban parameters
$target_username = $_POST['username'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$duration = $_POST['duration'] ?? 'permanent';

if (empty($target_username)) {
    echo json_encode(['status' => 'error', 'message' => 'Username required']);
    exit;
}

// Initialize target data
$target_user_id = null;
$target_user_id_string = '';
$target_ip = '';

try {
    $conn->begin_transaction();
    
    // First, try to find the user in the users table (registered user)
    $user_lookup_stmt = $conn->prepare("SELECT id, user_id FROM users WHERE username = ?");
    if ($user_lookup_stmt) {
        $user_lookup_stmt->bind_param("s", $target_username);
        $user_lookup_stmt->execute();
        $lookup_result = $user_lookup_stmt->get_result();
        
        if ($lookup_result->num_rows > 0) {
            $lookup_data = $lookup_result->fetch_assoc();
            $target_user_id = $lookup_data['id'];
            $target_user_id_string = $lookup_data['user_id'];
            
            // Don't allow banning yourself
            if ($target_user_id == $moderator_id) {
                echo json_encode(['status' => 'error', 'message' => 'Cannot ban yourself']);
                $user_lookup_stmt->close();
                exit;
            }
            
            // Don't allow moderators to ban other moderators/admins
            if (!$is_admin) {
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
                            $user_lookup_stmt->close();
                            exit;
                        }
                    }
                    $check_stmt->close();
                }
            }
            
            error_log("Found registered user: ID=$target_user_id, user_id_string=$target_user_id_string");
        }
        $user_lookup_stmt->close();
    }
    
    // Look up IP address from global_users (for both registered and guest users)
    if (!empty($target_user_id_string)) {
        // For registered users, use their user_id_string
        $ip_lookup_stmt = $conn->prepare("SELECT ip_address FROM global_users WHERE user_id_string = ? ORDER BY last_activity DESC LIMIT 1");
        if ($ip_lookup_stmt) {
            $ip_lookup_stmt->bind_param("s", $target_user_id_string);
            $ip_lookup_stmt->execute();
            $ip_result = $ip_lookup_stmt->get_result();
            
            if ($ip_result->num_rows > 0) {
                $ip_data = $ip_result->fetch_assoc();
                $target_ip = $ip_data['ip_address'];
                error_log("Found IP for registered user: $target_ip");
            }
            $ip_lookup_stmt->close();
        }
    } else {
        // If not found in users table, try to find as guest by username/guest_name
        $guest_lookup_stmt = $conn->prepare("SELECT user_id_string, ip_address FROM global_users WHERE username = ? OR guest_name = ? ORDER BY last_activity DESC LIMIT 1");
        if ($guest_lookup_stmt) {
            $guest_lookup_stmt->bind_param("ss", $target_username, $target_username);
            $guest_lookup_stmt->execute();
            $guest_result = $guest_lookup_stmt->get_result();
            
            if ($guest_result->num_rows > 0) {
                $guest_data = $guest_result->fetch_assoc();
                $target_user_id_string = $guest_data['user_id_string'];
                $target_ip = $guest_data['ip_address'];
                error_log("Found guest user: user_id_string=$target_user_id_string, IP=$target_ip");
            }
            $guest_lookup_stmt->close();
        }
        
        // If still not found, check if the input is an IP address
        if (empty($target_user_id_string) && filter_var($target_username, FILTER_VALIDATE_IP)) {
            $target_ip = $target_username;
            $target_username = 'IP: ' . $target_ip; // Update display name
            error_log("Treating input as IP address: $target_ip");
        }
    }
    
    // Must have at least user_id_string or IP to create a ban
    if (empty($target_user_id_string) && empty($target_ip)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not find user data for: ' . $target_username]);
        exit;
    }
    
    // Calculate ban expiry
    $ban_until = null;
    if ($duration !== 'permanent' && is_numeric($duration)) {
        $ban_until = date('Y-m-d H:i:s', time() + (int)$duration);
    }
    
    error_log("About to create ban: user_id=$target_user_id, user_id_string=$target_user_id_string, username=$target_username, ip=$target_ip");
    
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
    $log_details = "Site banned user: " . $target_username;
    if ($target_ip) $log_details .= " (IP: $target_ip)";
    if ($reason) $log_details .= " Reason: $reason";
    if ($ban_until) $log_details .= " Until: $ban_until";
    
    $log_stmt = $conn->prepare("INSERT INTO moderator_logs (moderator_id, moderator_username, action_type, target_user_id, target_username, target_ip, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($log_stmt) {
        $action_type = 'site_ban';
        $log_stmt->bind_param("issssss", $moderator_id, $moderator_username, $action_type, $target_user_id, $target_username, $target_ip, $log_details);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    $conn->commit();
    
    $ban_message = "User " . $target_username . " has been site-banned";
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
            'user_id_string' => $target_user_id_string,
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
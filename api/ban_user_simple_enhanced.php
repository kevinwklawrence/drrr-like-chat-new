<?php
// api/ban_user_simple_enhanced.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

// Check if current user is host or admin
$is_authorized = false;
if (!empty($current_user_id_string)) {
    $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $current_user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $is_authorized = ($user_data['is_host'] == 1);
        }
        $stmt->close();
    }
}

// Also check if user is admin
if (!$is_authorized && isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    $is_authorized = true;
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Only hosts and admins can ban users']);
    exit;
}

// Get POST data
$user_id_string = $_POST['user_id_string'] ?? '';
$duration = $_POST['duration'] ?? '';
$reason = $_POST['reason'] ?? '';

if (empty($user_id_string) || empty($duration)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// Don't allow banning yourself
if ($user_id_string === $current_user_id_string) {
    echo json_encode(['status' => 'error', 'message' => 'Cannot ban yourself']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get current banlist and room info
    $stmt = $conn->prepare("SELECT banlist, name FROM chatrooms WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Room not found');
    }
    
    $room_data = $result->fetch_assoc();
    $current_banlist = $room_data['banlist'] ? json_decode($room_data['banlist'], true) : [];
    $stmt->close();
    
    // Get user display name and check if they're in the room
    $user_display_name = 'Unknown User';
    $is_host = false;
    $user_in_room = false;
    
    $user_stmt = $conn->prepare("SELECT guest_name, username, is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ? LIMIT 1");
    if ($user_stmt) {
        $user_stmt->bind_param("is", $room_id, $user_id_string);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            $user_display_name = $user_data['username'] ?: $user_data['guest_name'] ?: 'Unknown User';
            $is_host = ($user_data['is_host'] == 1);
            $user_in_room = true;
        }
        $user_stmt->close();
    }
    
    // Don't allow banning hosts
    if ($is_host) {
        throw new Exception('Cannot ban the host');
    }
    
    // Create fallback name if user not in room
    if ($user_display_name === 'Unknown User') {
        if (strpos($user_id_string, 'TEST_') === 0) {
            $user_display_name = 'Test User (' . substr($user_id_string, 5, 6) . ')';
        } elseif (strpos($user_id_string, 'GUEST_') === 0) {
            $user_display_name = 'Guest User';
        } else {
            $user_display_name = 'User ' . substr($user_id_string, 0, 8);
        }
    }
    
    // Calculate ban expiry
    $ban_until = null;
    if ($duration !== 'permanent') {
        $duration_seconds = (int)$duration;
        $ban_until = time() + $duration_seconds;
    }
    
    // Create ban entry
    $ban_entry = [
        'user_id_string' => $user_id_string,
        'username' => $user_display_name,
        'banned_by' => $current_user_id_string,
        'reason' => $reason,
        'ban_until' => $ban_until,
        'banned_at' => time()
    ];
    
    // Remove any existing ban for this user
    $current_banlist = array_filter($current_banlist, function($ban) use ($user_id_string) {
        return $ban['user_id_string'] !== $user_id_string;
    });
    
    // Add new ban
    $current_banlist[] = $ban_entry;
    
    // Update room banlist
    $update_stmt = $conn->prepare("UPDATE chatrooms SET banlist = ? WHERE id = ?");
    if (!$update_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $banlist_json = json_encode($current_banlist);
    $update_stmt->bind_param("si", $banlist_json, $room_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Execute failed: ' . $update_stmt->error);
    }
    $update_stmt->close();
    
    // CRITICAL: Remove user from room immediately (this triggers the ban detection)
    $remove_stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($remove_stmt) {
        $remove_stmt->bind_param("is", $room_id, $user_id_string);
        $remove_success = $remove_stmt->execute();
        $affected_rows = $remove_stmt->affected_rows;
        $remove_stmt->close();
        
        error_log("Ban: Removed user $user_id_string from room $room_id. Affected rows: $affected_rows");
    }
    
    // Add system message about the ban
    $ban_message = "<span style='color: #dc3545; font-weight: bold;'>" . $user_display_name . " has been banned from the room";
    if ($duration === 'permanent') {
        $ban_message .= " permanently";
    } else {
        $minutes = round($duration_seconds / 60);
        $ban_message .= " for " . $minutes . " minute" . ($minutes != 1 ? "s" : "");
    }
    $ban_message .= "</span>";
    
    if (!empty($reason)) {
        $ban_message .= "<br><span style='color: #6c757d;'><em>Reason: " . htmlspecialchars($reason) . "</em></span>";
    }
    
    $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'banhammer.png', 'system')");
    if ($msg_stmt) {
        $msg_stmt->bind_param("is", $room_id, $ban_message);
        $msg_stmt->execute();
        $msg_stmt->close();
    }
    
    // Log the ban action
    error_log("USER BANNED: User '$user_display_name' ($user_id_string) banned from room $room_id by $current_user_id_string. Duration: $duration. Reason: $reason");
    
    $conn->commit();
    
    // Return success with details
    echo json_encode([
        'status' => 'success', 
        'message' => 'User banned successfully',
        'banned_user' => $user_display_name,
        'duration' => $duration,
        'reason' => $reason,
        'was_in_room' => $user_in_room,
        'removed_from_room' => isset($affected_rows) ? $affected_rows > 0 : false
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Ban user error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to ban user: ' . $e->getMessage()]);
}

$conn->close();
?>
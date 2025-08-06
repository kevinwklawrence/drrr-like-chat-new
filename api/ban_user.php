<?php
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

// Check if user exists in the room (allow banning even if user has left)
$stmt = $conn->prepare("SELECT * FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();

$user_to_ban = null;
$user_display_name = 'Unknown User';

if ($result->num_rows > 0) {
    $user_to_ban = $result->fetch_assoc();
    $user_display_name = $user_to_ban['username'] ?? $user_to_ban['guest_name'] ?? 'Unknown User';
    
    // Don't allow banning hosts
    if ($user_to_ban['is_host']) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot ban the host']);
        $stmt->close();
        exit;
    }
} else {
    // User not in room currently - try to get their name from various sources
    
    // First try regular users table (if user_id_string is numeric)
    if (is_numeric($user_id_string)) {
        $name_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        if ($name_stmt) {
            $user_id = (int)$user_id_string;
            $name_stmt->bind_param("i", $user_id);
            $name_stmt->execute();
            $name_result = $name_stmt->get_result();
            if ($name_result->num_rows > 0) {
                $name_data = $name_result->fetch_assoc();
                $user_display_name = $name_data['username'];
            }
            $name_stmt->close();
        }
    }
    
    // If still unknown, try to find any chatroom_users record (from any room)
    if ($user_display_name === 'Unknown User') {
        $any_room_stmt = $conn->prepare("SELECT guest_name, username FROM chatroom_users WHERE user_id_string = ? LIMIT 1");
        if ($any_room_stmt) {
            $any_room_stmt->bind_param("s", $user_id_string);
            $any_room_stmt->execute();
            $any_room_result = $any_room_stmt->get_result();
            if ($any_room_result->num_rows > 0) {
                $any_room_data = $any_room_result->fetch_assoc();
                $user_display_name = $any_room_data['username'] ?? $any_room_data['guest_name'] ?? 'Unknown User';
            }
            $any_room_stmt->close();
        }
    }
    
    // If still unknown, create a meaningful fallback name
    if ($user_display_name === 'Unknown User') {
        if (strpos($user_id_string, 'GUEST_') === 0) {
            $user_display_name = 'Guest User';
        } elseif (strpos($user_id_string, 'TEST_') === 0) {
            $user_display_name = 'Test User';
        } else {
            $user_display_name = 'User ' . substr($user_id_string, 0, 8);
        }
    }
}

$stmt->close();

// Calculate ban expiry
$ban_until = null;
if ($duration !== 'permanent') {
    $duration_seconds = (int)$duration;
    $ban_until = date('Y-m-d H:i:s', time() + $duration_seconds);
}

try {
    $conn->begin_transaction();
    
    // Insert ban record
    $stmt = $conn->prepare("INSERT INTO room_bans (room_id, user_id_string, banned_by, reason, ban_until, timestamp) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE banned_by = VALUES(banned_by), reason = VALUES(reason), ban_until = VALUES(ban_until), timestamp = NOW()");
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("issss", $room_id, $user_id_string, $current_user_id_string, $reason, $ban_until);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
    
    // Remove user from room (if they're still in it)
    if ($user_to_ban) {
        $stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("is", $room_id, $user_id_string);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->close();
    }
    
    // Add system message about the ban
    $ban_message = $user_display_name . " has been banned from the room";
    if ($duration === 'permanent') {
        $ban_message .= " permanently.";
    } else {
        $minutes = round($duration_seconds / 60);
        $ban_message .= " for " . $minutes . " minute" . ($minutes != 1 ? "s." : "");
    }
    if (!empty($reason)) {
        $ban_message .= " (Reason: " . $reason . ")";
    }
    
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'banhammer.png', 'system')");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $ban_message);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'User banned successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Ban user error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to ban user: ' . $e->getMessage()]);
}

$conn->close();
?>
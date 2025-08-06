<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output, just log them

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode([]);
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
    echo json_encode([]);
    exit;
}

try {
    // First check if room_bans table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'room_bans'");
    if ($check_table->num_rows === 0) {
        // Table doesn't exist, return empty array
        error_log("room_bans table does not exist");
        echo json_encode([]);
        exit;
    }

    // Debug: First get all bans for this room without joins to see raw data
    $debug_stmt = $conn->prepare("SELECT *, NOW() as current_time, (ban_until IS NULL OR ban_until > NOW()) as is_active FROM room_bans WHERE room_id = ?");
    $debug_stmt->bind_param("i", $room_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    $raw_bans = [];
    while ($row = $debug_result->fetch_assoc()) {
        $raw_bans[] = $row;
    }
    $debug_stmt->close();
    error_log("All bans for room $room_id with time check: " . print_r($raw_bans, true));

    // Get only active bans - try different time comparison methods
    $active_stmt = $conn->prepare("SELECT * FROM room_bans WHERE room_id = ? AND (ban_until IS NULL OR ban_until > CURRENT_TIMESTAMP)");
    $active_stmt->bind_param("i", $room_id);
    $active_stmt->execute();
    $active_result = $active_stmt->get_result();
    $active_bans = [];
    while ($row = $active_result->fetch_assoc()) {
        $active_bans[] = $row;
    }
    $active_stmt->close();
    error_log("Active bans for room $room_id: " . print_r($active_bans, true));

    // If no active bans found, return empty array
    if (empty($active_bans)) {
        error_log("No active bans found for room $room_id");
        echo json_encode([]);
        exit;
    }

    // Now try to get user info with the correct table structure
    $banned_users = [];
    
    foreach ($active_bans as $ban) {
        $user_info = [
            'user_id_string' => $ban['user_id_string'],
            'banned_by' => $ban['banned_by'],
            'reason' => $ban['reason'],
            'ban_until' => $ban['ban_until'],
            'timestamp' => $ban['timestamp'],
            'username' => null,
            'guest_name' => null
        ];
        
        // Try to get username from users table (for registered users)
        if (is_numeric($ban['user_id_string'])) {
            $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
            if ($user_stmt) {
                $user_id = (int)$ban['user_id_string'];
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                if ($user_result->num_rows > 0) {
                    $user_data = $user_result->fetch_assoc();
                    $user_info['username'] = $user_data['username'];
                }
                $user_stmt->close();
            }
        }
        
        // If no username found, try to find in current chatroom_users or use fallback
        if (!$user_info['username']) {
            // Try to find in current chatroom_users (might still be there)
            $room_stmt = $conn->prepare("SELECT guest_name, username FROM chatroom_users WHERE user_id_string = ? LIMIT 1");
            if ($room_stmt) {
                $room_stmt->bind_param("s", $ban['user_id_string']);
                $room_stmt->execute();
                $room_result = $room_stmt->get_result();
                if ($room_result->num_rows > 0) {
                    $room_data = $room_result->fetch_assoc();
                    $user_info['username'] = $room_data['username'];
                    $user_info['guest_name'] = $room_data['guest_name'];
                }
                $room_stmt->close();
            }
            
            // If still no name, create a fallback based on user_id_string
            if (!$user_info['username'] && !$user_info['guest_name']) {
                if (strpos($ban['user_id_string'], 'TEST_') === 0) {
                    $user_info['guest_name'] = 'Test User (' . substr($ban['user_id_string'], 5, 6) . ')';
                } elseif (strpos($ban['user_id_string'], 'GUEST_') === 0) {
                    $user_info['guest_name'] = 'Guest User (' . substr($ban['user_id_string'], 6, 6) . ')';
                } else {
                    $user_info['guest_name'] = 'User (' . substr($ban['user_id_string'], 0, 8) . ')';
                }
            }
        }
        
        $banned_users[] = $user_info;
    }

    error_log("Final banned users for room $room_id: " . print_r($banned_users, true));
    echo json_encode($banned_users);

} catch (Exception $e) {
    error_log("Exception in get_banned_users.php: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
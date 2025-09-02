<?php
// api/get_active_users.php - Updated active users endpoint
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/activity_config.php';

try {
    // Ensure required columns exist
    ensureActivityColumns($conn);
    
    // Get all users who are currently in rooms (these are "active" by definition)
    $room_users_sql = "
        SELECT DISTINCT
            gu.user_id_string,
            gu.username,
            gu.guest_name,
            gu.avatar,
            gu.avatar_hue,
            gu.avatar_saturation,
            gu.color,
            gu.is_admin,
            gu.last_activity,
            'in_room' as status,
            c.name as room_name,
            cu.is_host,
            cu.is_afk,
            TIMESTAMPDIFF(SECOND, gu.last_activity, NOW()) as seconds_inactive
        FROM global_users gu
        JOIN chatroom_users cu ON gu.user_id_string = cu.user_id_string
        JOIN chatrooms c ON cu.room_id = c.id
        WHERE gu.last_activity >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ORDER BY gu.last_activity DESC
    ";
    
    $room_stmt = $conn->prepare($room_users_sql);
    if (!$room_stmt) {
        throw new Exception('Failed to prepare room users query: ' . $conn->error);
    }
    
    $room_stmt->bind_param("i", SESSION_TIMEOUT);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    
    $room_users = [];
    while ($row = $room_result->fetch_assoc()) {
        $room_users[] = $row;
    }
    $room_stmt->close();
    
    // Get users in lounge (active within session timeout but not in rooms)
    $lounge_users_sql = "
        SELECT 
            gu.user_id_string,
            gu.username,
            gu.guest_name,
            gu.avatar,
            gu.avatar_hue,
            gu.avatar_saturation,
            gu.color,
            gu.is_admin,
            gu.last_activity,
            'in_lounge' as status,
            NULL as room_name,
            0 as is_host,
            0 as is_afk,
            TIMESTAMPDIFF(SECOND, gu.last_activity, NOW()) as seconds_inactive
        FROM global_users gu
        LEFT JOIN chatroom_users cu ON gu.user_id_string = cu.user_id_string
        WHERE gu.last_activity >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        AND cu.user_id_string IS NULL
        ORDER BY gu.last_activity DESC
    ";
    
    $lounge_stmt = $conn->prepare($lounge_users_sql);
    if (!$lounge_stmt) {
        throw new Exception('Failed to prepare lounge users query: ' . $conn->error);
    }
    
    $lounge_stmt->bind_param("i", SESSION_TIMEOUT);
    $lounge_stmt->execute();
    $lounge_result = $lounge_stmt->get_result();
    
    $lounge_users = [];
    while ($row = $lounge_result->fetch_assoc()) {
        $lounge_users[] = $row;
    }
    $lounge_stmt->close();
    
    // Combine and categorize users
    $active_users = [
        'room_users' => $room_users,
        'lounge_users' => $lounge_users,
        'total_active' => count($room_users) + count($lounge_users),
        'stats' => [
            'users_in_rooms' => count($room_users),
            'users_in_lounge' => count($lounge_users),
            'total_active_sessions' => count($room_users) + count($lounge_users)
        ],
        'configuration' => [
            'session_timeout_minutes' => SESSION_TIMEOUT / 60,
            'afk_timeout_minutes' => AFK_TIMEOUT / 60,
            'disconnect_timeout_minutes' => DISCONNECT_TIMEOUT / 60
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    logActivity("Active users check: " . count($room_users) . " in rooms, " . count($lounge_users) . " in lounge");
    
    echo json_encode($active_users);
    
} catch (Exception $e) {
    logActivity("Get active users error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to get active users: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>

<?php
// api/private_messages.php - Updated to track private message activity
// Add this to the existing private_messages.php file in the 'send' action

// In the send action, after validation but before sending:
if (isset($_SESSION['room_id'])) {
    $room_id = (int)$_SESSION['room_id'];
    $tracker = new ActivityTracker($conn, $sender_id_string, $room_id);
    $tracker->updateActivity('private_message');
} else {
    $tracker = new ActivityTracker($conn, $sender_id_string);
    $tracker->updateActivity('private_message');
}

// Similar addition needed in api/room_whispers.php for whisper activity
?>

<?php
// api/room_whispers.php - Updated to track whisper activity
// Add this to the existing room_whispers.php file in the 'send' action

// In the send action, after validation but before sending:
if (isset($_SESSION['room_id'])) {
    $room_id = (int)$_SESSION['room_id'];
    $tracker = new ActivityTracker($conn, $sender_user_id_string, $room_id);
    $tracker->updateActivity('whisper');
} else {
    $tracker = new ActivityTracker($conn, $sender_user_id_string);  
    $tracker->updateActivity('whisper');
}
?>

<?php
// api/create_room.php - Updated to track room creation activity
// Add this to the existing create_room.php file after room creation:

// After successful room creation:
$tracker = new ActivityTracker($conn, $user_id_string);
$tracker->updateActivity('room_create');
?>

<?php
// api/join_room.php - Updated to track room joining activity  
// Add this to the existing join_room.php file after successful join:

// After successful room join:
$tracker = new ActivityTracker($conn, $user_id_string, $room_id);
$tracker->updateActivity('room_join');
?>

<?php
// api/toggle_afk.php - Updated AFK toggle endpoint
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
$action = $_POST['action'] ?? 'toggle'; // toggle, set_afk, set_active

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Get current AFK status
    $stmt = $conn->prepare("SELECT is_afk, manual_afk, username, guest_name FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found in room']);
        $stmt->close();
        exit;
    }
    
    $user_data = $result->fetch_assoc();
    $current_afk = (bool)$user_data['is_afk'];
    $current_manual = (bool)$user_data['manual_afk'];
    $display_name = $user_data['username'] ?: $user_data['guest_name'] ?: 'Unknown User';
    $stmt->close();
    
    // Determine new AFK state
    $new_afk_state = $current_afk;
    $new_manual_state = $current_manual;
    
    switch ($action) {
        case 'toggle':
            $new_afk_state = !$current_afk;
            $new_manual_state = $new_afk_state;
            break;
        case 'set_afk':
            $new_afk_state = true;
            $new_manual_state = true;
            break;
        case 'set_active':
            $new_afk_state = false;
            $new_manual_state = false;
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            exit;
    }
    
    // Update user's AFK status
    if ($new_afk_state) {
        // Going AFK - update last activity to maintain session but set AFK
        $update_stmt = $conn->prepare("UPDATE chatroom_users SET is_afk = 1, manual_afk = ?, afk_since = NOW(), last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
        $update_stmt->bind_param("iis", $new_manual_state, $room_id, $user_id_string);
        
        $system_message = "$display_name is now AFK.";
        $avatar = 'afk.png';
        $action_text = 'marked as AFK';
    } else {
        // Going active - update activity tracker to ensure proper tracking
        $tracker = new ActivityTracker($conn, $user_id_string, $room_id);
        $tracker->updateActivity('manual_activity');
        
        $update_stmt = $conn->prepare("UPDATE chatroom_users SET is_afk = 0, manual_afk = 0, afk_since = NULL, last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
        $update_stmt->bind_param("is", $room_id, $user_id_string);
        
        $system_message = "$display_name is back from AFK.";
        $avatar = 'active.png';
        $action_text = 'no longer AFK';
    }
    
    $success = $update_stmt->execute();
    $affected_rows = $update_stmt->affected_rows;
    $update_stmt->close();
    
    if (!$success || $affected_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update AFK status']);
        exit;
    }
    
    // Add system message if status actually changed
    if ($current_afk !== $new_afk_state) {
        $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), ?, 'system')");
        if ($msg_stmt) {
            $msg_stmt->bind_param("iss", $room_id, $system_message, $avatar);
            $msg_stmt->execute();
            $msg_stmt->close();
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "User $action_text successfully",
        'is_afk' => $new_afk_state,
        'manual_afk' => $new_manual_state,
        'display_name' => $display_name,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    logActivity("Toggle AFK error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to toggle AFK status: ' . $e->getMessage()]);
}

$conn->close();
?>
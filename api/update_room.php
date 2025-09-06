<?php
// UPDATE api/update_room.php - Replace the existing update_room.php with this version:

session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in update_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;
$background = isset($_POST['background']) ? trim($_POST['background']) : '';
$theme = isset($_POST['theme']) ? trim($_POST['theme']) : 'default';
$has_password = isset($_POST['has_password']) ? (int)$_POST['has_password'] : 0;
$allow_knocking = isset($_POST['allow_knocking']) ? (int)$_POST['allow_knocking'] : 1;
$permanent = isset($_POST['permanent']) ? (int)$_POST['permanent'] : 0;
$youtube_enabled = isset($_POST['youtube_enabled']) ? (int)$_POST['youtube_enabled'] : 0;

// NEW: Get new feature data
$is_rp = isset($_POST['is_rp']) ? (int)$_POST['is_rp'] : 0;
$friends_only = isset($_POST['friends_only']) ? (int)$_POST['friends_only'] : 0;
$invite_only = isset($_POST['invite_only']) ? (int)$_POST['invite_only'] : 0;
$members_only = isset($_POST['members_only']) ? (int)$_POST['members_only'] : 0;
$disappearing_messages = isset($_POST['disappearing_messages']) ? (int)$_POST['disappearing_messages'] : 0;
$message_lifetime_minutes = isset($_POST['message_lifetime_minutes']) ? (int)$_POST['message_lifetime_minutes'] : 0;

if ($room_id <= 0 || empty($name) || $capacity <= 0) {
    error_log("Invalid input in update_room.php: room_id=$room_id, name=$name, capacity=$capacity");
    echo json_encode(['status' => 'error', 'message' => 'Room ID, name, and valid capacity are required']);
    exit;
}

$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    error_log("Missing user_id_string in update_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

// Check if user is a host OR admin/moderator (for permanent room settings)
$is_authorized = false;

// Check if user is host of the room
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if ($stmt) {
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0 && $result->fetch_assoc()['is_host'] == 1) {
        $is_authorized = true;
    }
    $stmt->close();
}

// Check if user is admin or moderator (for permanent room settings)
$is_admin = false;
$is_moderator = false;
if ($_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['id'])) {
    $stmt = $conn->prepare("SELECT is_admin, is_moderator FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user']['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $is_admin = ($user_data['is_admin'] == 1);
            $is_moderator = ($user_data['is_moderator'] == 1);
            
            // Allow admins/moderators to update room settings
            if ($is_admin || $is_moderator) {
                $is_authorized = true;
            }
        }
        $stmt->close();
    }
}

if (!$is_authorized) {
    error_log("Unauthorized user in update_room.php: room_id=$room_id, user_id_string=$user_id_string");
    echo json_encode(['status' => 'error', 'message' => 'Only hosts, moderators, or administrators can update room settings']);
    exit;
}

// Only allow admins/moderators to change permanent setting
if (isset($_POST['permanent']) && (int)$_POST['permanent'] === 1 && !($is_admin || $is_moderator)) {
    error_log("Non-admin/moderator attempting to make room permanent: user_id_string=$user_id_string");
    echo json_encode(['status' => 'error', 'message' => 'Only administrators and moderators can make rooms permanent']);
    exit;
}

// Validation
if (!in_array($capacity, [5, 10, 20, 50])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid capacity value']);
    exit;
}

// NEW: Validate friends_only for guests
if ($friends_only && $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Only registered users can create friends-only rooms']);
    exit;
}

// NEW: Validate disappearing messages
if ($disappearing_messages && ($message_lifetime_minutes < 1 || $message_lifetime_minutes > 1440)) {
    echo json_encode(['status' => 'error', 'message' => 'Message lifetime must be between 1 and 1440 minutes']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Check what columns exist in chatrooms table and add missing ones
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    $existing_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    // Add missing columns if they don't exist
    $required_columns = [
        'theme' => 'VARCHAR(50) DEFAULT "default"',
        'has_password' => 'TINYINT(1) DEFAULT 0',
        'allow_knocking' => 'TINYINT(1) DEFAULT 1',
        'permanent' => 'TINYINT(1) DEFAULT 0',
        'youtube_enabled' => 'TINYINT(1) DEFAULT 0',
        'youtube_current_video' => 'VARCHAR(255) DEFAULT NULL',
        'youtube_current_time' => 'INT DEFAULT 0',
        'youtube_is_playing' => 'TINYINT(1) DEFAULT 0',
        'youtube_last_updated' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'is_rp' => 'TINYINT(1) DEFAULT 0',
        'friends_only' => 'TINYINT(1) DEFAULT 0',
        'invite_only' => 'TINYINT(1) DEFAULT 0',
        'invite_code' => 'VARCHAR(32) DEFAULT NULL',
        'members_only' => 'TINYINT(1) DEFAULT 0',
        'disappearing_messages' => 'TINYINT(1) DEFAULT 0',
        'message_lifetime_minutes' => 'INT DEFAULT 0'
    ];
    
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            $conn->query("ALTER TABLE chatrooms ADD COLUMN $column $definition");
            error_log("Added $column column to chatrooms table");
        }
    }
    
    // Build the UPDATE query based on available fields
    $update_fields = [];
    $param_types = '';
    $param_values = [];
    
    // Always update these core fields
    $update_fields[] = 'name = ?';
    $param_types .= 's';
    $param_values[] = $name;
    
    $update_fields[] = 'description = ?';
    $param_types .= 's';
    $param_values[] = $description;
    
    $update_fields[] = 'capacity = ?';
    $param_types .= 'i';
    $param_values[] = $capacity;
    
    // Add all other fields
    $field_mappings = [
        'background' => [$background, 's'],
        'theme' => [$theme, 's'],
        'has_password' => [$has_password, 'i'],
        'allow_knocking' => [$allow_knocking, 'i'],
        'permanent' => [$permanent, 'i'],
        'youtube_enabled' => [$youtube_enabled, 'i'],
        'is_rp' => [$is_rp, 'i'],
        'friends_only' => [$friends_only, 'i'],
        'invite_only' => [$invite_only, 'i'],
        'members_only' => [$members_only, 'i'],
        'disappearing_messages' => [$disappearing_messages, 'i'],
        'message_lifetime_minutes' => [$message_lifetime_minutes, 'i']
    ];
    
    foreach ($field_mappings as $field => [$value, $type]) {
        if (in_array($field, $existing_columns)) {
            $update_fields[] = "$field = ?";
            $param_types .= $type;
            $param_values[] = $value;
        }
    }
    
    // Handle password update separately if provided
    $hashed_password = null;
    if ($has_password && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if (in_array('password', $existing_columns)) {
            $update_fields[] = 'password = ?';
            $param_types .= 's';
            $param_values[] = $hashed_password;
        }
    } elseif (!$has_password) {
        // Clear password if has_password is unchecked
        if (in_array('password', $existing_columns)) {
            $update_fields[] = 'password = NULL';
        }
    }
    
    // NEW: Generate invite code if invite_only is enabled and no code exists
    if ($invite_only && in_array('invite_code', $existing_columns)) {
        // Check if room already has an invite code
        $check_stmt = $conn->prepare("SELECT invite_code FROM chatrooms WHERE id = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("i", $room_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $current_room = $check_result->fetch_assoc();
            $check_stmt->close();
            
            if (empty($current_room['invite_code'])) {
                $invite_code = bin2hex(random_bytes(16)); // 32 character hex string
                $update_fields[] = 'invite_code = ?';
                $param_types .= 's';
                $param_values[] = $invite_code;
                error_log("Generated new invite code for room $room_id: $invite_code");
            }
        }
    } elseif (!$invite_only && in_array('invite_code', $existing_columns)) {
        // Clear invite code if invite_only is disabled
        $update_fields[] = 'invite_code = NULL';
    }
    
    // Add room_id to parameters
    $param_types .= 'i';
    $param_values[] = $room_id;
    
    // Execute the update
    $sql = "UPDATE chatrooms SET " . implode(', ', $update_fields) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
    
    // If YouTube was disabled, clean up player state
    if (!$youtube_enabled && in_array('youtube_enabled', $existing_columns)) {
        $cleanup_stmt = $conn->prepare("
            UPDATE chatrooms 
            SET youtube_current_video = NULL, 
                youtube_current_time = 0, 
                youtube_is_playing = 0
            WHERE id = ?
        ");
        if ($cleanup_stmt) {
            $cleanup_stmt->bind_param("i", $room_id);
            $cleanup_stmt->execute();
            $cleanup_stmt->close();
        }
        
        // Clean up sync table
        $sync_cleanup = $conn->prepare("DELETE FROM room_player_sync WHERE room_id = ?");
        if ($sync_cleanup) {
            $sync_cleanup->bind_param("i", $room_id);
            $sync_cleanup->execute();
            $sync_cleanup->close();
        }
        
        // Clean up queue
        $queue_cleanup = $conn->prepare("DELETE FROM room_queue WHERE room_id = ?");
        if ($queue_cleanup) {
            $queue_cleanup->bind_param("i", $room_id);
            $queue_cleanup->execute();
            $queue_cleanup->close();
        }
    }
    
    // Insert system message about room update
    $host_name = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['username'] : $_SESSION['user']['name'];
    $system_message = "$host_name has updated the room settings.";
    
    // Add details about what changed
    $changes = [];
    if (!empty($theme) && $theme !== 'default') {
        $changes[] = "theme changed to $theme";
    }
    if ($has_password) {
        $changes[] = "password protection " . ($has_password ? "enabled" : "disabled");
    }
    if ($youtube_enabled) {
        $changes[] = "YouTube player enabled";
    }
    if ($is_rp) {
        $changes[] = "roleplay mode enabled";
    }
    if ($friends_only) {
        $changes[] = "friends-only mode enabled";
    }
    if ($invite_only) {
        $changes[] = "invite-only mode enabled";
    }
    if ($members_only) {
        $changes[] = "members-only mode enabled";
    }
    if ($disappearing_messages) {
        $changes[] = "disappearing messages enabled ($message_lifetime_minutes min)";
    }
    if ($permanent) {
        $changes[] = "room marked as permanent";
    }
    
    if (!empty($changes)) {
        $system_message .= " (" . implode(", ", $changes) . ")";
    }
    
    $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
    $user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
    $guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;
    
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, user_id_string, type) VALUES (?, ?, ?, ?, ?, ?, 'system')");
    if ($stmt) {
        $stmt->bind_param("iissss", $room_id, $user_id, $guest_name, $system_message, $avatar, $user_id_string);
        if (!$stmt->execute()) {
            error_log("Execute failed for system message in update_room.php: " . $stmt->error);
        }
        $stmt->close();
    }
    
    $conn->commit();
    
    $response = [
        'status' => 'success',
        'message' => 'Room settings updated successfully'
    ];
    
    // Include invite code in response if invite_only is enabled
    if ($invite_only && isset($invite_code)) {
        $response['invite_code'] = $invite_code;
        $response['invite_link'] = "lounge.php?invite=" . $invite_code;
    }
    
    error_log("Room settings updated: room_id=$room_id, name=$name, theme=$theme, permanent=$permanent, host=$host_name");
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Update room error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update room settings: ' . $e->getMessage()]);
}

$conn->close();
?>
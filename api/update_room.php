<?php
// api/update_room.php - Enhanced version
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

// Check if user is a host
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    error_log("Prepare failed in update_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0 || $result->fetch_assoc()['is_host'] != 1) {
    error_log("User not a host in update_room.php: room_id=$room_id, user_id_string=$user_id_string");
    echo json_encode(['status' => 'error', 'message' => 'Only hosts can update room settings']);
    $stmt->close();
    exit;
}
$stmt->close();

try {
    $conn->begin_transaction();
    
    // Check what columns exist in chatrooms table and add missing ones
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    $existing_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    // Add missing columns if they don't exist
    if (!in_array('theme', $existing_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN theme VARCHAR(50) DEFAULT 'default'");
        error_log("Added theme column to chatrooms table");
    }
    
    if (!in_array('has_password', $existing_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN has_password TINYINT(1) DEFAULT 0");
        error_log("Added has_password column to chatrooms table");
    }
    
    if (!in_array('allow_knocking', $existing_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN allow_knocking TINYINT(1) DEFAULT 1");
        error_log("Added allow_knocking column to chatrooms table");
    }
    
    // Add YouTube columns if they don't exist
    if (!in_array('youtube_enabled', $existing_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN youtube_enabled TINYINT(1) DEFAULT 0");
        error_log("Added youtube_enabled column to chatrooms table");
    }
    
    if (!in_array('youtube_current_video', $existing_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN youtube_current_video VARCHAR(255) DEFAULT NULL");
        error_log("Added youtube_current_video column to chatrooms table");
    }
    
    if (!in_array('youtube_current_time', $existing_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN youtube_current_time INT DEFAULT 0");
        error_log("Added youtube_current_time column to chatrooms table");
    }
    
    if (!in_array('youtube_is_playing', $existing_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN youtube_is_playing TINYINT(1) DEFAULT 0");
        error_log("Added youtube_is_playing column to chatrooms table");
    }
    
    if (!in_array('youtube_last_updated', $existing_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN youtube_last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        error_log("Added youtube_last_updated column to chatrooms table");
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
    
    // Add optional fields
    if (in_array('background', $existing_columns)) {
        $update_fields[] = 'background = ?';
        $param_types .= 's';
        $param_values[] = $background;
    }
    
    if (in_array('theme', $existing_columns)) {
        $update_fields[] = 'theme = ?';
        $param_types .= 's';
        $param_values[] = $theme;
    }
    
    if (in_array('has_password', $existing_columns)) {
        $update_fields[] = 'has_password = ?';
        $param_types .= 'i';
        $param_values[] = $has_password;
    }
    
    if (in_array('allow_knocking', $existing_columns)) {
        $update_fields[] = 'allow_knocking = ?';
        $param_types .= 'i';
        $param_values[] = $allow_knocking;
    }
    
    if (in_array('permanent', $existing_columns)) {
        $update_fields[] = 'permanent = ?';
        $param_types .= 'i';
        $param_values[] = $permanent;
    }
    
    if (in_array('youtube_enabled', $existing_columns)) {
        $update_fields[] = 'youtube_enabled = ?';
        $param_types .= 'i';
        $param_values[] = $youtube_enabled;
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
    
    error_log("Room settings updated: room_id=$room_id, name=$name, theme=$theme, has_password=$has_password, allow_knocking=$allow_knocking, youtube_enabled=$youtube_enabled, host=$host_name");
    echo json_encode(['status' => 'success', 'message' => 'Room settings updated successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Update room error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update room settings: ' . $e->getMessage()]);
}

$conn->close();
?>
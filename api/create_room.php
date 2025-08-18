<?php
session_start();
header('Content-Type: application/json');

// Turn off error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Get form data
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$capacity = (int)($_POST['capacity'] ?? 10);
$background = $_POST['background'] ?? '';
$has_password = (int)($_POST['has_password'] ?? 0);
$password = $_POST['password'] ?? '';
$allow_knocking = (int)($_POST['allow_knocking'] ?? 1);

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$host_user_id = $_SESSION['user']['type'] === 'registered' ? $_SESSION['user']['user_id'] : null;

error_log("SAFE_CREATE_ROOM: Starting - name='$name', has_password=$has_password, password_length=" . strlen($password));

// Validation
if (empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Room name is required']);
    exit;
}

if (strlen($name) > 50) {
    echo json_encode(['status' => 'error', 'message' => 'Room name is too long (max 50 characters)']);
    exit;
}

// FIXED: Simple password validation
if ($has_password && empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Password is required when password protection is enabled']);
    exit;
}

if (!in_array($capacity, [5, 10, 20, 50])) {
    $capacity = 10;
}

try {
    $conn->begin_transaction();
    
    // FIXED: Simple password hashing - no complex logic
    $hashed_password = null;
    if ($has_password && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        error_log("SAFE_CREATE_ROOM: Password hashed successfully");
    }
    
    // FIXED: Use your original dynamic approach but with better error handling
    // Check what columns exist in chatrooms table
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    if (!$columns_query) {
        throw new Exception('Cannot check table structure: ' . $conn->error);
    }
    
    $chatroom_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $chatroom_columns[] = $row['Field'];
    }
    
    error_log("SAFE_CREATE_ROOM: Available chatrooms columns: " . implode(', ', $chatroom_columns));
    
    // Build INSERT query based on available columns - IMPROVED VERSION
    $insert_fields = ['name', 'description', 'capacity', 'created_at'];
    $insert_values = ['?', '?', '?', 'NOW()'];
    $param_types = 'ssi';
    $param_values = [$name, $description, $capacity];
    
    // Add password fields - CRITICAL FIX
    if (in_array('password', $chatroom_columns)) {
        $insert_fields[] = 'password';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $hashed_password;
        error_log("SAFE_CREATE_ROOM: Added password field");
    }
    
    if (in_array('has_password', $chatroom_columns)) {
        $insert_fields[] = 'has_password';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $has_password;
        error_log("SAFE_CREATE_ROOM: Added has_password field");
    }
    
    // Add other optional fields
    if (in_array('background', $chatroom_columns)) {
        $insert_fields[] = 'background';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $background;
    }
    
    if (in_array('allow_knocking', $chatroom_columns)) {
        $insert_fields[] = 'allow_knocking';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $allow_knocking;
    }
    
    if (in_array('host_user_id', $chatroom_columns)) {
        $insert_fields[] = 'host_user_id';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $host_user_id;
    }
    
    if (in_array('host_user_id_string', $chatroom_columns)) {
        $insert_fields[] = 'host_user_id_string';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $user_id_string;
    }
    
    $insert_sql = "INSERT INTO chatrooms (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_values) . ")";
    
    error_log("SAFE_CREATE_ROOM: SQL: $insert_sql");
    error_log("SAFE_CREATE_ROOM: Params: " . print_r($param_values, true));
    
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create room: ' . $stmt->error);
    }
    
    $room_id = $conn->insert_id;
    $stmt->close();
    
    error_log("SAFE_CREATE_ROOM: Room created with ID: $room_id");
    
    // CRITICAL: Verify password was saved correctly
    $verify_stmt = $conn->prepare("SELECT password, has_password FROM chatrooms WHERE id = ?");
    if ($verify_stmt) {
        $verify_stmt->bind_param("i", $room_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $verify_data = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        error_log("SAFE_CREATE_ROOM: Verification - has_password: " . ($verify_data['has_password'] ?? 'NULL') . ", password: " . (!empty($verify_data['password']) ? 'SET' : 'EMPTY'));
        
        // If password should be set but isn't, try fallback
        if ($has_password && empty($verify_data['password'])) {
            error_log("SAFE_CREATE_ROOM: Password missing, applying fallback fix");
            $fallback_stmt = $conn->prepare("UPDATE chatrooms SET password = ?, has_password = ? WHERE id = ?");
            if ($fallback_stmt) {
                $fallback_stmt->bind_param("sii", $hashed_password, $has_password, $room_id);
                if ($fallback_stmt->execute()) {
                    error_log("SAFE_CREATE_ROOM: Fallback password update successful");
                } else {
                    error_log("SAFE_CREATE_ROOM: Fallback failed: " . $fallback_stmt->error);
                }
                $fallback_stmt->close();
            }
        }
    }
    
    // Add host to chatroom_users - USE YOUR ORIGINAL WORKING CODE
    $user_columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    if (!$user_columns_query) {
        throw new Exception('Cannot check user table structure: ' . $conn->error);
    }
    
    $user_columns = [];
    while ($row = $user_columns_query->fetch_assoc()) {
        $user_columns[] = $row['Field'];
    }
    
    error_log("SAFE_CREATE_ROOM: Available chatroom_users columns: " . implode(', ', $user_columns));
    
    // Build INSERT query for chatroom_users based on available columns
    $user_insert_fields = ['room_id', 'user_id_string', 'is_host'];
    $user_insert_values = ['?', '?', '1'];
    $user_param_types = 'is';
    $user_param_values = [$room_id, $user_id_string];
    
    if (in_array('user_id', $user_columns)) {
        $user_insert_fields[] = 'user_id';
        $user_insert_values[] = '?';
        $user_param_types .= 'i';
        $user_param_values[] = $host_user_id;
    }
    
    if (in_array('guest_name', $user_columns)) {
        $guest_name = $_SESSION['user']['name'] ?? null;
        $user_insert_fields[] = 'guest_name';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $guest_name;
    }
    
    if (in_array('username', $user_columns)) {
        $username = $_SESSION['user']['username'] ?? null;
        $user_insert_fields[] = 'username';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $username;
    }
    
    if (in_array('guest_avatar', $user_columns)) {
        $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
        $user_insert_fields[] = 'guest_avatar';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $avatar;
    }
    
    if (in_array('avatar', $user_columns)) {
        $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
        $user_insert_fields[] = 'avatar';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $avatar;
    }
    
    if (in_array('ip_address', $user_columns)) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_insert_fields[] = 'ip_address';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $ip_address;
    }
    
    if (in_array('color', $user_columns)) {
        $color = $_SESSION['user']['color'] ?? 'blue';
        $user_insert_fields[] = 'color';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $color;
    }

    if (in_array('avatar_hue', $user_columns)) {
    $avatar_hue = (int)($_SESSION['user']['avatar_hue'] ?? 0);
    $user_insert_fields[] = 'avatar_hue';
    $user_insert_values[] = '?';
    $user_param_types .= 'i';
    $user_param_values[] = $avatar_hue;
}

if (in_array('avatar_saturation', $user_columns)) {
    $avatar_saturation = (int)($_SESSION['user']['avatar_saturation'] ?? 100);
    $user_insert_fields[] = 'avatar_saturation';
    $user_insert_values[] = '?';
    $user_param_types .= 'i';
    $user_param_values[] = $avatar_saturation;
}

if (in_array('bubble_hue', $user_columns)) {
    $bubble_hue = (int)($_SESSION['user']['bubble_hue'] ?? 0);
    $user_insert_fields[] = 'bubble_hue';
    $user_insert_values[] = '?';
    $user_param_types .= 'i';
    $user_param_values[] = $bubble_hue;
}

if (in_array('bubble_saturation', $user_columns)) {
    $bubble_saturation = (int)($_SESSION['user']['bubble_saturation'] ?? 100);
    $user_insert_fields[] = 'bubble_saturation';
    $user_insert_values[] = '?';
    $user_param_types .= 'i';
    $user_param_values[] = $bubble_saturation;
}
    
    $user_insert_sql = "INSERT INTO chatroom_users (" . implode(', ', $user_insert_fields) . ") VALUES (" . implode(', ', $user_insert_values) . ")";
    
    error_log("SAFE_CREATE_ROOM: User SQL: $user_insert_sql");
    
    $stmt = $conn->prepare($user_insert_sql);
    if (!$stmt) {
        throw new Exception('Database prepare error for user: ' . $conn->error);
    }
    
    $stmt->bind_param($user_param_types, ...$user_param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add host to room: ' . $stmt->error);
    }
    $stmt->close();
    
    // Add room creation message
    $display_name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Host';
    $password_status = $has_password ? ' (password protected)' : '';
    $creation_message = "Room '{$name}' has been created by {$display_name}{$password_status}";
    
    // Check if messages table has the right columns
    $message_columns_query = $conn->query("SHOW COLUMNS FROM messages");
    if ($message_columns_query) {
        $message_columns = [];
        while ($row = $message_columns_query->fetch_assoc()) {
            $message_columns[] = $row['Field'];
        }
        
        if (in_array('is_system', $message_columns)) {
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, ?, ?, 1, NOW(), ?, 'system')");
if ($stmt) {
    $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
    $stmt->bind_param("isss", $room_id, $user_id_string, $creation_message, $avatar);
    $stmt->execute();
    $stmt->close();
}
        }
    }
    
    // Set session room_id so user is automatically in the room
    $_SESSION['room_id'] = $room_id;
    
    $conn->commit();
    
    error_log("SAFE_CREATE_ROOM: Success! Room ID=$room_id, Name=$name, HasPassword=$has_password");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Room created successfully',
        'room_id' => $room_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("SAFE_CREATE_ROOM: Error - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to create room: ' . $e->getMessage()]);
}

$conn->close();
?>
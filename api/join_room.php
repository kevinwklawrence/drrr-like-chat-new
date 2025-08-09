<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized - please log in']);
    exit;
}

$user_session = $_SESSION['user'];
include '../db_connect.php';

$room_id = (int)($_POST['room_id'] ?? 0);
$password = $_POST['password'] ?? '';
$user_id_string = $user_session['user_id'] ?? '';

error_log("JOIN_ROOM_DEBUG: Starting - room_id: $room_id, user: $user_id_string, password_provided: " . (!empty($password) ? 'YES' : 'NO'));

if ($room_id <= 0 || empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // First, check if room_keys column exists
    $columns_check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'room_keys'");
    $room_keys_column_exists = $columns_check->num_rows > 0;
    error_log("JOIN_ROOM_DEBUG: room_keys column exists: " . ($room_keys_column_exists ? 'YES' : 'NO'));
    
    // Get room information - conditionally include room_keys
    $select_fields = "id, name, capacity, password, has_password";
    if ($room_keys_column_exists) {
        $select_fields .= ", room_keys";
    }
    
    $stmt = $conn->prepare("SELECT $select_fields FROM chatrooms WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Room not found']);
        $stmt->close();
        exit;
    }
    
    $room = $result->fetch_assoc();
    $stmt->close();
    
    error_log("JOIN_ROOM_DEBUG: Room found - name: {$room['name']}, has_password: {$room['has_password']}");
    error_log("JOIN_ROOM_DEBUG: Stored password hash: " . substr($room['password'], 0, 20) . "...");
    
    // Check if user is already in the room
    $stmt = $conn->prepare("SELECT id FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['room_id'] = $room_id;
            echo json_encode(['status' => 'success', 'message' => 'Rejoined room']);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }
    
    // Check room capacity
    $stmt = $conn->prepare("SELECT COUNT(*) as user_count FROM chatroom_users WHERE room_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count_data = $result->fetch_assoc();
        
        if ($count_data['user_count'] >= $room['capacity']) {
            echo json_encode(['status' => 'error', 'message' => 'Room is full']);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }
    
    // Handle password protection and room keys
    $requires_password = ($room['has_password'] == 1);
    $access_granted = false;
    
    error_log("JOIN_ROOM_DEBUG: Requires password: " . ($requires_password ? 'YES' : 'NO'));
    
    if ($requires_password) {
        // First check if user has a valid room key (from accepted knock)
        $has_valid_room_key = false;
        
        if ($room_keys_column_exists && isset($room['room_keys']) && !empty($room['room_keys'])) {
            error_log("JOIN_ROOM_DEBUG: Checking room keys...");
            $room_keys = json_decode($room['room_keys'], true) ?: [];
            error_log("JOIN_ROOM_DEBUG: Room keys data: " . print_r($room_keys, true));
            
            if (isset($room_keys[$user_id_string])) {
                $key_data = $room_keys[$user_id_string];
                error_log("JOIN_ROOM_DEBUG: Found key for user, checking expiration...");
                error_log("JOIN_ROOM_DEBUG: Key expires at: " . date('Y-m-d H:i:s', $key_data['expires_at']) . ", current time: " . date('Y-m-d H:i:s'));
                
                // Check if key is still valid
                if (isset($key_data['expires_at']) && $key_data['expires_at'] > time()) {
                    $has_valid_room_key = true;
                    $access_granted = true;
                    error_log("JOIN_ROOM_DEBUG: Valid room key found, granting access");
                    
                    // Remove used key
                    unset($room_keys[$user_id_string]);
                    $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
                    if ($stmt) {
                        $room_keys_json = json_encode($room_keys);
                        $stmt->bind_param("si", $room_keys_json, $room_id);
                        $stmt->execute();
                        $stmt->close();
                        error_log("JOIN_ROOM_DEBUG: Room key consumed and removed");
                    }
                } else {
                    error_log("JOIN_ROOM_DEBUG: Room key found but expired");
                }
            } else {
                error_log("JOIN_ROOM_DEBUG: No room key found for user $user_id_string");
            }
        } else {
            error_log("JOIN_ROOM_DEBUG: No room keys to check (column_exists: $room_keys_column_exists, room_keys: " . ($room['room_keys'] ?? 'NULL') . ")");
        }
        
        // If no valid key, check password
        if (!$access_granted) {
            error_log("JOIN_ROOM_DEBUG: No valid room key, checking password...");
            
            if (empty($password)) {
                error_log("JOIN_ROOM_DEBUG: No password provided");
                echo json_encode(['status' => 'error', 'message' => 'Password required']);
                exit;
            }
            
            $stored_password = $room['password'];
            error_log("JOIN_ROOM_DEBUG: Verifying password '$password' against stored hash");
            
            $password_valid = password_verify($password, $stored_password);
            error_log("JOIN_ROOM_DEBUG: Password verification result: " . ($password_valid ? 'VALID' : 'INVALID'));
            
            if (!$password_valid) {
                // Additional debug - let's test the password manually
                error_log("JOIN_ROOM_DEBUG: Testing password manually...");
                $manual_test = password_verify($password, $stored_password);
                error_log("JOIN_ROOM_DEBUG: Manual test result: " . ($manual_test ? 'VALID' : 'INVALID'));
                error_log("JOIN_ROOM_DEBUG: Password length: " . strlen($password));
                error_log("JOIN_ROOM_DEBUG: Password chars: " . bin2hex($password));
                error_log("JOIN_ROOM_DEBUG: Hash info: " . print_r(password_get_info($stored_password), true));
                
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
                exit;
            }
            
            $access_granted = true;
            error_log("JOIN_ROOM_DEBUG: Password verified successfully");
        }
    } else {
        $access_granted = true;
        error_log("JOIN_ROOM_DEBUG: Room has no password, access granted");
    }
    
    if (!$access_granted) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit;
    }
    
    // Get available columns in chatroom_users table
    $columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    $available_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    error_log("JOIN_ROOM_DEBUG: Available columns in chatroom_users: " . implode(', ', $available_columns));
    
    // Build dynamic INSERT statement
    $conn->begin_transaction();
    
    $insert_fields = ['room_id', 'user_id_string', 'is_host'];
    $insert_values = ['?', '?', '0'];
    $param_types = 'is';
    $param_values = [$room_id, $user_id_string];
    
    // Add optional fields if they exist
    if (in_array('user_id', $available_columns)) {
        $insert_fields[] = 'user_id';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = ($user_session['type'] === 'user') ? $user_session['id'] : null;
    }
    
    if (in_array('username', $available_columns)) {
        $insert_fields[] = 'username';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $user_session['username'] ?? null;
    }
    
    if (in_array('guest_name', $available_columns)) {
        $insert_fields[] = 'guest_name';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $user_session['name'] ?? null;
    }
    
    if (in_array('avatar', $available_columns)) {
        $insert_fields[] = 'avatar';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $user_session['avatar'] ?? 'default_avatar.jpg';
    }
    
    if (in_array('guest_avatar', $available_columns)) {
        $insert_fields[] = 'guest_avatar';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $user_session['avatar'] ?? 'default_avatar.jpg';
    }
    
    if (in_array('ip_address', $available_columns)) {
        $insert_fields[] = 'ip_address';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $_SERVER['REMOTE_ADDR'];
    }
    
    $insert_sql = "INSERT INTO chatroom_users (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_values) . ")";
    
    error_log("JOIN_ROOM_DEBUG: Insert SQL: $insert_sql");
    error_log("JOIN_ROOM_DEBUG: Insert params: " . print_r($param_values, true));
    
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to join room: ' . $stmt->error);
    }
    $stmt->close();
    
    // Add join message
    $display_name = $user_session['name'] ?? $user_session['username'] ?? 'Unknown User';
    $join_message = $display_name . ' joined the room';
    
    // Check if messages table has required columns
    $msg_columns_query = $conn->query("SHOW COLUMNS FROM messages");
    $msg_columns = [];
    while ($row = $msg_columns_query->fetch_assoc()) {
        $msg_columns[] = $row['Field'];
    }
    
    if (in_array('is_system', $msg_columns)) {
        $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), ?, 'system')");
        if ($stmt) {
            $avatar = $user_session['avatar'] ?? 'default_avatar.jpg';
            $stmt->bind_param("iss", $room_id, $join_message, $avatar);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $_SESSION['room_id'] = $room_id;
    
    $conn->commit();
    
    error_log("JOIN_ROOM_DEBUG: Successfully joined room $room_id");
    echo json_encode(['status' => 'success', 'message' => 'Joined room successfully']);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("JOIN_ROOM_DEBUG: Exception - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to join room: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
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

error_log("JOIN_ROOM_WITH_BAN_CHECK: Starting - room_id: $room_id, user: $user_id_string, password_provided: " . (!empty($password) ? 'YES' : 'NO'));

if ($room_id <= 0 || empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // CRITICAL FIX: Check if user is banned FIRST before any other checks
    $ban_check_result = checkUserBanStatus($conn, $room_id, $user_id_string);
    if ($ban_check_result['banned']) {
        error_log("JOIN_ROOM_WITH_BAN_CHECK: User $user_id_string is banned from room $room_id");
        
        $ban_message = "You are banned from this room";
        if ($ban_check_result['permanent']) {
            $ban_message .= " permanently";
        } else {
            $expires_time = strtotime($ban_check_result['expires']);
            $remaining_time = $expires_time - time();
            if ($remaining_time > 0) {
                $minutes_remaining = ceil($remaining_time / 60);
                $ban_message .= " for $minutes_remaining more minute" . ($minutes_remaining != 1 ? 's' : '');
            }
        }
        if (!empty($ban_check_result['reason'])) {
            $ban_message .= ". Reason: " . $ban_check_result['reason'];
        }
        
        echo json_encode([
            'status' => 'error', 
            'message' => $ban_message,
            'banned' => true,
            'ban_info' => $ban_check_result
        ]);
        exit;
    }
    
    // Ensure room_keys column exists
    $columns_check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'room_keys'");
    $room_keys_column_exists = $columns_check->num_rows > 0;
    
    if (!$room_keys_column_exists) {
        error_log("JOIN_ROOM_WITH_BAN_CHECK: Creating room_keys column...");
        $create_column = $conn->query("ALTER TABLE chatrooms ADD COLUMN room_keys TEXT DEFAULT NULL");
        if ($create_column) {
            $room_keys_column_exists = true;
            error_log("JOIN_ROOM_WITH_BAN_CHECK: room_keys column created successfully");
        }
    }
    
    // Get room information
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
    // After user entry is made to chatroom_users, copy color from global_users to chatroom_users for this user
    if (in_array('color', $available_columns)) {
        $copy_color_stmt = $conn->prepare("UPDATE chatroom_users SET color = (SELECT color FROM global_users WHERE user_id_string = ?) WHERE room_id = ? AND user_id_string = ?");
        if ($copy_color_stmt) {
            $copy_color_stmt->bind_param("sis", $user_id_string, $room_id, $user_id_string);
            $copy_color_stmt->execute();
            $copy_color_stmt->close();
        }
    }
        exit;
    }
    
    $room = $result->fetch_assoc();
    $stmt->close();
    
    error_log("JOIN_ROOM_WITH_BAN_CHECK: Room found - name: {$room['name']}, has_password: {$room['has_password']}");
    
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
    
    // Handle password protection and room keys (rest of existing logic)
    $requires_password = ($room['has_password'] == 1);
    $access_granted = false;
    $used_room_key = false;
    
    error_log("JOIN_ROOM_WITH_BAN_CHECK: Requires password: " . ($requires_password ? 'YES' : 'NO'));
    
    if ($requires_password) {
        // Check for room key first
        if ($room_keys_column_exists && isset($room['room_keys']) && !empty($room['room_keys']) && $room['room_keys'] !== 'null') {
            error_log("JOIN_ROOM_WITH_BAN_CHECK: Checking room keys...");
            
            $room_keys = json_decode($room['room_keys'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($room_keys)) {
                if (isset($room_keys[$user_id_string])) {
                    $key_data = $room_keys[$user_id_string];
                    
                    if (isset($key_data['expires_at']) && $key_data['expires_at'] > time()) {
                        $access_granted = true;
                        $used_room_key = true;
                        error_log("JOIN_ROOM_WITH_BAN_CHECK: ✅ Valid room key found, granting access");
                        
                        // Update key usage stats
                        $room_keys[$user_id_string]['last_used'] = time();
                        $room_keys[$user_id_string]['use_count'] = ($room_keys[$user_id_string]['use_count'] ?? 0) + 1;
                        
                        $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
                        if ($stmt) {
                            $room_keys_json = json_encode($room_keys, JSON_UNESCAPED_SLASHES);
                            $stmt->bind_param("si", $room_keys_json, $room_id);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }
        
        // If no valid key, check password
        if (!$access_granted) {
            if (empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Password required']);
                exit;
            }
            
            $password_valid = password_verify($password, $room['password']);
            
            if (!$password_valid) {
                // Try trimmed versions as fallback
                $password_variants = [trim($password), rtrim($password), ltrim($password)];
                foreach ($password_variants as $variant) {
                    if ($variant !== $password && password_verify($variant, $room['password'])) {
                        $password_valid = true;
                        break;
                    }
                }
            }
            
            if (!$password_valid) {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
                exit;
            }
            
            $access_granted = true;
        }
    } else {
        $access_granted = true;
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

    if (in_array('color', $available_columns)) {
        $insert_fields[] = 'color';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $user_session['color'] ?? 'blue';
    }

    if (in_array('ip_address', $available_columns)) {
        $insert_fields[] = 'ip_address';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $_SERVER['REMOTE_ADDR'];
    }
    
    
    $insert_sql = "INSERT INTO chatroom_users (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_values) . ")";
    
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
    $join_method = $used_room_key ? ' (using permanent room key)' : '';
    $join_message = $display_name . ' joined the room' . $join_method;
    
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
    
    error_log("JOIN_ROOM_WITH_BAN_CHECK: ✅ Successfully joined room $room_id" . ($used_room_key ? ' using permanent room key' : ''));
    echo json_encode([
        'status' => 'success', 
        'message' => 'Joined room successfully',
        'used_room_key' => $used_room_key,
        'permanent_access' => $used_room_key
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("JOIN_ROOM_WITH_BAN_CHECK: ❌ Exception - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to join room: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}

// CRITICAL FUNCTION: Check if user is banned from room
function checkUserBanStatus($conn, $room_id, $user_id_string) {
    // Get banlist from chatrooms table (simple version)
    $stmt = $conn->prepare("SELECT banlist FROM chatrooms WHERE id = ?");
    if (!$stmt) {
        error_log("Failed to prepare ban check query: " . $conn->error);
        return ['banned' => false];
    }
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['banned' => false];
    }
    
    $room_data = $result->fetch_assoc();
    $banlist = $room_data['banlist'] ? json_decode($room_data['banlist'], true) : [];
    $stmt->close();
    
    if (!is_array($banlist)) {
        return ['banned' => false];
    }
    
    // Check if user is in banlist
    foreach ($banlist as $ban) {
        if ($ban['user_id_string'] === $user_id_string) {
            // Check if ban is still active
            if ($ban['ban_until'] === null) {
                // Permanent ban
                return [
                    'banned' => true,
                    'permanent' => true,
                    'reason' => $ban['reason'] ?? ''
                ];
            } else {
                // Temporary ban - check if still valid
                if ($ban['ban_until'] > time()) {
                    return [
                        'banned' => true,
                        'permanent' => false,
                        'expires' => date('Y-m-d H:i:s', $ban['ban_until']),
                        'reason' => $ban['reason'] ?? ''
                    ];
                }
                // Ban expired, should clean it up but for now just ignore
            }
        }
    }
    
    return ['banned' => false];
}
?>
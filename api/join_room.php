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

error_log("JOIN_ROOM_FIXED: Starting - room_id: $room_id, user: $user_id_string, password_provided: " . (!empty($password) ? 'YES' : 'NO'));

if ($room_id <= 0 || empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Ensure room_keys column exists
    $columns_check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'room_keys'");
    $room_keys_column_exists = $columns_check->num_rows > 0;
    
    if (!$room_keys_column_exists) {
        error_log("JOIN_ROOM_FIXED: Creating room_keys column...");
        $create_column = $conn->query("ALTER TABLE chatrooms ADD COLUMN room_keys TEXT DEFAULT NULL");
        if ($create_column) {
            $room_keys_column_exists = true;
            error_log("JOIN_ROOM_FIXED: room_keys column created successfully");
        }
    }
    
    error_log("JOIN_ROOM_FIXED: room_keys column exists: " . ($room_keys_column_exists ? 'YES' : 'NO'));
    
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
        exit;
    }
    
    $room = $result->fetch_assoc();
    $stmt->close();
    
    error_log("JOIN_ROOM_FIXED: Room found - name: {$room['name']}, has_password: {$room['has_password']}");
    
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
    $used_room_key = false;
    
    error_log("JOIN_ROOM_FIXED: Requires password: " . ($requires_password ? 'YES' : 'NO'));
    
    if ($requires_password) {
        // First check if user has a valid room key (from accepted knock)
        if ($room_keys_column_exists && isset($room['room_keys']) && !empty($room['room_keys']) && $room['room_keys'] !== 'null') {
            error_log("JOIN_ROOM_FIXED: Checking room keys...");
            
            $room_keys = json_decode($room['room_keys'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($room_keys)) {
                error_log("JOIN_ROOM_FIXED: Room keys decoded successfully: " . count($room_keys) . " keys found");
                error_log("JOIN_ROOM_FIXED: Available keys for users: " . implode(', ', array_keys($room_keys)));
                
                if (isset($room_keys[$user_id_string])) {
                    $key_data = $room_keys[$user_id_string];
                    error_log("JOIN_ROOM_FIXED: Found key for user, checking expiration...");
                    error_log("JOIN_ROOM_FIXED: Key expires at: " . date('Y-m-d H:i:s', $key_data['expires_at']) . ", current time: " . date('Y-m-d H:i:s'));
                    
                    // Check if key is still valid
                    if (isset($key_data['expires_at']) && $key_data['expires_at'] > time()) {
                        $access_granted = true;
                        $used_room_key = true;
                        error_log("JOIN_ROOM_FIXED: ✅ Valid room key found, granting access");
                        
                        // Remove used key
                        unset($room_keys[$user_id_string]);
                        $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
                        if ($stmt) {
                            $room_keys_json = json_encode($room_keys, JSON_UNESCAPED_SLASHES);
                            $stmt->bind_param("si", $room_keys_json, $room_id);
                            $stmt->execute();
                            $stmt->close();
                            error_log("JOIN_ROOM_FIXED: Room key consumed and removed");
                        }
                    } else {
                        error_log("JOIN_ROOM_FIXED: ❌ Room key found but expired");
                    }
                } else {
                    error_log("JOIN_ROOM_FIXED: ❌ No room key found for user $user_id_string");
                }
            } else {
                error_log("JOIN_ROOM_FIXED: ❌ Failed to decode room keys or not an array");
            }
        } else {
            error_log("JOIN_ROOM_FIXED: No room keys to check (column_exists: " . ($room_keys_column_exists ? 'YES' : 'NO') . ", room_keys: " . ($room['room_keys'] ?? 'NULL') . ")");
        }
        
        // If no valid key, check password
        if (!$access_granted) {
            error_log("JOIN_ROOM_FIXED: No valid room key, checking password...");
            
            if (empty($password)) {
                error_log("JOIN_ROOM_FIXED: ❌ No password provided");
                echo json_encode(['status' => 'error', 'message' => 'Password required']);
                exit;
            }
            
            $stored_password = $room['password'];
            error_log("JOIN_ROOM_FIXED: Verifying password against stored hash");
            error_log("JOIN_ROOM_FIXED: Password length: " . strlen($password));
            error_log("JOIN_ROOM_FIXED: Hash info: " . print_r(password_get_info($stored_password), true));
            
            // Try password verification
            $password_valid = password_verify($password, $stored_password);
            error_log("JOIN_ROOM_FIXED: Password verification result: " . ($password_valid ? '✅ VALID' : '❌ INVALID'));
            
            if (!$password_valid) {
                // Try trimmed versions as fallback
                $password_variants = [
                    trim($password),
                    rtrim($password),
                    ltrim($password)
                ];
                
                foreach ($password_variants as $variant) {
                    if ($variant !== $password) {
                        $test_result = password_verify($variant, $stored_password);
                        error_log("JOIN_ROOM_FIXED: Testing variant '$variant': " . ($test_result ? '✅ VALID' : '❌ INVALID'));
                        if ($test_result) {
                            $password_valid = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$password_valid) {
                error_log("JOIN_ROOM_FIXED: ❌ All password attempts failed");
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
                exit;
            }
            
            $access_granted = true;
            error_log("JOIN_ROOM_FIXED: ✅ Password verified successfully");
        }
    } else {
        $access_granted = true;
        error_log("JOIN_ROOM_FIXED: ✅ Room has no password, access granted");
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
    
    error_log("JOIN_ROOM_FIXED: Available columns in chatroom_users: " . implode(', ', $available_columns));
    
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
    
    error_log("JOIN_ROOM_FIXED: Insert SQL: $insert_sql");
    
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
    $join_method = $used_room_key ? ' (via accepted knock)' : '';
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
    
    error_log("JOIN_ROOM_FIXED: ✅ Successfully joined room $room_id" . ($used_room_key ? ' using room key' : ''));
    echo json_encode([
        'status' => 'success', 
        'message' => 'Joined room successfully',
        'used_room_key' => $used_room_key
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("JOIN_ROOM_FIXED: ❌ Exception - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to join room: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
<?php
// Add error logging
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');
error_reporting(E_ALL);

// Log all errors to see what's happening
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    return false;
});

try {
    session_start();
    header('Content-Type: application/json');

    ini_set('display_errors', 0);

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

    // CRITICAL FIX: Check if user is banned FIRST before any other checks
    $ban_check_result = checkUserBanStatus($conn, $room_id, $user_id_string);
    if ($ban_check_result['banned']) {
        error_log("JOIN_ROOM_DEBUG: User $user_id_string is banned from room $room_id");
        
        $ban_message = "You are banned from this room";
        if ($ban_check_result['permanent']) {
            $ban_message .= " permanently";
        } else {
            if (isset($ban_check_result['expires'])) {
                $expires_time = strtotime($ban_check_result['expires']);
                $remaining_time = $expires_time - time();
                if ($remaining_time > 0) {
                    $minutes_remaining = ceil($remaining_time / 60);
                    $ban_message .= " for $minutes_remaining more minute" . ($minutes_remaining != 1 ? 's' : '');
                }
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
    
    error_log("JOIN_ROOM_DEBUG: Ban check passed");
    
    // Ensure room_keys column exists
    $columns_check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'room_keys'");
    $room_keys_column_exists = $columns_check->num_rows > 0;
    
    if (!$room_keys_column_exists) {
        error_log("JOIN_ROOM_DEBUG: Creating room_keys column...");
        $create_column = $conn->query("ALTER TABLE chatrooms ADD COLUMN room_keys TEXT DEFAULT NULL");
        if ($create_column) {
            $room_keys_column_exists = true;
            error_log("JOIN_ROOM_DEBUG: room_keys column created successfully");
        }
    }
    
    error_log("JOIN_ROOM_DEBUG: Room keys column check passed");
    
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
    
    error_log("JOIN_ROOM_DEBUG: Room found - name: {$room['name']}, has_password: {$room['has_password']}");
    
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
    
    error_log("JOIN_ROOM_DEBUG: User not already in room");
    
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
    
    error_log("JOIN_ROOM_DEBUG: Room capacity check passed");
    
    // Handle password protection and room keys
    $requires_password = ($room['has_password'] == 1);
    $access_granted = false;
    $used_room_key = false;
    
    error_log("JOIN_ROOM_DEBUG: Requires password: " . ($requires_password ? 'YES' : 'NO'));
    
    if ($requires_password) {
        // Check for room key first
        if ($room_keys_column_exists && isset($room['room_keys']) && !empty($room['room_keys']) && $room['room_keys'] !== 'null') {
            error_log("JOIN_ROOM_DEBUG: Checking room keys...");
            
            $room_keys = json_decode($room['room_keys'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($room_keys)) {
                if (isset($room_keys[$user_id_string])) {
                    $key_data = $room_keys[$user_id_string];
                    
                    if (isset($key_data['expires_at']) && $key_data['expires_at'] > time()) {
                        $access_granted = true;
                        $used_room_key = true;
                        error_log("JOIN_ROOM_DEBUG: Valid room key found, granting access");
                        
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
    
    error_log("JOIN_ROOM_DEBUG: Access granted");
    
    // Get available columns in chatroom_users table
    $columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    $available_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    error_log("JOIN_ROOM_DEBUG: Available columns: " . implode(', ', $available_columns));
    
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

    // Add avatar customization columns if they don't exist
if (!in_array('avatar_hue', $available_columns)) {
    $conn->query("ALTER TABLE chatroom_users ADD COLUMN avatar_hue INT DEFAULT 0 NOT NULL");
    $available_columns[] = 'avatar_hue';
}

if (!in_array('avatar_saturation', $available_columns)) {
    $conn->query("ALTER TABLE chatroom_users ADD COLUMN avatar_saturation INT DEFAULT 100 NOT NULL");
    $available_columns[] = 'avatar_saturation';
}

if (!in_array('bubble_hue', $available_columns)) {
    $conn->query("ALTER TABLE chatroom_users ADD COLUMN bubble_hue INT DEFAULT 0 NOT NULL");
    $available_columns[] = 'bubble_hue';
}

if (!in_array('bubble_saturation', $available_columns)) {
    $conn->query("ALTER TABLE chatroom_users ADD COLUMN bubble_saturation INT DEFAULT 100 NOT NULL");
    $available_columns[] = 'bubble_saturation';
}

// Add avatar customization to the INSERT
if (in_array('avatar_hue', $available_columns)) {
    $insert_fields[] = 'avatar_hue';
    $insert_values[] = '?';
    $param_types .= 'i';
    $param_values[] = (int)($user_session['avatar_hue'] ?? 0);
}

if (in_array('avatar_saturation', $available_columns)) {
    $insert_fields[] = 'avatar_saturation';
    $insert_values[] = '?';
    $param_types .= 'i';
    $param_values[] = (int)($user_session['avatar_saturation'] ?? 100);
}

if (in_array('bubble_hue', $available_columns)) {
    $insert_fields[] = 'bubble_hue';
    $insert_values[] = '?';
    $param_types .= 'i';
    $param_values[] = (int)($user_session['bubble_hue'] ?? 0);
}

if (in_array('bubble_saturation', $available_columns)) {
    $insert_fields[] = 'bubble_saturation';
    $insert_values[] = '?';
    $param_types .= 'i';
    $param_values[] = (int)($user_session['bubble_saturation'] ?? 100);
}
    
    $insert_sql = "INSERT INTO chatroom_users (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_values) . ")";
    
    error_log("JOIN_ROOM_DEBUG: Insert SQL: $insert_sql");
    error_log("JOIN_ROOM_DEBUG: Param types: $param_types");
    error_log("JOIN_ROOM_DEBUG: Param values: " . print_r($param_values, true));
    
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to join room: ' . $stmt->error);
    }
    $stmt->close();
    
    error_log("JOIN_ROOM_DEBUG: User inserted into chatroom_users");
    
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
        $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, ?, ?, 1, NOW(), ?, 'system')");
if ($stmt) {
    $avatar = $user_session['avatar'] ?? 'default_avatar.jpg';
    $stmt->bind_param("isss", $room_id, $user_id_string, $join_message, $avatar);
    $stmt->execute();
    $stmt->close();
}
    }
    
    $_SESSION['room_id'] = $room_id;
    
    $conn->commit();

// Sync avatar customization - prioritize users table for registered users
if ($user_session['type'] === 'user' && isset($user_session['id'])) {
    // For registered users, get data from users table
    try {
        $user_sync_stmt = $conn->prepare("UPDATE chatroom_users cu 
                                 JOIN users u ON cu.user_id = u.id 
                                 SET cu.avatar_hue = u.avatar_hue, 
                                     cu.avatar_saturation = u.avatar_saturation,
                                     cu.bubble_hue = u.bubble_hue,
                                     cu.bubble_saturation = u.bubble_saturation,
                                     cu.avatar = u.avatar
                                 WHERE cu.room_id = ? AND cu.user_id_string = ?");
        if ($user_sync_stmt) {
            $user_sync_stmt->bind_param("is", $room_id, $user_id_string);
            $user_sync_stmt->execute();
            $affected_rows = $user_sync_stmt->affected_rows;
            if ($affected_rows > 0) {
                error_log("JOIN_ROOM_DEBUG: Synced avatar data from users table");
            }
            $user_sync_stmt->close();
        }
    } catch (Exception $e) {
        error_log("JOIN_ROOM_DEBUG: User table sync failed (non-critical): " . $e->getMessage());
    }
} else {
    // For guests, sync from global_users as fallback
    try {
        $sync_stmt = $conn->prepare("UPDATE chatroom_users cu 
                             JOIN global_users gu ON cu.user_id_string = gu.user_id_string 
                             SET cu.avatar_hue = gu.avatar_hue, cu.avatar_saturation = gu.avatar_saturation, cu.bubble_hue = gu.bubble_hue, cu.bubble_saturation = gu.bubble_saturation 
                             WHERE cu.room_id = ? AND cu.user_id_string = ?");
        if ($sync_stmt) {
            $sync_stmt->bind_param("is", $room_id, $user_id_string);
            $sync_stmt->execute();
            $affected_rows = $sync_stmt->affected_rows;
            if ($affected_rows > 0) {
                error_log("JOIN_ROOM_DEBUG: Synced avatar customization from global_users");
            }
            $sync_stmt->close();
        }
    } catch (Exception $e) {
        error_log("JOIN_ROOM_DEBUG: Avatar sync failed (non-critical): " . $e->getMessage());
    }
}

error_log("JOIN_ROOM_DEBUG: Successfully joined room $room_id" . ($used_room_key ? ' using permanent room key' : ''));
    
    error_log("JOIN_ROOM_DEBUG: Successfully joined room $room_id" . ($used_room_key ? ' using permanent room key' : ''));
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
    error_log("JOIN_ROOM_DEBUG: Exception - " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['status' => 'error', 'message' => 'Failed to join room: ' . $e->getMessage()]);
} catch (Error $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("JOIN_ROOM_DEBUG: Fatal Error - " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['status' => 'error', 'message' => 'Fatal error: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}

// CRITICAL FUNCTION: Check if user is banned from room
function checkUserBanStatus($conn, $room_id, $user_id_string) {
    try {
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
        
    } catch (Exception $e) {
        error_log("Ban check error: " . $e->getMessage());
        return ['banned' => false];
    }
}
?>
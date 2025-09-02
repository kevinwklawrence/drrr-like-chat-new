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
    include '../check_site_ban.php';

    // Check for site ban before processing (but skip for admins/moderators)
    $is_admin = false;
    $is_moderator = false;
    $ghost_mode = false;
    
    if ($user_session['type'] === 'user' && isset($user_session['id'])) {
        $admin_check = $conn->prepare("SELECT is_admin, is_moderator, ghost_mode FROM users WHERE id = ?");
        if ($admin_check) {
            $admin_check->bind_param("i", $user_session['id']);
            $admin_check->execute();
            $admin_result = $admin_check->get_result();
            if ($admin_result->num_rows > 0) {
                $admin_data = $admin_result->fetch_assoc();
                $is_admin = (bool)$admin_data['is_admin'];
                $is_moderator = (bool)$admin_data['is_moderator'];
                $ghost_mode = (bool)$admin_data['ghost_mode'];
            }
            $admin_check->close();
        }
    }
    
    // Only check site ban for non-staff members
    if (!$is_admin && !$is_moderator) {
        checkSiteBan($conn);
    }

    $room_id = (int)($_POST['room_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $user_id_string = $user_session['user_id'] ?? '';

    error_log("JOIN_ROOM_DEBUG: Starting - room_id: $room_id, user: $user_id_string, is_admin: " . ($is_admin ? 'YES' : 'NO') . ", is_moderator: " . ($is_moderator ? 'YES' : 'NO') . ", ghost_mode: " . ($ghost_mode ? 'YES' : 'NO'));

    if ($room_id <= 0 || empty($user_id_string)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        exit;
    }

    // BYPASS: Skip ban check for admins and moderators
    if (!$is_admin && !$is_moderator) {
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
    } else {
        error_log("JOIN_ROOM_DEBUG: Ban check bypassed for admin/moderator");
    }
    
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
    
    // Get room information
    $select_fields = "id, name, capacity, password, has_password, permanent, host_user_id_string, invite_only, invite_code, members_only, friends_only";
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

    // Check if this is a permanent room and user is the original host
    $is_returning_permanent_host = false;
    $is_permanent = (bool)($room['permanent'] ?? false);
    $original_host_id = $room['host_user_id_string'] ?? '';
    
    if ($is_permanent && $original_host_id === $user_id_string) {
        error_log("JOIN_ROOM_DEBUG: Permanent room original host rejoining");
        $is_returning_permanent_host = true;
        error_log("JOIN_ROOM_DEBUG: Original host of permanent room granted automatic access and host privileges");
    }

    // BYPASS: Skip all access restrictions for admins and moderators
    $access_granted = $is_admin || $is_moderator || $is_returning_permanent_host;
    
    if (!$access_granted) {
        // Check invite-only access
        if (isset($room['invite_only']) && $room['invite_only']) {
            $provided_invite = $_POST['invite_code'] ?? $_GET['invite'] ?? '';
            $room_invite_code = $room['invite_code'] ?? '';
            
            error_log("JOIN_ROOM: Invite check - provided: '$provided_invite', expected: '$room_invite_code'");
            
            if (empty($provided_invite) || $provided_invite !== $room_invite_code) {
                echo json_encode(['status' => 'error', 'message' => 'This room requires a valid invite code']);
                exit;
            } else {
                error_log("JOIN_ROOM: Valid invite code provided");
            }
        }
        
        // Check access restrictions
        $access_denied_reason = null;
        
        // Check members-only access
        if ($room['members_only']) {
            if ($user_session['type'] !== 'user') {
                $access_denied_reason = 'This room is for registered members only';
            }
        }
        
        // Check friends-only access
        if (!$access_denied_reason && $room['friends_only']) {
            if ($user_session['type'] !== 'user') {
                $access_denied_reason = 'This room is for friends only';
            } else {
                // Check if current user is friend of host
                $current_user_id = $user_session['id'] ?? null;
                $host_user_id_string = $room['host_user_id_string'] ?? '';
                
                if ($current_user_id && $host_user_id_string) {
                    $friend_check = $conn->prepare("
                        SELECT COUNT(*) as is_friend 
                        FROM friends f
                        JOIN users u ON (f.user_id = u.id OR f.friend_user_id = u.id)
                        WHERE f.status = 'accepted' 
                        AND ((f.user_id = ? AND u.user_id_string = ?) 
                             OR (f.friend_user_id = ? AND u.user_id_string = ?))
                    ");
                    if ($friend_check) {
                        $friend_check->bind_param("isis", $current_user_id, $host_user_id_string, $current_user_id, $host_user_id_string);
                        $friend_check->execute();
                        $friend_result = $friend_check->get_result();
                        $friend_data = $friend_result->fetch_assoc();
                        if ($friend_data['is_friend'] == 0) {
                            $access_denied_reason = 'This room is for friends of the host only';
                        }
                        $friend_check->close();
                    } else {
                        $access_denied_reason = 'Unable to verify friend status';
                    }
                } else {
                    $access_denied_reason = 'This room is for friends only';
                }
            }
        }
        
        // If access is denied, return error
        if ($access_denied_reason) {
            echo json_encode(['status' => 'error', 'message' => $access_denied_reason]);
            exit;
        }
        
        $access_granted = true;
    } else {
        error_log("JOIN_ROOM_DEBUG: Access restrictions bypassed for admin/moderator");
    }

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
    
    // BYPASS: Skip capacity check for admins and moderators
    if (!$is_admin && !$is_moderator) {
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
    } else {
        error_log("JOIN_ROOM_DEBUG: Capacity check bypassed for admin/moderator");
    }
    
    // BYPASS: Skip password check for admins and moderators
    $requires_password = ($room['has_password'] == 1);
    $used_room_key = false;
    
    if (!$access_granted && $requires_password && !$is_admin && !$is_moderator) {
        // Check for room key first
        if ($room_keys_column_exists && isset($room['room_keys']) && !empty($room['room_keys']) && $room['room_keys'] !== 'null') {
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
    } else if ($is_admin || $is_moderator) {
        error_log("JOIN_ROOM_DEBUG: Password check bypassed for admin/moderator");
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
    $insert_values = ['?', '?', '?'];
    $param_types = 'isi';
    $param_values = [$room_id, $user_id_string, $is_returning_permanent_host ? 1 : 0];
    
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
    
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to join room: ' . $stmt->error);
    }
    $stmt->close();
    
    // GHOST MODE: Only add join message if user is not in ghost mode
    if (!$ghost_mode) {
        $display_name = $user_session['name'] ?? $user_session['username'] ?? 'Unknown User';
        $join_method = '';
        
        if ($used_room_key) {
            $join_method = ' (using permanent room key)';
        } elseif ($is_returning_permanent_host) {
            $join_method = ' (permanent room host returned)';
        } elseif ($is_admin || $is_moderator) {
            $join_method = ' (staff access)';
        }
        
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
    } else {
        error_log("JOIN_ROOM_DEBUG: Ghost mode active - join message suppressed");
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
                $user_sync_stmt->close();
            }
        } catch (Exception $e) {
            error_log("JOIN_ROOM_DEBUG: User table sync failed (non-critical): " . $e->getMessage());
        }
    }

    $success_message = 'Joined room successfully';
    if ($is_returning_permanent_host) {
        $success_message = 'Rejoined as host of permanent room';
    } elseif ($is_admin || $is_moderator) {
        $success_message = 'Joined room with staff privileges';
        if ($ghost_mode) {
            $success_message .= ' (ghost mode active)';
        }
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => $success_message,
        'used_room_key' => $used_room_key,
        'permanent_access' => $used_room_key,
        'is_host' => $is_returning_permanent_host,
        'ghost_mode' => $ghost_mode,
        'bypassed_restrictions' => $is_admin || $is_moderator
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

// Ban check function remains the same but is bypassed for admins/moderators
function checkUserBanStatus($conn, $room_id, $user_id_string) {
    try {
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
        
        foreach ($banlist as $ban) {
            if ($ban['user_id_string'] === $user_id_string) {
                if ($ban['ban_until'] === null) {
                    return [
                        'banned' => true,
                        'permanent' => true,
                        'reason' => $ban['reason'] ?? ''
                    ];
                } else {
                    if ($ban['ban_until'] > time()) {
                        return [
                            'banned' => true,
                            'permanent' => false,
                            'expires' => date('Y-m-d H:i:s', $ban['ban_until']),
                            'reason' => $ban['reason'] ?? ''
                        ];
                    }
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
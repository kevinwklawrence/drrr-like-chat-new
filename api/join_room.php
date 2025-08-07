<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)($_POST['room_id'] ?? 0);
$password = $_POST['password'] ?? '';
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if ($room_id <= 0 || empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Get room information
    $stmt = $conn->prepare("SELECT * FROM chatrooms WHERE id = ?");
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
    
    // Check if user is already in the room
    $stmt = $conn->prepare("SELECT id FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // User is already in room, just set session and redirect
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
    
    // Check if user is banned using simple banlist
    $banlist = $room['banlist'] ? json_decode($room['banlist'], true) : [];
    $current_time = time();
    
    foreach ($banlist as $ban) {
        if ($ban['user_id_string'] === $user_id_string) {
            // Check if ban is still active
            if ($ban['ban_until'] === null || $ban['ban_until'] > $current_time) {
                $ban_message = 'You are banned from this room';
                if ($ban['ban_until'] !== null) {
                    $ban_message .= ' until ' . date('Y-m-d H:i:s', $ban['ban_until']);
                }
                echo json_encode(['status' => 'error', 'message' => $ban_message]);
                exit;
            }
        }
    }
    
    // Handle password protection
    if ($room['has_password']) {
        $access_granted = false;
        
        // First check if user has a valid room key
        $room_keys = $room['room_keys'] ? json_decode($room['room_keys'], true) : [];
        if (isset($room_keys[$user_id_string])) {
            $key_data = $room_keys[$user_id_string];
            
            // Check if key is still valid
            if ($key_data['expires_at'] > time()) {
                $access_granted = true;
                
                // Remove used key
                unset($room_keys[$user_id_string]);
                $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
                if ($stmt) {
                    $room_keys_json = json_encode($room_keys);
                    $stmt->bind_param("si", $room_keys_json, $room_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        
        // If no valid key, check password
        if (!$access_granted) {
            if (empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Password required']);
                exit;
            }
            
            if (!password_verify($password, $room['password'])) {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
                exit;
            }
        }
    }
    
    // Add user to room - Check what columns exist first
    $conn->begin_transaction();
    
    // Check what columns exist in chatroom_users table
    $columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    $available_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Get user data
    $user_data = $_SESSION['user'];
    $guest_name = $user_data['name'] ?? null;
    $username = $user_data['username'] ?? null;
    $avatar = $user_data['avatar'] ?? 'default_avatar.jpg';
    $user_id = $user_data['type'] === 'user' ? $user_data['id'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Build dynamic insert query based on available columns
    $insert_fields = ['room_id', 'user_id_string', 'is_host'];
    $insert_values = ['?', '?', '0'];
    $param_types = 'is';
    $param_values = [$room_id, $user_id_string];
    
    // Add optional columns if they exist
    if (in_array('user_id', $available_columns) && $user_id) {
        $insert_fields[] = 'user_id';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $user_id;
    }
    
    if (in_array('username', $available_columns) && $username) {
        $insert_fields[] = 'username';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $username;
    }
    
    if (in_array('guest_name', $available_columns) && $guest_name) {
        $insert_fields[] = 'guest_name';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $guest_name;
    }
    
    if (in_array('avatar', $available_columns)) {
        $insert_fields[] = 'avatar';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $avatar;
    }
    
    if (in_array('guest_avatar', $available_columns)) {
        $insert_fields[] = 'guest_avatar';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $avatar;
    }
    
    if (in_array('ip_address', $available_columns)) {
        $insert_fields[] = 'ip_address';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $ip_address;
    }
    
    // Build and execute the insert query
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
    $display_name = $guest_name ?? $username ?? 'Unknown User';
    $join_message = $display_name . ' joined the room';
    
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), ?, 'system')");
    if ($stmt) {
        $stmt->bind_param("iss", $room_id, $join_message, $avatar);
        $stmt->execute();
        $stmt->close();
    }
    
    $_SESSION['room_id'] = $room_id;
    
    $conn->commit();
    
    echo json_encode(['status' => 'success', 'message' => 'Joined room successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Join room error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to join room: ' . $e->getMessage()]);
}

$conn->close();
?>
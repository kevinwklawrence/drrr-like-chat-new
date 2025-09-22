<?php
// api/join_room_by_invite.php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$invite_code = trim($_POST['invite_code'] ?? '');

if (empty($invite_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Invite code is required']);
    exit;
}

try {
    // Find room by invite code
    $stmt = $conn->prepare("SELECT id, name, invite_only, has_password FROM chatrooms WHERE invite_code = ?");
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $invite_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid invite code']);
        $stmt->close();
        exit;
    }
    
    $room = $result->fetch_assoc();
    $room_id = (int)$room['id'];
    $stmt->close();
    
    // Check if user is already in a room
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
    
    $stmt = $conn->prepare("SELECT room_id FROM chatroom_users WHERE user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("s", $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $current_room = $result->fetch_assoc();
            if ((int)$current_room['room_id'] === $room_id) {
                $_SESSION['room_id'] = $room_id;
                echo json_encode(['status' => 'success', 'message' => 'Already in room']);
                $stmt->close();
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'You are already in another room. Please leave it first.']);
                $stmt->close();
                exit;
            }
        }
        $stmt->close();
    }
    
    // Check room capacity
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chatroom_users WHERE room_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count_data = $result->fetch_assoc();
        $current_users = (int)$count_data['count'];
        $stmt->close();
        
        $capacity_stmt = $conn->prepare("SELECT capacity FROM chatrooms WHERE id = ?");
        if ($capacity_stmt) {
            $capacity_stmt->bind_param("i", $room_id);
            $capacity_stmt->execute();
            $capacity_result = $capacity_stmt->get_result();
            $room_data = $capacity_result->fetch_assoc();
            $capacity = (int)$room_data['capacity'];
            $capacity_stmt->close();
            
            if ($current_users >= $capacity) {
                echo json_encode(['status' => 'error', 'message' => 'Room is full']);
                exit;
            }
        }
    }
    
    // Get all required user columns
    $user_columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    $user_columns = [];
    while ($row = $user_columns_query->fetch_assoc()) {
        $user_columns[] = $row['Field'];
    }
    
    // Build INSERT query
    $insert_fields = ['room_id', 'user_id_string'];
    $insert_values = ['?', '?'];
    $param_types = 'is';
    $param_values = [$room_id, $user_id_string];
    
    // Add optional fields if they exist
    $optional_fields = [
        'user_id' => ['i', $_SESSION['user']['id'] ?? null],
        'username' => ['s', $_SESSION['user']['username'] ?? null],
        'guest_name' => ['s', $_SESSION['user']['name'] ?? null],
        'avatar' => ['s', $_SESSION['user']['avatar'] ?? 'default_avatar.jpg'],
        'guest_avatar' => ['s', $_SESSION['user']['avatar'] ?? 'default_avatar.jpg'],
        'ip_address' => ['s', $_SERVER['REMOTE_ADDR']],
        'color' => ['s', $_SESSION['user']['color'] ?? '#ffffff'],
        'avatar_hue' => ['i', $_SESSION['user']['avatar_hue'] ?? 0],
        'avatar_saturation' => ['i', $_SESSION['user']['avatar_saturation'] ?? 100],
        'bubble_hue' => ['i', $_SESSION['user']['bubble_hue'] ?? 0],
        'bubble_saturation' => ['i', $_SESSION['user']['bubble_saturation'] ?? 100]
    ];
    
    foreach ($optional_fields as $field => $data) {
        if (in_array($field, $user_columns)) {
            $insert_fields[] = $field;
            $insert_values[] = '?';
            $param_types .= $data[0];
            $param_values[] = $data[1];
        }
    }
    
    $insert_sql = "INSERT INTO chatroom_users (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_values) . ")";
    
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to join room: ' . $stmt->error);
    }
    $stmt->close();
    
    // Set session room_id
    $_SESSION['room_id'] = $room_id;
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Successfully joined room via invite',
        'room_id' => $room_id
    ]);
    
} catch (Exception $e) {
    error_log("Join room by invite error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to join room: ' . $e->getMessage()]);
}

$conn->close();
?>
<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if ($room_id <= 0) {
    error_log("Invalid room_id in get_room_users.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

error_log("Fetching users for room_id=$room_id"); // Debug

try {
    // Check what columns exist in chatroom_users table
    $columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    $available_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Build query based on available columns
    $select_fields = ['cu.user_id_string', 'cu.is_host'];
    $joins = '';
    
    if (in_array('user_id', $available_columns)) {
        $select_fields[] = 'cu.user_id';
        $joins = 'LEFT JOIN users u ON cu.user_id = u.id';
        $select_fields[] = 'u.username';
        $select_fields[] = 'u.is_admin';
        $select_fields[] = 'u.avatar as user_avatar';
    }
    
    if (in_array('guest_name', $available_columns)) {
        $select_fields[] = 'cu.guest_name';
    }
    
    if (in_array('guest_avatar', $available_columns)) {
        $select_fields[] = 'cu.guest_avatar';
    }
    
    if (in_array('avatar', $available_columns)) {
        $select_fields[] = 'cu.avatar';
    }
    
    if (in_array('username', $available_columns)) {
        $select_fields[] = 'cu.username as chatroom_username';
    }
    
    $sql = "SELECT " . implode(', ', $select_fields) . " FROM chatroom_users cu " . $joins . " WHERE cu.room_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in get_room_users.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Determine the best name to use
        $display_name = 'Unknown';
        if (!empty($row['username'])) {
            $display_name = $row['username'];
        } elseif (!empty($row['chatroom_username'])) {
            $display_name = $row['chatroom_username'];
        } elseif (!empty($row['guest_name'])) {
            $display_name = $row['guest_name'];
        }
        
        // Determine the best avatar to use
        $avatar = 'default_avatar.jpg';
        if (!empty($row['user_avatar'])) {
            $avatar = $row['user_avatar'];
        } elseif (!empty($row['guest_avatar'])) {
            $avatar = $row['guest_avatar'];
        } elseif (!empty($row['avatar'])) {
            $avatar = $row['avatar'];
        }
        
        $user_data = [
            'user_id_string' => $row['user_id_string'],
            'display_name' => $display_name,
            'avatar' => $avatar,
            'is_host' => (int)$row['is_host'],
            'is_admin' => isset($row['is_admin']) ? (int)$row['is_admin'] : 0,
            'user_type' => isset($row['user_id']) && $row['user_id'] ? 'registered' : 'guest'
        ];
        
        // Include original fields for compatibility
        if (isset($row['username'])) {
            $user_data['username'] = $row['username'];
        }
        if (isset($row['guest_name'])) {
            $user_data['guest_name'] = $row['guest_name'];
        }
        if (isset($row['user_id'])) {
            $user_data['user_id'] = $row['user_id'];
        }
        
        $users[] = $user_data;
    }
    
    $stmt->close();
    
    error_log("Retrieved " . count($users) . " users for room_id=$room_id"); // Debug
    echo json_encode($users);
    
} catch (Exception $e) {
    error_log("Error in get_room_users.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load users']);
}

$conn->close();
?>
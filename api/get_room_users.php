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

error_log("Fetching users for room_id=$room_id");

try {
    // Ensure avatar customization columns exist in chatroom_users
    $check_cu_hue = $conn->query("SHOW COLUMNS FROM chatroom_users LIKE 'avatar_hue'");
    if ($check_cu_hue->num_rows === 0) {
        $conn->query("ALTER TABLE chatroom_users ADD COLUMN avatar_hue INT DEFAULT 0");
    }

    $check_cu_sat = $conn->query("SHOW COLUMNS FROM chatroom_users LIKE 'avatar_saturation'");
    if ($check_cu_sat->num_rows === 0) {
        $conn->query("ALTER TABLE chatroom_users ADD COLUMN avatar_saturation INT DEFAULT 100");
    }

    // Sync avatar customization from users table for registered users, global_users for guests
    $sync_stmt = $conn->prepare("
        UPDATE chatroom_users cu 
        LEFT JOIN users u ON cu.user_id = u.id
        LEFT JOIN global_users gu ON cu.user_id_string = gu.user_id_string 
        SET cu.avatar_hue = COALESCE(u.avatar_hue, gu.avatar_hue, 0), 
            cu.avatar_saturation = COALESCE(u.avatar_saturation, gu.avatar_saturation, 100),
            cu.avatar = COALESCE(u.avatar, cu.guest_avatar, cu.avatar, 'default_avatar.jpg')
        WHERE cu.room_id = ?
    ");
    if ($sync_stmt) {
        $sync_stmt->bind_param("i", $room_id);
        $sync_stmt->execute();
        $sync_stmt->close();
    }

    // FIXED QUERY: Better logic to identify registered vs guest users
    $sql = "
        SELECT 
            cu.user_id_string,
            cu.user_id,
            cu.is_host,
            cu.guest_name,
            cu.guest_avatar,
            cu.avatar,
            cu.username as chatroom_username,
            cu.avatar_hue,
            cu.avatar_saturation,
            u.username,
            u.is_admin,
            u.avatar as user_avatar,
            u.avatar_hue as user_avatar_hue,
            u.avatar_saturation as user_avatar_saturation,
            -- Determine if user is registered by checking multiple conditions
            CASE 
                WHEN u.id IS NOT NULL THEN u.id
                WHEN cu.user_id IS NOT NULL AND cu.user_id > 0 THEN cu.user_id
                ELSE NULL 
            END as final_user_id,
            CASE 
                WHEN u.id IS NOT NULL THEN 'registered'
                WHEN cu.user_id IS NOT NULL AND cu.user_id > 0 THEN 'registered'
                ELSE 'guest' 
            END as user_type
        FROM chatroom_users cu 
        LEFT JOIN users u ON (cu.user_id = u.id OR (cu.username IS NOT NULL AND cu.username = u.username))
        WHERE cu.room_id = ?
    ";
    
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
        
        $users[] = [
            'user_id_string' => $row['user_id_string'],
            'display_name' => $display_name,
            'avatar' => $avatar,
            'is_host' => (int)$row['is_host'],
            'is_admin' => isset($row['is_admin']) ? (int)$row['is_admin'] : 0,
            'user_type' => $row['user_type'], // This will be 'registered' or 'guest'
            'username' => $row['username'],
            'guest_name' => $row['guest_name'],
            'user_id' => $row['final_user_id'], // Use the calculated user_id
            'avatar_hue' => (int)($row['avatar_hue'] ?? 0),
            'avatar_saturation' => (int)($row['avatar_saturation'] ?? 100)
        ];
    }
    
    $stmt->close();
    
    error_log("Retrieved " . count($users) . " users for room_id=$room_id");
    echo json_encode($users);
    
} catch (Exception $e) {
    error_log("Error in get_room_users.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load users']);
}

$conn->close();
?>
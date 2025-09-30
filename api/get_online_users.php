<?php
// api/get_online_users.php - Get truly active users with automatic logout for inactive users
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

include '../db_connect.php';

try {
    // Active threshold in minutes (30 minutes)
    $active_threshold = 20;
    
    // First, identify and logout users who exceed the threshold (exclude ghost mode users from auto-logout)
    $inactive_users_sql = "SELECT gu.user_id_string, gu.username, gu.guest_name 
        FROM global_users gu
        LEFT JOIN users u ON gu.username = u.username
        LEFT JOIN chatroom_users cu ON gu.user_id_string = cu.user_id_string
        WHERE gu.last_activity < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        AND cu.user_id_string IS NULL  -- This ensures they're NOT in any room (lounge only)
        AND COALESCE(u.ghost_mode, 0) = 0";
    
    $inactive_stmt = $conn->prepare($inactive_users_sql);
    $inactive_stmt->bind_param("i", $active_threshold);
    $inactive_stmt->execute();
    $inactive_result = $inactive_stmt->get_result();
    
    $logged_out_users = [];
    $current_user_logged_out = false;
    $current_user_id = $_SESSION['user']['user_id'] ?? '';
    
    // Begin transaction for cleanup
    $conn->begin_transaction();
    
    while ($inactive_user = $inactive_result->fetch_assoc()) {
        $user_id_string = $inactive_user['user_id_string'];
        $display_name = $inactive_user['username'] ?: $inactive_user['guest_name'] ?: 'Unknown User';
        
        // Check if this is the current user
        if ($user_id_string === $current_user_id) {
            $current_user_logged_out = true;
        }
        
        // Remove from chatroom_users (with disconnect messages)
        $remove_from_rooms = $conn->prepare("
            SELECT cu.room_id, c.name as room_name 
            FROM chatroom_users cu 
            JOIN chatrooms c ON cu.room_id = c.id 
            WHERE cu.user_id_string = ?
        ");
        $remove_from_rooms->bind_param("s", $user_id_string);
        $remove_from_rooms->execute();
        $rooms_result = $remove_from_rooms->get_result();
        
        while ($room = $rooms_result->fetch_assoc()) {
            $room_id = $room['room_id'];
            
            // Add disconnect message to room
            $disconnect_message = "$display_name disconnected due to inactivity.";
            $add_message = $conn->prepare("
                INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) 
                VALUES (?, '', ?, 1, NOW(), 'disconnect.png', 'system')
            ");
            $add_message->bind_param("is", $room_id, $disconnect_message);
            $add_message->execute();
            $add_message->close();
        }
        $remove_from_rooms->close();
        
        // Remove from chatroom_users
        $delete_chatroom = $conn->prepare("DELETE FROM chatroom_users WHERE user_id_string = ?");
        $delete_chatroom->bind_param("s", $user_id_string);
        $delete_chatroom->execute();
        $delete_chatroom->close();
        
        // Remove from global_users
        $delete_global = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
        $delete_global->bind_param("s", $user_id_string);
        $delete_global->execute();
        $delete_global->close();
        
        $logged_out_users[] = $display_name;
    }
    
    $inactive_stmt->close();
    $conn->commit();
    
    // If current user was logged out, clear session and return logout signal
    if ($current_user_logged_out) {
        // Preserve firewall session while clearing user data
        $preserve_firewall = $_SESSION['firewall_passed'] ?? false;
        unset($_SESSION['user']);
        unset($_SESSION['room_id']);
        unset($_SESSION['pending_invite']);
        if ($preserve_firewall) {
            $_SESSION['firewall_passed'] = true;
        }
        
        // Return special logout response
        echo json_encode(['__logout_required__' => true]);
        exit;
    }
    
    // Log the logout activity
    if (!empty($logged_out_users)) {
        error_log("Auto-logged out " . count($logged_out_users) . " inactive users: " . implode(', ', $logged_out_users));
    }
    
    // Now get the remaining active users (exclude ghost mode users from display)
    $columns_check = $conn->query("SHOW COLUMNS FROM global_users");
    $available_columns = [];
    while ($row = $columns_check->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Build the select fields
    $select_fields = [
        'gu.user_id_string',
        'gu.username', 
        'gu.guest_name', 
        'gu.avatar', 
        'gu.guest_avatar',
        'gu.is_admin',
        'gu.color',
        'gu.last_activity',
         "(
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'name', si.name,
                'rarity', si.rarity,
                'icon', si.icon
            )
        )
        FROM users u2
        JOIN user_inventory ui ON u2.id = ui.user_id
        JOIN shop_items si ON ui.item_id = si.item_id
        WHERE u2.username = gu.username
        AND ui.is_equipped = 1 
        AND si.type = 'title'
        ORDER BY FIELD(si.rarity, 'legendary', 'strange', 'rare', 'common')
        LIMIT 5
    ) as equipped_titles",
        'TIMESTAMPDIFF(SECOND, gu.last_activity, NOW()) as seconds_since_activity'
    ];
    
    // Add is_moderator if it exists
    if (in_array('is_moderator', $available_columns)) {
        $select_fields[] = 'gu.is_moderator';
    } else {
        $select_fields[] = '0 as is_moderator';
    }
    
    // Add avatar customization fields if they exist
    if (in_array('avatar_hue', $available_columns)) {
        $select_fields[] = 'gu.avatar_hue';
    } else {
        $select_fields[] = '0 as avatar_hue';
    }
    
    if (in_array('avatar_saturation', $available_columns)) {
        $select_fields[] = 'gu.avatar_saturation';
    } else {
        $select_fields[] = '100 as avatar_saturation';
    }
    
    $sql = "SELECT " . implode(', ', $select_fields) . "
            FROM global_users gu
            LEFT JOIN users u ON gu.username = u.username
            WHERE gu.last_activity >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND COALESCE(u.ghost_mode, 0) = 0
            ORDER BY gu.last_activity DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $active_threshold);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Additional filtering: skip users with empty or null user_id_string
        if (empty($row['user_id_string'])) {
            continue;
        }
        
        // Determine display name preference
        $display_name = '';
        if (!empty($row['guest_name'])) {
            $display_name = $row['guest_name'];
        } elseif (!empty($row['username'])) {
            $display_name = $row['username'];
        } else {
            $display_name = 'Unknown User';
        }
        
        // Determine avatar preference
        $avatar = 'default_avatar.jpg';
        if (!empty($row['avatar'])) {
            $avatar = $row['avatar'];
        } elseif (!empty($row['guest_avatar'])) {
            $avatar = $row['guest_avatar'];
        }
        
        // Calculate activity status
        $seconds_since = (int)$row['seconds_since_activity'];
        $activity_status = 'online';
        if ($seconds_since > 900) {
            $activity_status = 'away';
        }
        
        $users[] = [
            'user_id_string' => $row['user_id_string'],
            'username' => $row['username'],
            'guest_name' => $row['guest_name'],
            'display_name' => $display_name,
            'avatar' => $avatar,
            'guest_avatar' => $row['guest_avatar'],
            'is_admin' => (int)$row['is_admin'],
            'is_moderator' => (int)($row['is_moderator'] ?? 0),
            'color' => $row['color'] ?? 'black',
            'last_activity' => $row['last_activity'],
            'seconds_since_activity' => $seconds_since,
            'activity_status' => $activity_status,
            'equipped_titles' => $equipped_titles,
            'avatar_hue' => (int)($row['avatar_hue'] ?? 0),
            
            'avatar_saturation' => (int)($row['avatar_saturation'] ?? 100)
        ];
    }
    
    $stmt->close();
    
    // Log the count for debugging
    $log_message = "Online users query returned " . count($users) . " active users (threshold: {$active_threshold} minutes, ghost mode excluded)";
    if (!empty($logged_out_users)) {
        $log_message .= ". Auto-logged out: " . count($logged_out_users) . " users";
    }
    error_log($log_message);
    
    echo json_encode($users);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    error_log("Error getting online users: " . $e->getMessage());
    echo json_encode([]);
}
?>
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
    
    // === Load all users currently present in chatroom_users (ignore active threshold) ===
    $cu_columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    $cu_columns = [];
    while ($r = $cu_columns_query->fetch_assoc()) {
        $cu_columns[] = $r['Field'];
    }

    // Build select fields for chatroom_users that roughly match the global_users output
    $cu_select = [
        'cu.user_id',
        'cu.user_id_string',
        'cu.username as cu_username',
        'u.username as username',
        'cu.guest_name',
        "u.avatar as avatar",
        'cu.avatar as guest_avatar',
        "COALESCE(u.is_admin, 0) as is_admin",
        "COALESCE(u.is_moderator, 0) as is_moderator",
        "COALESCE(cu.color, 'black') as color",
        'cu.last_activity',
        'TIMESTAMPDIFF(SECOND, cu.last_activity, NOW()) as seconds_since_activity'
    ];

    // Add avatar customization fields if available on chatroom_users
    if (in_array('avatar_hue', $cu_columns)) {
        $cu_select[] = 'cu.avatar_hue';
    } else {
        $cu_select[] = '0 as avatar_hue';
    }
    if (in_array('avatar_saturation', $cu_columns)) {
        $cu_select[] = 'cu.avatar_saturation';
    } else {
        $cu_select[] = '100 as avatar_saturation';
    }

    // Equipped titles for registered users (uses registered username if present)
    $cu_select[] = "(
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT('name', si.name, 'rarity', si.rarity, 'icon', si.icon)
        )
        FROM users u2
        JOIN user_inventory ui ON u2.id = ui.user_id
        JOIN shop_items si ON ui.item_id = si.item_id
        WHERE u2.username = u.username
        AND ui.is_equipped = 1
        AND si.type = 'title'
        ORDER BY FIELD(si.rarity, 'legendary', 'strange', 'rare', 'common')
        LIMIT 5
    ) as equipped_titles";

    $sql_cu = "SELECT " . implode(', ', $cu_select) . " FROM chatroom_users cu LEFT JOIN users u ON cu.user_id = u.id ORDER BY cu.last_activity DESC";
    $cu_stmt = $conn->prepare($sql_cu);
    $cu_stmt->execute();
    $cu_result = $cu_stmt->get_result();

    // Use associative map keyed by user_id_string to prevent duplicates when merging with global_users
    $users_map = [];
    while ($row = $cu_result->fetch_assoc()) {
        if (empty($row['user_id_string'])) continue;

        // Determine registered vs guest
        $is_registered = !empty($row['user_id']) && $row['user_id'] > 0;
        $username = $is_registered ? ($row['username'] ?? '') : ($row['cu_username'] ?? '');
        $guest_name = $row['guest_name'] ?? null;

        $display_name = $username ?: $guest_name ?: 'Unknown User';

        $avatar = 'default_avatar.jpg';
        if (!empty($row['avatar'])) {
            $avatar = $row['avatar'];
        } elseif (!empty($row['guest_avatar'])) {
            $avatar = $row['guest_avatar'];
        }

        $seconds_since = (int)($row['seconds_since_activity'] ?? 0);
        $activity_status = $seconds_since > 900 ? 'away' : 'online';

        $users_map[$row['user_id_string']] = [
            'user_id_string' => $row['user_id_string'],
            'username' => $username,
            'guest_name' => $guest_name,
            'display_name' => $display_name,
            'avatar' => $avatar,
            'guest_avatar' => $row['guest_avatar'] ?? null,
            'is_admin' => (int)($row['is_admin'] ?? 0),
            'is_moderator' => (int)($row['is_moderator'] ?? 0),
            'color' => $row['color'] ?? 'black',
            'last_activity' => $row['last_activity'],
            'seconds_since_activity' => $seconds_since,
            'activity_status' => $activity_status,
            'equipped_titles' => json_decode($row['equipped_titles'] ?? '[]', true) ?? [],
            'avatar_hue' => (int)($row['avatar_hue'] ?? 0),
            'avatar_saturation' => (int)($row['avatar_saturation'] ?? 100)
        ];
    }
    $cu_stmt->close();

    // === Now load global_users (existing behavior) and merge without duplicates ===
    $columns_check = $conn->query("SHOW COLUMNS FROM global_users");
    $available_columns = [];
    while ($row = $columns_check->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }

    // Build the select fields (unchanged)
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

    // Merge global users into map if not already present
    while ($row = $result->fetch_assoc()) {
        if (empty($row['user_id_string'])) continue;
        if (isset($users_map[$row['user_id_string']])) {
            // already present from chatroom_users, skip
            continue;
        }

        $display_name = '';
        if (!empty($row['guest_name'])) {
            $display_name = $row['guest_name'];
        } elseif (!empty($row['username'])) {
            $display_name = $row['username'];
        } else {
            $display_name = 'Unknown User';
        }

        $avatar = 'default_avatar.jpg';
        if (!empty($row['avatar'])) {
            $avatar = $row['avatar'];
        } elseif (!empty($row['guest_avatar'])) {
            $avatar = $row['guest_avatar'];
        }

        $seconds_since = (int)$row['seconds_since_activity'];
        $activity_status = 'online';
        if ($seconds_since > 900) {
            $activity_status = 'away';
        }

        $users_map[$row['user_id_string']] = [
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
            'equipped_titles' => json_decode($row['equipped_titles'] ?? '[]', true) ?? [],
            'avatar_hue' => (int)($row['avatar_hue'] ?? 0),
            'avatar_saturation' => (int)($row['avatar_saturation'] ?? 100)
        ];
    }

    $stmt->close();

    // Final users array is values of the map
    $users = array_values($users_map);

    // Log merged counts for debugging
    $log_message = "Online users compiled: " . count($users) . " users (chatroom_users + active global_users merge)";
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
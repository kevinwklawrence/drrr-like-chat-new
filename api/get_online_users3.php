<?php
// api/get_online_users.php - Fixed to only disconnect inactive LOUNGE users
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

include '../db_connect.php';

try {
    // Active threshold in minutes (30 minutes for lounge users)
    $active_threshold = 30;
    
    // IMPORTANT: Only find inactive users who are IN THE LOUNGE (not in any room)
    // Users in the lounge = in global_users but NOT in chatroom_users
    $inactive_lounge_users_sql = "
        SELECT gu.user_id_string, gu.username, gu.guest_name 
        FROM global_users gu
        LEFT JOIN users u ON gu.username = u.username
        LEFT JOIN chatroom_users cu ON gu.user_id_string = cu.user_id_string
        WHERE gu.last_activity < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        AND cu.user_id_string IS NULL  -- This ensures they're NOT in any room (lounge only)
        AND COALESCE(u.ghost_mode, 0) = 0";
    
    $inactive_stmt = $conn->prepare($inactive_lounge_users_sql);
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
        $display_name = $inactive_user['username'] ?: $inactive_user['guest_name'] ?: 'test';
        
        // Check if this is the current user
        if ($user_id_string === $current_user_id) {
            $current_user_logged_out = true;
        }
        
        // Remove from global_users (they're in lounge, not in any room)
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
        error_log("Auto-logged out " . count($logged_out_users) . " inactive LOUNGE users: " . implode(', ', $logged_out_users));
    }
    
    // Now get active users for display
    $active_users_sql = "
        SELECT 
            gu.user_id_string,
            gu.username,
            gu.guest_name,
            gu.avatar,
            gu.avatar_hue,
            gu.avatar_saturation,
            gu.color,
            gu.is_admin,
            gu.last_activity,
            TIMESTAMPDIFF(SECOND, gu.last_activity, NOW()) as seconds_inactive,
            CASE 
                WHEN cu.user_id_string IS NOT NULL THEN 'in_room'
                ELSE 'in_lounge'
            END as location,
            c.name as room_name
        FROM global_users gu
        LEFT JOIN users u ON gu.username = u.username
        LEFT JOIN chatroom_users cu ON gu.user_id_string = cu.user_id_string
        LEFT JOIN chatrooms c ON cu.room_id = c.id
        WHERE COALESCE(u.ghost_mode, 0) = 0
        ORDER BY gu.last_activity DESC";
    
    $stmt = $conn->prepare($active_users_sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $online_users = [];
    while ($row = $result->fetch_assoc()) {
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
            $display_name = 'test2';
        }
        $online_users[] = [
            'user_id' => $row['user_id_string'],
            'display_name' => $display_name,
            'avatar' => $row['avatar'],
            'avatar_hue' => $row['avatar_hue'],
            'avatar_saturation' => $row['avatar_saturation'],
            'color' => $row['color'],
            'is_admin' => $row['is_admin'],
            'last_activity' => $row['last_activity'],
            'seconds_inactive' => $row['seconds_inactive'],
            'location' => $row['location'],
            'room_name' => $row['room_name']
        ];
    }
    
    echo json_encode($online_users);
    
} catch (Exception $e) {
    error_log("Get online users error: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
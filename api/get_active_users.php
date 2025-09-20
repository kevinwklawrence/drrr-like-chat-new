<?php
// api/get_active_users.php - Updated to show all users from global_users
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/activity_config.php';

try {
    // Ensure required columns exist
    ensureActivityColumns($conn);
    
    // Get all users who are currently in rooms (no activity filtering)
    $room_users_sql = "
        SELECT DISTINCT
            gu.user_id_string,
            gu.username,
            gu.guest_name,
            gu.avatar,
            gu.avatar_hue,
            gu.avatar_saturation,
            gu.color,
            gu.is_admin,
            gu.last_activity,
            'in_room' as status,
            c.name as room_name,
            cu.is_host,
            cu.is_afk,
            TIMESTAMPDIFF(SECOND, gu.last_activity, NOW()) as seconds_inactive
        FROM global_users gu
        JOIN chatroom_users cu ON gu.user_id_string = cu.user_id_string
        JOIN chatrooms c ON cu.room_id = c.id
        ORDER BY gu.last_activity DESC
    ";
    
    $room_stmt = $conn->prepare($room_users_sql);
    if (!$room_stmt) {
        throw new Exception('Failed to prepare room users query: ' . $conn->error);
    }
    
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    
    $room_users = [];
    while ($row = $room_result->fetch_assoc()) {
        $room_users[] = $row;
    }
    $room_stmt->close();
    
    // CHANGED: Get ALL users from global_users table who are NOT in rooms (no activity filtering)
    $lounge_users_sql = "
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
            'in_lounge' as status,
            NULL as room_name,
            0 as is_host,
            0 as is_afk,
            TIMESTAMPDIFF(SECOND, gu.last_activity, NOW()) as seconds_inactive
        FROM global_users gu
        LEFT JOIN chatroom_users cu ON gu.user_id_string = cu.user_id_string
        WHERE cu.user_id_string IS NULL
        ORDER BY gu.last_activity DESC
    ";
    
    $lounge_stmt = $conn->prepare($lounge_users_sql);
    if (!$lounge_stmt) {
        throw new Exception('Failed to prepare lounge users query: ' . $conn->error);
    }
    
    $lounge_stmt->execute();
    $lounge_result = $lounge_stmt->get_result();
    
    $lounge_users = [];
    while ($row = $lounge_result->fetch_assoc()) {
        $lounge_users[] = $row;
    }
    $lounge_stmt->close();
    
    // Combine and categorize users
    $active_users = [
        'room_users' => $room_users,
        'lounge_users' => $lounge_users,
        'total_active' => count($room_users) + count($lounge_users),
        'stats' => [
            'users_in_rooms' => count($room_users),
            'users_in_lounge' => count($lounge_users),
            'total_active_sessions' => count($room_users) + count($lounge_users)
        ],
        'configuration' => [
            'session_timeout_minutes' => SESSION_TIMEOUT / 60,
            'afk_timeout_minutes' => AFK_TIMEOUT / 60,
            'disconnect_timeout_minutes' => DISCONNECT_TIMEOUT / 60
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    logActivity("User list: " . count($room_users) . " in rooms, " . count($lounge_users) . " in lounge (ALL global_users shown regardless of activity)");
    
    echo json_encode($active_users);
    
} catch (Exception $e) {
    logActivity("Get active users error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to get active users: ' . $e->getMessage(),
        'room_users' => [],
        'lounge_users' => [],
        'total_active' => 0
    ]);
}

$conn->close();
?>
<?php
// api/get_all_user_activity.php - Get activity data for all users across all rooms
session_start();
header('Content-Type: application/json');

// Only allow this for logged-in users (basic security)
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

try {
    // Get all users with their activity status
    $users_sql = "
        SELECT 
            cu.room_id,
            cu.user_id_string,
            cu.guest_name,
            cu.username,
            cu.is_host,
            cu.last_activity,
            c.name as room_name,
            COALESCE(TIMESTAMPDIFF(MINUTE, cu.last_activity, NOW()), 0) as minutes_inactive,
            CASE 
                WHEN cu.last_activity IS NULL THEN 'never'
                WHEN TIMESTAMPDIFF(MINUTE, cu.last_activity, NOW()) > 15 THEN 'should_disconnect'
                WHEN TIMESTAMPDIFF(MINUTE, cu.last_activity, NOW()) > 10 THEN 'warning'
                ELSE 'active'
            END as activity_status
        FROM chatroom_users cu
        JOIN chatrooms c ON cu.room_id = c.id
        ORDER BY cu.room_id, cu.is_host DESC, cu.last_activity DESC
    ";
    
    $users_result = $conn->query($users_sql);
    $users = [];
    
    if ($users_result) {
        while ($row = $users_result->fetch_assoc()) {
            $users[] = [
                'room_id' => (int)$row['room_id'],
                'room_name' => $row['room_name'],
                'user_id_string' => $row['user_id_string'],
                'username' => $row['username'],
                'guest_name' => $row['guest_name'],
                'display_name' => $row['username'] ?: $row['guest_name'] ?: 'Unknown',
                'is_host' => (int)$row['is_host'],
                'last_activity' => $row['last_activity'],
                'minutes_inactive' => (float)$row['minutes_inactive'],
                'activity_status' => $row['activity_status']
            ];
        }
    }
    
    // Get room summary data
    $rooms_sql = "
        SELECT 
            c.id,
            c.name,
            c.created_at,
            COUNT(cu.id) as user_count,
            MAX(cu.last_activity) as last_room_activity
        FROM chatrooms c
        LEFT JOIN chatroom_users cu ON c.id = cu.room_id
        GROUP BY c.id, c.name, c.created_at
        ORDER BY user_count DESC, c.name
    ";
    
    $rooms_result = $conn->query($rooms_sql);
    $rooms = [];
    
    if ($rooms_result) {
        while ($row = $rooms_result->fetch_assoc()) {
            $rooms[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'user_count' => (int)$row['user_count'],
                'created_at' => $row['created_at'],
                'last_activity' => $row['last_room_activity']
            ];
        }
    }
    
    // Calculate summary statistics
    $total_users = count($users);
    $active_users = count(array_filter($users, function($u) { return $u['activity_status'] === 'active'; }));
    $warning_users = count(array_filter($users, function($u) { return $u['activity_status'] === 'warning'; }));
    $should_disconnect = count(array_filter($users, function($u) { return $u['activity_status'] === 'should_disconnect'; }));
    $total_rooms = count($rooms);
    $active_rooms = count(array_filter($rooms, function($r) { return $r['user_count'] > 0; }));
    
    echo json_encode([
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'users' => $users,
            'rooms' => $rooms,
            'summary' => [
                'total_users' => $total_users,
                'active_users' => $active_users,
                'warning_users' => $warning_users,
                'should_disconnect' => $should_disconnect,
                'total_rooms' => $total_rooms,
                'active_rooms' => $active_rooms
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get all user activity error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to get user activity data: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
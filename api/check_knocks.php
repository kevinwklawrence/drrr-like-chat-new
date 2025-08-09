<?php
session_start();
header('Content-Type: application/json');

// Debug logging
error_log("CHECK_KNOCKS: Starting check_knocks.php");

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    error_log("CHECK_KNOCKS: No user in session");
    echo json_encode([]);
    exit;
}

include '../db_connect.php';

$current_user_id_string = $_SESSION['user']['user_id'] ?? '';
error_log("CHECK_KNOCKS: User ID string: $current_user_id_string");

if (empty($current_user_id_string)) {
    error_log("CHECK_KNOCKS: Empty user ID string");
    echo json_encode([]);
    exit;
}

try {
    // Check if user is host of any room and get pending knocks
    $stmt = $conn->prepare("
        SELECT rk.*, c.name as room_name 
        FROM room_knocks rk 
        JOIN chatrooms c ON rk.room_id = c.id 
        JOIN chatroom_users cu ON c.id = cu.room_id 
        WHERE cu.user_id_string = ? 
        AND cu.is_host = 1 
        AND rk.status = 'pending'
        AND rk.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY rk.created_at DESC
    ");
    
    if (!$stmt) {
        error_log("CHECK_KNOCKS: Prepare failed: " . $conn->error);
        echo json_encode([]);
        exit;
    }
    
    $stmt->bind_param("s", $current_user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $knocks = [];
    while ($row = $result->fetch_assoc()) {
        $knock = [
            'id' => (int)$row['id'],
            'room_id' => (int)$row['room_id'],
            'room_name' => $row['room_name'],
            'user_id_string' => $row['user_id_string'],
            'username' => $row['username'],
            'guest_name' => $row['guest_name'],
            'avatar' => $row['avatar'] ?: 'default_avatar.jpg',
            'created_at' => $row['created_at']
        ];
        $knocks[] = $knock;
        error_log("CHECK_KNOCKS: Found knock ID {$row['id']} from user {$row['user_id_string']} for room {$row['room_name']}");
    }
    
    $stmt->close();
    
    error_log("CHECK_KNOCKS: Returning " . count($knocks) . " knocks");
    echo json_encode($knocks);
    
} catch (Exception $e) {
    error_log("CHECK_KNOCKS: Exception: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
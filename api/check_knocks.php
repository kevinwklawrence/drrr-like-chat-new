<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

include '../db_connect.php';

$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($current_user_id_string)) {
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
        echo json_encode([]);
        exit;
    }
    
    $stmt->bind_param("s", $current_user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $knocks = [];
    while ($row = $result->fetch_assoc()) {
        $knocks[] = [
            'id' => (int)$row['id'],
            'room_id' => (int)$row['room_id'],
            'room_name' => $row['room_name'],
            'user_id_string' => $row['user_id_string'],
            'username' => $row['username'],
            'guest_name' => $row['guest_name'],
            'avatar' => $row['avatar'],
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    echo json_encode($knocks);
    
} catch (Exception $e) {
    error_log("Check knocks error: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
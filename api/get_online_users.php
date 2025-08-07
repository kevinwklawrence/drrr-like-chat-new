<?php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

try {
    // Get users active in the last 10 minutes
    $stmt = $conn->prepare("
        SELECT user_id_string, username, guest_name, avatar, guest_avatar, is_admin 
        FROM global_users 
        WHERE last_activity > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY last_activity DESC
        LIMIT 50
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'user_id_string' => $row['user_id_string'],
            'username' => $row['username'],
            'guest_name' => $row['guest_name'],
            'avatar' => $row['avatar'] ?: $row['guest_avatar'],
            'is_admin' => (int)$row['is_admin']
        ];
    }
    
    $stmt->close();
    echo json_encode($users);
    
} catch (Exception $e) {
    error_log("Get online users error: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
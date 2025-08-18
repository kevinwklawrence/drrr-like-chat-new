<?php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

$user_id = (int)($_GET['user_id'] ?? 0);
$current_user_id = $_SESSION['user']['id'] ?? 0;

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, username, avatar, color, bio, status, hyperlinks, cover_photo, avatar_hue, avatar_saturation FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $user['hyperlinks'] = $user['hyperlinks'] ? json_decode($user['hyperlinks'], true) : [];
    $stmt->close();
    
    // Check friendship status if current user is logged in and it's not themselves
    $user['friendship_status'] = 'none';
    if ($current_user_id > 0 && $current_user_id != $user_id) {
        $friend_stmt = $conn->prepare("
            SELECT status, 
                   CASE WHEN user_id = ? THEN 'sent' ELSE 'received' END as request_type
            FROM friends 
            WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $friend_stmt->bind_param("iiiii", $current_user_id, $current_user_id, $user_id, $user_id, $current_user_id);
        $friend_stmt->execute();
        $friend_result = $friend_stmt->get_result();
        
        if ($friend_result->num_rows > 0) {
            $friendship = $friend_result->fetch_assoc();
            if ($friendship['status'] === 'accepted') {
                $user['friendship_status'] = 'friends';
            } elseif ($friendship['status'] === 'pending') {
                $user['friendship_status'] = $friendship['request_type'] === 'sent' ? 'request_sent' : 'request_received';
            }
        }
        $friend_stmt->close();
    }
    
    echo json_encode(['status' => 'success', 'user' => $user]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$conn->close();
?>
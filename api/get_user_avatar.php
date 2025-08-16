<?php
session_start();
include '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['username'])) {
    $username = trim($_GET['username']);
    
    if (empty($username)) {
        echo json_encode(['status' => 'error', 'message' => 'Username required']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT custom_av, avatar_memory FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Determine avatar priority: custom_av > avatar_memory > default
        $avatar = null;
        if (!empty($user['custom_av'])) {
            $avatar = $user['custom_av'];
        } elseif (!empty($user['avatar_memory'])) {
            $avatar = $user['avatar_memory'];
        }
        
        echo json_encode([
            'status' => 'success',
            'avatar' => $avatar
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    $stmt->close();
    exit;
}
?>
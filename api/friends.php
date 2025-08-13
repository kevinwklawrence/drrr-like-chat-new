<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Only registered users can manage friends']);
    exit;
}

include '../db_connect.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user']['id'];

switch($action) {
    case 'add':
        $friend_username = $_POST['friend_username'] ?? '';
        if (empty($friend_username)) {
            echo json_encode(['status' => 'error', 'message' => 'Username required']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $friend_username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
            exit;
        }
        
        $friend_id = $result->fetch_assoc()['id'];
        $stmt->close();
        
        if ($friend_id == $user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot add yourself as friend']);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $friend_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Friend request sent']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Friend request already exists']);
        }
        $stmt->close();
        break;
        
    case 'accept':
        $friend_id = (int)$_POST['friend_id'];
        $stmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $friend_id, $user_id);
        $stmt->execute();
        
        // Add reverse friendship
        $stmt2 = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted') ON DUPLICATE KEY UPDATE status = 'accepted'");
        $stmt2->bind_param("ii", $user_id, $friend_id);
        $stmt2->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Friend request accepted']);
        break;
        
    case 'get':
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, f.status, 
               CASE WHEN f.user_id = ? THEN 'sent' ELSE 'received' END as request_type
        FROM friends f 
        JOIN users u ON (f.friend_id = u.id AND f.user_id = ?) OR (f.user_id = u.id AND f.friend_id = ?)
        WHERE (f.user_id = ? OR f.friend_id = ?) AND u.id != ?
    ");
    
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        break;
    }
    
    $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $friends = [];
    while ($row = $result->fetch_assoc()) {
        $friends[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'friends' => $friends]);
    break;
}

$conn->close();
?>
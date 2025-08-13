<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Only registered users can manage friends']);
    exit;
}

include '../db_connect.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user']['id'];

try {
    switch($action) {
        case 'add':
            $friend_username = trim($_POST['friend_username'] ?? '');
            
            if (empty($friend_username)) {
                echo json_encode(['status' => 'error', 'message' => 'Username required']);
                exit;
            }
            
            // Find the user
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
            
            // Check if friendship already exists
            $stmt = $conn->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existing = $result->fetch_assoc();
                if ($existing['status'] === 'accepted') {
                    echo json_encode(['status' => 'error', 'message' => 'Already friends']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Friend request already sent']);
                }
                exit;
            }
            $stmt->close();
            
            // Add friend request
            $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("ii", $user_id, $friend_id);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Friend request sent']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send friend request']);
            }
            $stmt->close();
            break;
            
        case 'accept':
    $request_id = (int)($_POST['friend_id'] ?? 0);
    
    if ($request_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request ID']);
        exit;
    }
    
    // First, get the friend request details
    $stmt = $conn->prepare("SELECT user_id, friend_id FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $request_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No pending friend request found']);
        exit;
    }
    
    $request_data = $result->fetch_assoc();
    $sender_id = $request_data['user_id'];
    $stmt->close();
    
    // Update the original request
    $stmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $stmt->close();
    
    // Add reverse friendship
    $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted') ON DUPLICATE KEY UPDATE status = 'accepted'");
    $stmt->bind_param("ii", $user_id, $sender_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'message' => 'Friend request accepted']);
    break;
            
        case 'get':
    $stmt = $conn->prepare("
        SELECT 
            MIN(f.id) as id,
            CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END as friend_user_id,
            u.username, 
            u.avatar, 
            f.status,
            CASE WHEN f.user_id = ? THEN 'sent' ELSE 'received' END as request_type
        FROM friends f 
        JOIN users u ON (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END = u.id)
        WHERE (f.user_id = ? OR f.friend_id = ?)
        GROUP BY 
            CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END,
            u.username, 
            u.avatar, 
            f.status
        ORDER BY MIN(f.created_at) DESC
    ");
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
            
        case 'remove':
            $friend_id = (int)($_POST['friend_id'] ?? 0);
            
            if ($friend_id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid friend ID']);
                exit;
            }
            
            // Remove both directions of friendship
            $stmt = $conn->prepare("DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'message' => 'Friend removed']);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Friends API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}

$conn->close();
?>
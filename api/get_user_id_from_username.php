<?php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

$username = trim($_GET['username'] ?? '');

if (empty($username)) {
    echo json_encode(['status' => 'error', 'message' => 'Username required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'user_id' => $user['id']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$conn->close();
?>
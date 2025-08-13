<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$user_id = (int)($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

$stmt = $conn->prepare("SELECT username, avatar, color FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'user' => [
        'username' => $user['username'],
        'avatar' => $user['avatar'],
        'color' => $user['color'] ?? 'blue'
    ]
]);

$conn->close();
?>
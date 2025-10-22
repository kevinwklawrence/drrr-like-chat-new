<?php
session_start();
header('Content-Type: application/json');

require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// Get show_dms_automatically setting from users table
$stmt = $conn->prepare("SELECT show_dms_automatically FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'status' => 'success',
        'show_dms_automatically' => (bool)$row['show_dms_automatically']
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not found'
    ]);
}

$stmt->close();
$conn->close();
?>

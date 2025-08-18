<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Only registered users can update profiles']);
    exit;
}

include '../db_connect.php';

$user_id = $_SESSION['user']['id'];
$bio = trim($_POST['bio'] ?? '');
$status = trim($_POST['status'] ?? '');
$hyperlinks = $_POST['hyperlinks'] ?? '[]';

// Validate hyperlinks JSON
$links = json_decode($hyperlinks, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid hyperlinks format']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE users SET bio = ?, status = ?, hyperlinks = ? WHERE id = ?");
    $stmt->bind_param("sssi", $bio, $status, $hyperlinks, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update profile']);
    }
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$conn->close();
?>
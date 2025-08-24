<?php
// === api/check_moderation_privileges.php ===
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode([
        'status' => 'success',
        'is_moderator' => false,
        'is_admin' => false
    ]);
    exit;
}

include '../db_connect.php';

$user_id = $_SESSION['user']['id'];

try {
    $stmt = $conn->prepare("SELECT is_moderator, is_admin FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        echo json_encode([
            'status' => 'success',
            'is_moderator' => (bool)$user_data['is_moderator'],
            'is_admin' => (bool)$user_data['is_admin'],
            'can_moderate' => (bool)($user_data['is_moderator'] || $user_data['is_admin'])
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'is_moderator' => false,
            'is_admin' => false
        ]);
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Check moderation privileges error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to check privileges',
        'is_moderator' => false,
        'is_admin' => false
    ]);
}

$conn->close();
?>
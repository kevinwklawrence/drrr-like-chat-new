<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

$user_id = $_GET['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            equipped_avatar_overlay,
            equipped_avatar_glow,
            equipped_bubble_effect
        FROM users
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $effects = $result->fetch_assoc();
        echo json_encode([
            'status' => 'success',
            'effects' => [
                'avatar_overlay' => $effects['equipped_avatar_overlay'],
                'avatar_glow' => $effects['equipped_avatar_glow'],
                'bubble_effect' => $effects['equipped_bubble_effect']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
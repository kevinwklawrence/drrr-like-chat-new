<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Must be logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];

try {
    $stmt = $conn->prepare("SELECT dura, tokens, event_currency FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        // Update session
        $_SESSION['user']['dura'] = $data['dura'];
        $_SESSION['user']['tokens'] = $data['tokens'];
        $_SESSION['user']['event_currency'] = $data['event_currency'];
        
        echo json_encode([
            'status' => 'success',
            'dura' => $data['dura'],
            'tokens' => $data['tokens'],
            'event_currency' => $data['event_currency']
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
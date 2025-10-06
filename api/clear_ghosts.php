<?php
// api/clear_ghosts.php - Clear all active ghost hunts
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

// Check if user is admin or moderator
$is_authorized = false;
if (isset($_SESSION['user']) && $_SESSION['user']['type'] === 'user') {
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT is_admin, is_moderator FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $is_authorized = ($user_data['is_admin'] == 1 || $user_data['is_moderator'] == 1);
        }
        $stmt->close();
    }
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Moderators and Admins only']);
    exit;
}

try {
    // Count active ghosts before clearing
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM ghost_hunt_events WHERE is_active = 1");
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    
    // Deactivate all active ghost hunts
    $clear_stmt = $conn->prepare("UPDATE ghost_hunt_events SET is_active = 0 WHERE is_active = 1");
    $clear_stmt->execute();
    $clear_stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'cleared' => $count,
        'message' => "Cleared $count active ghost hunt(s)"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
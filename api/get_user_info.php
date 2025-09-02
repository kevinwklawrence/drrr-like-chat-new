<?php
// api/get_user_info.php - Updated to include ghost_mode
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id || !is_numeric($user_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Get comprehensive user information including ghost_mode
    $stmt = $conn->prepare("
        SELECT 
            id, username, email, avatar, color, is_admin, is_moderator,
            avatar_hue, avatar_saturation, bubble_hue, bubble_saturation,
            ghost_mode, created_at
        FROM users 
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        $stmt->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Convert numeric values
    $user['avatar_hue'] = (int)($user['avatar_hue'] ?? 0);
    $user['avatar_saturation'] = (int)($user['avatar_saturation'] ?? 100);
    $user['bubble_hue'] = (int)($user['bubble_hue'] ?? 0);
    $user['bubble_saturation'] = (int)($user['bubble_saturation'] ?? 100);
    $user['is_admin'] = (bool)$user['is_admin'];
    $user['is_moderator'] = (bool)$user['is_moderator'];
    $user['ghost_mode'] = (bool)$user['ghost_mode'];
    
    // Remove sensitive data if not the current user
    $is_current_user = (isset($_SESSION['user']['id']) && $_SESSION['user']['id'] == $user_id);
    if (!$is_current_user) {
        unset($user['email']);
        
        // Only show ghost_mode status to moderators/admins or the user themselves
        $viewer_is_mod = (isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) ||
                        (isset($_SESSION['user']['is_moderator']) && $_SESSION['user']['is_moderator']);
        
        if (!$viewer_is_mod) {
            unset($user['ghost_mode']);
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Get user info error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to get user information']);
}

$conn->close();
?>
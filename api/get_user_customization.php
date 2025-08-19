<?php
// api/get_user_customization.php - Get user's saved customization settings
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

$username = trim($_GET['username'] ?? '');

if (empty($username)) {
    echo json_encode(['status' => 'error', 'message' => 'Username required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT avatar, custom_av, avatar_memory, color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Determine the avatar to show based on priority
        $display_avatar = null;
        if (!empty($user['custom_av'])) {
            $display_avatar = $user['custom_av'];
        } elseif (!empty($user['avatar_memory'])) {
            $display_avatar = $user['avatar_memory'];
        } elseif (!empty($user['avatar'])) {
            $display_avatar = $user['avatar'];
        } else {
            $display_avatar = 'default/u0.png';
        }
        
        echo json_encode([
            'status' => 'success',
            'customization' => [
                'avatar' => $display_avatar,
                'color' => $user['color'] ?? 'black',
                'avatar_hue' => (int)($user['avatar_hue'] ?? 0),
                'avatar_saturation' => (int)($user['avatar_saturation'] ?? 100),
                'bubble_hue' => (int)($user['bubble_hue'] ?? 0),
                'bubble_saturation' => (int)($user['bubble_saturation'] ?? 100)
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Get user customization error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$conn->close();
?>
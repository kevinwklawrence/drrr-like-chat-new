<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$avatar_hue = (int)($_POST['avatar_hue'] ?? 0);
$avatar_saturation = (int)($_POST['avatar_saturation'] ?? 100);
$user_id_string = $_SESSION['user']['user_id'] ?? '';

// Validate ranges
$avatar_hue = max(0, min(360, $avatar_hue));
$avatar_saturation = max(0, min(200, $avatar_saturation));

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Update session first
    $_SESSION['user']['avatar_hue'] = $avatar_hue;
    $_SESSION['user']['avatar_saturation'] = $avatar_saturation;
    
    // Update global_users table
    $stmt = $conn->prepare("UPDATE global_users SET avatar_hue = ?, avatar_saturation = ? WHERE user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("iis", $avatar_hue, $avatar_saturation, $user_id_string);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update any active chatroom_users records
    $stmt = $conn->prepare("UPDATE chatroom_users SET avatar_hue = ?, avatar_saturation = ? WHERE user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("iis", $avatar_hue, $avatar_saturation, $user_id_string);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update users table if it's a registered user
    if ($_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['id'])) {
        $stmt = $conn->prepare("UPDATE users SET avatar_hue = ?, avatar_saturation = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("iii", $avatar_hue, $avatar_saturation, $_SESSION['user']['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Avatar customization updated successfully']);
    
} catch (Exception $e) {
    error_log("Update avatar customization error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update avatar customization: ' . $e->getMessage()]);
}

$conn->close();
?>
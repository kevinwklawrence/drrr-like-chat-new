<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Get avatar customization from global_users
    $stmt = $conn->prepare("SELECT avatar_hue, avatar_saturation FROM global_users WHERE user_id_string = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $avatar_hue = (int)($data['avatar_hue'] ?? 0);
        $avatar_saturation = (int)($data['avatar_saturation'] ?? 100);
        
        // Update chatroom_users with the correct values
        $update_stmt = $conn->prepare("UPDATE chatroom_users SET avatar_hue = ?, avatar_saturation = ? WHERE room_id = ? AND user_id_string = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("iiis", $avatar_hue, $avatar_saturation, $room_id, $user_id_string);
            $update_stmt->execute();
            $affected_rows = $update_stmt->affected_rows;
            $update_stmt->close();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Avatar customization synced',
                'avatar_hue' => $avatar_hue,
                'avatar_saturation' => $avatar_saturation,
                'affected_rows' => $affected_rows
            ]);
        } else {
            throw new Exception('Failed to update chatroom_users');
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found in global_users']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Sync avatar customization error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to sync: ' . $e->getMessage()]);
}

$conn->close();
?>
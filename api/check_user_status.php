<?php
// api/check_user_status.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and in a room
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'not_in_room']);
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
    // Check if user is still in the room (not kicked/banned)
    $stmt = $conn->prepare("SELECT id FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // User is no longer in the room - check if they're banned
        $stmt->close();
        
        $ban_status = checkUserBanStatus($conn, $room_id, $user_id_string);
        
        if ($ban_status['banned']) {
            // User was banned
            echo json_encode([
                'status' => 'banned',
                'message' => 'You have been banned from this room',
                'ban_info' => $ban_status
            ]);
        } else {
            // User was kicked or left
            echo json_encode([
                'status' => 'removed',
                'message' => 'You are no longer in this room'
            ]);
        }
        exit;
    }
    
    $stmt->close();
    
    // User is still in the room
    echo json_encode(['status' => 'active']);
    
} catch (Exception $e) {
    error_log("Check user status error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to check user status']);
}

$conn->close();

// Function to check if user is banned
function checkUserBanStatus($conn, $room_id, $user_id_string) {
    // Get banlist from chatrooms table
    $stmt = $conn->prepare("SELECT banlist FROM chatrooms WHERE id = ?");
    if (!$stmt) {
        return ['banned' => false];
    }
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['banned' => false];
    }
    
    $room_data = $result->fetch_assoc();
    $banlist = $room_data['banlist'] ? json_decode($room_data['banlist'], true) : [];
    $stmt->close();
    
    if (!is_array($banlist)) {
        return ['banned' => false];
    }
    
    // Check if user is in banlist
    foreach ($banlist as $ban) {
        if ($ban['user_id_string'] === $user_id_string) {
            // Check if ban is still active
            if ($ban['ban_until'] === null) {
                // Permanent ban
                return [
                    'banned' => true,
                    'permanent' => true,
                    'reason' => $ban['reason'] ?? '',
                    'banned_by' => $ban['banned_by'] ?? 'Unknown'
                ];
            } else {
                // Temporary ban - check if still valid
                if ($ban['ban_until'] > time()) {
                    $remaining_minutes = ceil(($ban['ban_until'] - time()) / 60);
                    return [
                        'banned' => true,
                        'permanent' => false,
                        'expires_in_minutes' => $remaining_minutes,
                        'reason' => $ban['reason'] ?? '',
                        'banned_by' => $ban['banned_by'] ?? 'Unknown'
                    ];
                }
            }
        }
    }
    
    return ['banned' => false];
}
?>
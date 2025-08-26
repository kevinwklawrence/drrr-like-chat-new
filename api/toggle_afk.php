<?php
// api/toggle_afk.php - Manual AFK toggle
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not in room']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

$action = $_POST['action'] ?? 'toggle'; // toggle, set_afk, set_active

try {
    // Get current AFK status
    $stmt = $conn->prepare("SELECT is_afk, manual_afk, username, guest_name FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found in room']);
        $stmt->close();
        exit;
    }
    
    $user_data = $result->fetch_assoc();
    $current_afk = (bool)$user_data['is_afk'];
    $current_manual = (bool)$user_data['manual_afk'];
    $display_name = $user_data['username'] ?: $user_data['guest_name'] ?: 'Unknown User';
    $stmt->close();
    
    // Determine new AFK state
    $new_afk_state = $current_afk;
    $new_manual_state = $current_manual;
    
    switch ($action) {
        case 'toggle':
            $new_afk_state = !$current_afk;
            $new_manual_state = $new_afk_state;
            break;
        case 'set_afk':
            $new_afk_state = true;
            $new_manual_state = true;
            break;
        case 'set_active':
            $new_afk_state = false;
            $new_manual_state = false;
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            exit;
    }
    
    // Update user's AFK status
    if ($new_afk_state) {
        // Going AFK
        $update_stmt = $conn->prepare("UPDATE chatroom_users SET is_afk = 1, manual_afk = ?, afk_since = NOW(), last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
        $update_stmt->bind_param("iis", $new_manual_state, $room_id, $user_id_string);
        
        $system_message = $new_manual_state ? "$display_name is now AFK." : "$display_name is now AFK due to inactivity.";
        $avatar = 'afk.png';
        $action_text = 'marked as AFK';
    } else {
        // Going active
        $update_stmt = $conn->prepare("UPDATE chatroom_users SET is_afk = 0, manual_afk = 0, afk_since = NULL, last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
        $update_stmt->bind_param("is", $room_id, $user_id_string);
        
        $system_message = "$display_name is back from AFK.";
        $avatar = 'active.png';
        $action_text = 'no longer AFK';
    }
    
    $success = $update_stmt->execute();
    $affected_rows = $update_stmt->affected_rows;
    $update_stmt->close();
    
    if (!$success || $affected_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update AFK status']);
        exit;
    }
    
    // Add system message if status actually changed
    if ($current_afk !== $new_afk_state) {
        $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), ?, 'system')");
        if ($msg_stmt) {
            $msg_stmt->bind_param("iss", $room_id, $system_message, $avatar);
            $msg_stmt->execute();
            $msg_stmt->close();
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "User $action_text successfully",
        'is_afk' => $new_afk_state,
        'manual_afk' => $new_manual_state,
        'display_name' => $display_name,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Toggle AFK error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to toggle AFK status: ' . $e->getMessage()]);
}

$conn->close();
?>
<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

// Check if current user is host or admin
$is_authorized = false;
if (!empty($current_user_id_string)) {
    $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $current_user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $is_authorized = ($user_data['is_host'] == 1);
        }
        $stmt->close();
    }
}

// Also check if user is admin
if (!$is_authorized && isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    $is_authorized = true;
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Only hosts and admins can unban users']);
    exit;
}

// Get POST data
$user_id_string = $_POST['user_id_string'] ?? '';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user ID']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get current banlist
    $stmt = $conn->prepare("SELECT banlist FROM chatrooms WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Room not found');
    }
    
    $room_data = $result->fetch_assoc();
    $current_banlist = $room_data['banlist'] ? json_decode($room_data['banlist'], true) : [];
    $stmt->close();
    
    // Find the banned user's name
    $unbanned_user_name = 'Unknown User';
    foreach ($current_banlist as $ban) {
        if ($ban['user_id_string'] === $user_id_string) {
            $unbanned_user_name = $ban['username'];
            break;
        }
    }
    
    // Remove user from banlist
    $new_banlist = array_filter($current_banlist, function($ban) use ($user_id_string) {
        return $ban['user_id_string'] !== $user_id_string;
    });
    
    // Reindex array to maintain JSON array format
    $new_banlist = array_values($new_banlist);
    
    // Update room banlist
    $update_stmt = $conn->prepare("UPDATE chatrooms SET banlist = ? WHERE id = ?");
    if (!$update_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $banlist_json = json_encode($new_banlist);
    $update_stmt->bind_param("si", $banlist_json, $room_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Execute failed: ' . $update_stmt->error);
    }
    $update_stmt->close();
    
    // Add system message about the unban
    $unban_message = "<span id='unbanmessage'>" . $unbanned_user_name . " has been unbanned from the room</span>";
    
    $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'banhammer.png', 'system')");
    if ($msg_stmt) {
        $msg_stmt->bind_param("is", $room_id, $unban_message);
        $msg_stmt->execute();
        $msg_stmt->close();
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'User unbanned successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Unban user error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to unban user: ' . $e->getMessage()]);
}

$conn->close();
?>
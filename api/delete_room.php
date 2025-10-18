<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$user_id = $_SESSION['user']['id'];
$room_id = (int)($_POST['room_id'] ?? 0);

if ($room_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

// Check if user is admin or moderator
$is_admin = false;
$is_moderator = false;

$stmt = $conn->prepare("SELECT is_admin, is_moderator FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_admin = (bool)$user_data['is_admin'];
        $is_moderator = (bool)$user_data['is_moderator'];
    }
    $stmt->close();
}

if (!$is_admin && !$is_moderator) {
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to delete rooms']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get room info
    $room_stmt = $conn->prepare("SELECT name FROM chatrooms WHERE id = ?");
    $room_stmt->bind_param("i", $room_id);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    
    if ($room_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Room not found']);
        $room_stmt->close();
        exit;
    }
    
    $room_name = $room_result->fetch_assoc()['name'];
    $room_stmt->close();
    
    // Remove all users from the room
    $remove_users = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ?");
    $remove_users->bind_param("i", $room_id);
    $remove_users->execute();
    $remove_users->close();
    
    // Delete all messages
    $delete_messages = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
    $delete_messages->bind_param("i", $room_id);
    $delete_messages->execute();
    $delete_messages->close();
    
    // Delete the room
    $delete_room = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
    $delete_room->bind_param("i", $room_id);
    $delete_room->execute();
    $delete_room->close();
    
    $conn->commit();
    
    error_log("Room deleted by " . ($is_admin ? "admin" : "moderator") . " (user_id=$user_id): room_id=$room_id, name=$room_name");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Room deleted successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Delete room error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete room: ' . $e->getMessage()]);
}

$conn->close();
?>
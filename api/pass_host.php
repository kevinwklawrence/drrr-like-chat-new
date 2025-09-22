<?php
session_start();
include '../db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$target_user_id_string = isset($_POST['target_user_id_string']) ? trim($_POST['target_user_id_string']) : '';

if ($room_id <= 0 || empty($target_user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID or target user']);
    exit;
}

$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($current_user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'User session invalid']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Verify current user is host
    $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    $stmt->bind_param("is", $room_id, $current_user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0 || $result->fetch_assoc()['is_host'] != 1) {
        echo json_encode(['status' => 'error', 'message' => 'Only hosts can pass host privileges']);
        $stmt->close();
        $conn->rollback();
        exit;
    }
    $stmt->close();
    
    // Verify target user is in room
    $stmt = $conn->prepare("SELECT user_id_string FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    $stmt->bind_param("is", $room_id, $target_user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Target user not in room']);
        $stmt->close();
        $conn->rollback();
        exit;
    }
    $stmt->close();
    
    // Remove host from current user
    $stmt = $conn->prepare("UPDATE chatroom_users SET is_host = 0 WHERE room_id = ? AND user_id_string = ?");
    $stmt->bind_param("is", $room_id, $current_user_id_string);
    $stmt->execute();
    $stmt->close();
    
    // Grant host to target user
    $stmt = $conn->prepare("UPDATE chatroom_users SET is_host = 1 WHERE room_id = ? AND user_id_string = ?");
    $stmt->bind_param("is", $room_id, $target_user_id_string);
    $stmt->execute();
    $stmt->close();
    
    // Get usernames for system message
    $stmt = $conn->prepare("
        SELECT COALESCE(u.username, cu.guest_name) as display_name 
        FROM chatroom_users cu 
        LEFT JOIN users u ON cu.user_id = u.id 
        WHERE cu.room_id = ? AND cu.user_id_string = ?
    ");
    $stmt->bind_param("is", $room_id, $target_user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    $target_name = $result->num_rows > 0 ? $result->fetch_assoc()['display_name'] : 'Unknown';
    $stmt->close();
    
    // Add system message
    $system_message = $target_name . " is now the host of this room.";
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, timestamp, type) VALUES (?, 'SYSTEM', ?, NOW(), 'system')");
    $stmt->bind_param("is", $room_id, $system_message);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Host privileges passed successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Pass host error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$conn->close();
?>
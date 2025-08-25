<?php
// api/send_announcement.php - Final version after ENUM fix
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Check if user is moderator or admin
$user_id = $_SESSION['user']['id'];
$is_authorized = false;
$username = '';

$stmt = $conn->prepare("SELECT is_moderator, is_admin, username FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_authorized = ($user_data['is_moderator'] == 1 || $user_data['is_admin'] == 1);
        $username = $user_data['username'];
    }
    $stmt->close();
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Only moderators and admins can send announcements']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Announcement message cannot be empty']);
    exit;
}

if (strlen($message) > 500) {
    echo json_encode(['status' => 'error', 'message' => 'Announcement message too long (max 500 characters)']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Store announcement in announcements table
    $stmt = $conn->prepare("INSERT INTO announcements (moderator_id, moderator_username, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Failed to prepare announcement insert: ' . $conn->error);
    }
    
    $stmt->bind_param("iss", $user_id, $username, $message);
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert announcement: ' . $stmt->error);
    }
    $stmt->close();
    
    // Get all active rooms
    $rooms_stmt = $conn->prepare("SELECT id FROM chatrooms");
    if (!$rooms_stmt) {
        throw new Exception('Failed to prepare rooms query: ' . $conn->error);
    }
    
    $rooms_stmt->execute();
    $rooms_result = $rooms_stmt->get_result();
    
    $announcement_text = $message . " <hr>- " . $username;
    $system_user = 'SYSTEM_ANNOUNCEMENT';
    
    // Insert announcement message into all active rooms with 'announcement' type
    while ($room = $rooms_result->fetch_assoc()) {
        $room_id = $room['id'];
        
        $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, timestamp, type) VALUES (?, ?, ?, NOW(), 'announcement')");
        if ($msg_stmt) {
            $msg_stmt->bind_param("iss", $room_id, $system_user, $announcement_text);
            if (!$msg_stmt->execute()) {
                error_log("Failed to insert announcement message into room $room_id: " . $msg_stmt->error);
            }
            $msg_stmt->close();
        }
    }
    $rooms_stmt->close();
    
    // Log moderator action
    $log_stmt = $conn->prepare("INSERT INTO moderator_logs (moderator_id, moderator_username, action_type, details) VALUES (?, ?, ?, ?)");
    if ($log_stmt) {
        $action_type = 'announcement';
        $log_stmt->bind_param("isss", $user_id, $username, $action_type, $message);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Announcement sent to all rooms successfully',
        'announcement_text' => $announcement_text
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Send announcement error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to send announcement: ' . $e->getMessage()]);
}

$conn->close();
?>
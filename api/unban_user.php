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
    
    // Get user info before unbanning
    $stmt = $conn->prepare("
        SELECT rb.*, u.username, gu.name as guest_name 
        FROM room_bans rb 
        LEFT JOIN users u ON rb.user_id_string = CAST(u.id AS CHAR)
        LEFT JOIN guest_users gu ON rb.user_id_string = gu.id_string
        WHERE rb.room_id = ? AND rb.user_id_string = ?
        AND (rb.ban_until IS NULL OR rb.ban_until > NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User is not banned or ban has expired');
    }
    
    $banned_user = $result->fetch_assoc();
    $stmt->close();
    
    // Remove the ban (set ban_until to past date to preserve record)
    $stmt = $conn->prepare("UPDATE room_bans SET ban_until = '1970-01-01 00:00:00' WHERE room_id = ? AND user_id_string = ?");
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
    
    // Add system message about the unban
    $user_name = $banned_user['username'] ?? $banned_user['guest_name'] ?? 'Unknown';
    $unban_message = "User " . $user_name . " has been unbanned from the room";
    
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp) VALUES (?, '', ?, 1, NOW())");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $unban_message);
        $stmt->execute();
        $stmt->close();
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
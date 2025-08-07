<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)($_POST['room_id'] ?? 0);
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if ($room_id <= 0 || empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Check if room exists and allows knocking
    $stmt = $conn->prepare("SELECT id, name, allow_knocking, has_password FROM chatrooms WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Room not found']);
        $stmt->close();
        exit;
    }
    
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if (!$room['has_password']) {
        echo json_encode(['status' => 'error', 'message' => 'Room is not password protected']);
        exit;
    }
    
    if (!$room['allow_knocking']) {
        echo json_encode(['status' => 'error', 'message' => 'Room does not allow knocking']);
        exit;
    }
    
    // Check if user is already in the room
    $stmt = $conn->prepare("SELECT id FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'You are already in this room']);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }
    
    // Get user information
    $username = $_SESSION['user']['username'] ?? null;
    $guest_name = $_SESSION['user']['name'] ?? null;
    $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
    
    // Check for existing pending knock
    $stmt = $conn->prepare("SELECT id FROM room_knocks WHERE room_id = ? AND user_id_string = ? AND status = 'pending'");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'You already have a pending knock request for this room']);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }
    
    // Create knock request
    $stmt = $conn->prepare("INSERT INTO room_knocks (room_id, user_id_string, username, guest_name, avatar, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("issss", $room_id, $user_id_string, $username, $guest_name, $avatar);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create knock request: ' . $stmt->error);
    }
    
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'message' => 'Knock request sent successfully']);
    
} catch (Exception $e) {
    error_log("Knock room error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to send knock request']);
}

$conn->close();
?>
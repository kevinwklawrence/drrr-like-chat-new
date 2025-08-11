<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and in a room
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];

// Generate a random test user (based on debug_add_test_user.php pattern)
$fake_ip = '192.168.1.' . rand(100, 200);
$fake_user_id = 'TEST_' . substr(md5($fake_ip . time()), 0, 12);
$fake_name = 'TestUser' . rand(0000, 9999);
$test_avatars =  ['m1.png', 'm2.png', 'm3.png', 'm4.png', 'm5.png', 'm6.png', 'm7.png', 'f1.png', 'f2.png', 'f3.png', 'f4.png', 'f5.png', 'f6.png', 'f7.png'];
$fake_avatar = '/default//' . $test_avatars[array_rand($test_avatars)];

try {
    $conn->begin_transaction();
    
    // Add test user to the current room (following the existing pattern)
    $stmt = $conn->prepare("INSERT INTO chatroom_users (room_id, user_id, guest_name, guest_avatar, user_id_string, is_host, ip_address) VALUES (?, NULL, ?, ?, ?, 0, ?)");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("issss", $room_id, $fake_name, $fake_avatar, $fake_user_id, $fake_ip);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
    
    // Add a system message
    $join_message = $fake_name . " (test user) joined the room";
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), '$fake_avatar', 'system')");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $join_message);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => "Added test user: $fake_name ($fake_user_id)",
        'user' => [
            'user_id_string' => $fake_user_id,
            'name' => $fake_name,
            'avatar' => $fake_avatar
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to create test user: ' . $e->getMessage()]);
}

$conn->close();
?>
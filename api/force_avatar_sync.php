<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];

try {
    // Force sync all users in the room
    $stmt = $conn->prepare("UPDATE chatroom_users cu 
                           JOIN global_users gu ON cu.user_id_string = gu.user_id_string 
                           SET cu.avatar_hue = gu.avatar_hue, cu.avatar_saturation = gu.avatar_saturation 
                           WHERE cu.room_id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Avatar customization synced for all users',
        'affected_rows' => $affected_rows
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
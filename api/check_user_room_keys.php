<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

include '../db_connect.php';

$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    echo json_encode([]);
    exit;
}

try {
    // Get all rooms that have room_keys and check if user has valid keys
    $stmt = $conn->prepare("SELECT id, room_keys FROM chatrooms WHERE room_keys IS NOT NULL AND room_keys != '' AND room_keys != 'null'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $user_room_keys = [];
    
    while ($room = $result->fetch_assoc()) {
        $room_keys = json_decode($room['room_keys'], true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($room_keys)) {
            if (isset($room_keys[$user_id_string])) {
                $key_data = $room_keys[$user_id_string];
                
                // Check if key is still valid
                if (isset($key_data['expires_at']) && $key_data['expires_at'] > time()) {
                    $user_room_keys[] = (int)$room['id'];
                }
            }
        }
    }
    $stmt->close();
    
    echo json_encode($user_room_keys);
    
} catch (Exception $e) {
    error_log("Check user room keys error: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
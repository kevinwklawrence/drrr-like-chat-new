<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)($_POST['room_id'] ?? 0);
$target_user_id_string = $_POST['user_id_string'] ?? '';
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

if ($room_id <= 0 || empty($target_user_id_string) || empty($current_user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Verify current user is host
    $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $room_id, $current_user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0 || $result->fetch_assoc()['is_host'] != 1) {
        echo json_encode(['status' => 'error', 'message' => 'Only hosts can revoke keys']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Get current room keys
    $stmt = $conn->prepare("SELECT room_keys FROM chatrooms WHERE id = ?");
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
    
    // Decode room keys
    $room_keys = [];
    if (!empty($room['room_keys']) && $room['room_keys'] !== 'null') {
        $decoded_keys = json_decode($room['room_keys'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_keys)) {
            $room_keys = $decoded_keys;
        }
    }
    
    // Remove the key for target user
    if (isset($room_keys[$target_user_id_string])) {
        unset($room_keys[$target_user_id_string]);
        
        // Save updated keys
        $room_keys_json = json_encode($room_keys, JSON_UNESCAPED_SLASHES);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to encode room keys: ' . json_last_error_msg());
        }
        
        $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("si", $room_keys_json, $room_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update room keys: ' . $stmt->error);
        }
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'Key revoked successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User does not have a key']);
    }
    
} catch (Exception $e) {
    error_log("REVOKE_KEY: Error - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to revoke key: ' . $e->getMessage()]);
}

$conn->close();
?>
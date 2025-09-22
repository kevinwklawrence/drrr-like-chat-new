<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)($_GET['room_id'] ?? 0);
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

if ($room_id <= 0 || empty($current_user_id_string)) {
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
        echo json_encode(['status' => 'error', 'message' => 'Only hosts can view keys']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Get room keys
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
    
    // Get user details for each key
    $users_with_keys = [];
    foreach ($room_keys as $user_id_string => $key_data) {
        // Check if key is expired
        if (isset($key_data['expires_at']) && $key_data['expires_at'] < time()) {
            continue; // Skip expired keys
        }
        
        // Get user details from chatroom_users or users table
        $stmt = $conn->prepare("
            SELECT cu.username, cu.guest_name, cu.avatar, u.username as real_username
            FROM chatroom_users cu
            LEFT JOIN users u ON cu.user_id = u.id
            WHERE cu.user_id_string = ?
            LIMIT 1
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $display_name = $user['real_username'] ?: $user['username'] ?: $user['guest_name'] ?: 'Unknown User';
            } else {
                $display_name = 'Unknown User';
            }
            $stmt->close();
        } else {
            $display_name = 'Unknown User';
        }
        
        $users_with_keys[] = [
            'user_id_string' => $user_id_string,
            'display_name' => $display_name,
            'granted_at' => $key_data['granted_at'] ?? null,
            'expires_at' => $key_data['expires_at'] ?? null,
            'is_temp' => $key_data['is_temp'] ?? false,
            'uses_remaining' => $key_data['uses_remaining'] ?? null
        ];
    }
    
    echo json_encode(['status' => 'success', 'users' => $users_with_keys]);
    
} catch (Exception $e) {
    error_log("GET_USERS_WITH_KEYS: Error - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to get users with keys: ' . $e->getMessage()]);
}

$conn->close();
?>
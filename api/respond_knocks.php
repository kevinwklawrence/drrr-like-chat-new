<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$knock_id = (int)($_POST['knock_id'] ?? 0);
$response = $_POST['response'] ?? '';
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

if ($knock_id <= 0 || !in_array($response, ['accepted', 'denied']) || empty($current_user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get knock details and verify user is host
    $stmt = $conn->prepare("
        SELECT rk.*, c.room_keys 
        FROM room_knocks rk 
        JOIN chatrooms c ON rk.room_id = c.id 
        JOIN chatroom_users cu ON c.id = cu.room_id 
        WHERE rk.id = ? 
        AND cu.user_id_string = ? 
        AND cu.is_host = 1 
        AND rk.status = 'pending'
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $knock_id, $current_user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Knock request not found or you are not authorized']);
        $stmt->close();
        $conn->rollback();
        exit;
    }
    
    $knock = $result->fetch_assoc();
    $stmt->close();
    
    // Update knock status
    $stmt = $conn->prepare("UPDATE room_knocks SET status = ?, responded_by = ?, responded_at = NOW() WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ssi", $response, $current_user_id_string, $knock_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update knock status: ' . $stmt->error);
    }
    $stmt->close();
    
    if ($response === 'accepted') {
        // Generate a room key for the user
        $room_keys = $knock['room_keys'] ? json_decode($knock['room_keys'], true) : [];
        $room_key = bin2hex(random_bytes(16));
        $room_keys[$knock['user_id_string']] = [
            'key' => $room_key,
            'granted_by' => $current_user_id_string,
            'granted_at' => time(),
            'expires_at' => time() + (24 * 60 * 60) // 24 hours
        ];
        
        // Update room keys
        $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $room_keys_json = json_encode($room_keys);
        $stmt->bind_param("si", $room_keys_json, $knock['room_id']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update room keys: ' . $stmt->error);
        }
        $stmt->close();
        
        // Optionally notify the user (could be implemented with a notification system)
        // For now, we'll just log it
        error_log("Room key granted to {$knock['user_id_string']} for room {$knock['room_id']}");
    }
    
    $conn->commit();
    
    $message = $response === 'accepted' ? 'Knock request accepted' : 'Knock request denied';
    echo json_encode(['status' => 'success', 'message' => $message]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Respond knock error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to respond to knock request']);
}

$conn->close();
?>
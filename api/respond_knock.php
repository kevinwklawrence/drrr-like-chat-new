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
        SELECT rk.*, c.room_keys, c.name as room_name
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
    
    // If accepted, generate a temporary room key
    if ($response === 'accepted') {
        // Check if room_keys column exists
        $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'room_keys'");
        if ($columns_query->num_rows > 0) {
            // Get current room keys
            $room_keys = [];
            if (!empty($knock['room_keys'])) {
                $room_keys = json_decode($knock['room_keys'], true) ?: [];
            }
            
            // Create new room key
            $room_keys[$knock['user_id_string']] = [
                'granted_by' => $current_user_id_string,
                'granted_at' => time(),
                'expires_at' => time() + (24 * 60 * 60), // 24 hours
                'knock_id' => $knock_id
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
        }
        
        // Add system message about accepted knock
        $knocker_name = $knock['username'] ?: $knock['guest_name'] ?: 'Unknown User';
        $accept_message = $knocker_name . " was granted access to the room (knock accepted)";
        
        // Check if messages table supports system messages
        $msg_columns = [];
        $msg_columns_query = $conn->query("SHOW COLUMNS FROM messages");
        while ($row = $msg_columns_query->fetch_assoc()) {
            $msg_columns[] = $row['Field'];
        }
        
        if (in_array('is_system', $msg_columns)) {
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'key.png', 'system')");
        } else {
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, timestamp, avatar) VALUES (?, '', ?, NOW(), 'key.png')");
        }
        
        if ($stmt) {
            $stmt->bind_param("is", $knock['room_id'], $accept_message);
            $stmt->execute();
            $stmt->close();
        }
        
        error_log("Knock accepted: knock_id={$knock_id}, user={$knock['user_id_string']}, room={$knock['room_id']}");
    } else {
        // Add system message about denied knock (optional)
        $knocker_name = $knock['username'] ?: $knock['guest_name'] ?: 'Unknown User';
        error_log("Knock denied: knock_id={$knock_id}, user={$knock['user_id_string']}, room={$knock['room_id']}");
    }
    
    $conn->commit();
    
    $message = $response === 'accepted' ? 
        'Knock request accepted - user can now join the room' : 
        'Knock request denied';
        
    echo json_encode(['status' => 'success', 'message' => $message]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Respond knock error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to respond to knock request: ' . $e->getMessage()]);
}

$conn->close();
?>
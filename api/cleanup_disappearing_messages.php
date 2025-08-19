<?php
// api/cleanup_disappearing_messages.php - New file
header('Content-Type: application/json');

include '../db_connect.php';

try {
    // Get rooms with disappearing messages enabled
    $stmt = $conn->prepare("
        SELECT id, message_lifetime_minutes 
        FROM chatrooms 
        WHERE disappearing_messages = 1 
        AND message_lifetime_minutes > 0
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cleaned_messages = 0;
    
    while ($room = $result->fetch_assoc()) {
        $room_id = $room['id'];
        $lifetime_minutes = $room['message_lifetime_minutes'];
        
        // Delete messages older than the specified lifetime
        $delete_stmt = $conn->prepare("
            DELETE FROM messages 
            WHERE room_id = ? 
            AND timestamp < DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND type != 'system'
        ");
        
        if ($delete_stmt) {
            $delete_stmt->bind_param("ii", $room_id, $lifetime_minutes);
            $delete_stmt->execute();
            $cleaned_messages += $delete_stmt->affected_rows;
            $delete_stmt->close();
        }
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'cleaned_messages' => $cleaned_messages,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Disappearing messages cleanup error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>

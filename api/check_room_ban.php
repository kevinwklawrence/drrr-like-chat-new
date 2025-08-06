<?php
// This function should be included in your join_room.php or wherever users join rooms
function isUserBannedFromRoom($conn, $room_id, $user_id_string) {
    $stmt = $conn->prepare("
        SELECT ban_until, reason 
        FROM room_bans 
        WHERE room_id = ? AND user_id_string = ? 
        AND (ban_until IS NULL OR ban_until > NOW())
        LIMIT 1
    ");
    
    if (!$stmt) {
        return false; // If there's an error, allow access
    }
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ban_info = $result->fetch_assoc();
        $stmt->close();
        
        // Return ban information
        return [
            'banned' => true,
            'permanent' => ($ban_info['ban_until'] === null),
            'expires' => $ban_info['ban_until'],
            'reason' => $ban_info['reason']
        ];
    }
    
    $stmt->close();
    return ['banned' => false];
}

// Clean up expired bans (you can run this periodically)
function cleanupExpiredBans($conn) {
    $stmt = $conn->prepare("DELETE FROM room_bans WHERE ban_until IS NOT NULL AND ban_until <= NOW()");
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}
?>
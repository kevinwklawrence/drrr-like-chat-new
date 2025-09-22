<?php
// api/reset_inactivity.php - Helper function to reset timer

function resetInactivityTimer($conn, $room_id, $user_id_string) {
    $stmt = $conn->prepare("UPDATE chatroom_users SET inactivity_seconds = 0, is_afk = 0 WHERE room_id = ? AND user_id_string = ?");
    $stmt->bind_param("is", $room_id, $user_id_string);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// If called directly (for AJAX)
if (isset($_POST['reset_timer'])) {
    session_start();
    require_once __DIR__ . '/../db_connect.php';
    
    $user_id = $_SESSION['user']['user_id'] ?? '';
    $room_id = $_SESSION['room_id'] ?? 0;
    
    if ($user_id && $room_id) {
        $success = resetInactivityTimer($conn, $room_id, $user_id);
        echo json_encode(['status' => $success ? 'success' : 'error']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid session']);
    }
    
    $conn->close();
}
?>
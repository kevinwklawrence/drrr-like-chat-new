<?php
// api/check_room_settings.php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

$room_id = (int)$_SESSION['room_id'];

// Check for recent settings update event - return the event ID
$stmt = $conn->prepare("
    SELECT id, UNIX_TIMESTAMP(created_at) as timestamp
    FROM room_events 
    WHERE room_id = ? 
    AND event_type = 'settings_update' 
    AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
    ORDER BY created_at DESC
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'settings_changed' => true,
            'event_id' => $event['id'],
            'timestamp' => $event['timestamp']
        ]);
    } else {
        $stmt->close();
        echo json_encode([
            'status' => 'success',
            'settings_changed' => false
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

// Clean up events older than 10 seconds to prevent buildup
$cleanup = $conn->prepare("DELETE FROM room_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)");
if ($cleanup) {
    $cleanup->execute();
    $cleanup->close();
}

$conn->close();
?>
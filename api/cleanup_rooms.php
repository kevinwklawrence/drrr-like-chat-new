<?php
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Find non-permanent rooms with no users for 30 seconds
$stmt = $conn->prepare("
    SELECT c.id, c.name
    FROM chatrooms c
    LEFT JOIN chatroom_users cu ON c.id = cu.room_id
    WHERE c.permanent = 0
    GROUP BY c.id
    HAVING COUNT(cu.id) = 0
");
if (!$stmt) {
    error_log("Prepare failed in cleanup_rooms.php: " . $conn->error);
    exit;
}
$stmt->execute();
$result = $stmt->get_result();
$empty_rooms = [];
while ($row = $result->fetch_assoc()) {
    $empty_rooms[] = $row;
}
$stmt->close();

foreach ($empty_rooms as $room) {
    $room_id = $room['id'];
    // Check last activity (use joined_at from chatroom_users or messages)
    $stmt = $conn->prepare("
        SELECT MAX(joined_at) as last_activity
        FROM chatroom_users
        WHERE room_id = ?
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $last_activity_row = $result->fetch_assoc();
    $stmt->close();

    // Fallback to messages if no users
    if (!$last_activity_row['last_activity']) {
        $stmt = $conn->prepare("
            SELECT MAX(timestamp) as last_activity
            FROM messages
            WHERE room_id = ?
        ");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $last_activity_row = $result->fetch_assoc();
        $stmt->close();
    }

    $last_activity = $last_activity_row['last_activity'];
    if ($last_activity) {
        $last_activity_time = strtotime($last_activity);
        $current_time = time();
        $seconds_empty = $current_time - $last_activity_time;

        if ($seconds_empty >= 30) {
            // Delete the room (with proper foreign key handling)
            
            // First delete all users (should be none, but just in case)
            $stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Then delete all messages
            $stmt = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Finally delete the room
            $stmt = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
            $stmt->bind_param("i", $room_id);
            if ($stmt->execute()) {
                error_log("Cleaned up empty non-permanent room: id=$room_id, name={$room['name']}");
            } else {
                error_log("Failed to delete room id=$room_id: " . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log("Room id=$room_id not empty long enough: $seconds_empty seconds");
        }
    } else {
        // No activity ever, delete immediately if non-permanent
        
        // First delete all users (should be none, but just in case)
        $stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Then delete all messages
        $stmt = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Finally delete the room
        $stmt = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        if ($stmt->execute()) {
            error_log("Cleaned up empty non-permanent room with no activity: id=$room_id, name={$room['name']}");
        } else {
            error_log("Failed to delete room id=$room_id: " . $stmt->error);
        }
        $stmt->close();
    }
}

echo json_encode(['status' => 'success']);
?>
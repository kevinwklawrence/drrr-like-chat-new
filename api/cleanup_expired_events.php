<?php
// api/cleanup_expired_events.php - Clear expired pumpkins and ghosts
// Also provides cleanupExpiredEvents() function for spawn scripts
error_reporting(E_ALL);
ini_set('display_errors', 1);

$log_file = __DIR__ . '/../logs/event_cleanup.log';
ini_set('error_log', $log_file);

function log_cleanup($message) {
    global $log_file;
    $line = "[" . date('Y-m-d H:i:s') . "] $message\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $line;
}

include __DIR__ . '/../db_connect.php';

if (!isset($conn) || $conn->connect_error) {
    log_cleanup("FATAL: Database connection failed");
    die("ERROR: Database connection failed\n");
}

// Function to cleanup expired events
function cleanupExpiredEvents($conn) {
    $expired_pumpkins = 0;
    $expired_ghosts = 0;
    
    // Clear expired pumpkins
    $pumpkin_stmt = $conn->prepare("
        SELECT id, room_id 
        FROM pumpkin_smash_events 
        WHERE is_active = 1 
        AND spawned_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $pumpkin_stmt->execute();
    $pumpkin_result = $pumpkin_stmt->get_result();
    
    while ($pumpkin = $pumpkin_result->fetch_assoc()) {
        $pumpkin_id = $pumpkin['id'];
        $room_id = $pumpkin['room_id'];
        
        $deactivate_stmt = $conn->prepare("UPDATE pumpkin_smash_events SET is_active = 0 WHERE id = ?");
        $deactivate_stmt->bind_param("i", $pumpkin_id);
        $deactivate_stmt->execute();
        $deactivate_stmt->close();
        
        $escape_message = "🎃 <span style='color: #ffa500;'>The pumpkin got away! Too slow!</span> 👻";
        $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, 'PUMPKIN_SMASH', ?, 1, NOW(), 'pumpkin.png', 'system')");
        $msg_stmt->bind_param("is", $room_id, $escape_message);
        $msg_stmt->execute();
        $msg_stmt->close();
        
        $expired_pumpkins++;
        log_cleanup("Cleared expired pumpkin in room $room_id");
    }
    $pumpkin_stmt->close();
    
    // Clear expired ghosts
    $ghost_stmt = $conn->prepare("
        SELECT id, room_id 
        FROM ghost_hunt_events 
        WHERE is_active = 1 
        AND spawned_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $ghost_stmt->execute();
    $ghost_result = $ghost_stmt->get_result();
    
    while ($ghost = $ghost_result->fetch_assoc()) {
        $ghost_id = $ghost['id'];
        $room_id = $ghost['room_id'];
        
        $deactivate_stmt = $conn->prepare("UPDATE ghost_hunt_events SET is_active = 0 WHERE id = ?");
        $deactivate_stmt->bind_param("i", $ghost_id);
        $deactivate_stmt->execute();
        $deactivate_stmt->close();
        
        $escape_message = "👻 <span style='color: #9b59b6;'>The ghost vanished into the shadows...</span> 💨";
        $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, 'GHOST_HUNT', ?, 1, NOW(), 'ghost.png', 'system')");
        $msg_stmt->bind_param("is", $room_id, $escape_message);
        $msg_stmt->execute();
        $msg_stmt->close();
        
        $expired_ghosts++;
        log_cleanup("Cleared expired ghost in room $room_id");
    }
    $ghost_stmt->close();
    
    return ['pumpkins' => $expired_pumpkins, 'ghosts' => $expired_ghosts];
}

try {
    log_cleanup("Starting event cleanup");
    
    // Run cleanup
    $result = cleanupExpiredEvents($conn);
    
    log_cleanup("Cleanup complete - Pumpkins: {$result['pumpkins']}, Ghosts: {$result['ghosts']}");
    echo json_encode([
        'status' => 'success',
        'expired_pumpkins' => $result['pumpkins'],
        'expired_ghosts' => $result['ghosts']
    ]);
    
} catch (Exception $e) {
    log_cleanup("ERROR: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
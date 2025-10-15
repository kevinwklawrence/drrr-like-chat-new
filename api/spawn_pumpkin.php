<?php
// api/spawn_pumpkin.php - Spawn pumpkin smash events
error_reporting(E_ALL);
ini_set('display_errors', 1);

$log_file = __DIR__ . '/../logs/pumpkin_spawn.log';
ini_set('error_log', $log_file);

function log_spawn($message) {
    global $log_file;
    $line = "[" . date('Y-m-d H:i:s') . "] $message\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $line;
}

include __DIR__ . '/../db_connect.php';

if (!isset($conn) || $conn->connect_error) {
    log_spawn("FATAL: Database connection failed");
    die("ERROR: Database connection failed\n");
}

try {
    // Clear expired pumpkins before spawning new ones
    log_spawn("Checking for expired pumpkins...");
    $cleanup_stmt = $conn->prepare("
        SELECT id, room_id 
        FROM pumpkin_smash_events 
        WHERE is_active = 1 
        AND spawned_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $cleanup_stmt->execute();
    $cleanup_result = $cleanup_stmt->get_result();
    
    $cleaned = 0;
    while ($expired = $cleanup_result->fetch_assoc()) {
        $expired_id = $expired['id'];
        $room_id = $expired['room_id'];
        
        $deactivate_stmt = $conn->prepare("UPDATE pumpkin_smash_events SET is_active = 0 WHERE id = ?");
        $deactivate_stmt->bind_param("i", $expired_id);
        $deactivate_stmt->execute();
        $deactivate_stmt->close();
        
        $escape_message = "🎃 <span style='color: #ffa500;'>The pumpkin got away! Too slow!</span> 👻";
        $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, 'PUMPKIN_SMASH', ?, 1, NOW(), 'pumpkin.png', 'system')");
        $msg_stmt->bind_param("is", $room_id, $escape_message);
        $msg_stmt->execute();
        $msg_stmt->close();
        
        $cleaned++;
        log_spawn("Cleared expired pumpkin in room $room_id");
    }
    $cleanup_stmt->close();
    log_spawn("Expired pumpkins cleared: $cleaned");
    
    // Get all rooms
    $rooms_stmt = $conn->prepare("SELECT id FROM chatrooms");
    $rooms_stmt->execute();
    $rooms_result = $rooms_stmt->get_result();
    
    $spawned_count = 0;
    
    while ($room = $rooms_result->fetch_assoc()) {
        $room_id = $room['id'];
        
        // Check for active pumpkin
        $check_stmt = $conn->prepare("SELECT id FROM pumpkin_smash_events WHERE room_id = ? AND is_active = 1");
        $check_stmt->bind_param("i", $room_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();
        
        // 35% spawn chance (change 100 to 35 for normal operation)
        if (rand(1, 100) <= 100) {
            $reward = rand(5, 20);
            
            $spawn_stmt = $conn->prepare("INSERT INTO pumpkin_smash_events (room_id, reward_amount) VALUES (?, ?)");
            $spawn_stmt->bind_param("ii", $room_id, $reward);
            
            if ($spawn_stmt->execute()) {
                $pumpkin_id = $conn->insert_id;
                
                // System message with clickable pumpkin
                $pumpkin_message = "🎃 <strong style='color: #ff6b00;'>A WILD PUMPKIN APPEARS!</strong> 🎃<br><button class='pumpkin-smash-btn' data-pumpkin-id='$pumpkin_id' style='background: linear-gradient(135deg, #ff6b00, #ffa500); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 16px; box-shadow: 0 4px 15px rgba(255,107,0,0.4);'>🎃 SMASH IT!</button>";
                
                $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, 'PUMPKIN_SMASH', ?, 1, NOW(), 'pumpkin.png', 'system')");
                $msg_stmt->bind_param("is", $room_id, $pumpkin_message);
                $msg_stmt->execute();
                $msg_stmt->close();
                
                $spawned_count++;
                log_spawn("SUCCESS: Pumpkin spawned in room $room_id - Reward: $reward");
            }
            $spawn_stmt->close();
        }
    }
    
    $rooms_stmt->close();
    log_spawn("Spawn complete. Pumpkins spawned: $spawned_count");
    echo json_encode(['status' => 'success', 'spawned' => $spawned_count, 'cleaned' => $cleaned]);
    
} catch (Exception $e) {
    log_spawn("ERROR: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
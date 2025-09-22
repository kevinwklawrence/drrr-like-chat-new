<?php
// api/check_inactivity.php - Simple inactivity checker
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/inactivity_config.php';

try {
    $conn->begin_transaction();
    
    // Increment all inactivity timers by cron interval
    $conn->query("UPDATE chatroom_users SET inactivity_seconds = inactivity_seconds + " . CRON_INTERVAL);
    
    $stats = [
        'afk_marked' => 0,
        'disconnected' => 0,
        'hosts_transferred' => 0,
        'rooms_deleted' => 0
    ];
    
    // Get all users with their room info
    $sql = "SELECT cu.*, c.youtube_enabled, c.permanent, c.name as room_name,
            COALESCE(cu.username, cu.guest_name) as display_name
            FROM chatroom_users cu
            JOIN chatrooms c ON cu.room_id = c.id";
    $result = $conn->query($sql);
    
    while ($user = $result->fetch_assoc()) {
        $timeout = getDisconnectTimeout($user['room_id'], $user['is_host'], $conn);
        
        // Mark AFK at 15 minutes
        if ($user['inactivity_seconds'] >= AFK_TIMEOUT && !$user['is_afk']) {
            $stmt = $conn->prepare("UPDATE chatroom_users SET is_afk = 1 WHERE room_id = ? AND user_id_string = ?");
            $stmt->bind_param("is", $user['room_id'], $user['user_id_string']);
            $stmt->execute();
            $stmt->close();
            $stats['afk_marked']++;
        }
        
        // Disconnect at timeout
        if ($user['inactivity_seconds'] >= $timeout) {
            $room_id = $user['room_id'];
            $user_id = $user['user_id_string'];
            $is_host = $user['is_host'];
            $display_name = $user['display_name'];
            
            // Remove user
            $stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
            $stmt->bind_param("is", $room_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Add system message
            $msg = "$display_name disconnected due to inactivity.";
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'disconnect.png', 'system')");
            $stmt->bind_param("is", $room_id, $msg);
            $stmt->execute();
            $stmt->close();
            
            $stats['disconnected']++;
            
            // Handle host disconnection
            if ($is_host) {
                // Find another user
                $stmt = $conn->prepare("SELECT user_id_string, COALESCE(username, guest_name) as name FROM chatroom_users WHERE room_id = ? ORDER BY joined_at ASC LIMIT 1");
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $result2 = $stmt->get_result();
                
                if ($result2->num_rows > 0) {
                    // Transfer host
                    $new_host = $result2->fetch_assoc();
                    $stmt->close();
                    
                    $stmt = $conn->prepare("UPDATE chatroom_users SET is_host = 1 WHERE room_id = ? AND user_id_string = ?");
                    $stmt->bind_param("is", $room_id, $new_host['user_id_string']);
                    $stmt->execute();
                    $stmt->close();
                    
                    $stmt = $conn->prepare("UPDATE chatrooms SET host_user_id_string = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_host['user_id_string'], $room_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $msg = "Host transferred to " . $new_host['name'] . ".";
                    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'system.png', 'system')");
                    $stmt->bind_param("is", $room_id, $msg);
                    $stmt->execute();
                    $stmt->close();
                    
                    $stats['hosts_transferred']++;
                } else {
                    // No users left - delete room
                    $stmt->close();
                    
                    $stmt = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
                    $stmt->bind_param("i", $room_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $stmt = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
                    $stmt->bind_param("i", $room_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $stats['rooms_deleted']++;
                }
            }
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
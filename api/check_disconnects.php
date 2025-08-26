<?php
// api/check_disconnects.php - Enhanced with AFK detection
header('Content-Type: application/json');

include '../db_connect.php';

// Configuration
$DISCONNECT_TIMEOUT = 60 * 60; // 60 minutes in seconds  
$AFK_TIMEOUT = 10 * 60; // 10 minutes in seconds
$DEBUG_MODE = true; // Set to false in production

function logMessage($message) {
    global $DEBUG_MODE;
    if ($DEBUG_MODE) {
        error_log("DISCONNECT_SYSTEM: " . $message);
    }
}

logMessage("Starting disconnect and AFK check...");

try {
    $conn->begin_transaction();
    
    // Ensure required columns exist
    $columns_check = $conn->query("SHOW COLUMNS FROM chatroom_users LIKE 'last_activity'");
    if ($columns_check->num_rows === 0) {
        logMessage("Creating last_activity column...");
        $add_column = $conn->query("ALTER TABLE chatroom_users ADD COLUMN last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        if (!$add_column) {
            throw new Exception('Failed to create last_activity column: ' . $conn->error);
        }
        
        // Initialize existing users with current timestamp
        $init_activity = $conn->query("UPDATE chatroom_users SET last_activity = NOW() WHERE last_activity IS NULL");
        logMessage("Initialized last_activity for existing users");
    }
    
    // Add AFK columns if they don't exist
    $afk_check = $conn->query("SHOW COLUMNS FROM chatroom_users LIKE 'is_afk'");
    if ($afk_check->num_rows === 0) {
        logMessage("Creating AFK columns...");
        $conn->query("ALTER TABLE chatroom_users ADD COLUMN is_afk TINYINT(1) DEFAULT 0");
        $conn->query("ALTER TABLE chatroom_users ADD COLUMN afk_since TIMESTAMP NULL DEFAULT NULL");
        $conn->query("ALTER TABLE chatroom_users ADD COLUMN manual_afk TINYINT(1) DEFAULT 0");
        logMessage("AFK columns created");
    }
    
    // First, handle AFK detection (10 minutes of inactivity)
    $afk_sql = "SELECT 
        cu.room_id, 
        cu.user_id_string, 
        cu.guest_name, 
        cu.username,
        cu.is_host,
        cu.last_activity,
        cu.is_afk,
        cu.manual_afk,
        c.name as room_name,
        TIMESTAMPDIFF(SECOND, cu.last_activity, NOW()) as inactive_seconds
    FROM chatroom_users cu 
    JOIN chatrooms c ON cu.room_id = c.id 
    WHERE cu.last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
    AND cu.last_activity >= DATE_SUB(NOW(), INTERVAL ? SECOND)
    AND cu.is_afk = 0
    AND cu.manual_afk = 0
    ORDER BY cu.room_id, cu.is_host DESC";
    
    $afk_stmt = $conn->prepare($afk_sql);
    if (!$afk_stmt) {
        throw new Exception('AFK prepare failed: ' . $conn->error);
    }
    
    $afk_stmt->bind_param("ii", $AFK_TIMEOUT, $DISCONNECT_TIMEOUT);
    $afk_stmt->execute();
    $afk_result = $afk_stmt->get_result();
    
    $afk_users = [];
    while ($row = $afk_result->fetch_assoc()) {
        $afk_users[] = $row;
    }
    $afk_stmt->close();
    
    logMessage("Found " . count($afk_users) . " users to mark as AFK");
    
    // Mark users as AFK and send system messages
    foreach ($afk_users as $user) {
        $room_id = $user['room_id'];
        $user_id_string = $user['user_id_string'];
        $display_name = $user['username'] ?: $user['guest_name'] ?: 'Unknown User';
        $inactive_minutes = round($user['inactive_seconds'] / 60, 1);
        
        logMessage("Marking user as AFK: $display_name - Inactive for $inactive_minutes minutes");
        
        // Mark user as AFK
        $afk_update = $conn->prepare("UPDATE chatroom_users SET is_afk = 1, afk_since = NOW() WHERE room_id = ? AND user_id_string = ?");
        $afk_update->bind_param("is", $room_id, $user_id_string);
        $afk_update->execute();
        $afk_update->close();
        
        // Add system message
        $afk_message = "$display_name is now AFK due to inactivity.";
        $add_system_message = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'afk.png', 'system')");
        if ($add_system_message) {
            $add_system_message->bind_param("is", $room_id, $afk_message);
            $add_system_message->execute();
            $add_system_message->close();
        }
        
        logMessage("✅ User marked as AFK: $display_name");
    }
    
    // Handle users returning from AFK (active again)
    $return_sql = "SELECT 
        cu.room_id, 
        cu.user_id_string, 
        cu.guest_name, 
        cu.username,
        cu.is_afk,
        cu.manual_afk,
        c.name as room_name
    FROM chatroom_users cu 
    JOIN chatrooms c ON cu.room_id = c.id 
    WHERE cu.last_activity >= DATE_SUB(NOW(), INTERVAL ? SECOND)
    AND cu.is_afk = 1
    AND cu.manual_afk = 0
    ORDER BY cu.room_id";
    
    $return_stmt = $conn->prepare($return_sql);
    if (!$return_stmt) {
        throw new Exception('Return prepare failed: ' . $conn->error);
    }
    
    $return_stmt->bind_param("i", $AFK_TIMEOUT);
    $return_stmt->execute();
    $return_result = $return_stmt->get_result();
    
    $return_users = [];
    while ($row = $return_result->fetch_assoc()) {
        $return_users[] = $row;
    }
    $return_stmt->close();
    
    logMessage("Found " . count($return_users) . " users returning from AFK");
    
    // Mark users as no longer AFK
    foreach ($return_users as $user) {
        $room_id = $user['room_id'];
        $user_id_string = $user['user_id_string'];
        $display_name = $user['username'] ?: $user['guest_name'] ?: 'Unknown User';
        
        logMessage("Marking user as active (no longer AFK): $display_name");
        
        // Remove AFK status
        $active_update = $conn->prepare("UPDATE chatroom_users SET is_afk = 0, afk_since = NULL WHERE room_id = ? AND user_id_string = ?");
        $active_update->bind_param("is", $room_id, $user_id_string);
        $active_update->execute();
        $active_update->close();
        
        // Add system message
        $active_message = "$display_name is back from AFK.";
        $add_system_message = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'active.png', 'system')");
        if ($add_system_message) {
            $add_system_message->bind_param("is", $room_id, $active_message);
            $add_system_message->execute();
            $add_system_message->close();
        }
        
        logMessage("✅ User marked as active: $display_name");
    }
    
    // Now handle disconnections (60 minutes of inactivity)
    $timeout_sql = "SELECT 
        cu.room_id, 
        cu.user_id_string, 
        cu.guest_name, 
        cu.username,
        cu.is_host,
        cu.last_activity,
        c.name as room_name,
        TIMESTAMPDIFF(SECOND, cu.last_activity, NOW()) as inactive_seconds
    FROM chatroom_users cu 
    JOIN chatrooms c ON cu.room_id = c.id 
    WHERE cu.last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
    ORDER BY cu.room_id, cu.is_host DESC"; // Hosts first for each room
    
    $stmt = $conn->prepare($timeout_sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $DISCONNECT_TIMEOUT);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $inactive_users = [];
    while ($row = $result->fetch_assoc()) {
        $inactive_users[] = $row;
    }
    $stmt->close();
    
    logMessage("Found " . count($inactive_users) . " inactive users for disconnection");
    
    $disconnected_users = [];
    $rooms_processed = [];
    $rooms_deleted = [];
    $host_transfers = [];
    
    foreach ($inactive_users as $user) {
        $room_id = $user['room_id'];
        $user_id_string = $user['user_id_string'];
        $is_host = ($user['is_host'] == 1);
        $inactive_minutes = round($user['inactive_seconds'] / 60, 1);
        $display_name = $user['username'] ?: $user['guest_name'] ?: 'Unknown User';
        
        logMessage("Processing inactive user: $display_name (Host: " . ($is_host ? 'YES' : 'NO') . ") in room {$user['room_name']} - Inactive for $inactive_minutes minutes");
        
        // If this is a host, we need special handling
        if ($is_host) {
            logMessage("Processing inactive HOST: $display_name");
            
            // Find other users in the same room
            $other_users_stmt = $conn->prepare("
                SELECT user_id_string, guest_name, username 
                FROM chatroom_users 
                WHERE room_id = ? AND user_id_string != ? AND is_host = 0
                ORDER BY last_activity DESC
            ");
            $other_users_stmt->bind_param("is", $room_id, $user_id_string);
            $other_users_stmt->execute();
            $other_users_result = $other_users_stmt->get_result();
            
            $other_users = [];
            while ($other_user = $other_users_result->fetch_assoc()) {
                $other_users[] = $other_user;
            }
            $other_users_stmt->close();
            
            if (count($other_users) > 0) {
                // Transfer host to a random user
                $new_host = $other_users[array_rand($other_users)];
                $new_host_name = $new_host['username'] ?: $new_host['guest_name'] ?: 'Unknown User';
                
                logMessage("Transferring host from $display_name to $new_host_name");
                
                // Remove old host
                $remove_old_host = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
                $remove_old_host->bind_param("is", $room_id, $user_id_string);
                $remove_old_host->execute();
                $remove_old_host->close();
                
                // Set new host
                $set_new_host = $conn->prepare("UPDATE chatroom_users SET is_host = 1 WHERE room_id = ? AND user_id_string = ?");
                $set_new_host->bind_param("is", $room_id, $new_host['user_id_string']);
                $set_new_host->execute();
                $set_new_host->close();
                
                // Update room's host info
                $update_room_host = $conn->prepare("UPDATE chatrooms SET host_user_id_string = ? WHERE id = ?");
                if ($update_room_host) {
                    $update_room_host->bind_param("si", $new_host['user_id_string'], $room_id);
                    $update_room_host->execute();
                    $update_room_host->close();
                }
                
                // Add system message
                $transfer_message = "$display_name disconnected due to inactivity. Host privileges transferred to $new_host_name.";
                $add_system_message = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'disconnect.png', 'system')");
                if ($add_system_message) {
                    $add_system_message->bind_param("is", $room_id, $transfer_message);
                    $add_system_message->execute();
                    $add_system_message->close();
                }
                
                $host_transfers[] = [
                    'room_id' => $room_id,
                    'room_name' => $user['room_name'],
                    'old_host' => $display_name,
                    'new_host' => $new_host_name,
                    'inactive_minutes' => $inactive_minutes
                ];
                
                logMessage("✅ Host transfer completed: $display_name → $new_host_name");
                
            } else {
                // No other users, delete the room
                logMessage("No other users in room, deleting room: {$user['room_name']}");
                
                // Delete all room data
                $delete_messages = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
                if ($delete_messages) {
                    $delete_messages->bind_param("i", $room_id);
                    $delete_messages->execute();
                    $delete_messages->close();
                }
                
                $delete_users = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ?");
                if ($delete_users) {
                    $delete_users->bind_param("i", $room_id);
                    $delete_users->execute();
                    $delete_users->close();
                }
                
                $delete_room = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
                if ($delete_room) {
                    $delete_room->bind_param("i", $room_id);
                    $delete_room->execute();
                    $delete_room->close();
                }
                
                $rooms_deleted[] = [
                    'room_id' => $room_id,
                    'room_name' => $user['room_name'],
                    'host' => $display_name,
                    'inactive_minutes' => $inactive_minutes
                ];
                
                logMessage("✅ Room deleted: {$user['room_name']}");
            }
            
        } else {
            // Regular user disconnect
            logMessage("Disconnecting regular user: $display_name");
            
            // Remove user from room
            $remove_user = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
            $remove_user->bind_param("is", $room_id, $user_id_string);
            $remove_user->execute();
            $remove_user->close();
            
            // Add system message
            $disconnect_message = "$display_name disconnected due to inactivity.";
            $add_system_message = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'disconnect.png', 'system')");
            if ($add_system_message) {
                $add_system_message->bind_param("is", $room_id, $disconnect_message);
                $add_system_message->execute();
                $add_system_message->close();
            }
            
            logMessage("✅ User disconnected: $display_name");
        }
        
        $disconnected_users[] = [
            'user_id_string' => $user_id_string,
            'display_name' => $display_name,
            'room_id' => $room_id,
            'room_name' => $user['room_name'],
            'was_host' => $is_host,
            'inactive_minutes' => $inactive_minutes
        ];
    }
    
    $conn->commit();
    
    // Return comprehensive results
    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'timeout_minutes' => $DISCONNECT_TIMEOUT / 60,
        'afk_timeout_minutes' => $AFK_TIMEOUT / 60,
        'total_checked' => count($inactive_users),
        'afk_users_detected' => count($afk_users),
        'users_returned_from_afk' => count($return_users),
        'disconnected_users' => $disconnected_users,
        'host_transfers' => $host_transfers,
        'rooms_deleted' => $rooms_deleted,
        'summary' => [
            'users_marked_afk' => count($afk_users),
            'users_returned_from_afk' => count($return_users),
            'users_disconnected' => count($disconnected_users),
            'hosts_transferred' => count($host_transfers),
            'rooms_deleted' => count($rooms_deleted)
        ]
    ];
    
    logMessage("Disconnect and AFK check completed successfully:");
    logMessage("- Users marked AFK: " . count($afk_users));
    logMessage("- Users returned from AFK: " . count($return_users));
    logMessage("- Users disconnected: " . count($disconnected_users));
    logMessage("- Host transfers: " . count($host_transfers));
    logMessage("- Rooms deleted: " . count($rooms_deleted));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    logMessage("❌ Error during disconnect/AFK check: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Disconnect/AFK check failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>
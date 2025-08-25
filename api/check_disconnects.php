<?php
// api/check_disconnects.php - Main disconnect detection system
header('Content-Type: application/json');

include '../db_connect.php';

// Configuration
$DISCONNECT_TIMEOUT = 15 * 60; // 15 minutes in seconds
$DEBUG_MODE = true; // Set to false in production

function logMessage($message) {
    global $DEBUG_MODE;
    if ($DEBUG_MODE) {
        error_log("DISCONNECT_SYSTEM: " . $message);
    }
}

logMessage("Starting disconnect check...");

try {
    $conn->begin_transaction();
    
    // First, ensure last_activity column exists
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
    
    // Find users who have been inactive for more than the timeout
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
    
    logMessage("Found " . count($inactive_users) . " inactive users");
    
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
        'total_checked' => count($inactive_users),
        'disconnected_users' => $disconnected_users,
        'host_transfers' => $host_transfers,
        'rooms_deleted' => $rooms_deleted,
        'summary' => [
            'users_disconnected' => count($disconnected_users),
            'hosts_transferred' => count($host_transfers),
            'rooms_deleted' => count($rooms_deleted)
        ]
    ];
    
    logMessage("Disconnect check completed successfully:");
    logMessage("- Users disconnected: " . count($disconnected_users));
    logMessage("- Host transfers: " . count($host_transfers));
    logMessage("- Rooms deleted: " . count($rooms_deleted));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    logMessage("❌ Error during disconnect check: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Disconnect check failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>
<?php
// api/check_disconnects.php - Fixed disconnect and AFK detection system
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/activity_config.php';

logActivity("Starting disconnect and AFK check...");

try {
    $conn->begin_transaction();
    
    // Ensure required columns exist
    ensureActivityColumns($conn);
    
    // ===== PHASE 1: MARK USERS AS AFK =====
    // Find users who should be marked as AFK (inactive for AFK_TIMEOUT but not yet disconnected)
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
    
    $afk_stmt->bind_param("ii", AFK_TIMEOUT, DISCONNECT_TIMEOUT);
    $afk_stmt->execute();
    $afk_result = $afk_stmt->get_result();
    
    $afk_users = [];
    while ($row = $afk_result->fetch_assoc()) {
        $afk_users[] = $row;
    }
    $afk_stmt->close();
    
    logActivity("Found " . count($afk_users) . " users to mark as AFK");
    
    // Mark users as AFK and send system messages
    foreach ($afk_users as $user) {
        $room_id = $user['room_id'];
        $user_id_string = $user['user_id_string'];
        $display_name = $user['username'] ?: $user['guest_name'] ?: 'Unknown User';
        $inactive_minutes = round($user['inactive_seconds'] / 60, 1);
        
        logActivity("Marking user as AFK: $display_name in room {$user['room_name']} - Inactive for $inactive_minutes minutes");
        
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
        
        logActivity("✅ User marked as AFK: $display_name");
    }
    
    // ===== PHASE 2: DISCONNECT INACTIVE USERS =====
    // Find users who should be disconnected (inactive for DISCONNECT_TIMEOUT)
    $disconnect_sql = "SELECT 
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
    
    $disconnect_stmt = $conn->prepare($disconnect_sql);
    if (!$disconnect_stmt) {
        throw new Exception('Disconnect prepare failed: ' . $conn->error);
    }
    
    $disconnect_stmt->bind_param("i", DISCONNECT_TIMEOUT);
    $disconnect_stmt->execute();
    $disconnect_result = $disconnect_stmt->get_result();
    
    $inactive_users = [];
    while ($row = $disconnect_result->fetch_assoc()) {
        $inactive_users[] = $row;
    }
    $disconnect_stmt->close();
    
    logActivity("Found " . count($inactive_users) . " users to disconnect");
    
    $disconnected_users = [];
    $host_transfers = [];
    $rooms_deleted = [];
    
    foreach ($inactive_users as $user) {
        $room_id = $user['room_id'];
        $user_id_string = $user['user_id_string'];
        $is_host = ($user['is_host'] == 1);
        $inactive_minutes = round($user['inactive_seconds'] / 60, 1);
        $display_name = $user['username'] ?: $user['guest_name'] ?: 'Unknown User';
        
        logActivity("Processing inactive user: $display_name (Host: " . ($is_host ? 'YES' : 'NO') . ") in room {$user['room_name']} - Inactive for $inactive_minutes minutes");
        
        // If this is a host, we need special handling
        if ($is_host) {
            logActivity("Processing inactive HOST: $display_name");
            
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
                // Transfer host to the most recently active user
                $new_host = $other_users[0]; // Most recently active
                $new_host_name = $new_host['username'] ?: $new_host['guest_name'] ?: 'Unknown User';
                
                logActivity("Transferring host from $display_name to $new_host_name");
                
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
                
                logActivity("✅ Host transfer completed: $display_name → $new_host_name");
                
            } else {
                // No other users, delete the room (unless it's permanent)
                $room_check = $conn->prepare("SELECT permanent FROM chatrooms WHERE id = ?");
                $room_check->bind_param("i", $room_id);
                $room_check->execute();
                $room_result = $room_check->get_result();
                $room_data = $room_result->fetch_assoc();
                $room_check->close();
                
                $is_permanent = ($room_data && $room_data['permanent']);
                
                if ($is_permanent) {
                    // For permanent rooms, just remove the host but keep the room
                    $remove_host = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
                    $remove_host->bind_param("is", $room_id, $user_id_string);
                    $remove_host->execute();
                    $remove_host->close();
                    
                    // Add system message
                    $perm_message = "$display_name (Host) disconnected due to inactivity. Room remains available.";
                    $add_system_message = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'disconnect.png', 'system')");
                    if ($add_system_message) {
                        $add_system_message->bind_param("is", $room_id, $perm_message);
                        $add_system_message->execute();
                        $add_system_message->close();
                    }
                    
                    logActivity("✅ Host removed from permanent room: {$user['room_name']}");
                } else {
                    // Delete the room
                    logActivity("No other users in room, deleting room: {$user['room_name']}");
                    
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
                    
                    logActivity("✅ Room deleted: {$user['room_name']}");
                }
            }
            
        } else {
            // Regular user disconnect
            logActivity("Disconnecting regular user: $display_name");
            
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
            
            logActivity("✅ User disconnected: $display_name");
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
    
    // ===== PHASE 3: CLEAN UP GLOBAL SESSIONS =====
    // Remove inactive users from global_users (session timeout)
    $session_cleanup = $conn->prepare("DELETE FROM global_users WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $session_cleanup->bind_param("i", SESSION_TIMEOUT);
    $session_cleanup->execute();
    $session_cleanups = $session_cleanup->affected_rows;
    $session_cleanup->close();
    
    if ($session_cleanups > 0) {
        logActivity("Cleaned up $session_cleanups expired sessions from global_users");
    }
    
    // Also clean up orphaned chatroom_users where the user is no longer in global_users
    $orphan_cleanup = $conn->query("
        DELETE cu FROM chatroom_users cu 
        LEFT JOIN global_users gu ON cu.user_id_string = gu.user_id_string 
        WHERE gu.user_id_string IS NULL
    ");
    $orphan_cleanups = $conn->affected_rows;
    
    if ($orphan_cleanups > 0) {
        logActivity("Cleaned up $orphan_cleanups orphaned records from chatroom_users");
    }
    
    $conn->commit();
    
    // Return comprehensive results
    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'configuration' => [
            'afk_timeout_minutes' => AFK_TIMEOUT / 60,
            'disconnect_timeout_minutes' => DISCONNECT_TIMEOUT / 60,
            'session_timeout_minutes' => SESSION_TIMEOUT / 60
        ],
        'total_checked' => count($inactive_users),
        'afk_users_detected' => count($afk_users),
        'disconnected_users' => $disconnected_users,
        'host_transfers' => $host_transfers,
        'rooms_deleted' => $rooms_deleted,
        'session_cleanups' => $session_cleanups,
        'orphan_cleanups' => $orphan_cleanups,
        'summary' => [
            'users_marked_afk' => count($afk_users),
            'users_disconnected' => count($disconnected_users),
            'hosts_transferred' => count($host_transfers),
            'rooms_deleted' => count($rooms_deleted),
            'sessions_cleaned' => $session_cleanups,
            'orphans_cleaned' => $orphan_cleanups
        ]
    ];
    
    logActivity("Disconnect and AFK check completed successfully:");
    logActivity("- Users marked AFK: " . count($afk_users));
    logActivity("- Users disconnected: " . count($disconnected_users));
    logActivity("- Host transfers: " . count($host_transfers));
    logActivity("- Rooms deleted: " . count($rooms_deleted));
    logActivity("- Sessions cleaned: $session_cleanups");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    logActivity("❌ Error during disconnect/AFK check: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Disconnect/AFK check failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>
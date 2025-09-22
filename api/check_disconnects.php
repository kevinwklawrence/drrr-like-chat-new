<?php
// api/check_disconnects.php - Updated to preserve global_users
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/activity_config.php';

try {
    // Ensure required columns exist
    ensureActivityColumns($conn);
    
    $conn->begin_transaction();
    
    // Get timeout values
    $afk_timeout = AFK_TIMEOUT;        // 20 minutes
    $disconnect_timeout = DISCONNECT_TIMEOUT; // 80 minutes
    $session_timeout = SESSION_TIMEOUT; // 60 minutes (NOT USED for global_users cleanup anymore)
    
    logActivity("Starting disconnect and AFK check...");
    logActivity("Timeouts: AFK={$afk_timeout}s, Disconnect={$disconnect_timeout}s");
    
    // ===== PHASE 1: IDENTIFY INACTIVE USERS =====
    // Get users who haven't been active in rooms for the disconnect timeout
     $inactive_users_sql = "
        SELECT 
            cu.user_id_string,
            cu.room_id,
            cu.is_host,
            cu.is_afk,
            cu.last_activity,
            cu.username,
            cu.guest_name,
            c.name as room_name,
            c.permanent as room_permanent,
            COALESCE(u.ghost_mode, 0) as ghost_mode,
            TIMESTAMPDIFF(SECOND, cu.last_activity, NOW()) as seconds_inactive
        FROM chatroom_users cu
        JOIN chatrooms c ON cu.room_id = c.id
        LEFT JOIN users u ON cu.user_id = u.id
        WHERE cu.last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
        AND COALESCE(u.ghost_mode, 0) = 0
        ORDER BY cu.last_activity ASC
    ";
    
    $inactive_stmt = $conn->prepare($inactive_users_sql);
    if (!$inactive_stmt) {
        throw new Exception('Failed to prepare inactive users query: ' . $conn->error);
    }
    
    $inactive_stmt->bind_param("i", $disconnect_timeout);
    $inactive_stmt->execute();
    $inactive_result = $inactive_stmt->get_result();
    
    $inactive_users = [];
    while ($row = $inactive_result->fetch_assoc()) {
        $inactive_users[] = $row;
    }
    $inactive_stmt->close();
    
    logActivity("Found " . count($inactive_users) . " inactive users to process");
    
    // ===== PHASE 2: HANDLE AFK AND DISCONNECTIONS =====
    $afk_users = [];
    $disconnected_users = [];
    $host_transfers = [];
    $rooms_deleted = [];
    
    foreach ($inactive_users as $user) {
        $user_id_string = $user['user_id_string'];
        $room_id = $user['room_id'];
        $is_host = $user['is_host'];
        $seconds_inactive = $user['seconds_inactive'];
        $inactive_minutes = round($seconds_inactive / 60, 1);
        $display_name = $user['username'] ?: $user['guest_name'] ?: 'Unknown User';
        
        logActivity("Processing user: $display_name (inactive: {$inactive_minutes}min)");
        
        // Check if user should be marked AFK (but not disconnected yet)
        if ($seconds_inactive >= $afk_timeout && $seconds_inactive < $disconnect_timeout && !$user['is_afk']) {
            // Mark user as AFK
            $afk_stmt = $conn->prepare("UPDATE chatroom_users SET is_afk = 1, afk_since = NOW() WHERE room_id = ? AND user_id_string = ?");
            if ($afk_stmt) {
                $afk_stmt->bind_param("is", $room_id, $user_id_string);
                $afk_stmt->execute();
                $afk_stmt->close();
                
                // Add system message
                $afk_message = "$display_name is now AFK due to inactivity.";
                $add_afk_message = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'afk.png', 'system')");
                if ($add_afk_message) {
                    $add_afk_message->bind_param("is", $room_id, $afk_message);
                    $add_afk_message->execute();
                    $add_afk_message->close();
                }
                
                $afk_users[] = [
                    'user_id_string' => $user_id_string,
                    'display_name' => $display_name,
                    'room_id' => $room_id,
                    'room_name' => $user['room_name'],
                    'inactive_minutes' => $inactive_minutes
                ];
                
                logActivity("✅ User marked AFK: $display_name");
            }
            continue; // Don't disconnect AFK users yet
        }
        
        // Disconnect users who have exceeded the disconnect timeout
        if ($seconds_inactive >= $disconnect_timeout) {
            logActivity("Disconnecting user due to inactivity: $display_name");
            
            if ($is_host) {
                // Handle host disconnect
                logActivity("User is host, checking for other users...");
                
                // Check for other users in the room
                $other_users_check = $conn->prepare("
                    SELECT cu.user_id_string, cu.username, cu.guest_name 
                    FROM chatroom_users cu 
                    WHERE cu.room_id = ? AND cu.user_id_string != ?
                    ORDER BY cu.joined_at ASC 
                    LIMIT 1
                ");
                $other_users_check->bind_param("is", $room_id, $user_id_string);
                $other_users_check->execute();
                $other_result = $other_users_check->get_result();
                
                if ($other_result->num_rows > 0) {
                    // Transfer host to another user
                    $new_host = $other_result->fetch_assoc();
                    $new_host_name = $new_host['username'] ?: $new_host['guest_name'] ?: 'Unknown User';
                    $other_users_check->close();
                    
                    logActivity("Transferring host to: $new_host_name");
                    
                    // Remove old host
                    $remove_host = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
                    $remove_host->bind_param("is", $room_id, $user_id_string);
                    $remove_host->execute();
                    $remove_host->close();
                    
                    // Set new host
                    $set_new_host = $conn->prepare("UPDATE chatroom_users SET is_host = 1 WHERE room_id = ? AND user_id_string = ?");
                    $set_new_host->bind_param("is", $room_id, $new_host['user_id_string']);
                    $set_new_host->execute();
                    $set_new_host->close();
                    
                    // Update room host record
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
                    // No other users, handle based on room type
                    $other_users_check->close();
                    
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
    }
    
    // ===== PHASE 3: CLEAN UP ORPHANED CHATROOM USERS =====
    // REMOVED: Session cleanup from global_users - users now stay until logout
    // Only clean up orphaned chatroom_users where the user is no longer in global_users
    $grace_period = 60; // seconds

$orphan_cleanup = $conn->query("
    DELETE cu FROM chatroom_users cu 
    LEFT JOIN global_users gu ON cu.user_id_string = gu.user_id_string 
    WHERE gu.user_id_string IS NULL
    AND cu.last_activity < DATE_SUB(NOW(), INTERVAL $grace_period SECOND)
");
$orphan_cleanups = $conn->affected_rows;

if ($orphan_cleanups > 0) {
    logActivity("Cleaned up $orphan_cleanups orphaned records from chatroom_users (with $grace_period second grace period)");
}
    $conn->commit();
    
    // Return comprehensive results
    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'configuration' => [
            'afk_timeout_minutes' => $afk_timeout / 60,
            'disconnect_timeout_minutes' => $disconnect_timeout / 60,
            'session_timeout_minutes' => $session_timeout / 60,
            'note' => 'global_users are no longer cleaned up by activity timeout'
        ],
        'total_checked' => count($inactive_users),
        'afk_users_detected' => count($afk_users),
        'disconnected_users' => $disconnected_users,
        'host_transfers' => $host_transfers,
        'rooms_deleted' => $rooms_deleted,
        'session_cleanups' => 0, // CHANGED: No longer cleaning global_users
        'orphan_cleanups' => $orphan_cleanups,
        'summary' => [
            'users_marked_afk' => count($afk_users),
            'users_disconnected' => count($disconnected_users),
            'hosts_transferred' => count($host_transfers),
            'rooms_deleted' => count($rooms_deleted),
            'sessions_cleaned' => 0, // CHANGED: No longer cleaning global_users
            'orphans_cleaned' => $orphan_cleanups
        ]
    ];
    
    logActivity("Disconnect and AFK check completed successfully:");
    logActivity("- Users marked AFK: " . count($afk_users));
    logActivity("- Users disconnected: " . count($disconnected_users));
    logActivity("- Host transfers: " . count($host_transfers));
    logActivity("- Rooms deleted: " . count($rooms_deleted));
    logActivity("- Global users preserved (no session cleanup)");
    
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
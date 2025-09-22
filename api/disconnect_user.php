<?php
// api/disconnect_user.php - Enhanced disconnect with host transfer and room cleanup
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

include '../db_connect.php';

try {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
    
    if (empty($user_id_string)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user']);
        exit;
    }
    
    $conn->begin_transaction();
    
    // Get user's room info before removing them
    $room_check = $conn->prepare("
        SELECT cu.room_id, cu.is_host, c.permanent, c.name as room_name,
               COALESCE(cu.username, cu.guest_name) as display_name
        FROM chatroom_users cu
        JOIN chatrooms c ON cu.room_id = c.id
        WHERE cu.user_id_string = ?
    ");
    $room_check->bind_param("s", $user_id_string);
    $room_check->execute();
    $room_result = $room_check->get_result();
    
    $rooms_affected = 0;
    $host_transfers = 0;
    $rooms_deleted = 0;
    
    while ($room = $room_result->fetch_assoc()) {
        $room_id = $room['room_id'];
        $is_host = $room['is_host'];
        $is_permanent = $room['permanent'];
        $room_name = $room['room_name'];
        $display_name = $room['display_name'] ?: 'Unknown User';
        
        if ($is_host) {
            // Check for other users in the room
            $other_users = $conn->prepare("
                SELECT user_id_string, COALESCE(username, guest_name) as name
                FROM chatroom_users 
                WHERE room_id = ? AND user_id_string != ?
                ORDER BY joined_at ASC 
                LIMIT 1
            ");
            $other_users->bind_param("is", $room_id, $user_id_string);
            $other_users->execute();
            $other_result = $other_users->get_result();
            
            if ($other_result->num_rows > 0) {
                // Transfer host to next user
                $new_host = $other_result->fetch_assoc();
                $new_host_id = $new_host['user_id_string'];
                $new_host_name = $new_host['name'];
                
                // Update new host
                $set_host = $conn->prepare("UPDATE chatroom_users SET is_host = 1 WHERE room_id = ? AND user_id_string = ?");
                $set_host->bind_param("is", $room_id, $new_host_id);
                $set_host->execute();
                $set_host->close();
                
                // Update room record
                $update_room = $conn->prepare("UPDATE chatrooms SET host_user_id_string = ? WHERE id = ?");
                $update_room->bind_param("si", $new_host_id, $room_id);
                $update_room->execute();
                $update_room->close();
                
                // Add system message
                $msg = "$display_name disconnected. Host privileges transferred to $new_host_name.";
                $add_msg = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'disconnect.png', 'system')");
                $add_msg->bind_param("is", $room_id, $msg);
                $add_msg->execute();
                $add_msg->close();
                
                $host_transfers++;
            } else {
                // No other users - delete room if not permanent
                if (!$is_permanent) {
                    // Delete messages
                    $del_msg = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
                    $del_msg->bind_param("i", $room_id);
                    $del_msg->execute();
                    $del_msg->close();
                    
                    // Delete room
                    $del_room = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
                    $del_room->bind_param("i", $room_id);
                    $del_room->execute();
                    $del_room->close();
                    
                    $rooms_deleted++;
                } else {
                    // Permanent room - just add disconnect message
                    $msg = "$display_name (Host) disconnected. Room remains available.";
                    $add_msg = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'disconnect.png', 'system')");
                    $add_msg->bind_param("is", $room_id, $msg);
                    $add_msg->execute();
                    $add_msg->close();
                }
            }
            $other_users->close();
        } else {
            // Regular user - just add disconnect message
            $msg = "$display_name disconnected.";
            $add_msg = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'disconnect.png', 'system')");
            $add_msg->bind_param("is", $room_id, $msg);
            $add_msg->execute();
            $add_msg->close();
        }
        
        $rooms_affected++;
    }
    $room_check->close();
    
    // Remove from all rooms
    $remove_rooms = $conn->prepare("DELETE FROM chatroom_users WHERE user_id_string = ?");
    $remove_rooms->bind_param("s", $user_id_string);
    $remove_rooms->execute();
    $remove_rooms->close();
    
    // Remove from global_users
    $remove_global = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
    $remove_global->bind_param("s", $user_id_string);
    $remove_global->execute();
    $global_affected = $remove_global->affected_rows;
    $remove_global->close();
    
    $conn->commit();
    
    error_log("User disconnected: {$user_id_string} | Rooms: {$rooms_affected} | Hosts transferred: {$host_transfers} | Rooms deleted: {$rooms_deleted}");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User disconnected successfully',
        'rooms_removed' => $rooms_affected,
        'global_removed' => $global_affected,
        'host_transfers' => $host_transfers,
        'rooms_deleted' => $rooms_deleted
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Disconnect API error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Disconnect failed']);
}

$conn->close();
?>
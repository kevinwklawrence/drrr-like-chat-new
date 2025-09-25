<?php
// api/get_room_data.php - Enhanced with automatic host reassignment failsafe
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not in a room']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$current_user_id = $_SESSION['user']['user_id'] ?? '';

try {
    // FAILSAFE: Check if room has no host or empty host_user_id_string
    $host_check = $conn->prepare("
        SELECT 
            c.id,
            c.host_user_id_string,
            (SELECT COUNT(*) FROM chatroom_users WHERE room_id = c.id AND is_host = 1) as host_count,
            (SELECT COUNT(*) FROM chatroom_users WHERE room_id = c.id) as total_users
        FROM chatrooms c 
        WHERE c.id = ?
    ");
    $host_check->bind_param("i", $room_id);
    $host_check->execute();
    $host_result = $host_check->get_result();
    
    if ($host_result->num_rows > 0) {
        $host_data = $host_result->fetch_assoc();
        
        // Check if host reassignment is needed
        $needs_new_host = false;
        $reassignment_reason = '';
        
        // Check various conditions for host reassignment
        if (empty($host_data['host_user_id_string']) || $host_data['host_user_id_string'] === 'null') {
            $needs_new_host = true;
            $reassignment_reason = 'empty_host_id';
        } elseif ($host_data['host_count'] == 0 && $host_data['total_users'] > 0) {
            $needs_new_host = true;
            $reassignment_reason = 'no_host_flag';
        } else {
            // Check if the host_user_id_string user is actually in the room
            $verify_host = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM chatroom_users 
                WHERE room_id = ? AND user_id_string = ?
            ");
            $verify_host->bind_param("is", $room_id, $host_data['host_user_id_string']);
            $verify_host->execute();
            $verify_result = $verify_host->get_result();
            $verify_data = $verify_result->fetch_assoc();
            $verify_host->close();
            
            if ($verify_data['count'] == 0 && $host_data['total_users'] > 0) {
                $needs_new_host = true;
                $reassignment_reason = 'host_not_in_room';
            }
        }
        
        // Perform host reassignment if needed
        if ($needs_new_host && $host_data['total_users'] > 0) {
            error_log("FAILSAFE: Room $room_id needs new host (reason: $reassignment_reason)");
            
            $conn->begin_transaction();
            
            try {
                // First, clear any existing host flags
                $clear_hosts = $conn->prepare("UPDATE chatroom_users SET is_host = 0 WHERE room_id = ?");
                $clear_hosts->bind_param("i", $room_id);
                $clear_hosts->execute();
                $clear_hosts->close();
                
                // Select a new host (oldest user in room)
                $new_host_query = $conn->prepare("
                    SELECT user_id_string, COALESCE(username, guest_name) as display_name
                    FROM chatroom_users 
                    WHERE room_id = ?
                    ORDER BY joined_at ASC, id ASC
                    LIMIT 1
                ");
                $new_host_query->bind_param("i", $room_id);
                $new_host_query->execute();
                $new_host_result = $new_host_query->get_result();
                
                if ($new_host_result->num_rows > 0) {
                    $new_host = $new_host_result->fetch_assoc();
                    $new_host_id = $new_host['user_id_string'];
                    $new_host_name = $new_host['display_name'] ?: 'User';
                    
                    // Set new host in chatroom_users
                    $set_host = $conn->prepare("UPDATE chatroom_users SET is_host = 1 WHERE room_id = ? AND user_id_string = ?");
                    $set_host->bind_param("is", $room_id, $new_host_id);
                    $set_host->execute();
                    $set_host->close();
                    
                    // Update chatrooms table
                    $update_room = $conn->prepare("UPDATE chatrooms SET host_user_id_string = ? WHERE id = ?");
                    $update_room->bind_param("si", $new_host_id, $room_id);
                    $update_room->execute();
                    $update_room->close();
                    
                    // Add system message
                    $msg = "System: $new_host_name has been automatically assigned as host.";
                    $add_msg = $conn->prepare("
                        INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) 
                        VALUES (?, '', ?, 1, NOW(), 'system.png', 'system')
                    ");
                    $add_msg->bind_param("is", $room_id, $msg);
                    $add_msg->execute();
                    $add_msg->close();
                    
                    error_log("FAILSAFE: Successfully assigned $new_host_name as new host for room $room_id");
                }
                $new_host_query->close();
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                error_log("FAILSAFE: Failed to reassign host: " . $e->getMessage());
            }
        }
    }
    $host_check->close();
    
    // Get room data with all users
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            cu.user_id_string,
            cu.username,
            cu.guest_name,
            cu.avatar,
            cu.guest_avatar,
            cu.is_host,
            cu.is_afk,
            cu.afk_since,
            cu.joined_at,
            cu.last_activity,
            COALESCE(cu.username, cu.guest_name) as display_name,
            COALESCE(cu.avatar, cu.guest_avatar, 'default_avatar.jpg') as user_avatar
        FROM chatrooms c
        LEFT JOIN chatroom_users cu ON c.id = cu.room_id
        WHERE c.id = ?
        ORDER BY cu.is_host DESC, cu.joined_at ASC
    ");
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $room = null;
    $users = [];
    $current_user_is_host = false;
    
    while ($row = $result->fetch_assoc()) {
        if (!$room) {
            $room = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'capacity' => $row['capacity'],
                'background' => $row['background'],
                'theme' => $row['theme'] ?? 'default',
                'has_password' => (bool)$row['has_password'],
                'youtube_enabled' => (bool)($row['youtube_enabled'] ?? false),
                'disappearing_messages' => (bool)($row['disappearing_messages'] ?? false),
                'message_lifetime_minutes' => (int)($row['message_lifetime_minutes'] ?? 0),
                'host_user_id_string' => $row['host_user_id_string'] ?? ''
            ];
        }
        
        if ($row['user_id_string']) {
            $is_current_user = ($row['user_id_string'] === $current_user_id);
            
            if ($is_current_user && $row['is_host']) {
                $current_user_is_host = true;
            }
            
            $users[] = [
                'user_id_string' => $row['user_id_string'],
                'display_name' => $row['display_name'] ?: 'Anonymous',
                'avatar' => $row['user_avatar'],
                'is_host' => (bool)$row['is_host'],
                'is_afk' => (bool)$row['is_afk'],
                'afk_since' => $row['afk_since'],
                'joined_at' => $row['joined_at'],
                'last_activity' => $row['last_activity'],
                'is_current_user' => $is_current_user
            ];
        }
    }
    $stmt->close();
    
    // Verify user is still in room
    $user_in_room = false;
    foreach ($users as $user) {
        if ($user['is_current_user']) {
            $user_in_room = true;
            break;
        }
    }
    
    if (!$user_in_room) {
        unset($_SESSION['room_id']);
        echo json_encode([
            'status' => 'error',
            'message' => 'You are not in this room',
            'redirect' => '/lounge'
        ]);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'room' => $room,
        'users' => $users,
        'current_user' => [
            'user_id' => $current_user_id,
            'is_host' => $current_user_is_host
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get room data error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load room data'
    ]);
}

$conn->close();
?>
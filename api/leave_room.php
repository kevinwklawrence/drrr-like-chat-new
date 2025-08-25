<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$action = $_POST['action'] ?? 'leave';
$new_host_user_id = $_POST['new_host_user_id'] ?? '';

if ($room_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$user_id_string = $_SESSION['user']['user_id'] ?? '';

error_log("Leave room: user_id=$user_id, user_id_string=$user_id_string, room_id=$room_id, action=$action");

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Check if user is currently in the room
    $check_stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    $check_stmt->bind_param("is", $room_id, $user_id_string);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'You are not in this room']);
        $check_stmt->close();
        exit;
    }
    
    $user_data = $check_result->fetch_assoc();
    $is_host = $user_data['is_host'];
    $check_stmt->close();
    
    // Get other users in the room - FIXED: specify table aliases
    $other_users_stmt = $conn->prepare("SELECT cu.user_id_string, u.username, cu.guest_name, cu.user_id FROM chatroom_users cu LEFT JOIN users u ON cu.user_id = u.id WHERE cu.room_id = ? AND cu.user_id_string != ?");
    $other_users_stmt->bind_param("is", $room_id, $user_id_string);
    $other_users_stmt->execute();
    $other_users_result = $other_users_stmt->get_result();
    $other_users = [];
    while ($row = $other_users_result->fetch_assoc()) {
        $other_users[] = $row;
    }
    $other_users_stmt->close();
    
    if ($action === 'check_options') {
        if ($is_host && count($other_users) > 0) {
            echo json_encode([
                'status' => 'host_leaving',
                'other_users' => $other_users,
                'show_transfer' => true,
                'last_user' => false
            ]);
        } elseif ($is_host && count($other_users) === 0) {
            echo json_encode([
                'status' => 'host_leaving',
                'other_users' => [],
                'show_transfer' => false,
                'last_user' => true
            ]);
        } else {
            // Regular user leaving
            $leave_stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
            $leave_stmt->bind_param("is", $room_id, $user_id_string);
            $leave_stmt->execute();
            $leave_stmt->close();
            
            $conn->commit();
            echo json_encode(['status' => 'success']);
        }
        exit;
    }
    
    if ($action === 'delete_room') {
        if (!$is_host) {
            echo json_encode(['status' => 'error', 'message' => 'Only the host can delete the room']);
            exit;
        }
        
        // PRESERVE MESSAGES - Don't delete them
        // Add system message that room was deleted
        $delete_message = "This room has been deleted by the host.";
        $system_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, timestamp, type) VALUES (?, 'SYSTEM', ?, NOW(), 'system')");
        if ($system_stmt) {
            $system_stmt->bind_param("is", $room_id, $delete_message);
            $system_stmt->execute();
            $system_stmt->close();
        }
        
        // Remove all users from the room
        $remove_all_stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ?");
        $remove_all_stmt->bind_param("i", $room_id);
        $remove_all_stmt->execute();
        $remove_all_stmt->close();
        
        // Delete the room itself
        $delete_room_stmt = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
        $delete_room_stmt->bind_param("i", $room_id);
        $delete_room_stmt->execute();
        $delete_room_stmt->close();
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Room deleted successfully']);
        exit;
    }
    
    if ($action === 'transfer_host') {
        if (!$is_host) {
            echo json_encode(['status' => 'error', 'message' => 'Only the host can transfer privileges']);
            exit;
        }
        
        if (empty($new_host_user_id)) {
            echo json_encode(['status' => 'error', 'message' => 'New host user ID required']);
            exit;
        }
        
        // Verify new host is in the room
        $verify_stmt = $conn->prepare("SELECT user_id_string FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
        $verify_stmt->bind_param("is", $room_id, $new_host_user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'New host is not in the room']);
            $verify_stmt->close();
            exit;
        }
        $verify_stmt->close();
        
        // Remove current host
        $remove_current_stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
        $remove_current_stmt->bind_param("is", $room_id, $user_id_string);
        $remove_current_stmt->execute();
        $remove_current_stmt->close();
        
        // Set new host
        $set_new_host_stmt = $conn->prepare("UPDATE chatroom_users SET is_host = 1 WHERE room_id = ? AND user_id_string = ?");
        $set_new_host_stmt->bind_param("is", $room_id, $new_host_user_id);
        $set_new_host_stmt->execute();
        $set_new_host_stmt->close();
        
        // Add system message about host transfer - FIXED: specify table alias
        $get_new_host_name_stmt = $conn->prepare("
            SELECT COALESCE(u.username, cu.guest_name) as display_name 
            FROM chatroom_users cu 
            LEFT JOIN users u ON cu.user_id = u.id 
            WHERE cu.room_id = ? AND cu.user_id_string = ?
        ");
        $get_new_host_name_stmt->bind_param("is", $room_id, $new_host_user_id);
        $get_new_host_name_stmt->execute();
        $name_result = $get_new_host_name_stmt->get_result();
        
        if ($name_result->num_rows > 0) {
            $name_data = $name_result->fetch_assoc();
            $new_host_name = $name_data['display_name'];
            
            $transfer_message = $new_host_name . " is now the host of this room.";
            $system_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, timestamp, type) VALUES (?, 'SYSTEM', ?, NOW(), 'system')");
            if ($system_stmt) {
                $system_stmt->bind_param("is", $room_id, $transfer_message);
                $system_stmt->execute();
                $system_stmt->close();
            }
        }
        $get_new_host_name_stmt->close();
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Host transferred successfully']);
        exit;
    }
    
    // Default leave action
    $leave_stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    $leave_stmt->bind_param("is", $room_id, $user_id_string);
    $leave_stmt->execute();
    $leave_stmt->close();
    
    $conn->commit();
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Leave room error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
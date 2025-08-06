<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in leave_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
if ($room_id <= 0) {
    error_log("Invalid room_id in leave_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;

// Get the user_id string for both registered users and guests
$user_id_string = '';
if ($_SESSION['user']['type'] === 'user') {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
} else {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
}

$name = $user_id ? $_SESSION['user']['username'] : $guest_name;
$avatar = $user_id ? $_SESSION['user']['avatar'] : ($_SESSION['user']['avatar'] ?? null);

error_log("Leaving room: room_id=$room_id, user_id=$user_id, guest_name=$guest_name, user_id_string=$user_id_string");

// Check if the leaving user is the host
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    error_log("Prepare failed for host check in leave_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();
$is_leaving_user_host = false;
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $is_leaving_user_host = ($row['is_host'] == 1);
}
$stmt->close();

error_log("Host check result: room_id=$room_id, user_id_string=$user_id_string, is_host=$is_leaving_user_host");
?>
<?php
//session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in leave_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : 'leave'; // 'leave', 'delete_room', or 'transfer_host'
$new_host_user_id_string = isset($_POST['new_host_user_id']) ? $_POST['new_host_user_id'] : '';

if ($room_id <= 0) {
    error_log("Invalid room_id in leave_room.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;

// Get the user_id string for both registered users and guests
$user_id_string = '';
if ($_SESSION['user']['type'] === 'user') {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
} else {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
}

$name = $user_id ? $_SESSION['user']['username'] : $guest_name;
$avatar = $user_id ? $_SESSION['user']['avatar'] : ($_SESSION['user']['avatar'] ?? null);

error_log("Session debug in leave_room.php: " . json_encode($_SESSION['user']));
error_log("Leave room action: room_id=$room_id, user_id=$user_id, guest_name=$guest_name, user_id_string=$user_id_string, action=$action");

if (empty($user_id_string)) {
    error_log("Missing user_id_string in leave_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session - missing user_id. Please log in again.']);
    exit;
}

// Check if the leaving user is the host
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND (user_id = ? OR guest_name = ? OR user_id_string = ?)");
if (!$stmt) {
    error_log("Prepare failed for host check in leave_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iiss", $room_id, $user_id, $guest_name, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();
$is_leaving_user_host = false;
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $is_leaving_user_host = ($row['is_host'] == 1);
}
$stmt->close();

// If the host is leaving, check if there are other users in the room
$other_users = [];
if ($is_leaving_user_host) {
    $stmt = $conn->prepare("
        SELECT cu.user_id, cu.guest_name, cu.user_id_string, u.username 
        FROM chatroom_users cu 
        LEFT JOIN users u ON cu.user_id = u.id 
        WHERE cu.room_id = ? AND cu.user_id_string != ?
    ");
    if (!$stmt) {
        error_log("Prepare failed for other users check in leave_room.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $other_users[] = $row;
    }
    $stmt->close();
    
    error_log("Other users in room: " . count($other_users) . " users found");
    
    // If host is leaving and there are other users, handle the action
    if (count($other_users) > 0) {
        if ($action === 'check_options' || $action === 'leave') {
            // CRITICAL: Return options for host leaving - DON'T ACTUALLY LEAVE YET
            error_log("Host trying to leave, showing options - STOPPING HERE");
            echo json_encode([
                'status' => 'host_leaving',
                'message' => 'You are the host. Choose an action:',
                'other_users' => $other_users
            ]);
            exit; // STOP EXECUTION HERE - DO NOT PROCEED TO NORMAL LEAVE LOGIC
        } elseif ($action === 'delete_room') {
            // Host chose to delete the room
            error_log("Host chose to delete room");
            
            // First delete all users from the room (to avoid foreign key constraint)
            $stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $room_id);
                if ($stmt->execute()) {
                    error_log("Deleted all users from room: room_id=$room_id");
                } else {
                    error_log("Failed to delete users from room: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Then delete all messages from the room
            $stmt = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $room_id);
                if ($stmt->execute()) {
                    error_log("Deleted all messages from room: room_id=$room_id");
                } else {
                    error_log("Failed to delete messages from room: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Finally delete the room itself
            $stmt = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $room_id);
                if ($stmt->execute()) {
                    error_log("Room deleted by host: room_id=$room_id, host=$name");
                } else {
                    error_log("Failed to delete room: " . $stmt->error);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to delete room: ' . $stmt->error]);
                    $stmt->close();
                    exit;
                }
                $stmt->close();
            }
            
            unset($_SESSION['room_id']);
            echo json_encode(['status' => 'success', 'message' => 'Room deleted']);
            exit;
            
        } elseif ($action === 'transfer_host' && !empty($new_host_user_id_string)) {
            // Host chose to transfer host privileges
            error_log("Host chose to transfer host to: $new_host_user_id_string");
            
            // Verify the new host is actually in the room
            $valid_new_host = false;
            foreach ($other_users as $other_user) {
                if ($other_user['user_id_string'] === $new_host_user_id_string) {
                    $valid_new_host = true;
                    break;
                }
            }
            
            if (!$valid_new_host) {
                error_log("Invalid new host selection: $new_host_user_id_string");
                echo json_encode(['status' => 'error', 'message' => 'Invalid new host selection']);
                exit;
            }
            
            // Remove host status from current host
            $stmt = $conn->prepare("UPDATE chatroom_users SET is_host = 0 WHERE room_id = ? AND user_id_string = ?");
            if (!$stmt) {
                error_log("Prepare failed for removing host status in leave_room.php: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param("is", $room_id, $user_id_string);
            $stmt->execute();
            $stmt->close();
            
            // Set new host
            $stmt = $conn->prepare("UPDATE chatroom_users SET is_host = 1 WHERE room_id = ? AND user_id_string = ?");
            if (!$stmt) {
                error_log("Prepare failed for setting new host in leave_room.php: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param("is", $room_id, $new_host_user_id_string);
            $stmt->execute();
            $stmt->close();
            
            // Update room's host_user_id
            $stmt = $conn->prepare("UPDATE chatrooms SET host_user_id = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $new_host_user_id_string, $room_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Get new host name for system message
            $new_host_name = 'Unknown';
            foreach ($other_users as $other_user) {
                if ($other_user['user_id_string'] === $new_host_user_id_string) {
                    $new_host_name = $other_user['username'] ?: $other_user['guest_name'];
                    break;
                }
            }
            
            // Insert system message for host transfer
            $system_message = "$name has transferred host privileges to $new_host_name and left the room.";
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, user_id_string, type) VALUES (?, ?, ?, ?, ?, ?, 'system')");
            if ($stmt) {
                $stmt->bind_param("iissss", $room_id, $user_id, $guest_name, $system_message, $avatar, $user_id_string);
                $stmt->execute();
                $stmt->close();
            }
            
            error_log("Host transferred: room_id=$room_id, old_host=$name, new_host=$new_host_name");
            
            // NOW proceed to remove the old host from the room (continue to normal leave logic below)
        }
    } else {
        // Host is the last user in the room - show delete-only modal
        error_log("Host is the last user in room - showing delete-only modal");
        
        if ($action === 'check_options' || $action === 'leave') {
            // Show delete-only modal for last user
            echo json_encode([
                'status' => 'host_leaving',
                'message' => 'You are the last user in the room. The room will be deleted when you leave.',
                'other_users' => [],
                'show_transfer' => false,  // Don't show transfer host option
                'last_user' => true
            ]);
            exit;
        } elseif ($action === 'delete_room') {
            // Handle room deletion (same logic as above)
            error_log("Last user chose to delete room");
            
            // First delete all users from the room (to avoid foreign key constraint)
            $stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $room_id);
                if ($stmt->execute()) {
                    error_log("Deleted all users from room: room_id=$room_id");
                } else {
                    error_log("Failed to delete users from room: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Then delete all messages from the room
            $stmt = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $room_id);
                if ($stmt->execute()) {
                    error_log("Deleted all messages from room: room_id=$room_id");
                } else {
                    error_log("Failed to delete messages from room: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Finally delete the room itself
            $stmt = $conn->prepare("DELETE FROM chatrooms WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $room_id);
                if ($stmt->execute()) {
                    error_log("Room deleted by last user: room_id=$room_id, user=$name");
                } else {
                    error_log("Failed to delete room: " . $stmt->error);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to delete room: ' . $stmt->error]);
                    $stmt->close();
                    exit;
                }
                $stmt->close();
            }
            
            unset($_SESSION['room_id']);
            echo json_encode(['status' => 'success', 'message' => 'Room deleted']);
            exit;
        }
        // If no specific action, fall through to normal leave logic (shouldn't happen)
    }
} else {
    // Not a host, can leave normally
    error_log("User is not a host, leaving normally");
}

// ONLY REACH THIS POINT IF:
// 1. User is not a host, OR
// 2. Host is leaving but there are no other users, OR  
// 3. Host completed a transfer_host action

// Remove user from room (standard leave process)
$stmt = $conn->prepare("DELETE FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    error_log("Prepare failed in leave_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $room_id, $user_id_string);
if (!$stmt->execute()) {
    error_log("Execute failed in leave_room.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    exit;
}
$affected_rows = $stmt->affected_rows;
$stmt->close();

error_log("User removal result: room_id=$room_id, user_id_string=$user_id_string, affected_rows=$affected_rows");

// Insert system message for leave (unless it was a host transfer, which has its own message)
if (!($is_leaving_user_host && $action === 'transfer_host')) {
    $system_message = "$name has left the room.";
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, user_id_string, type) VALUES (?, ?, ?, ?, ?, ?, 'system')");
    if ($stmt) {
        $stmt->bind_param("iissss", $room_id, $user_id, $guest_name, $system_message, $avatar, $user_id_string);
        if (!$stmt->execute()) {
            error_log("Execute failed for system message in leave_room.php: " . $stmt->error);
        }
        $stmt->close();
    }
}

unset($_SESSION['room_id']);

// Trigger cleanup of empty rooms
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/cleanup_rooms.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if ($response === false) {
    error_log("cURL error in leave_room.php: " . curl_error($ch));
} else {
    error_log("Cleanup triggered: " . $response);
}
curl_close($ch);

echo json_encode(['status' => 'success']);
?>
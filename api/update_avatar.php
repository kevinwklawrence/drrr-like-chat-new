<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$avatar = $_POST['avatar'] ?? '';
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($avatar) || empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

// Validate avatar file exists
if (!file_exists("../images/$avatar")) {
    echo json_encode(['status' => 'error', 'message' => 'Avatar file not found']);
    exit;
}

try {
    // Update session first
    $_SESSION['user']['avatar'] = $avatar;
    
    // Update global_users table if it exists
    $check_table = $conn->query("SHOW TABLES LIKE 'global_users'");
    if ($check_table->num_rows > 0) {
        $username = $_SESSION['user']['username'] ?? null;
        $guest_name = $_SESSION['user']['name'] ?? null;
        $is_admin = $_SESSION['user']['is_admin'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $stmt = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, guest_avatar, is_admin, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), last_activity = CURRENT_TIMESTAMP");
        
        if ($stmt) {
            $stmt->bind_param("sssssis", $user_id_string, $username, $guest_name, $avatar, $avatar, $is_admin, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Update users table for registered users
    if ($_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['id'])) {
        // Check if avatar_memory column exists
        $check_memory_col = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_memory'");
        $has_memory_col = $check_memory_col->num_rows > 0;
        
        if ($has_memory_col) {
            $stmt2 = $conn->prepare("UPDATE users SET avatar = ?, avatar_memory = ? WHERE id = ?");
            if ($stmt2) {
                $stmt2->bind_param("ssi", $avatar, $avatar, $_SESSION['user']['id']);
                $stmt2->execute();
                $stmt2->close();
            }
        } else {
            $stmt2 = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            if ($stmt2) {
                $stmt2->bind_param("si", $avatar, $_SESSION['user']['id']);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    }
    
    // Update any active chatroom_users records
    $chatroom_columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    $chatroom_columns = [];
    while ($row = $chatroom_columns_query->fetch_assoc()) {
        $chatroom_columns[] = $row['Field'];
    }
    
    if (in_array('guest_avatar', $chatroom_columns)) {
        $stmt3 = $conn->prepare("UPDATE chatroom_users SET guest_avatar = ? WHERE user_id_string = ?");
        if ($stmt3) {
            $stmt3->bind_param("ss", $avatar, $user_id_string);
            $stmt3->execute();
            $stmt3->close();
        }
    }
    
    // If there's an avatar column, update that too
    if (in_array('avatar', $chatroom_columns)) {
        $stmt4 = $conn->prepare("UPDATE chatroom_users SET avatar = ? WHERE user_id_string = ?");
        if ($stmt4) {
            $stmt4->bind_param("ss", $avatar, $user_id_string);
            $stmt4->execute();
            $stmt4->close();
        }
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Avatar updated successfully']);
    
} catch (Exception $e) {
    error_log("Update avatar error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update avatar: ' . $e->getMessage()]);
}

$conn->close();
?>
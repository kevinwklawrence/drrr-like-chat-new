<?php
// api/update_user_color.php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$new_color = $_POST['color'] ?? '';

// Validate color
$valid_colors = [
    'black', 'blue', 'purple', 'pink', 'cyan', 'mint', 'orange', 
    'lavender', 'peach', 'green', 'yellow', 'red', 'teal', 
    'indigo', 'emerald', 'rose'
];

if (!in_array($new_color, $valid_colors)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid color selected']);
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'] ?? null;
$user_id_string = $user['user_id'] ?? '';
$user_type = $user['type'] ?? 'guest';

error_log("UPDATE_COLOR: Updating color to '$new_color' for user: $user_id_string (type: $user_type)");

try {
    $conn->begin_transaction();
    
    // Update registered user in users table
    if ($user_type === 'user' && !empty($user_id)) {
        $stmt = $conn->prepare("UPDATE users SET color = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_color, $user_id);
            if ($stmt->execute()) {
                error_log("UPDATE_COLOR: Updated users table for user ID: $user_id");
            } else {
                error_log("UPDATE_COLOR: Failed to update users table: " . $stmt->error);
            }
            $stmt->close();
        }
    }
    
    // Update global_users table (for both guests and registered users)
    if (!empty($user_id_string)) {
        $stmt = $conn->prepare("UPDATE global_users SET color = ? WHERE user_id_string = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $new_color, $user_id_string);
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                error_log("UPDATE_COLOR: Updated global_users table - affected rows: $affected_rows");
                
                // If no rows were affected, insert the user
                if ($affected_rows === 0) {
                    $stmt->close();
                    $username = $user['username'] ?? null;
                    $guest_name = $user['name'] ?? null;
                    $avatar = $user['avatar'] ?? 'default_avatar.jpg';
                    $is_admin = $user['is_admin'] ?? 0;
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    
                    $insert_stmt = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, guest_avatar, color, is_admin, ip_address, last_activity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("ssssssss", $user_id_string, $username, $guest_name, $avatar, $avatar, $new_color, $is_admin, $ip_address);
                        if ($insert_stmt->execute()) {
                            error_log("UPDATE_COLOR: Inserted new record in global_users table");
                        }
                        $insert_stmt->close();
                    }
                }
            } else {
                error_log("UPDATE_COLOR: Failed to update global_users table: " . $stmt->error);
            }
            
            if ($stmt) {
                $stmt->close();
            }
        }
    }
    
    // Update any chatroom_users entries for this user
    if (!empty($user_id_string)) {
        $stmt = $conn->prepare("UPDATE chatroom_users SET color = ? WHERE user_id_string = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $new_color, $user_id_string);
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                error_log("UPDATE_COLOR: Updated chatroom_users table - affected rows: $affected_rows");
            } else {
                error_log("UPDATE_COLOR: Failed to update chatroom_users table: " . $stmt->error);
            }
            $stmt->close();
        }
    }
    
    // Update session data
    $_SESSION['user']['color'] = $new_color;
    
    $conn->commit();
    
    error_log("UPDATE_COLOR: Successfully updated color to '$new_color' for user: $user_id_string");
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Color updated successfully',
        'new_color' => $new_color
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("UPDATE_COLOR: Error updating user color: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}

$conn->close();
?>
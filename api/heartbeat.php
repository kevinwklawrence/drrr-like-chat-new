<?php
// api/heartbeat.php - Keep user marked as active
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

include '../db_connect.php';

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$username = $_SESSION['user']['username'] ?? null;
$guest_name = $_SESSION['user']['name'] ?? null;
$avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
$is_admin = $_SESSION['user']['is_admin'] ?? 0;
$color = $_SESSION['user']['color'] ?? 'black';
$ip_address = $_SERVER['REMOTE_ADDR'];

if (!empty($user_id_string)) {
    // Check if color column exists in global_users
    $check_column_stmt = $conn->prepare("SELECT COUNT(*) as column_exists FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'global_users' AND COLUMN_NAME = 'color'");
    if ($check_column_stmt) {
        $check_column_stmt->execute();
        $result = $check_column_stmt->get_result();
        $row = $result->fetch_assoc();
        $color_column_exists = $row['column_exists'] > 0;
        $check_column_stmt->close();
        
        // If color column doesn't exist, add it
        if (!$color_column_exists) {
            $add_column_sql = "ALTER TABLE global_users ADD COLUMN color VARCHAR(50) DEFAULT 'black' NOT NULL";
            $conn->query($add_column_sql);
        }
    } else {
        $color_column_exists = false;
    }
    
    // Update user's last activity in global_users with current session data
    $columns = "user_id_string, username, guest_name, avatar, guest_avatar, is_admin, ip_address";
    $placeholders = "?, ?, ?, ?, ?, ?, ?";
    $update_clause = "username = VALUES(username), guest_name = VALUES(guest_name), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), is_admin = VALUES(is_admin), ip_address = VALUES(ip_address), last_activity = CURRENT_TIMESTAMP";
    $bind_types = "sssssis";
    $bind_values = [$user_id_string, $username, $guest_name, $avatar, $avatar, $is_admin, $ip_address];
    
    // Only add color if the column exists
    if ($color_column_exists) {
        $columns .= ", color";
        $placeholders .= ", ?";
        $update_clause .= ", color = VALUES(color)";
        $bind_types .= "s";
        $bind_values[] = $color;
    }
    
    $sql = "INSERT INTO global_users ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $update_clause";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param($bind_types, ...$bind_values);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Heartbeat updated',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update heartbeat']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
}
?>
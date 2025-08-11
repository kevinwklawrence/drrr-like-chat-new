<?php
// api/debug_user_color.php - Debug endpoint to check user color status
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user = $_SESSION['user'];
$user_id_string = $user['user_id'] ?? '';
$user_type = $user['type'] ?? 'guest';
$session_color = $user['color'] ?? 'not_set';

$debug_info = [
    'session_data' => [
        'user_id_string' => $user_id_string,
        'type' => $user_type,
        'color_in_session' => $session_color,
        'username' => $user['username'] ?? null,
        'guest_name' => $user['name'] ?? null
    ]
];

try {
    // Check users table (for registered users)
    if ($user_type === 'user' && !empty($user['id'])) {
        $stmt = $conn->prepare("SELECT id, username, color FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $debug_info['users_table'] = $result->fetch_assoc();
            } else {
                $debug_info['users_table'] = 'not_found';
            }
            $stmt->close();
        }
    } else {
        $debug_info['users_table'] = 'not_applicable_for_guest';
    }
    
    // Check global_users table
    if (!empty($user_id_string)) {
        $stmt = $conn->prepare("SELECT user_id_string, username, guest_name, color FROM global_users WHERE user_id_string = ?");
        if ($stmt) {
            $stmt->bind_param("s", $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $debug_info['global_users_table'] = $result->fetch_assoc();
            } else {
                $debug_info['global_users_table'] = 'not_found';
            }
            $stmt->close();
        }
    }
    
    // Check chatroom_users table
    if (!empty($user_id_string)) {
        $stmt = $conn->prepare("SELECT room_id, user_id_string, username, guest_name, color FROM chatroom_users WHERE user_id_string = ?");
        if ($stmt) {
            $stmt->bind_param("s", $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $chatroom_entries = [];
            while ($row = $result->fetch_assoc()) {
                $chatroom_entries[] = $row;
            }
            
            $debug_info['chatroom_users_table'] = $chatroom_entries;
            $stmt->close();
        }
    }
    
    // Check if color column exists in tables
    $color_column_status = [];
    
    $tables_to_check = ['users', 'global_users', 'chatroom_users'];
    foreach ($tables_to_check as $table) {
        $check_column = $conn->query("SHOW COLUMNS FROM $table LIKE 'color'");
        $color_column_status[$table] = $check_column->num_rows > 0 ? 'exists' : 'missing';
    }
    
    $debug_info['color_column_status'] = $color_column_status;
    
    echo json_encode([
        'status' => 'success',
        'debug_info' => $debug_info,
        'recommendations' => [
            'If color columns are missing, run setup_color_columns.php',
            'If global_users shows not_found, the user needs to login again',
            'If colors dont match between tables, use update_user_color.php API'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Debug error: ' . $e->getMessage(),
        'partial_debug_info' => $debug_info
    ]);
}

$conn->close();
?>
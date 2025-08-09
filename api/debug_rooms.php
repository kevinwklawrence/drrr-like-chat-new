<?php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

$debug_info = [
    'step' => 'start',
    'session_user' => $_SESSION['user'] ?? null
];

try {
    // Check if chatrooms table exists
    $tables_query = $conn->query("SHOW TABLES LIKE 'chatrooms'");
    $debug_info['chatrooms_table_exists'] = ($tables_query->num_rows > 0);
    
    if (!$debug_info['chatrooms_table_exists']) {
        $debug_info['error'] = 'chatrooms table does not exist';
        echo json_encode($debug_info);
        exit;
    }
    
    // Check chatrooms table structure
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    $chatroom_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $chatroom_columns[] = $row['Field'];
    }
    $debug_info['chatroom_columns'] = $chatroom_columns;
    
    // Count total rooms
    $count_result = $conn->query("SELECT COUNT(*) as total FROM chatrooms");
    $count_data = $count_result->fetch_assoc();
    $debug_info['total_rooms'] = $count_data['total'];
    
    // Get all rooms (simple query)
    $rooms_result = $conn->query("SELECT * FROM chatrooms ORDER BY created_at DESC LIMIT 10");
    $all_rooms = [];
    while ($row = $rooms_result->fetch_assoc()) {
        $all_rooms[] = $row;
    }
    $debug_info['raw_rooms'] = $all_rooms;
    
    // Check if chatroom_users table exists
    $cu_tables_query = $conn->query("SHOW TABLES LIKE 'chatroom_users'");
    $debug_info['chatroom_users_table_exists'] = ($cu_tables_query->num_rows > 0);
    
    if ($debug_info['chatroom_users_table_exists']) {
        // Count users in rooms
        $users_result = $conn->query("SELECT room_id, COUNT(*) as user_count FROM chatroom_users GROUP BY room_id");
        $user_counts = [];
        while ($row = $users_result->fetch_assoc()) {
            $user_counts[$row['room_id']] = $row['user_count'];
        }
        $debug_info['user_counts'] = $user_counts;
    }
    
    $debug_info['step'] = 'complete';
    
} catch (Exception $e) {
    $debug_info['step'] = 'error';
    $debug_info['error'] = $e->getMessage();
}

echo json_encode($debug_info);
$conn->close();
?>
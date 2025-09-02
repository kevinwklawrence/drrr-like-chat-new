<?php
// Create this file as api/debug_permanent_column.php

header('Content-Type: application/json');
include '../db_connect.php';

try {
    $debug_info = [];
    
    // Check if chatrooms table exists
    $tables_result = $conn->query("SHOW TABLES LIKE 'chatrooms'");
    $debug_info['chatrooms_table_exists'] = $tables_result && $tables_result->num_rows > 0;
    
    if ($debug_info['chatrooms_table_exists']) {
        // Get all columns in chatrooms table
        $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
        $columns = [];
        while ($row = $columns_query->fetch_assoc()) {
            $columns[] = [
                'field' => $row['Field'],
                'type' => $row['Type'],
                'null' => $row['Null'],
                'default' => $row['Default']
            ];
        }
        $debug_info['chatrooms_columns'] = $columns;
        
        // Check specifically for permanent column
        $permanent_column_exists = false;
        foreach ($columns as $col) {
            if ($col['field'] === 'permanent') {
                $permanent_column_exists = true;
                $debug_info['permanent_column'] = $col;
                break;
            }
        }
        $debug_info['permanent_column_exists'] = $permanent_column_exists;
        
        // If permanent column doesn't exist, try to add it
        if (!$permanent_column_exists) {
            $debug_info['attempting_to_add_permanent_column'] = true;
            $add_column_result = $conn->query("ALTER TABLE chatrooms ADD COLUMN permanent TINYINT(1) DEFAULT 0");
            if ($add_column_result) {
                $debug_info['permanent_column_added'] = true;
                $debug_info['permanent_column'] = ['field' => 'permanent', 'type' => 'tinyint(1)', 'default' => '0'];
            } else {
                $debug_info['permanent_column_add_error'] = $conn->error;
            }
        }
        
        // Get sample room data
        $sample_query = $conn->query("SELECT id, name, permanent FROM chatrooms LIMIT 3");
        if ($sample_query) {
            $sample_rooms = [];
            while ($row = $sample_query->fetch_assoc()) {
                $sample_rooms[] = $row;
            }
            $debug_info['sample_rooms'] = $sample_rooms;
        }
        
        // Count permanent rooms
        $permanent_count_query = $conn->query("SELECT COUNT(*) as count FROM chatrooms WHERE permanent = 1");
        if ($permanent_count_query) {
            $count_row = $permanent_count_query->fetch_assoc();
            $debug_info['permanent_rooms_count'] = (int)$count_row['count'];
        }
        
        // Test the full query that get_rooms.php uses
        $test_query = "SELECT id, name, description, capacity, created_at, permanent FROM chatrooms ORDER BY permanent DESC, id DESC LIMIT 5";
        $debug_info['test_query'] = $test_query;
        
        $test_result = $conn->query($test_query);
        if ($test_result) {
            $test_rooms = [];
            while ($row = $test_result->fetch_assoc()) {
                $test_rooms[] = $row;
            }
            $debug_info['test_query_results'] = $test_rooms;
        } else {
            $debug_info['test_query_error'] = $conn->error;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'debug_info' => $debug_info
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug_info' => $debug_info ?? []
    ]);
}

$conn->close();
?>
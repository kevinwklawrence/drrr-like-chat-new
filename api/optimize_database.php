<?php
// api/optimize_database.php - Run database optimizations
// Can be run via cron or called manually: 0 4 * * 0 (weekly at 4am Sunday)

header('Content-Type: application/json');
include __DIR__ . '/../db_connect.php';

// Optional: Add basic auth for manual runs
$secret = $_GET['secret'] ?? '';
$expected_secret = 'your-optimization-secret-here'; // Change this!

// Allow cron jobs without secret
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli && $secret !== $expected_secret) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $stats = [
        'start_time' => date('Y-m-d H:i:s'),
        'operations' => []
    ];
    
    // 1. OPTIMIZE TABLES
    $tables_to_optimize = [
        'messages',
        'chatroom_users',
        'message_events',
        'user_mentions',
        'chatrooms',
        'users',
        'global_users',
        'room_whispers'
    ];
    
    foreach ($tables_to_optimize as $table) {
        $result = $conn->query("OPTIMIZE TABLE `$table`");
        if ($result) {
            $stats['operations'][] = "Optimized table: $table";
        }
    }
    
    // 2. ANALYZE TABLES (update index statistics)
    foreach ($tables_to_optimize as $table) {
        $result = $conn->query("ANALYZE TABLE `$table`");
        if ($result) {
            $stats['operations'][] = "Analyzed table: $table";
        }
    }
    
    // 3. CHECK AND ADD MISSING INDEXES
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_messages_room_timestamp ON messages (room_id, timestamp)",
        "CREATE INDEX IF NOT EXISTS idx_events_lookup ON message_events (id, room_id, created_at)",
        "CREATE INDEX IF NOT EXISTS idx_room_user ON chatroom_users (room_id, user_id_string, last_activity)",
        "CREATE INDEX IF NOT EXISTS idx_mentions_lookup ON user_mentions (room_id, mentioned_user_id_string, is_read)",
        "CREATE INDEX IF NOT EXISTS idx_global_users_activity ON global_users (last_activity)",
        "CREATE INDEX IF NOT EXISTS idx_whispers_room ON room_whispers (room_id, created_at)"
    ];
    
    foreach ($indexes as $index_query) {
        $result = $conn->query($index_query);
        if ($result) {
            $stats['operations'][] = "Added/verified index";
        }
    }
    
    // 4. GET TABLE SIZES AND ROW COUNTS
    $size_query = $conn->query("
        SELECT 
            table_name,
            table_rows,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
            ROUND((data_length / 1024 / 1024), 2) AS data_mb,
            ROUND((index_length / 1024 / 1024), 2) AS index_mb
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
        AND table_name IN ('messages', 'message_events', 'chatroom_users', 'user_mentions', 'global_users')
        ORDER BY (data_length + index_length) DESC
    ");
    
    $stats['table_stats'] = [];
    while ($row = $size_query->fetch_assoc()) {
        $stats['table_stats'][] = [
            'table' => $row['table_name'],
            'rows' => (int)$row['table_rows'],
            'total_mb' => (float)$row['size_mb'],
            'data_mb' => (float)$row['data_mb'],
            'index_mb' => (float)$row['index_mb']
        ];
    }
    
    // 5. CLEAN UP STALE DATA
    // Clean very old disconnected users from global_users
    $cleanup_stmt = $conn->prepare("
        DELETE FROM global_users 
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    if ($cleanup_stmt) {
        $cleanup_stmt->execute();
        $cleaned = $cleanup_stmt->affected_rows;
        $stats['operations'][] = "Cleaned $cleaned stale global_users records";
        $cleanup_stmt->close();
    }
    
    // 6. VACUUM/RECLAIM SPACE (MySQL InnoDB)
    $conn->query("SET GLOBAL innodb_file_per_table = 1");
    $stats['operations'][] = "Enabled file-per-table for future space reclamation";
    
    $stats['end_time'] = date('Y-m-d H:i:s');
    $stats['status'] = 'success';
    
    error_log("DB_OPTIMIZATION: Completed successfully - " . count($stats['operations']) . " operations");
    
    echo json_encode($stats, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("DB_OPTIMIZATION ERROR: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

$conn->close();
?>
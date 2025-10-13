<?php
// api/performance_stats.php - Get real-time performance statistics
session_start();
header('Content-Type: application/json');

// Optional: Require admin
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}

include '../db_connect.php';

try {
    $stats = [
        'timestamp' => date('Y-m-d H:i:s'),
        'tables' => [],
        'health' => [],
        'recommendations' => []
    ];
    
    // 1. TABLE STATISTICS
    $table_query = $conn->query("
        SELECT 
            table_name,
            table_rows,
            ROUND((data_length / 1024 / 1024), 2) AS data_mb,
            ROUND((index_length / 1024 / 1024), 2) AS index_mb,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS total_mb,
            ROUND((data_free / 1024 / 1024), 2) AS fragmented_mb
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
        AND table_name IN ('messages', 'message_events', 'chatroom_users', 'user_mentions', 'global_users', 'chatrooms', 'users')
        ORDER BY (data_length + index_length) DESC
    ");
    
    while ($row = $table_query->fetch_assoc()) {
        $stats['tables'][] = [
            'name' => $row['table_name'],
            'rows' => (int)$row['table_rows'],
            'data_mb' => (float)$row['data_mb'],
            'index_mb' => (float)$row['index_mb'],
            'total_mb' => (float)$row['total_mb'],
            'fragmented_mb' => (float)$row['fragmented_mb']
        ];
        
        // Health checks
        if ($row['table_name'] === 'messages' && $row['table_rows'] > 50000) {
            $stats['recommendations'][] = "Messages table has {$row['table_rows']} rows - consider running cleanup";
        }
        
        if ($row['table_name'] === 'message_events' && $row['table_rows'] > 10000) {
            $stats['recommendations'][] = "Message events table has {$row['table_rows']} rows - cleanup_events.php may not be running";
        }
        
        if ($row['fragmented_mb'] > 100) {
            $stats['recommendations'][] = "{$row['table_name']} has {$row['fragmented_mb']} MB fragmentation - run OPTIMIZE TABLE";
        }
    }
    
    // 2. MESSAGE AGE DISTRIBUTION
    $age_query = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as last_week,
            SUM(CASE WHEN timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last_month,
            SUM(CASE WHEN timestamp > DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as last_3_months,
            SUM(CASE WHEN timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as older_than_3_months
        FROM messages
    ");
    
    if ($age_row = $age_query->fetch_assoc()) {
        $stats['message_age'] = [
            'total' => (int)$age_row['total'],
            'last_week' => (int)$age_row['last_week'],
            'last_month' => (int)$age_row['last_month'],
            'last_3_months' => (int)$age_row['last_3_months'],
            'older_than_3_months' => (int)$age_row['older_than_3_months'],
            'cleanup_potential' => (int)$age_row['older_than_3_months'] . ' messages can be safely cleaned'
        ];
    }
    
    // 3. INDEX HEALTH
    $index_query = $conn->query("
        SELECT 
            table_name,
            index_name,
            cardinality
        FROM information_schema.STATISTICS
        WHERE table_schema = DATABASE()
        AND table_name IN ('messages', 'message_events', 'chatroom_users')
        AND index_name != 'PRIMARY'
        ORDER BY table_name, index_name
    ");
    
    $stats['indexes'] = [];
    while ($idx = $index_query->fetch_assoc()) {
        $stats['indexes'][] = [
            'table' => $idx['table_name'],
            'index' => $idx['index_name'],
            'cardinality' => (int)$idx['cardinality']
        ];
        
        if ($idx['cardinality'] < 100 && $idx['table_name'] === 'messages') {
            $stats['recommendations'][] = "Index {$idx['index_name']} has low cardinality - may need ANALYZE TABLE";
        }
    }
    
    // 4. ACTIVE CONNECTIONS
    $conn_query = $conn->query("
        SELECT 
            COUNT(*) as active_rooms,
            SUM(CASE WHEN permanent = 1 THEN 1 ELSE 0 END) as permanent_rooms,
            SUM(CASE WHEN permanent = 0 THEN 1 ELSE 0 END) as temporary_rooms
        FROM chatrooms
    ");
    
    if ($conn_row = $conn_query->fetch_assoc()) {
        $stats['rooms'] = [
            'active' => (int)$conn_row['active_rooms'],
            'permanent' => (int)$conn_row['permanent_rooms'],
            'temporary' => (int)$conn_row['temporary_rooms']
        ];
    }
    
    // 5. USER ACTIVITY
    $user_query = $conn->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as active_5min,
            SUM(CASE WHEN last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as active_1hour
        FROM global_users
    ");
    
    if ($user_row = $user_query->fetch_assoc()) {
        $stats['users'] = [
            'total_tracked' => (int)$user_row['total_users'],
            'active_5min' => (int)$user_row['active_5min'],
            'active_1hour' => (int)$user_row['active_1hour']
        ];
        
        if ($user_row['total_users'] > 10000) {
            $stats['recommendations'][] = "Global users table is large ({$user_row['total_users']} records) - old records should be cleaned";
        }
    }
    
    // 6. OVERALL HEALTH SCORE
    $health_score = 100;
    foreach ($stats['tables'] as $table) {
        if ($table['fragmented_mb'] > 50) $health_score -= 10;
        if ($table['name'] === 'message_events' && $table['rows'] > 5000) $health_score -= 15;
        if ($table['name'] === 'messages' && $table['rows'] > 100000) $health_score -= 10;
    }
    
    $stats['health']['score'] = max(0, $health_score);
    $stats['health']['status'] = $health_score >= 80 ? 'Excellent' : ($health_score >= 60 ? 'Good' : ($health_score >= 40 ? 'Fair' : 'Needs Attention'));
    
    // 7. CRON JOB STATUS (check last log entries)
    $log_checks = [];
    
    // Check if cleanup has run recently (messages table)
    $last_cleanup = $conn->query("
        SELECT MAX(timestamp) as last_cleanup 
        FROM messages 
        WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    if ($last_cleanup && $row = $last_cleanup->fetch_assoc()) {
        if ($row['last_cleanup']) {
            $log_checks['message_cleanup'] = 'May need to run - old messages detected';
        } else {
            $log_checks['message_cleanup'] = 'Recently run or no old messages';
        }
    }
    
    $stats['cron_status'] = $log_checks;
    
    // 8. QUICK ACTIONS
    $stats['quick_actions'] = [
        'run_cleanup' => '/api/cleanup_messages.php',
        'optimize_db' => '/api/optimize_database.php?secret=YOUR_SECRET',
        'cleanup_events' => '/api/cleanup_events.php'
    ];
    
    if (empty($stats['recommendations'])) {
        $stats['recommendations'][] = 'All systems running optimally! ✅';
    }
    
    $stats['status'] = 'success';
    echo json_encode($stats, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
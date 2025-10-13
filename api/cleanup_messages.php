<?php
// api/cleanup_messages.php - Cleanup old messages from database
// Run via cron: 0 3 * * * /usr/bin/php /path/to/api/cleanup_messages.php

header('Content-Type: application/json');
include __DIR__ . '/../db_connect.php';

// Configuration - adjust these values based on your needs
$retention_days = 90; // Keep messages for 90 days by default
$batch_size = 1000; // Delete in batches to avoid locking
$permanent_room_retention = 365; // Keep permanent room messages longer

try {
    $conn->begin_transaction();
    
    $stats = [
        'start_time' => date('Y-m-d H:i:s'),
        'deleted_messages' => 0,
        'deleted_from_permanent_rooms' => 0,
        'deleted_from_temp_rooms' => 0,
        'cleaned_orphaned_mentions' => 0,
        'cleaned_orphaned_events' => 0
    ];
    
    // 1. Clean messages from temporary (non-permanent) rooms older than retention_days
    $temp_rooms_stmt = $conn->prepare("
        DELETE m FROM messages m
        INNER JOIN chatrooms c ON m.room_id = c.id
        WHERE c.permanent = 0 
        AND m.timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    
    if ($temp_rooms_stmt) {
        $temp_rooms_stmt->bind_param("ii", $retention_days, $batch_size);
        $temp_rooms_stmt->execute();
        $stats['deleted_from_temp_rooms'] = $temp_rooms_stmt->affected_rows;
        $temp_rooms_stmt->close();
    }
    
    // 2. Clean messages from permanent rooms older than extended retention
    $perm_rooms_stmt = $conn->prepare("
        DELETE m FROM messages m
        INNER JOIN chatrooms c ON m.room_id = c.id
        WHERE c.permanent = 1 
        AND m.timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    
    if ($perm_rooms_stmt) {
        $perm_rooms_stmt->bind_param("ii", $permanent_room_retention, $batch_size);
        $perm_rooms_stmt->execute();
        $stats['deleted_from_permanent_rooms'] = $perm_rooms_stmt->affected_rows;
        $perm_rooms_stmt->close();
    }
    
    $stats['deleted_messages'] = $stats['deleted_from_temp_rooms'] + $stats['deleted_from_permanent_rooms'];
    
    // 3. Clean orphaned mentions (mentions pointing to deleted messages)
    $mentions_cleanup = $conn->prepare("
        DELETE um FROM user_mentions um
        LEFT JOIN messages m ON um.message_id = m.id
        WHERE m.id IS NULL
    ");
    
    if ($mentions_cleanup) {
        $mentions_cleanup->execute();
        $stats['cleaned_orphaned_mentions'] = $mentions_cleanup->affected_rows;
        $mentions_cleanup->close();
    }
    
    // 4. Clean orphaned message events (older than 1 hour)
    $events_cleanup = $conn->prepare("
        DELETE FROM message_events 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    
    if ($events_cleanup) {
        $events_cleanup->execute();
        $stats['cleaned_orphaned_events'] = $events_cleanup->affected_rows;
        $events_cleanup->close();
    }
    
    // 5. Clean old room events (older than 1 day)
    $room_events_cleanup = $conn->prepare("
        DELETE FROM room_events 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    
    if ($room_events_cleanup) {
        $room_events_cleanup->execute();
        $room_events_cleanup->close();
    }
    
    $conn->commit();
    
    $stats['end_time'] = date('Y-m-d H:i:s');
    $stats['status'] = 'success';
    
    // Log the cleanup
    error_log("MESSAGE_CLEANUP: Deleted {$stats['deleted_messages']} messages, {$stats['cleaned_orphaned_mentions']} mentions, {$stats['cleaned_orphaned_events']} events");
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    $conn->rollback();
    
    $error_stats = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log("MESSAGE_CLEANUP ERROR: " . $e->getMessage());
    echo json_encode($error_stats);
}

$conn->close();
?>
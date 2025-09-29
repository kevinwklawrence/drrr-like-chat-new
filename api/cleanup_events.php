<?php
// api/cleanup_events.php - Run periodically via cron to clean old events
// Example cron: */15 * * * * /usr/bin/php /path/to/api/cleanup_events.php

include __DIR__ . '/../db_connect.php';

try {
    // Delete events older than 1 hour
    $stmt = $conn->prepare("DELETE FROM message_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    
    error_log("Cleaned up $deleted old message events");
    echo "Cleaned up $deleted old message events\n";
    
} catch (Exception $e) {
    error_log("Event cleanup error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
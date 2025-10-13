<?php
// optimize_performance.php - Add indexes and optimize database
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Admin only");
}

echo "<h2>Performance Optimization</h2>\n<pre>\n";

try {
    echo "=== Adding Database Indexes ===\n";
    
    // 1. Index on message_events for SSE queries
    $conn->query("CREATE INDEX IF NOT EXISTS idx_events_lookup ON message_events (id, room_id, created_at)");
    echo "✓ Added composite index on message_events (id, room_id, created_at)\n";
    
    // 2. Index for chatroom_users lookups
    $conn->query("CREATE INDEX IF NOT EXISTS idx_room_user ON chatroom_users (room_id, user_id_string, last_activity)");
    echo "✓ Added composite index on chatroom_users\n";
    
    // 3. Index for messages queries
    $conn->query("CREATE INDEX IF NOT EXISTS idx_room_timestamp ON messages (room_id, timestamp)");
    echo "✓ Added index on messages (room_id, timestamp)\n";
    
    // 4. Index for user_mentions
    $conn->query("CREATE INDEX IF NOT EXISTS idx_mentions_lookup ON user_mentions (room_id, mentioned_user_id_string, is_read)");
    echo "✓ Added index on user_mentions\n";
    
    echo "\n=== Cleanup Old Events ===\n";
    
    // Clean events older than 1 hour
    $stmt = $conn->prepare("DELETE FROM message_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    echo "✓ Cleaned $deleted old events\n";
    
    echo "\n=== Analysis ===\n";
    
    // Show table sizes
    $result = $conn->query("
        SELECT 
            table_name,
            table_rows,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.TABLES 
        WHERE table_schema = 'duranune_drrr_clone'
        AND table_name IN ('message_events', 'messages', 'chatroom_users', 'user_mentions')
        ORDER BY (data_length + index_length) DESC
    ");
    
    echo "\nTable Sizes:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  {$row['table_name']}: {$row['table_rows']} rows, {$row['size_mb']} MB\n";
    }
    
    echo "\n✅ Optimization Complete!\n";
    echo "\nRecommendations:\n";
    echo "1. Set up cron job for api/cleanup_events.php (every 15 min)\n";
    echo "2. Monitor slow query log\n";
    echo "3. Consider query caching for high-traffic periods\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
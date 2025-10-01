<?php
// api/sse_test.php - Minimal SSE endpoint for testing
session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Disable output buffering
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', 1);
@ob_implicit_flush(1);

while (@ob_get_level()) {
    @ob_end_clean();
}

// Send initial message
echo "data: " . json_encode(['type' => 'test', 'message' => 'SSE Test Connected', 'time' => date('Y-m-d H:i:s')]) . "\n\n";
flush();

// Send 10 test messages
for ($i = 0; $i < 10; $i++) {
    if (connection_status() != CONNECTION_NORMAL) {
        break;
    }
    
    echo "data: " . json_encode([
        'type' => 'test',
        'counter' => $i + 1,
        'message' => "Test message " . ($i + 1),
        'time' => date('Y-m-d H:i:s')
    ]) . "\n\n";
    
    flush();
    sleep(2);
}

echo "data: " . json_encode(['type' => 'complete', 'message' => 'Test completed']) . "\n\n";
flush();
?>
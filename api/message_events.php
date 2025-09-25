<?php
session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Not authorized']) . "\n\n";
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$last_check = isset($_GET['last_check']) ? (int)$_GET['last_check'] : time();

// Send initial connection confirmation
echo "data: " . json_encode(['type' => 'connected', 'room_id' => $room_id]) . "\n\n";
flush();

// Main event loop
$max_iterations = 60; // Run for 60 iterations (5 minutes with 5-second intervals)
$iteration = 0;

while ($iteration < $max_iterations && connection_status() == CONNECTION_NORMAL) {
    try {
        // Check for new message events
        $stmt = $conn->prepare("
            SELECT id, timestamp 
            FROM messages 
            WHERE room_id = ? AND UNIX_TIMESTAMP(timestamp) > ? 
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        
        if ($stmt) {
            $stmt->bind_param("ii", $room_id, $last_check);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = $result->fetch_assoc();
                
                // Send reload event to client
                echo "data: " . json_encode([
                    'type' => 'new_message',
                    'room_id' => $room_id,
                    'timestamp' => $message['timestamp']
                ]) . "\n\n";
                
                $last_check = time();
                flush();
            }
            $stmt->close();
        }
        
        // Check for user updates (joins/leaves)
        $user_stmt = $conn->prepare("
            SELECT COUNT(*) as user_count 
            FROM chatroom_users 
            WHERE room_id = ? AND last_activity > FROM_UNIXTIME(?)
        ");
        
        if ($user_stmt) {
            $user_stmt->bind_param("ii", $room_id, $last_check - 10);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows > 0) {
                $data = $user_result->fetch_assoc();
                if ($data['user_count'] > 0) {
                    echo "data: " . json_encode([
                        'type' => 'user_update',
                        'room_id' => $room_id
                    ]) . "\n\n";
                    flush();
                }
            }
            $user_stmt->close();
        }
        
    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
    }
    
    // Send heartbeat every 30 seconds
    if ($iteration % 6 == 0) {
        echo "data: " . json_encode(['type' => 'heartbeat']) . "\n\n";
        flush();
    }
    
    $iteration++;
    
    // Wait before next iteration
    sleep(5);
    
    // Reset connection after 5 minutes
    if ($iteration >= $max_iterations) {
        echo "data: " . json_encode(['type' => 'reconnect']) . "\n\n";
        flush();
    }
}

$conn->close();
?>
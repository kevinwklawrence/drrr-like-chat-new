<?php
// api/youtube_combined.php - Combined YouTube sync and queue API
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';

// Check if user is in room
$stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
}

$stmt->bind_param("is", $room_id, $user_id_string);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not in room']);
    exit;
}

$user_data = $result->fetch_assoc();
$is_host = ($user_data['is_host'] == 1);
$stmt->close();

// Check if YouTube is enabled
$stmt = $conn->prepare("SELECT youtube_enabled FROM chatrooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room_data = $result->fetch_assoc();
$stmt->close();

if (!$room_data['youtube_enabled']) {
    echo json_encode([
        'status' => 'success',
        'sync_data' => [
            'enabled' => false,
            'video_id' => null,
            'current_time' => 0,
            'is_playing' => false,
            'last_sync_time' => null,
            'sync_token' => null
        ],
        'queue_data' => [
            'suggestions' => [],
            'queue' => [],
            'current_playing' => null
        ]
    ]);
    exit;
}

try {
    // Get sync data
    $stmt = $conn->prepare("
        SELECT current_video_id, `current_time`, is_playing, last_sync_time, sync_token
        FROM room_player_sync 
        WHERE room_id = ?
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sync_data = ['enabled' => true];
    
    if ($result->num_rows > 0) {
        $sync_row = $result->fetch_assoc();
        
        // Calculate adjusted time if playing
        $adjusted_time = $sync_row['current_time'];
        if ($sync_row['is_playing']) {
            $time_diff = time() - strtotime($sync_row['last_sync_time']);
            $adjusted_time += $time_diff;
        }
        
        $sync_data = [
            'enabled' => true,
            'video_id' => $sync_row['current_video_id'],
            'current_time' => round($adjusted_time, 2),
            'is_playing' => (bool)$sync_row['is_playing'],
            'last_sync_time' => $sync_row['last_sync_time'],
            'sync_token' => $sync_row['sync_token']
        ];
    } else {
        $sync_data = [
            'enabled' => true,
            'video_id' => null,
            'current_time' => 0,
            'is_playing' => false,
            'last_sync_time' => null,
            'sync_token' => null
        ];
    }
    $stmt->close();
    
    // Get queue data
    $stmt = $conn->prepare("
        SELECT *, 
            CASE 
                WHEN status = 'suggested' THEN 0
                WHEN status = 'queued' THEN queue_position
                WHEN status = 'playing' THEN -1
                ELSE 999
            END as sort_order
        FROM room_queue 
        WHERE room_id = ? AND status IN ('suggested', 'queued', 'playing')
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    $queue = [];
    $current_playing = null;
    
    while ($row = $result->fetch_assoc()) {
        $item = [
            'id' => $row['id'],
            'video_id' => $row['video_id'],
            'video_title' => $row['video_title'],
            'video_duration' => $row['video_duration'],
            'video_thumbnail' => $row['video_thumbnail'],
            'suggested_by_user_id_string' => $row['suggested_by_user_id_string'],
            'suggested_by_name' => $row['suggested_by_name'],
            'suggested_at' => $row['suggested_at'],
            'queue_position' => $row['queue_position'],
            'status' => $row['status'],
            'youtube_url' => "https://www.youtube.com/watch?v={$row['video_id']}"
        ];
        
        if ($row['status'] === 'suggested') {
            $suggestions[] = $item;
        } elseif ($row['status'] === 'queued') {
            $queue[] = $item;
        } elseif ($row['status'] === 'playing') {
            $current_playing = $item;
        }
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'sync_data' => $sync_data,
        'queue_data' => [
            'suggestions' => $suggestions,
            'queue' => $queue,
            'current_playing' => $current_playing
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
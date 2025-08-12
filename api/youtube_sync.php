<?php
// api/youtube_sync.php - YouTube player synchronization API
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_sync';

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
        ]
    ]);
    exit;
}

try {
    switch ($action) {
        case 'get_sync':
    // Get current sync state
    $stmt = $conn->prepare("
        SELECT current_video_id, `current_time`, is_playing, last_sync_time, sync_token
        FROM room_player_sync 
        WHERE room_id = ?
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $sync_data = $result->fetch_assoc();
        
        // Calculate adjusted time if playing
        $adjusted_time = $sync_data['current_time'];
        if ($sync_data['is_playing']) {
            $time_diff = time() - strtotime($sync_data['last_sync_time']);
            $adjusted_time += $time_diff;
        }
        
        echo json_encode([
            'status' => 'success',
            'sync_data' => [
                'enabled' => true,
                'video_id' => $sync_data['current_video_id'],
                'current_time' => round($adjusted_time, 2),
                'is_playing' => (bool)$sync_data['is_playing'],
                'last_sync_time' => $sync_data['last_sync_time'],
                'sync_token' => $sync_data['sync_token']
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'sync_data' => [
                'enabled' => true,
                'video_id' => null,
                'current_time' => 0,
                'is_playing' => false,
                'last_sync_time' => null,
                'sync_token' => null
            ]
        ]);
    }
    $stmt->close();
    break;
            
        case 'update_time':
    if (!$is_host) {
        echo json_encode(['status' => 'error', 'message' => 'Only hosts can update sync time']);
        exit;
    }
    
    $current_time = (float)($_POST['current_time'] ?? 0);
    $video_id = $_POST['video_id'] ?? '';
    $is_playing = (int)($_POST['is_playing'] ?? 0);
    
    $sync_token = bin2hex(random_bytes(16));
    
    $stmt = $conn->prepare("
        INSERT INTO room_player_sync (room_id, current_video_id, `current_time`, is_playing, sync_token)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        current_video_id = VALUES(current_video_id),
        `current_time` = VALUES(`current_time`),
        is_playing = VALUES(is_playing),
        sync_token = VALUES(sync_token),
        last_sync_time = NOW()
    ");
    $stmt->bind_param("isdis", $room_id, $video_id, $current_time, $is_playing, $sync_token);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Sync time updated',
        'sync_token' => $sync_token
    ]);
    break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
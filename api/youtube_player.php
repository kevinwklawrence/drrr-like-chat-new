<?php
// api/youtube_player.php - YouTube player control API
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$action = $_POST['action'] ?? '';

// Check if user is host
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

if (!$is_host) {
    echo json_encode(['status' => 'error', 'message' => 'Only hosts can control the player']);
    exit;
}

// Check if YouTube is enabled
$stmt = $conn->prepare("SELECT youtube_enabled FROM chatrooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room_data = $result->fetch_assoc();
$stmt->close();

if (!$room_data['youtube_enabled']) {
    echo json_encode(['status' => 'error', 'message' => 'YouTube player not enabled']);
    exit;
}

try {
    $conn->begin_transaction();
    
    switch ($action) {
        case 'play':
            $video_id = $_POST['video_id'] ?? '';
            $current_time = (float)($_POST['current_time'] ?? 0);
            
            if (empty($video_id)) {
                throw new Exception('Video ID required');
            }
            
            // Update room state
            $stmt = $conn->prepare("
                UPDATE chatrooms 
                SET youtube_current_video = ?, 
                    youtube_current_time = ?, 
                    youtube_is_playing = 1,
                    youtube_last_updated = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sdi", $video_id, $current_time, $room_id);
            $stmt->execute();
            $stmt->close();
            
            // Update sync table
            updatePlayerSync($conn, $room_id, $video_id, $current_time, 1);
            
            // Add system message
            $host_name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Host';
            addSystemMessage($conn, $room_id, "$host_name started playing a video");
            
            echo json_encode(['status' => 'success', 'message' => 'Video started']);
            break;
            
        case 'pause':
            $current_time = (float)($_POST['current_time'] ?? 0);
            
            $stmt = $conn->prepare("
                UPDATE chatrooms 
                SET youtube_current_time = ?, 
                    youtube_is_playing = 0,
                    youtube_last_updated = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("di", $current_time, $room_id);
            $stmt->execute();
            $stmt->close();
            
            // Get current video
            $stmt = $conn->prepare("SELECT youtube_current_video FROM chatrooms WHERE id = ?");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $video_data = $result->fetch_assoc();
            $stmt->close();
            
            updatePlayerSync($conn, $room_id, $video_data['youtube_current_video'], $current_time, 0);
            
            echo json_encode(['status' => 'success', 'message' => 'Video paused']);
            break;
            
        case 'resume':
            $current_time = (float)($_POST['current_time'] ?? 0);
            
            $stmt = $conn->prepare("
                UPDATE chatrooms 
                SET youtube_current_time = ?, 
                    youtube_is_playing = 1,
                    youtube_last_updated = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("di", $current_time, $room_id);
            $stmt->execute();
            $stmt->close();
            
            // Get current video
            $stmt = $conn->prepare("SELECT youtube_current_video FROM chatrooms WHERE id = ?");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $video_data = $result->fetch_assoc();
            $stmt->close();
            
            updatePlayerSync($conn, $room_id, $video_data['youtube_current_video'], $current_time, 1);
            
            echo json_encode(['status' => 'success', 'message' => 'Video resumed']);
            break;
            
        case 'seek':
            $seek_time = (float)($_POST['seek_time'] ?? 0);
            
            $stmt = $conn->prepare("
                UPDATE chatrooms 
                SET youtube_current_time = ?,
                    youtube_last_updated = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("di", $seek_time, $room_id);
            $stmt->execute();
            $stmt->close();
            
            // Get current video and playing state
            $stmt = $conn->prepare("SELECT youtube_current_video, youtube_is_playing FROM chatrooms WHERE id = ?");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $video_data = $result->fetch_assoc();
            $stmt->close();
            
            updatePlayerSync($conn, $room_id, $video_data['youtube_current_video'], $seek_time, $video_data['youtube_is_playing']);
            
            echo json_encode(['status' => 'success', 'message' => 'Video seeked']);
            break;
            
        case 'skip':
            // Get next video from queue
            $next_video = getNextQueuedVideo($conn, $room_id);
            
            if ($next_video) {
                // Mark current as played
                markCurrentVideoAsPlayed($conn, $room_id);
                
                // Start next video
                $stmt = $conn->prepare("
                    UPDATE chatrooms 
                    SET youtube_current_video = ?, 
                        youtube_current_time = 0, 
                        youtube_is_playing = 1,
                        youtube_last_updated = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("si", $next_video['video_id'], $room_id);
                $stmt->execute();
                $stmt->close();
                
                // Mark as playing in queue
                $stmt = $conn->prepare("UPDATE room_queue SET status = 'playing' WHERE id = ?");
                $stmt->bind_param("i", $next_video['id']);
                $stmt->execute();
                $stmt->close();
                
                updatePlayerSync($conn, $room_id, $next_video['video_id'], 0, 1);
                
                $host_name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Host';
                addSystemMessage($conn, $room_id, "$host_name skipped to next video: " . $next_video['video_title']);
                
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Skipped to next video',
                    'next_video' => $next_video
                ]);
            } else {
                // No more videos
                $stmt = $conn->prepare("
                    UPDATE chatrooms 
                    SET youtube_current_video = NULL, 
                        youtube_current_time = 0, 
                        youtube_is_playing = 0,
                        youtube_last_updated = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $stmt->close();
                
                updatePlayerSync($conn, $room_id, null, 0, 0);
                
                echo json_encode(['status' => 'success', 'message' => 'No more videos in queue']);
            }
            break;
            
        case 'stop':
            $stmt = $conn->prepare("
                UPDATE chatrooms 
                SET youtube_current_video = NULL, 
                    youtube_current_time = 0, 
                    youtube_is_playing = 0,
                    youtube_last_updated = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $stmt->close();
            
            updatePlayerSync($conn, $room_id, null, 0, 0);
            
            $host_name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Host';
            $avatar = "<i class='fas fa-youtube'></i>";
            addSystemMessage($conn, $room_id, "$host_name stopped the video");

            
            
            echo json_encode(['status' => 'success', 'message' => 'Video stopped']);
            break;
            
        case 'get_state':
            $stmt = $conn->prepare("
                SELECT youtube_current_video, youtube_current_time, youtube_is_playing, youtube_last_updated
                FROM chatrooms WHERE id = ?
            ");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $player_state = $result->fetch_assoc();
            $stmt->close();
            
            echo json_encode([
                'status' => 'success',
                'player_state' => [
                    'video_id' => $player_state['youtube_current_video'],
                    'current_time' => (float)$player_state['youtube_current_time'],
                    'is_playing' => (bool)$player_state['youtube_is_playing'],
                    'last_updated' => $player_state['youtube_last_updated']
                ]
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    $conn->commit();
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();

// Helper functions
function updatePlayerSync($conn, $room_id, $video_id, $current_time, $is_playing) {
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
}

function addSystemMessage($conn, $room_id, $message) {
    $stmt = $conn->prepare("
        INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, type) 
        VALUES (?, '', ?, 1, NOW(), 'system')
    ");
    $stmt->bind_param("is", $room_id, $message);
    $stmt->execute();
    $stmt->close();
}

function getNextQueuedVideo($conn, $room_id) {
    $stmt = $conn->prepare("
        SELECT * FROM room_queue 
        WHERE room_id = ? AND status = 'queued' AND approved_by_host = 1
        ORDER BY queue_position ASC, id ASC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $next_video = $result->fetch_assoc();
    $stmt->close();
    
    return $next_video;
}

function markCurrentVideoAsPlayed($conn, $room_id) {
    $stmt = $conn->prepare("UPDATE room_queue SET status = 'played' WHERE room_id = ? AND status = 'playing'");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $stmt->close();
}
?>
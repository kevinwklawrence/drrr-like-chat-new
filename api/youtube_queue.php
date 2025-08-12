<?php
// api/youtube_queue.php - YouTube queue management API
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
    echo json_encode(['status' => 'error', 'message' => 'YouTube player not enabled']);
    exit;
}

try {
    $conn->begin_transaction();
    
    switch ($action) {
        case 'suggest':
            $video_id = extractVideoId($_POST['video_url'] ?? '');
            $video_title = trim($_POST['video_title'] ?? '');
            
            if (empty($video_id)) {
                throw new Exception('Invalid YouTube URL');
            }
            
            // Get video info if title not provided
            if (empty($video_title)) {
                $video_title = 'YouTube Video';
                $video_duration = '';
                $video_thumbnail = "https://img.youtube.com/vi/$video_id/maxresdefault.jpg";
            } else {
                $video_duration = '';
                $video_thumbnail = "https://img.youtube.com/vi/$video_id/maxresdefault.jpg";
            }
            
            // Check if video already exists
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM room_queue 
                WHERE room_id = ? AND video_id = ? AND status IN ('suggested', 'queued', 'playing')
            ");
            $stmt->bind_param("is", $room_id, $video_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $count_data = $result->fetch_assoc();
            $stmt->close();
            
            if ($count_data['count'] > 0) {
                throw new Exception('This video is already in the queue');
            }
            
            // Add suggestion
            $user_name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Unknown User';
            $stmt = $conn->prepare("
                INSERT INTO room_queue 
                (room_id, video_id, video_title, video_duration, video_thumbnail, 
                 suggested_by_user_id_string, suggested_by_name, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'suggested')
            ");
            $stmt->bind_param("issssss", $room_id, $video_id, $video_title, $video_duration, 
                            $video_thumbnail, $user_id_string, $user_name);
            $stmt->execute();
            $suggestion_id = $conn->insert_id;
            $stmt->close();
            
            // Add system message
            addSystemMessage($conn, $room_id, "$user_name suggested a video: $video_title");
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Video suggested successfully',
                'suggestion_id' => $suggestion_id
            ]);
            break;
            
        case 'approve':
            if (!$is_host) {
                throw new Exception('Only hosts can approve suggestions');
            }
            
            $suggestion_id = (int)($_POST['suggestion_id'] ?? 0);
            if ($suggestion_id <= 0) {
                throw new Exception('Invalid suggestion ID');
            }
            
            // Get max queue position
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(queue_position), 0) + 1 as next_position 
                FROM room_queue WHERE room_id = ? AND status = 'queued'
            ");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $pos_data = $result->fetch_assoc();
            $next_position = $pos_data['next_position'];
            $stmt->close();
            
            // Approve suggestion
            $stmt = $conn->prepare("
                UPDATE room_queue 
                SET status = 'queued', 
                    approved_by_host = 1, 
                    approved_at = NOW(),
                    queue_position = ?
                WHERE id = ? AND room_id = ? AND status = 'suggested'
            ");
            $stmt->bind_param("iii", $next_position, $suggestion_id, $room_id);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Suggestion not found');
            }
            $stmt->close();
            
            // Get suggestion details
            $stmt = $conn->prepare("
                SELECT video_title, suggested_by_name 
                FROM room_queue WHERE id = ?
            ");
            $stmt->bind_param("i", $suggestion_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $suggestion_data = $result->fetch_assoc();
            $stmt->close();
            
            $host_name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Host';
            addSystemMessage($conn, $room_id, 
                "$host_name approved {$suggestion_data['suggested_by_name']}'s suggestion: {$suggestion_data['video_title']}");
            
            echo json_encode(['status' => 'success', 'message' => 'Video approved and added to queue']);
            break;
            
        case 'deny':
            if (!$is_host) {
                throw new Exception('Only hosts can deny suggestions');
            }
            
            $suggestion_id = (int)($_POST['suggestion_id'] ?? 0);
            if ($suggestion_id <= 0) {
                throw new Exception('Invalid suggestion ID');
            }
            
            $stmt = $conn->prepare("
                DELETE FROM room_queue 
                WHERE id = ? AND room_id = ? AND status = 'suggested'
            ");
            $stmt->bind_param("ii", $suggestion_id, $room_id);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Suggestion not found');
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'message' => 'Suggestion denied']);
            break;
            
        case 'remove':
            if (!$is_host) {
                throw new Exception('Only hosts can remove videos from queue');
            }
            
            $queue_id = (int)($_POST['queue_id'] ?? 0);
            if ($queue_id <= 0) {
                throw new Exception('Invalid queue ID');
            }
            
            // Get video details
            $stmt = $conn->prepare("
                SELECT video_title, queue_position 
                FROM room_queue WHERE id = ? AND room_id = ? AND status = 'queued'
            ");
            $stmt->bind_param("ii", $queue_id, $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $video_data = $result->fetch_assoc();
            $stmt->close();
            
            if (!$video_data) {
                throw new Exception('Video not found in queue');
            }
            
            // Remove from queue
            $stmt = $conn->prepare("DELETE FROM room_queue WHERE id = ?");
            $stmt->bind_param("i", $queue_id);
            $stmt->execute();
            $stmt->close();
            
            // Reorder remaining items
            $stmt = $conn->prepare("
                UPDATE room_queue 
                SET queue_position = queue_position - 1 
                WHERE room_id = ? AND status = 'queued' AND queue_position > ?
            ");
            $stmt->bind_param("ii", $room_id, $video_data['queue_position']);
            $stmt->execute();
            $stmt->close();
            
            $host_name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Host';
            addSystemMessage($conn, $room_id, "$host_name removed from queue: {$video_data['video_title']}");
            
            echo json_encode(['status' => 'success', 'message' => 'Video removed from queue']);
            break;
            
        case 'get':
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
                'data' => [
                    'suggestions' => $suggestions,
                    'queue' => $queue,
                    'current_playing' => $current_playing
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
function extractVideoId($url) {
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    // If it's just a video ID
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
        return $url;
    }
    
    return '';
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
?>
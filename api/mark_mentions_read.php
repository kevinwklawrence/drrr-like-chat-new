<?php
// api/mark_mentions_read.php - Mark mentions as read
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Try multiple possible paths for db_connect.php
$db_paths = [
    '../db_connect.php',
    './db_connect.php',
    dirname(__DIR__) . '/db_connect.php',
    $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php'
];

$db_connected = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$mention_id = isset($_POST['mention_id']) ? (int)$_POST['mention_id'] : 0;
$mark_all = isset($_POST['mark_all']) ? (bool)$_POST['mark_all'] : false;

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    if ($mark_all) {
        // Mark all mentions as read for this user in this room
        $stmt = $conn->prepare("
            UPDATE user_mentions 
            SET is_read = TRUE 
            WHERE room_id = ? 
            AND mentioned_user_id_string = ? 
            AND is_read = FALSE
        ");
        
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("is", $room_id, $user_id_string);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($success) {
            echo json_encode([
                'status' => 'success',
                'message' => 'All mentions marked as read',
                'marked_count' => $affected_rows
            ]);
        } else {
            throw new Exception('Failed to mark mentions as read');
        }
        
    } else if ($mention_id > 0) {
        // Mark specific mention as read
        $stmt = $conn->prepare("
            UPDATE user_mentions 
            SET is_read = TRUE 
            WHERE id = ? 
            AND room_id = ? 
            AND mentioned_user_id_string = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("iis", $mention_id, $room_id, $user_id_string);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($success && $affected_rows > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Mention marked as read'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Mention not found or already read'
            ]);
        }
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    }
    
} catch (Exception $e) {
    error_log("Mark mentions read error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark mentions as read: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
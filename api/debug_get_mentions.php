<?php
// debug_get_mentions.php - Detailed debugging version
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

$debug = [];
$debug['step'] = 'Starting';

try {
    // Check session
    if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not authorized', 'debug' => $debug]);
        exit;
    }
    
    $debug['session_user'] = isset($_SESSION['user']) ? 'YES' : 'NO';
    $debug['session_room'] = isset($_SESSION['room_id']) ? $_SESSION['room_id'] : 'NO';
    $debug['step'] = 'Session check passed';
    
    // Try to find database connection
    $db_paths = [
        '../db_connect.php',
        './db_connect.php',
        dirname(__DIR__) . '/db_connect.php',
        $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php',
        'db_connect.php'
    ];
    
    $db_connected = false;
    $debug['tried_paths'] = [];
    
    foreach ($db_paths as $path) {
        $debug['tried_paths'][] = $path . ' - ' . (file_exists($path) ? 'EXISTS' : 'NOT FOUND');
        if (file_exists($path)) {
            include $path;
            $db_connected = true;
            $debug['db_path_used'] = $path;
            break;
        }
    }
    
    if (!$db_connected) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection file not found', 'debug' => $debug]);
        exit;
    }
    
    $debug['step'] = 'DB file included';
    
    // Check if $conn exists
    if (!isset($conn)) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection variable not set', 'debug' => $debug]);
        exit;
    }
    
    $debug['step'] = 'DB connection exists';
    
    $room_id = (int)$_SESSION['room_id'];
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
    
    if (empty($user_id_string)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user session', 'debug' => $debug]);
        exit;
    }
    
    $debug['room_id'] = $room_id;
    $debug['user_id_string'] = $user_id_string;
    $debug['step'] = 'Parameters extracted';
    
    // Test database connection
    $test_query = $conn->query("SELECT 1");
    if (!$test_query) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->error, 'debug' => $debug]);
        exit;
    }
    
    $debug['step'] = 'DB connection tested';
    
    // Check if user_mentions table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_mentions'");
    if ($table_check->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'user_mentions table does not exist', 'debug' => $debug]);
        exit;
    }
    
    $debug['step'] = 'Table exists';
    
    // Try a simple count first
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_mentions WHERE room_id = ? AND mentioned_user_id_string = ?");
    if (!$count_stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Count query prepare failed: ' . $conn->error, 'debug' => $debug]);
        exit;
    }
    
    $count_stmt->bind_param("is", $room_id, $user_id_string);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $debug['total_mentions'] = $count_row['total'];
    $count_stmt->close();
    
    // Try the main query
    $stmt = $conn->prepare("
        SELECT 
            um.id,
            um.message_id,
            um.mention_type,
            um.created_at,
            um.is_read,
            m.message,
            m.timestamp as message_timestamp,
            m.user_id_string as sender_user_id_string,
            m.guest_name as sender_guest_name,
            m.avatar as sender_avatar
        FROM user_mentions um
        LEFT JOIN messages m ON um.message_id = m.id
        WHERE um.room_id = ? 
        AND um.mentioned_user_id_string = ? 
        AND um.is_read = FALSE
        ORDER BY um.created_at DESC
        LIMIT 10
    ");
    
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Main query prepare failed: ' . $conn->error, 'debug' => $debug]);
        exit;
    }
    
    $debug['step'] = 'Query prepared';
    
    $stmt->bind_param("is", $room_id, $user_id_string);
    $success = $stmt->execute();
    
    if (!$success) {
        echo json_encode(['status' => 'error', 'message' => 'Query execute failed: ' . $stmt->error, 'debug' => $debug]);
        exit;
    }
    
    $debug['step'] = 'Query executed';
    
    $result = $stmt->get_result();
    $mentions = [];
    $row_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $row_count++;
        $sender_name = $row['sender_guest_name'] ?: 'Unknown';
        $sender_avatar = $row['sender_avatar'] ?: 'default_avatar.jpg';
        
        $mentions[] = [
            'id' => $row['id'],
            'message_id' => $row['message_id'],
            'type' => $row['mention_type'],
            'message' => $row['message'] ?: 'No message content',
            'sender_name' => $sender_name,
            'sender_avatar' => $sender_avatar,
            'sender_user_id_string' => $row['sender_user_id_string'],
            'timestamp' => $row['message_timestamp'],
            'created_at' => $row['created_at'],
            'is_read' => $row['is_read']
        ];
    }
    
    $debug['rows_found'] = $row_count;
    $debug['step'] = 'Query completed';
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'mentions' => $mentions,
        'unread_count' => count($mentions),
        'debug' => $debug
    ]);
    
} catch (Exception $e) {
    $debug['exception'] = $e->getMessage();
    $debug['exception_file'] = $e->getFile();
    $debug['exception_line'] = $e->getLine();
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage(), 'debug' => $debug]);
} catch (Error $e) {
    $debug['fatal_error'] = $e->getMessage();
    $debug['error_file'] = $e->getFile();
    $debug['error_line'] = $e->getLine();
    echo json_encode(['status' => 'error', 'message' => 'Fatal error: ' . $e->getMessage(), 'debug' => $debug]);
}

if (isset($conn)) {
    $conn->close();
}
?>
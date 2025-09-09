<?php
// api/validate-room-membership.php
include '../db_connect.php'; // Adjust path as needed

// Prevent any HTML output that could break JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'reason' => 'Method not allowed']);
    exit;
}

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['room_id']) || !isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode([
            'valid' => false,
            'reason' => 'Missing required parameters'
        ]);
        exit;
    }
    
    $roomId = $input['room_id'];
    $userId = $input['user_id'];
    
    // Include your database configuration
    require_once '../db_connect.php'; // Adjust path as needed
    
    // Alternative: Define database connection here if config file doesn't exist
    /*
    $host = 'localhost';
    $dbname = 'your_database_name';
    $username = 'your_db_username';
    $password = 'your_db_password';
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    */
    
    $pdo = new PDO($dsn, $db_username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Check if user is in the room
    $stmt = $pdo->prepare("
        SELECT u.user_id_string, u.username, u.guest_name, u.avatar, u.color, r.room_name 
        FROM room_users ru 
        JOIN users u ON ru.user_id_string = u.user_id_string 
        JOIN rooms r ON ru.room_id = r.room_id 
        WHERE ru.room_id = ? AND ru.user_id_string = ? AND ru.is_active = 1
    ");
    
    $stmt->execute([$roomId, $userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // User is valid, return auth data
        echo json_encode([
            'valid' => true,
            'authData' => [
                'user_id_string' => $user['user_id_string'],
                'room_id' => $roomId,
                'username' => $user['username'],
                'guest_name' => $user['guest_name'],
                'avatar' => $user['avatar'],
                'color' => $user['color']
            ]
        ]);
    } else {
        // Check if user exists but not in room
        $userStmt = $pdo->prepare("SELECT user_id_string FROM users WHERE user_id_string = ?");
        $userStmt->execute([$userId]);
        $userExists = $userStmt->fetch();
        
        if ($userExists) {
            // User exists but not in room - they can be re-added
            echo json_encode([
                'valid' => false,
                'reason' => 'User not in room',
                'needsRejoin' => true
            ]);
        } else {
            // User doesn't exist
            echo json_encode([
                'valid' => false,
                'reason' => 'User not found'
            ]);
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error in validate-room-membership: " . $e->getMessage());
    echo json_encode([
        'valid' => false,
        'reason' => 'Database connection error'
    ]);
} catch (Exception $e) {
    error_log("General error in validate-room-membership: " . $e->getMessage());
    echo json_encode([
        'valid' => false,
        'reason' => 'Server error'
    ]);
}
?>

<?php
// api/rejoin-room.php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['room_id']) || !isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        exit;
    }
    
    $roomId = $input['room_id'];
    $userId = $input['user_id'];
    
    require_once '../db_connect.php';
    
    $pdo = new PDO($dsn, $db_username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Check if user exists
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE user_id_string = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    // Check if room exists and is active
    $roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ? AND is_active = 1");
    $roomStmt->execute([$roomId]);
    $room = $roomStmt->fetch();
    
    if (!$room) {
        echo json_encode([
            'success' => false,
            'message' => 'Room not found or inactive'
        ]);
        exit;
    }
    
    // Re-add user to room (or update existing record)
    $rejoinStmt = $pdo->prepare("
        INSERT INTO room_users (room_id, user_id_string, joined_at, is_active, last_seen) 
        VALUES (?, ?, NOW(), 1, NOW()) 
        ON DUPLICATE KEY UPDATE 
        is_active = 1, 
        last_seen = NOW(),
        rejoined_at = NOW()
    ");
    
    $success = $rejoinStmt->execute([$roomId, $userId]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Successfully rejoined room'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to rejoin room'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Room rejoin error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}
?>
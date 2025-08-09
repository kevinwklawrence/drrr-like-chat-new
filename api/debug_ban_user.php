<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$debug_info = [
    'step' => 'start',
    'post_data' => $_POST,
    'session_user' => $_SESSION['user'] ?? null,
    'session_room_id' => $_SESSION['room_id'] ?? null
];

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    $debug_info['step'] = 'no_session';
    echo json_encode($debug_info);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

$debug_info['room_id'] = $room_id;
$debug_info['current_user_id_string'] = $current_user_id_string;

// Check if current user is host or admin
$is_authorized = false;
if (!empty($current_user_id_string)) {
    $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $current_user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $is_authorized = ($user_data['is_host'] == 1);
            $debug_info['host_check'] = $user_data;
        } else {
            $debug_info['host_check'] = 'user_not_found_in_room';
        }
        $stmt->close();
    }
}

// Also check if user is admin
if (!$is_authorized && isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    $is_authorized = true;
    $debug_info['admin_override'] = true;
}

$debug_info['is_authorized'] = $is_authorized;

if (!$is_authorized) {
    $debug_info['step'] = 'not_authorized';
    echo json_encode($debug_info);
    exit;
}

// Get POST data
$user_id_string = $_POST['user_id_string'] ?? '';
$duration = $_POST['duration'] ?? '';
$reason = $_POST['reason'] ?? '';

$debug_info['ban_data'] = [
    'user_id_string' => $user_id_string,
    'duration' => $duration,
    'reason' => $reason
];

if (empty($user_id_string) || empty($duration)) {
    $debug_info['step'] = 'missing_params';
    echo json_encode($debug_info);
    exit;
}

// Check if room_bans table exists
$check_table = $conn->query("SHOW TABLES LIKE 'room_bans'");
if ($check_table->num_rows === 0) {
    $debug_info['step'] = 'table_not_exists';
    echo json_encode($debug_info);
    exit;
}

$debug_info['step'] = 'table_exists';

// Calculate ban expiry
$ban_until = null;
if ($duration !== 'permanent') {
    $duration_seconds = (int)$duration;
    $ban_until = date('Y-m-d H:i:s', time() + $duration_seconds);
}

$debug_info['ban_until'] = $ban_until;
$debug_info['duration_seconds'] = $duration !== 'permanent' ? (int)$duration : 'permanent';

// Now actually try to insert the ban
try {
    $conn->begin_transaction();
    
    $debug_info['step'] = 'starting_transaction';
    
    // Insert ban record
    $stmt = $conn->prepare("INSERT INTO room_bans (room_id, user_id_string, banned_by, reason, ban_until, timestamp) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE banned_by = VALUES(banned_by), reason = VALUES(reason), ban_until = VALUES(ban_until), timestamp = NOW()");
    
    if (!$stmt) {
        $debug_info['step'] = 'prepare_failed';
        $debug_info['error'] = $conn->error;
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $debug_info['step'] = 'prepare_success';
    
    $stmt->bind_param("issss", $room_id, $user_id_string, $current_user_id_string, $reason, $ban_until);
    
    $debug_info['bind_params'] = [
        'room_id' => $room_id,
        'user_id_string' => $user_id_string,
        'banned_by' => $current_user_id_string,
        'reason' => $reason,
        'ban_until' => $ban_until
    ];
    
    if (!$stmt->execute()) {
        $debug_info['step'] = 'execute_failed';
        $debug_info['error'] = $stmt->error;
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $debug_info['step'] = 'insert_success';
    $debug_info['affected_rows'] = $stmt->affected_rows;
    $debug_info['insert_id'] = $conn->insert_id;
    
    $stmt->close();
    
    // Check if the record was actually inserted
    $check_stmt = $conn->prepare("SELECT * FROM room_bans WHERE room_id = ? AND user_id_string = ? ORDER BY timestamp DESC LIMIT 1");
    $check_stmt->bind_param("is", $room_id, $user_id_string);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $inserted_ban = $check_result->fetch_assoc();
        $debug_info['inserted_record'] = $inserted_ban;
        $debug_info['step'] = 'record_confirmed';
    } else {
        $debug_info['step'] = 'record_not_found_after_insert';
    }
    $check_stmt->close();
    
    $conn->commit();
    $debug_info['step'] = 'transaction_committed';
    
} catch (Exception $e) {
    $conn->rollback();
    $debug_info['step'] = 'transaction_rolled_back';
    $debug_info['exception'] = $e->getMessage();
}

echo json_encode($debug_info);
$conn->close();
?>
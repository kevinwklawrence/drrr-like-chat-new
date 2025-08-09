<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

$debug_info = [
    'session_user' => $_SESSION['user'] ?? null,
    'session_room_id' => $_SESSION['room_id'] ?? null,
    'step' => 'start'
];

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    $debug_info['step'] = 'no_session';
    $debug_info['error'] = 'Missing user or room_id in session';
    echo json_encode($debug_info);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

$debug_info['room_id'] = $room_id;
$debug_info['current_user_id_string'] = $current_user_id_string;
$debug_info['step'] = 'checking_auth';

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
    } else {
        $debug_info['host_check'] = 'prepare_failed';
    }
}

// Also check if user is admin
if (!$is_authorized && isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    $is_authorized = true;
    $debug_info['admin_override'] = true;
}

$debug_info['is_authorized'] = $is_authorized;
$debug_info['step'] = 'auth_complete';

if (!$is_authorized) {
    $debug_info['step'] = 'not_authorized';
    echo json_encode($debug_info);
    exit;
}

try {
    // Check if room_bans table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'room_bans'");
    if ($check_table->num_rows === 0) {
        $debug_info['step'] = 'table_not_exists';
        echo json_encode($debug_info);
        exit;
    }
    
    $debug_info['step'] = 'table_exists';

    // Get ALL bans for this room (no time filter for debugging)
    $debug_stmt = $conn->prepare("SELECT * FROM room_bans WHERE room_id = ?");
    $debug_stmt->bind_param("i", $room_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    $all_bans = [];
    while ($row = $debug_result->fetch_assoc()) {
        $all_bans[] = $row;
    }
    $debug_stmt->close();
    
    $debug_info['all_bans_count'] = count($all_bans);
    $debug_info['all_bans'] = $all_bans;
    
    // Get only active bans
    $active_stmt = $conn->prepare("SELECT * FROM room_bans WHERE room_id = ? AND (ban_until IS NULL OR ban_until > NOW())");
    $active_stmt->bind_param("i", $room_id);
    $active_stmt->execute();
    $active_result = $active_stmt->get_result();
    $active_bans = [];
    while ($row = $active_result->fetch_assoc()) {
        $active_bans[] = $row;
    }
    $active_stmt->close();
    
    $debug_info['active_bans_count'] = count($active_bans);
    $debug_info['active_bans'] = $active_bans;
    $debug_info['step'] = 'queries_complete';
    
} catch (Exception $e) {
    $debug_info['step'] = 'exception';
    $debug_info['error'] = $e->getMessage();
}

echo json_encode($debug_info);
$conn->close();
?>
<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode([]);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

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
        }
        $stmt->close();
    }
}

// Also check if user is admin
if (!$is_authorized && isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    $is_authorized = true;
}

if (!$is_authorized) {
    echo json_encode([]);
    exit;
}

try {
    // Get banlist from chatrooms table
    $stmt = $conn->prepare("SELECT banlist FROM chatrooms WHERE id = ?");
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }
    
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([]);
        $stmt->close();
        exit;
    }
    
    $room_data = $result->fetch_assoc();
    $banlist = $room_data['banlist'] ? json_decode($room_data['banlist'], true) : [];
    $stmt->close();
    
    // Filter out expired bans
    $current_time = time();
    $active_bans = array_filter($banlist, function($ban) use ($current_time) {
        // Keep permanent bans (ban_until is null) or bans that haven't expired yet
        return $ban['ban_until'] === null || $ban['ban_until'] > $current_time;
    });
    
    // Convert to format expected by frontend
    $formatted_bans = [];
    foreach ($active_bans as $ban) {
        $formatted_bans[] = [
            'user_id_string' => $ban['user_id_string'],
            'username' => null,
            'guest_name' => $ban['username'], // We store the display name in 'username' field
            'banned_by' => $ban['banned_by'],
            'reason' => $ban['reason'] ?? '',
            'ban_until' => $ban['ban_until'] ? date('Y-m-d H:i:s', $ban['ban_until']) : null,
            'timestamp' => isset($ban['banned_at']) ? date('Y-m-d H:i:s', $ban['banned_at']) : null
        ];
    }
    
    echo json_encode($formatted_bans);
    
} catch (Exception $e) {
    error_log("Get banned users error: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
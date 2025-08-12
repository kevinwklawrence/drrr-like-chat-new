<?php
// api/set_user_inactive.php - Testing endpoint to manually set users inactive
session_start();
header('Content-Type: application/json');

// Security: Only allow this in development or for admins
$TESTING_ENABLED = false; // Set to false in production
$REQUIRE_ADMIN = false; // Set to true to require admin privileges

if (!$TESTING_ENABLED) {
    echo json_encode(['status' => 'error', 'message' => 'Testing endpoints disabled']);
    exit;
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

if ($REQUIRE_ADMIN && (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Admin privileges required']);
    exit;
}

include '../db_connect.php';

$room_id = (int)($_POST['room_id'] ?? 0);
$user_id_string = $_POST['user_id_string'] ?? '';
$minutes = (int)($_POST['minutes'] ?? 16);

if ($room_id <= 0 || empty($user_id_string) || $minutes < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

// Limit to reasonable values
if ($minutes > 120) {
    $minutes = 120; // Max 2 hours
}

try {
    // Verify user exists in the room
    $check_stmt = $conn->prepare("SELECT guest_name, username, is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if (!$check_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $check_stmt->bind_param("is", $room_id, $user_id_string);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found in room']);
        $check_stmt->close();
        exit;
    }
    
    $user_data = $result->fetch_assoc();
    $check_stmt->close();
    
    // Set the user's last_activity to the specified time in the past
    $update_stmt = $conn->prepare("UPDATE chatroom_users SET last_activity = DATE_SUB(NOW(), INTERVAL ? MINUTE) WHERE room_id = ? AND user_id_string = ?");
    if (!$update_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $update_stmt->bind_param("iis", $minutes, $room_id, $user_id_string);
    $success = $update_stmt->execute();
    $affected_rows = $update_stmt->affected_rows;
    $update_stmt->close();
    
    if ($success && $affected_rows > 0) {
        $display_name = $user_data['username'] ?: $user_data['guest_name'] ?: 'Unknown';
        
        error_log("TESTING: Set user '$display_name' ($user_id_string) inactive for $minutes minutes in room $room_id");
        
        echo json_encode([
            'status' => 'success',
            'message' => "User set inactive for $minutes minutes",
            'user' => [
                'user_id_string' => $user_id_string,
                'display_name' => $display_name,
                'is_host' => (int)$user_data['is_host'],
                'room_id' => $room_id,
                'minutes_inactive' => $minutes
            ],
            'warning' => 'This is a testing function - user will appear inactive and may be disconnected'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update user activity']);
    }
    
} catch (Exception $e) {
    error_log("Set user inactive error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to set user inactive: ' . $e->getMessage()]);
}

$conn->close();
?>
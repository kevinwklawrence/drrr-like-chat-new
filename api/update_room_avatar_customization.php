<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Add avatar customization columns if they don't exist
    $columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    $available_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }

    if (!in_array('avatar_hue', $available_columns)) {
        $conn->query("ALTER TABLE chatroom_users ADD COLUMN avatar_hue INT DEFAULT 0 NOT NULL");
    }

    if (!in_array('avatar_saturation', $available_columns)) {
        $conn->query("ALTER TABLE chatroom_users ADD COLUMN avatar_saturation INT DEFAULT 100 NOT NULL");
    }
    
    // Get avatar customization from session or global_users
    $avatar_hue = (int)($_SESSION['user']['avatar_hue'] ?? 0);
    $avatar_saturation = (int)($_SESSION['user']['avatar_saturation'] ?? 100);
    
    // Try to get from global_users if session values are defaults
    if ($avatar_hue === 0 && $avatar_saturation === 100) {
        $global_stmt = $conn->prepare("SELECT avatar_hue, avatar_saturation FROM global_users WHERE user_id_string = ?");
        if ($global_stmt) {
            $global_stmt->bind_param("s", $user_id_string);
            $global_stmt->execute();
            $result = $global_stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
                $avatar_hue = (int)($data['avatar_hue'] ?? 0);
                $avatar_saturation = (int)($data['avatar_saturation'] ?? 100);
            }
            $global_stmt->close();
        }
    }
    
    // Update chatroom_users
    $stmt = $conn->prepare("UPDATE chatroom_users SET avatar_hue = ?, avatar_saturation = ? WHERE room_id = ? AND user_id_string = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("iiis", $avatar_hue, $avatar_saturation, $room_id, $user_id_string);
    $success = $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    if ($success) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Avatar customization updated',
            'avatar_hue' => $avatar_hue,
            'avatar_saturation' => $avatar_saturation,
            'affected_rows' => $affected_rows
        ]);
    } else {
        throw new Exception('Failed to update avatar customization');
    }
    
} catch (Exception $e) {
    error_log("Update room avatar customization error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update: ' . $e->getMessage()]);
}

$conn->close();
?>
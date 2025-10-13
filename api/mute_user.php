<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['room_id']) || !isset($_SESSION['user']['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not in a room']);
    exit;
}

$action = $_POST['action'] ?? '';
$muted_user_id_string = $_POST['muted_user_id_string'] ?? '';
$muter_user_id_string = $_SESSION['user']['user_id'];

if (empty($muted_user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user']);
    exit;
}

try {
    if ($action === 'mute') {
        // Add to muted_users table
        $stmt = $conn->prepare("INSERT IGNORE INTO muted_users (muter_user_id_string, muted_user_id_string) VALUES (?, ?)");
        $stmt->bind_param("ss", $muter_user_id_string, $muted_user_id_string);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'User muted']);
        
    } elseif ($action === 'unmute') {
        // Remove from muted_users table
        $stmt = $conn->prepare("DELETE FROM muted_users WHERE muter_user_id_string = ? AND muted_user_id_string = ?");
        $stmt->bind_param("ss", $muter_user_id_string, $muted_user_id_string);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'User unmuted']);
        
    } elseif ($action === 'get_muted_list') {
        // Get list of muted users
        $stmt = $conn->prepare("SELECT muted_user_id_string FROM muted_users WHERE muter_user_id_string = ?");
        $stmt->bind_param("s", $muter_user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $muted_users = [];
        while ($row = $result->fetch_assoc()) {
            $muted_users[] = $row['muted_user_id_string'];
        }
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'muted_users' => $muted_users]);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
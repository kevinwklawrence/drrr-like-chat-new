<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$new_name = trim($_POST['name'] ?? '');
$user_type = $_SESSION['user']['type'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($new_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Name cannot be empty']);
    exit;
}

try {
    // Update session
    if ($user_type === 'guest') {
        $_SESSION['user']['name'] = $new_name;
    } else {
        $_SESSION['user']['username'] = $new_name;
    }
    
    // Update global_users table
    if ($user_type === 'guest') {
        $stmt = $conn->prepare("UPDATE global_users SET guest_name = ? WHERE user_id_string = ?");
        $stmt->bind_param("ss", $new_name, $user_id_string);
    } else {
        $stmt = $conn->prepare("UPDATE global_users SET username = ? WHERE user_id_string = ?");
        $stmt->bind_param("ss", $new_name, $user_id_string);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update global_users');
    }
    $stmt->close();
    
    // Update chatroom_users if in any room
    if ($user_type === 'guest') {
        $stmt2 = $conn->prepare("UPDATE chatroom_users SET guest_name = ? WHERE user_id_string = ?");
        $stmt2->bind_param("ss", $new_name, $user_id_string);
    } else {
        $stmt2 = $conn->prepare("UPDATE chatroom_users SET username = ? WHERE user_id_string = ?");
        $stmt2->bind_param("ss", $new_name, $user_id_string);
    }
    $stmt2->execute();
    $stmt2->close();
    
    // Update users table for registered users (username only, user_id stays the same)
    if ($user_type === 'user' && isset($_SESSION['user']['id'])) {
        $stmt3 = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt3->bind_param("si", $new_name, $_SESSION['user']['id']);
        $stmt3->execute();
        $stmt3->close();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Name updated successfully',
        'name' => $new_name
    ]);
    
} catch (Exception $e) {
    error_log("Update name error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update name']);
}

$conn->close();
?>
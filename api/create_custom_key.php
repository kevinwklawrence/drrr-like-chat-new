<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user']['id'];

if ($action === 'create') {
    $custom_key = trim($_POST['custom_key'] ?? '');
    
    // Validate: only alphanumeric, 6-64 characters
    if (empty($custom_key)) {
        echo json_encode(['status' => 'error', 'message' => 'Key cannot be empty']);
        exit;
    }
    
    if (!preg_match('/^[a-zA-Z0-9]+$/', $custom_key)) {
        echo json_encode(['status' => 'error', 'message' => 'Key can only contain letters and numbers']);
        exit;
    }
    
    if (strlen($custom_key) < 6 || strlen($custom_key) > 64) {
        echo json_encode(['status' => 'error', 'message' => 'Key must be 6-64 characters']);
        exit;
    }
    
    // Check if key already exists
    $check = $conn->prepare("SELECT id FROM personal_keys WHERE key_value = ?");
    $check->bind_param("s", $custom_key);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This key is already taken']);
        $check->close();
        exit;
    }
    $check->close();
    
    // Create key
    $stmt = $conn->prepare("INSERT INTO personal_keys (user_id, key_value) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $custom_key);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Personal key created', 'key' => $custom_key]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create key']);
    }
    $stmt->close();
    
} elseif ($action === 'delete') {
    $key_id = (int)$_POST['key_id'];
    
    $stmt = $conn->prepare("DELETE FROM personal_keys WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $key_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Key deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete key']);
    }
    $stmt->close();
    
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

$conn->close();
?>
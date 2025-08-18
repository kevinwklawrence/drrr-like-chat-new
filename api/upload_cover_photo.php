<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Only registered users can upload cover photos']);
    exit;
}

include '../db_connect.php';

if (!isset($_FILES['cover_photo'])) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['cover_photo'];
$user_id = $_SESSION['user']['id'];
$upload_dir = '../images/covers/';

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Validate file
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
    echo json_encode(['status' => 'error', 'message' => 'File too large (max 5MB)']);
    exit;
}

try {
    // Remove old cover photo
    $stmt = $conn->prepare("SELECT cover_photo FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['cover_photo'] && file_exists($upload_dir . $row['cover_photo'])) {
            unlink($upload_dir . $row['cover_photo']);
        }
    }
    $stmt->close();
    
    // Upload new file
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'cover_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database
        $stmt = $conn->prepare("UPDATE users SET cover_photo = ? WHERE id = ?");
        $stmt->bind_param("si", $filename, $user_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'filename' => $filename]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload file']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Upload error']);
}

$conn->close();
?>
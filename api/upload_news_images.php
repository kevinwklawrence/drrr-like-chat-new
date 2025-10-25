<?php
// api/upload_news_images.php - Upload images for news posts (admin/moderator only)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$user_id = $_SESSION['user']['id'];

// Check if user is admin or moderator
$stmt = $conn->prepare("SELECT is_admin, is_moderator FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$user_data = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user_data['is_admin'] && !$user_data['is_moderator']) {
    echo json_encode(['status' => 'error', 'message' => 'Only admins and moderators can upload images']);
    exit;
}

$category = $_POST['category'] ?? '';

if (!in_array($category, ['announcements', 'events', 'updates'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category']);
    exit;
}

if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
    echo json_encode(['status' => 'error', 'message' => 'No images uploaded']);
    exit;
}

try {
    $upload_dir = __DIR__ . '/../news/' . $category . '/images/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_images = [];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $files = $_FILES['images'];
    $file_count = count($files['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        // Skip if error
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Validate file type
        $file_type = $files['type'][$i];
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Invalid file type: ' . $files['name'][$i]);
        }
        
        // Validate file size
        if ($files['size'][$i] > $max_size) {
            throw new Exception('File too large: ' . $files['name'][$i] . ' (max 5MB)');
        }
        
        // Generate unique filename
        $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        $filename = 'img_' . time() . '_' . $i . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
            // Store relative URL for markdown
            $url = 'news/' . $category . '/images/' . $filename;
            $uploaded_images[] = [
                'filename' => $filename,
                'url' => $url
            ];
        } else {
            throw new Exception('Failed to upload: ' . $files['name'][$i]);
        }
    }
    
    if (empty($uploaded_images)) {
        throw new Exception('No images were uploaded successfully');
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => count($uploaded_images) . ' image(s) uploaded',
        'images' => $uploaded_images
    ]);
    
} catch (Exception $e) {
    error_log("Upload news images error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
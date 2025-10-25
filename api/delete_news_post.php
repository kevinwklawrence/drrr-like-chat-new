<?php
// api/delete_news_post.php - Delete news post (admin/moderator only)
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
    echo json_encode(['status' => 'error', 'message' => 'Only admins and moderators can delete posts']);
    exit;
}

// Get POST data
$filename = $_POST['filename'] ?? '';
$category = $_POST['category'] ?? '';

if (!in_array($category, ['announcements', 'events', 'updates'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category']);
    exit;
}

if (empty($filename)) {
    echo json_encode(['status' => 'error', 'message' => 'Filename is required']);
    exit;
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);

if (!preg_match('/^[a-z0-9\-]+\.md$/', $filename)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid filename']);
    exit;
}

try {
    $posts_dir = __DIR__ . '/../news/' . $category . '/';
    $images_dir = $posts_dir . 'images/';
    $filepath = $posts_dir . $filename;
    
    if (!file_exists($filepath)) {
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
        exit;
    }
    
    // Read the markdown file to find associated images
    $content = file_get_contents($filepath);
    $associated_images = [];
    
    // Find all image references in the markdown
    preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $matches);
    
    if (!empty($matches[2])) {
        foreach ($matches[2] as $img_url) {
            // Check if it's a local image (in the news directory)
            if (strpos($img_url, 'news/' . $category . '/images/') !== false) {
                $img_filename = basename($img_url);
                $img_path = $images_dir . $img_filename;
                if (file_exists($img_path)) {
                    $associated_images[] = $img_path;
                }
            }
        }
    }
    
    // Delete the markdown file
    if (unlink($filepath)) {
        // Delete associated images
        foreach ($associated_images as $img_path) {
            @unlink($img_path);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Post deleted successfully',
            'images_deleted' => count($associated_images)
        ]);
    } else {
        throw new Exception('Failed to delete file');
    }
    
} catch (Exception $e) {
    error_log("Delete news post error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete post: ' . $e->getMessage()]);
}
?>
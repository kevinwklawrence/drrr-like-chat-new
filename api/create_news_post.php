<?php
// api/create_news_post.php - Create new news post (admin/moderator only)
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
    echo json_encode(['status' => 'error', 'message' => 'Only admins and moderators can create posts']);
    exit;
}

// Get POST data
$category = $_POST['category'] ?? '';
$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';

if (!in_array($category, ['announcements', 'events', 'updates'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category']);
    exit;
}

if (empty($title) || empty($content)) {
    echo json_encode(['status' => 'error', 'message' => 'Title and content are required']);
    exit;
}

try {
    $posts_dir = __DIR__ . '/../news/' . $category . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($posts_dir)) {
        mkdir($posts_dir, 0755, true);
    }
    
    // Generate filename from title
    $filename = strtolower($title);
    $filename = preg_replace('/[^a-z0-9]+/', '-', $filename);
    $filename = trim($filename, '-');
    
    // Add timestamp to ensure uniqueness
    $filename = $filename . '-' . time() . '.md';
    
    $filepath = $posts_dir . $filename;
    
    // Format content with title as markdown header
    $markdown_content = "# " . $title . "\n\n" . $content;
    
    // Write to file
    if (file_put_contents($filepath, $markdown_content) === false) {
        throw new Exception('Failed to write file');
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Post created successfully',
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    error_log("Create news post error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to create post: ' . $e->getMessage()]);
}
?>
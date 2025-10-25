<?php
// api/news_posts.php - Fetch news posts from markdown files
session_start();
header('Content-Type: application/json');

$category = $_GET['category'] ?? '';

if (!in_array($category, ['announcements', 'events', 'updates'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category']);
    exit;
}

$posts_dir = __DIR__ . '/../news/' . $category . '/';

// Create directory if it doesn't exist
if (!is_dir($posts_dir)) {
    mkdir($posts_dir, 0755, true);
}

try {
    $posts = [];
    $files = glob($posts_dir . '*.md');
    
    // Sort files by modification time (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        
        // Parse title from first line or use filename
        $lines = explode("\n", $content);
        $title = '';
        $body = $content;
        
        // Check if first line is a markdown header
        if (isset($lines[0]) && preg_match('/^#\s+(.+)$/', $lines[0], $matches)) {
            $title = trim($matches[1]);
            // Remove title from body
            array_shift($lines);
            $body = implode("\n", $lines);
        } else {
            // Use filename as title
            $title = basename($file, '.md');
            $title = str_replace('-', ' ', $title);
            $title = ucwords($title);
        }
        
        // Convert markdown to HTML
        $html_content = convertMarkdownToHtml(trim($body));
        
        $posts[] = [
            'filename' => basename($file),
            'title' => $title,
            'content' => $html_content,
            'created_at' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'posts' => $posts
    ]);
    
} catch (Exception $e) {
    error_log("News posts error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load posts']);
}

function convertMarkdownToHtml($text) {
    // Simple markdown conversion
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Images - must come before links since ![alt](url) contains link syntax
    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function($matches) {
        $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
        return '<img src="' . $url . '" alt="' . $alt . '" class="news-image" style="max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0; display: block;" loading="lazy">';
    }, $text);
    
    // Headers
    $text = preg_replace('/^### (.*)$/m', '<h6>$1</h6>', $text);
    $text = preg_replace('/^## (.*)$/m', '<h5>$1</h5>', $text);
    $text = preg_replace('/^# (.*)$/m', '<h4>$1</h4>', $text);
    
    // Bold, italic, underline
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/__(.*?)__/', '<u>$1</u>', $text);
    
    // Links
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer" style="color: #4a9eff;">$1</a>', $text);
    
    // Line breaks
    $text = nl2br($text);
    
    return $text;
}
?>
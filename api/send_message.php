<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure avatar customization and color columns exist in messages table
$check_color_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'color'");
if ($check_color_col->num_rows === 0) {
    $conn->query("ALTER TABLE messages ADD COLUMN color VARCHAR(50) DEFAULT 'blue'");
}

$check_hue_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'avatar_hue'");
if ($check_hue_col->num_rows === 0) {
    $conn->query("ALTER TABLE messages ADD COLUMN avatar_hue INT DEFAULT 0");
}

$check_sat_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'avatar_saturation'");
if ($check_sat_col->num_rows === 0) {
    $conn->query("ALTER TABLE messages ADD COLUMN avatar_saturation INT DEFAULT 100");
}

function sanitizeMarkup($message) {
    // Convert markdown-style formatting to HTML safely
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    // Helper function to validate URLs
    $validateUrl = function($url) {
        // Remove any potential XSS attempts
        $url = trim($url);
        // Only allow http, https, and data URLs (for images)
        if (preg_match('/^(https?:\/\/|data:image\/)/i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }
        return false;
    };
    
    // Process in order of complexity (most specific first)
    
    // 1. Code blocks (triple backticks) - must come before single backticks
    $message = preg_replace('/```([^`]*?)```/s', '<pre><code>$1</code></pre>', $message);
    
    // 2. Images: ![alt text](url)
    $message = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function($matches) use ($validateUrl) {
        $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $url = trim($matches[2]);
        if ($validateUrl($url)) {
            return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" style="max-height: 400px; max-width: 800px; border: 2px solid white;" loading="lazy">';
        }
        return $matches[0]; // Return original if URL is invalid
    }, $message);
    
    // 3. Links: [link text](url)
    $message = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($matches) use ($validateUrl) {
        $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $url = trim($matches[2]);
        if ($validateUrl($url)) {
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
        }
        return $matches[0]; // Return original if URL is invalid
    }, $message);
    
    // 4. Headers (must come before other formatting)
    $message = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $message);
    $message = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $message);
    $message = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $message);
    
    // 5. Horizontal rules
    $message = preg_replace('/^(---|\*\*\*|___)$/m', '<hr>', $message);
    
    // 6. Blockquotes
    $message = preg_replace('/^> (.*)$/m', '<blockquote>$1</blockquote>', $message);
    
    // 7. Unordered lists
    $message = preg_replace('/^[-*+] (.*)$/m', '<li>$1</li>', $message);
    $message = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $message);
    
    // 8. Ordered lists
    $message = preg_replace('/^\d+\. (.*)$/m', '<li>$1</li>', $message);
    // Note: This is a simplified approach. For proper nested lists, you'd need more complex parsing
    
    // 9. Text formatting (existing patterns)
    $patterns = [
        '/\*\*(.*?)\*\*/' => '<strong>$1</strong>',  // **bold**
        '/\*(.*?)\*/' => '<em>$1</em>',              // *italic*
        '/__(.*?)__/' => '<u>$1</u>',                // __underline__
        '/~~(.*?)~~/' => '<del>$1</del>',            // ~~strikethrough~~
        '/`(.*?)`/' => '<code>$1</code>'             // `code` (inline)
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $message = preg_replace($pattern, $replacement, $message);
    }
    
    // 10. Line breaks (convert double line breaks to paragraphs)
    $message = preg_replace('/\n\s*\n/', '</p><p>', $message);
    $message = '<p>' . $message . '</p>';
    
    // Clean up empty paragraphs
    $message = preg_replace('/<p><\/p>/', '', $message);
    
    // 11. Single line breaks (convert to <br>)
    $message = preg_replace('/\n/', '<br>', $message);
    
    return $message;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in send_message.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($room_id <= 0 || empty($message)) {
    error_log("Missing room_id or message in send_message.php: room_id=$room_id, message=$message");
    echo json_encode(['status' => 'error', 'message' => 'Room ID and message are required']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;
$guest_name = ($_SESSION['user']['type'] === 'guest') ? $_SESSION['user']['name'] : null;
$avatar = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['avatar'] : ($_SESSION['user']['avatar'] ?? null);

// Get the user_id string for both registered users and guests
$user_id_string = '';
if ($_SESSION['user']['type'] === 'user') {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
} else {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
}

error_log("Inserting message: room_id=$room_id, user_id=$user_id, guest_name=$guest_name, avatar=$avatar, user_id_string=$user_id_string, message=$message");

$sanitized_message = sanitizeMarkup($message);
$color = $_SESSION['user']['color'] ?? 'blue';
$avatar_hue = (int)($_SESSION['user']['avatar_hue'] ?? 0);
$avatar_saturation = (int)($_SESSION['user']['avatar_saturation'] ?? 100);
$stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, guest_name, message, avatar, user_id_string, color, avatar_hue, avatar_saturation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    error_log("Prepare failed in send_message.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iisssssii", $room_id, $user_id, $guest_name, $sanitized_message, $avatar, $user_id_string, $color, $avatar_hue, $avatar_saturation);
if (!$stmt->execute()) {
    error_log("Execute failed in send_message.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    exit;
}
$stmt->close();

echo json_encode(['status' => 'success']);
?>
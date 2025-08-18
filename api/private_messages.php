<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Only registered users can send private messages']);
    exit;
}

include '../db_connect.php';

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
            return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" style="max-height: 200px; max-width: 100%; border: 2px solid white;" loading="lazy">';
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
    $message = preg_replace('/^### (.*$)/m', '<h6>$1</h6>', $message);
    $message = preg_replace('/^## (.*$)/m', '<h5>$1</h5>', $message);
    $message = preg_replace('/^# (.*$)/m', '<h4>$1</h4>', $message);
    
    // 5. Horizontal rules
    $message = preg_replace('/^(---|\*\*\*|___)$/m', '<hr>', $message);
    
    // 6. Blockquotes
    $message = preg_replace('/^> (.*)$/m', '<blockquote style="border-left: 3px solid #ccc; padding-left: 10px; margin: 5px 0;">$1</blockquote>', $message);
    
    // 7. Text formatting
    $patterns = [
        '/\*\*(.*?)\*\*/' => '<strong>$1</strong>',  // **bold**
        '/\*(.*?)\*/' => '<em>$1</em>',              // *italic*
        '/__(.*?)__/' => '<u>$1</u>',                // __underline__
        '/~~(.*?)~~/' => '<del>$1</del>',            // ~~strikethrough~~
        '/`(.*?)`/' => '<code style="background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 3px;">$1</code>'             // `code` (inline)
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $message = preg_replace($pattern, $replacement, $message);
    }
    
    // 8. Line breaks (convert single line breaks to <br>)
    $message = preg_replace('/\n/', '<br>', $message);
    
    return $message;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user']['id'];

switch($action) {
    case 'send':
    $recipient_id = (int)$_POST['recipient_id'];
    $message = trim($_POST['message'] ?? '');
    
    error_log("Private message send attempt: recipient_id=$recipient_id, sender_id=$user_id, message_length=" . strlen($message));
    
    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
        exit;
    }
    
    // Check if accepting_whispers column exists
    $columns_check = $conn->query("SHOW COLUMNS FROM users LIKE 'accepting_whispers'");
    $has_whispers_column = $columns_check->num_rows > 0;
    
    if (!$has_whispers_column) {
        // Add the column if it doesn't exist
        $conn->query("ALTER TABLE users ADD COLUMN accepting_whispers TINYINT(1) DEFAULT 1");
        error_log("Added accepting_whispers column to users table");
    }
    
    // Check if recipient exists and accepts whispers
    $query = $has_whispers_column ? 
        "SELECT id, username, accepting_whispers FROM users WHERE id = ?" :
        "SELECT id, username, 1 as accepting_whispers FROM users WHERE id = ?";
        
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("User not found: recipient_id=$recipient_id");
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    $recipient = $result->fetch_assoc();
    $stmt->close();
    
    error_log("Recipient found: " . print_r($recipient, true));
    
    if (!$recipient['accepting_whispers']) {
        echo json_encode(['status' => 'error', 'message' => 'User is not accepting private messages']);
        exit;
    }
    
    // Ensure columns exist in private_messages table  
    $check_color_col = $conn->query("SHOW COLUMNS FROM private_messages LIKE 'color'");
    if ($check_color_col->num_rows === 0) {
        $conn->query("ALTER TABLE private_messages ADD COLUMN color VARCHAR(50) DEFAULT 'blue'");
    }

    $check_hue_col = $conn->query("SHOW COLUMNS FROM private_messages LIKE 'avatar_hue'");
    if ($check_hue_col->num_rows === 0) {
        $conn->query("ALTER TABLE private_messages ADD COLUMN avatar_hue INT DEFAULT 0");
    }

    $check_sat_col = $conn->query("SHOW COLUMNS FROM private_messages LIKE 'avatar_saturation'");
    if ($check_sat_col->num_rows === 0) {
        $conn->query("ALTER TABLE private_messages ADD COLUMN avatar_saturation INT DEFAULT 100");
    }

    $check_bubble_hue_col = $conn->query("SHOW COLUMNS FROM private_messages LIKE 'bubble_hue'");
if ($check_bubble_hue_col->num_rows === 0) {
    $conn->query("ALTER TABLE private_messages ADD COLUMN bubble_hue INT DEFAULT 0");
}

$check_bubble_sat_col = $conn->query("SHOW COLUMNS FROM private_messages LIKE 'bubble_saturation'");
if ($check_bubble_sat_col->num_rows === 0) {
    $conn->query("ALTER TABLE private_messages ADD COLUMN bubble_saturation INT DEFAULT 100");
}
    
    // Apply markdown formatting
    $sanitized_message = sanitizeMarkup($message);
    $color = $_SESSION['user']['color'] ?? 'blue';
    $avatar_hue = (int)($_SESSION['user']['avatar_hue'] ?? 0);
    $avatar_saturation = (int)($_SESSION['user']['avatar_saturation'] ?? 100);
    $bubble_hue = (int)($_SESSION['user']['bubble_hue'] ?? 0);
$bubble_saturation = (int)($_SESSION['user']['bubble_saturation'] ?? 100);
    
    $stmt = $conn->prepare("INSERT INTO private_messages (sender_id, recipient_id, message, color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissiiii", $user_id, $recipient_id, $sanitized_message, $color, $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation);
    
    if ($stmt->execute()) {
        error_log("Private message sent successfully from $user_id to $recipient_id");
        echo json_encode(['status' => 'success', 'message' => 'Message sent']);
    } else {
        error_log("Failed to insert private message: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
    }
    $stmt->close();
    break;
        
    case 'get':
    $other_user_id = (int)$_GET['other_user_id'];
    $stmt = $conn->prepare("
    SELECT pm.*, 
           s.username as sender_username, s.avatar as sender_avatar,
           r.username as recipient_username, r.avatar as recipient_avatar
    FROM private_messages pm
    JOIN users s ON pm.sender_id = s.id 
    JOIN users r ON pm.recipient_id = r.id
    WHERE (pm.sender_id = ? AND pm.recipient_id = ?) OR (pm.sender_id = ? AND pm.recipient_id = ?)
    ORDER BY pm.created_at ASC
    LIMIT 50
");
$stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    // Use stored values from when the message was sent (no lookups needed)
    $row['sender_color'] = $row['color'] ?? 'blue';
    $row['sender_avatar_hue'] = (int)($row['avatar_hue'] ?? 0);
    $row['sender_avatar_saturation'] = (int)($row['avatar_saturation'] ?? 100);
    $row['bubble_hue'] = (int)($row['bubble_hue'] ?? 0);
$row['bubble_saturation'] = (int)($row['bubble_saturation'] ?? 100);
    
    // For recipient, we don't store their customization in private messages
    // so we set defaults (this matches the previous behavior)
    $row['recipient_color'] = 'blue';
    $row['recipient_avatar_hue'] = 0;
    $row['recipient_avatar_saturation'] = 100;
    
    $messages[] = $row;
}
    $stmt->close();
    
    // Mark messages as read
    $stmt2 = $conn->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND recipient_id = ?");
    $stmt2->bind_param("ii", $other_user_id, $user_id);
    $stmt2->execute();
    $stmt2->close();
    
    echo json_encode(['status' => 'success', 'messages' => $messages]);
    break;
        
    case 'get_conversations':
        $stmt = $conn->prepare("
            SELECT DISTINCT 
                CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END as other_user_id,
                u.username, u.avatar, u.avatar_hue, u.avatar_saturation,
                (SELECT message FROM private_messages pm2 WHERE 
                 (pm2.sender_id = ? AND pm2.recipient_id = other_user_id) OR 
                 (pm2.sender_id = other_user_id AND pm2.recipient_id = ?)
                 ORDER BY pm2.created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM private_messages pm3 WHERE 
                 pm3.sender_id = other_user_id AND pm3.recipient_id = ? AND pm3.is_read = 0) as unread_count
            FROM private_messages pm
            JOIN users u ON u.id = (CASE WHEN pm.sender_id = ? THEN pm.recipient_id ELSE pm.sender_id END)
            WHERE pm.sender_id = ? OR pm.recipient_id = ?
            ORDER BY (SELECT MAX(created_at) FROM private_messages pm4 WHERE 
                     (pm4.sender_id = ? AND pm4.recipient_id = other_user_id) OR 
                     (pm4.sender_id = other_user_id AND pm4.recipient_id = ?)) DESC
        ");
        $stmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }
        
        echo json_encode(['status' => 'success', 'conversations' => $conversations]);
        break;
}

$conn->close();
?>
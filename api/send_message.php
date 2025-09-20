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

// Ensure reply and mention columns exist
$check_reply_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'reply_to_message_id'");
if ($check_reply_col->num_rows === 0) {
    $conn->query("ALTER TABLE messages ADD COLUMN reply_to_message_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE messages ADD INDEX idx_reply_to (reply_to_message_id)");
}

$check_mentions_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'mentions'");
if ($check_mentions_col->num_rows === 0) {
    $conn->query("ALTER TABLE messages ADD COLUMN mentions TEXT DEFAULT NULL");
}

function sanitizeMarkup($message) {
    // Convert markdown-style formatting to HTML safely
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    
    
    // Helper function to validate URLs
    $validateUrl = function($url) {
        $url = trim($url);
        if (preg_match('/^https?:\/\//', $url)) {
            // Allow query parameters after image extensions
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $url)) {
                return filter_var($url, FILTER_VALIDATE_URL) !== false;
            }
        }
        return false;
    };
    
    // Process in order of complexity (most specific first)
    
    // 1. Code blocks (triple backticks) - must come before single backticks
    $message = preg_replace('/```([^`]*?)```/s', '<pre><code>$1</code></pre>', $message);
    
    // Images with proxy for external URLs
    $message = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function($matches) use ($validateUrl) {
        $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $url = trim($matches[2]);
        
        if ($validateUrl($url)) {
            // Check if it's an external image
            $current_domain = $_SERVER['HTTP_HOST'];
            $url_host = parse_url($url, PHP_URL_HOST);
            
            if ($url_host && $url_host !== $current_domain) {
                // Use proxy for external images
                $proxy_url = 'api/image_proxy.php?url=' . urlencode($url);
                return '<br><img src="' . htmlspecialchars($proxy_url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" class="messageimg" loading="lazy" data-original="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><br>';
            } else {
                // Local images - use directly
                return '<br><img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" class="messageimg" loading="lazy"><br>';
            }
        }
        return $matches[0];
    }, $message);
    
    // 3. Links: [link text](url)
    $message = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($matches) use ($validateUrl) {
        $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $url = trim($matches[2]);
        if ($validateUrl($url)) {
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="messagelink" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
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

function processMentions($message, $conn, $room_id) {
    $mentions = [];
    
    // Find @mentions in the message
    preg_match_all('/@(\w+)/', $message, $matches);
    
    if (!empty($matches[1])) {
        $usernames = $matches[1];
        
        foreach ($usernames as $username) {
            // Check if mentioned user exists and is in the room
            $stmt = $conn->prepare("
                SELECT DISTINCT cu.user_id_string, u.username, cu.guest_name, cu.username as cu_username
                FROM chatroom_users cu
                LEFT JOIN users u ON cu.user_id = u.id
                WHERE cu.room_id = ? 
                AND (u.username = ? OR cu.username = ? OR cu.guest_name = ?)
                LIMIT 1
            ");
            
            if ($stmt) {
                $stmt->bind_param("isss", $room_id, $username, $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user_data = $result->fetch_assoc();
                    $mentions[] = [
                        'username' => $username,
                        'user_id_string' => $user_data['user_id_string'],
                        'display_name' => $user_data['username'] ?: ($user_data['cu_username'] ?: $user_data['guest_name'])
                    ];
                }
                $stmt->close();
            }
        }
    }
    
    return $mentions;
}

function highlightMentions($message, $mentions) {
    foreach ($mentions as $mention) {
        $username = $mention['username'];
        $displayName = $mention['display_name'];
        
        // Replace @username with highlighted version
        $message = preg_replace(
            '/@' . preg_quote($username, '/') . '\b/',
            '<span class="mention" data-user="' . htmlspecialchars($mention['user_id_string']) . '">@' . htmlspecialchars($displayName) . '</span>',
            $message
        );
    }
    
    return $message;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in send_message.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$reply_to_message_id = isset($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;

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

error_log("Inserting message: room_id=$room_id, user_id=$user_id, guest_name=$guest_name, avatar=$avatar, user_id_string=$user_id_string, message=$message, reply_to=$reply_to_message_id");

// Process mentions
$mentions = processMentions($message, $conn, $room_id);
$mentionsJson = !empty($mentions) ? json_encode($mentions) : null;

// Apply markdown and mention highlighting
$sanitized_message = sanitizeMarkup($message);
$sanitized_message = highlightMentions($sanitized_message, $mentions);

$color = $_SESSION['user']['color'] ?? 'blue';
$avatar_hue = (int)($_SESSION['user']['avatar_hue'] ?? 0);
$avatar_saturation = (int)($_SESSION['user']['avatar_saturation'] ?? 100);
$bubble_hue = (int)($_SESSION['user']['bubble_hue'] ?? 0);
$bubble_saturation = (int)($_SESSION['user']['bubble_saturation'] ?? 100);

try {
    $conn->begin_transaction();
    
    // CHECK AND CLEAR AFK STATUS WHEN SENDING MESSAGE
    $afk_check_stmt = $conn->prepare("SELECT is_afk, manual_afk, username, guest_name FROM chatroom_users WHERE room_id = ? AND user_id_string = ? AND is_afk = 1");
    if ($afk_check_stmt) {
        $afk_check_stmt->bind_param("is", $room_id, $user_id_string);
        $afk_check_stmt->execute();
        $afk_result = $afk_check_stmt->get_result();
        
        if ($afk_result->num_rows > 0) {
            // User is currently AFK, clear it
            $afk_data = $afk_result->fetch_assoc();
            $display_name = $afk_data['username'] ?: $afk_data['guest_name'] ?: 'Unknown User';
            
            error_log("Clearing AFK status for user sending message: $display_name");
            
            // Clear AFK status
            $clear_afk_stmt = $conn->prepare("UPDATE chatroom_users SET is_afk = 0, manual_afk = 0, afk_since = NULL, last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
            if ($clear_afk_stmt) {
                $clear_afk_stmt->bind_param("is", $room_id, $user_id_string);
                $clear_afk_stmt->execute();
                $clear_afk_stmt->close();
                
                // Add system message about returning from AFK
                $return_message = "$display_name is back from AFK.";
                $afk_system_msg = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'active.png', 'system')");
                if ($afk_system_msg) {
                    $afk_system_msg->bind_param("is", $room_id, $return_message);
                    $afk_system_msg->execute();
                    $afk_system_msg->close();
                }
                
                error_log("✅ Cleared AFK status for user: $display_name");
            }
        }
        $afk_check_stmt->close();
    }
    
    // Update user activity (this is important for the activity tracking system)
    $activity_stmt = $conn->prepare("UPDATE chatroom_users SET last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
    if ($activity_stmt) {
        $activity_stmt->bind_param("is", $room_id, $user_id_string);
        $activity_stmt->execute();
        $activity_stmt->close();
    }
    
    // Insert the actual message
    $stmt = $conn->prepare("
        INSERT INTO messages (
            room_id, user_id, guest_name, message, avatar, user_id_string, 
            color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation,
            reply_to_message_id, mentions
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param(
        "iisssssiiiiis", 
        $room_id, $user_id, $guest_name, $sanitized_message, $avatar, $user_id_string, 
        $color, $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation,
        $reply_to_message_id, $mentionsJson
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $message_id = $conn->insert_id;
    $stmt->close();
    
    // Create mention notifications
    if (!empty($mentions)) {
        foreach ($mentions as $mention) {
            // Don't create mention for self
            if ($mention['user_id_string'] !== $user_id_string) {
                $mention_stmt = $conn->prepare("
                    INSERT INTO user_mentions (
                        room_id, message_id, mentioned_user_id_string, 
                        mentioned_by_user_id_string, mention_type
                    ) VALUES (?, ?, ?, ?, 'mention')
                ");
                
                if ($mention_stmt) {
                    $mention_stmt->bind_param(
                        "iiss", 
                        $room_id, $message_id, $mention['user_id_string'], $user_id_string
                    );
                    $mention_stmt->execute();
                    $mention_stmt->close();
                }
            }
        }
    }
    
    // Create reply notification
    if ($reply_to_message_id) {
        // Get the original message author
        $reply_stmt = $conn->prepare("SELECT user_id_string FROM messages WHERE id = ?");
        if ($reply_stmt) {
            $reply_stmt->bind_param("i", $reply_to_message_id);
            $reply_stmt->execute();
            $reply_result = $reply_stmt->get_result();
            
            if ($reply_result->num_rows > 0) {
                $original_author = $reply_result->fetch_assoc();
                
                // Don't create reply notification for self
                if ($original_author['user_id_string'] !== $user_id_string) {
                    $reply_notify_stmt = $conn->prepare("
                        INSERT INTO user_mentions (
                            room_id, message_id, mentioned_user_id_string, 
                            mentioned_by_user_id_string, mention_type
                        ) VALUES (?, ?, ?, ?, 'reply')
                    ");
                    
                    if ($reply_notify_stmt) {
                        $reply_notify_stmt->bind_param(
                            "iiss", 
                            $room_id, $message_id, $original_author['user_id_string'], $user_id_string
                        );
                        $reply_notify_stmt->execute();
                        $reply_notify_stmt->close();
                    }
                }
            }
            $reply_stmt->close();
        }
    }
    
    $conn->commit();
    echo json_encode([
        'status' => 'success', 
        'message_id' => $message_id,
        'afk_cleared' => isset($afk_data) ? true : false
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Send message error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
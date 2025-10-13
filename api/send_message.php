<?php
session_start();
session_write_close();
include '../db_connect.php';
require_once __DIR__ . '/check_ghost_hunt.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function checkSpamProtection($conn, $user_id_string, $room_id, $message) {
    $MAX_LENGTH = 5000;
    $BURST_LIMIT = 5;
    $SHORT_LIMIT = 10;
    $MEDIUM_LIMIT = 15;
    $DUPLICATE_WINDOW = 5;
    
    // Message length check
    if (mb_strlen($message, 'UTF-8') > $MAX_LENGTH) {
        logSpamViolation($conn, $user_id_string, $room_id, 'message_too_long', $message);
        return [
            'blocked' => true,
            'reason' => "Message too long. Maximum $MAX_LENGTH characters allowed."
        ];
    }
    
    // 10 second burst check
    $stmt = $conn->prepare("
        SELECT COUNT(*) as msg_count 
        FROM messages 
        WHERE room_id = ? AND user_id_string = ? 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 10 SECOND)
    ");
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['msg_count'] >= $BURST_LIMIT) {
        logSpamViolation($conn, $user_id_string, $room_id, 'burst_limit', $message);
        return [
            'blocked' => true,
            'reason' => 'Sending messages too quickly. Please wait a moment.'
        ];
    }
    
    // 30 second rate check
    $stmt = $conn->prepare("
        SELECT COUNT(*) as msg_count 
        FROM messages 
        WHERE room_id = ? AND user_id_string = ? 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 20 SECOND)
    ");
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['msg_count'] >= $SHORT_LIMIT) {
        logSpamViolation($conn, $user_id_string, $room_id, 'short_rate_limit', $message);
        return [
            'blocked' => true,
            'reason' => 'Too many messages in a short time. Please slow down.'
        ];
    }
    
    // 60 second rate check
    $stmt = $conn->prepare("
        SELECT COUNT(*) as msg_count 
        FROM messages 
        WHERE room_id = ? AND user_id_string = ? 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 30 SECOND)
    ");
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['msg_count'] >= $MEDIUM_LIMIT) {
        logSpamViolation($conn, $user_id_string, $room_id, 'sustained_rate_limit', $message);
        return [
            'blocked' => true,
            'reason' => 'Message rate limit reached. Please wait before sending more.'
        ];
    }
    
    // Duplicate message check
    $stmt = $conn->prepare("
        SELECT message FROM messages 
        WHERE room_id = ? AND user_id_string = ? 
        AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ORDER BY timestamp DESC LIMIT 3
    ");
    $stmt->bind_param("isi", $room_id, $user_id_string, $DUPLICATE_WINDOW);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (trim($message) === trim($row['message'])) {
            $stmt->close();
            logSpamViolation($conn, $user_id_string, $room_id, 'duplicate_message', $message);
            return [
                'blocked' => true,
                'reason' => 'Please avoid sending duplicate messages.'
            ];
        }
    }
    $stmt->close();
    
    return ['blocked' => false];
}

function logSpamViolation($conn, $user_id_string, $room_id, $violation_type, $message_content) {
    $create_table = "CREATE TABLE IF NOT EXISTS spam_violations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id_string VARCHAR(255) NOT NULL,
        room_id INT NOT NULL,
        violation_type VARCHAR(50) NOT NULL,
        message_preview TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_violations (user_id_string, created_at),
        INDEX idx_room_violations (room_id, created_at)
    )";
    $conn->query($create_table);
    
    $stmt = $conn->prepare("
        INSERT INTO spam_violations 
        (user_id_string, room_id, violation_type, message_preview, ip_address) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $message_preview = mb_substr($message_content, 0, 100, 'UTF-8');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt->bind_param("sisss", $user_id_string, $room_id, $violation_type, $message_preview, $ip_address);
    $stmt->execute();
    $stmt->close();
    
    error_log("SPAM_VIOLATION: user=$user_id_string, room=$room_id, type=$violation_type");
}

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
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    $validateUrl = function($url) {
        $url = trim($url);
        if (preg_match('/^https?:\/\//', $url)) {
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $url)) {
                return filter_var($url, FILTER_VALIDATE_URL) !== false;
            }
        }
        return false;
    };
    
    $message = preg_replace('/```([^`]*?)```/s', '<pre><code>$1</code></pre>', $message);
    
    $message = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function($matches) use ($validateUrl) {
        $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $url = trim($matches[2]);
        
        if ($validateUrl($url)) {
            $current_domain = $_SERVER['HTTP_HOST'];
            $url_host = parse_url($url, PHP_URL_HOST);
            
            if ($url_host && $url_host !== $current_domain) {
                $proxy_url = 'api/image_proxy.php?url=' . urlencode($url);
                return '<br><img src="' . htmlspecialchars($proxy_url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" class="messageimg" loading="lazy" data-original="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><br>';
            } else {
                return '<br><img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" class="messageimg" loading="lazy"><br>';
            }
        }
        return $matches[0];
    }, $message);
    
    // NEW (fixed) - validates any http/https URL
$message = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($matches) {
    $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
    $url = trim($matches[2]);
    // Validate any http/https URL for links
    if (preg_match('/^https?:\/\//', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false) {
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="messagelink" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
    }
    return $matches[0];
}, $message);
    
    $message = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $message);
    $message = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $message);
    $message = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $message);
    $message = preg_replace('/^(---|\*\*\*|___)$/m', '<hr>', $message);
    $message = preg_replace('/^> (.*)$/m', '<blockquote>$1</blockquote>', $message);
    $message = preg_replace('/^[-*+] (.*)$/m', '<li>$1</li>', $message);
    $message = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $message);
    $message = preg_replace('/^\d+\. (.*)$/m', '<li>$1</li>', $message);
    
    $patterns = [
        '/\*\*(.*?)\*\*/' => '<strong>$1</strong>',
        '/\*(.*?)\*/' => '<em>$1</em>',
        '/__(.*?)__/' => '<u>$1</u>',
        '/~~(.*?)~~/' => '<del>$1</del>',
        '/`(.*?)`/' => '<code>$1</code>'
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $message = preg_replace($pattern, $replacement, $message);
    }
    
    $message = preg_replace('/\n\s*\n/', '</p><p>', $message);
    $message = '<p>' . $message . '</p>';
    $message = preg_replace('/<p><\/p>/', '', $message);
    $message = preg_replace('/\n/', '<br>', $message);
    
    return $message;
}

function processMentions($message, $conn, $room_id) {
    $mentions = [];
    preg_match_all('/@(\w+)/', $message, $matches);
    
    if (!empty($matches[1])) {
        $usernames = $matches[1];
        
        foreach ($usernames as $username) {
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

// Get username for verified users - MOVED HERE SO IT'S AVAILABLE FOR RP COMMANDS
$username = null;
if ($_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['username'])) {
    $username = $_SESSION['user']['username'];
}

// Get the user_id string for both registered users and guests
$user_id_string = '';
if ($_SESSION['user']['type'] === 'user') {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
} else {
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
}

$verify_stmt = $conn->prepare("SELECT 1 FROM chatroom_users WHERE room_id = ? AND user_id_string = ? LIMIT 1");
$verify_stmt->bind_param("is", $room_id, $user_id_string);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();
$verify_stmt->close();

if ($verify_result->num_rows === 0) {
    unset($_SESSION['room_id']);
    echo json_encode([
        'status' => 'not_in_room',
        'message' => 'You have been disconnected from the room',
        'redirect' => '/lounge'
    ]);
    exit;
}

// Check spam protection
$spam_check = checkSpamProtection($conn, $user_id_string, $room_id, $message);
if ($spam_check['blocked']) {
    echo json_encode([
        'status' => 'error',
        'message' => $spam_check['reason'],
        'spam_blocked' => true
    ]);
    $conn->close();
    exit;
}

require_once __DIR__ . '/reset_inactivity.php';
resetInactivityTimer($conn, $room_id, $user_id_string);

error_log("Inserting message: room_id=$room_id, user_id=$user_id, guest_name=$guest_name, avatar=$avatar, user_id_string=$user_id_string, message=$message, reply_to=$reply_to_message_id");

// Process RP commands
$message_type = 'chat';
$is_special_message = false;

// Check for /me command (RP action)
if (substr($message, 0, 4) === '/me ') {
    $is_special_message = true;
    $message_type = 'rp';
    $message = substr($message, 4);
    error_log("RP MESSAGE DETECTED: " . $message);
}
// Check for /roll command (dice roll)
else if (preg_match('/^\/roll(?:\s+(\d+)d(\d+))?$/i', $message, $matches)) {
    $is_special_message = true;
    $message_type = 'roll';
    
    $num_dice = isset($matches[1]) ? (int)$matches[1] : 1;
    $num_sides = isset($matches[2]) ? (int)$matches[2] : 20;
    
    $num_dice = min(max($num_dice, 1), 10);
    $num_sides = min(max($num_sides, 2), 100);
    
    $rolls = [];
    $total = 0;
    for ($i = 0; $i < $num_dice; $i++) {
        $roll = rand(1, $num_sides);
        $rolls[] = $roll;
        $total += $roll;
    }
    
    $display_name = $username ?: $guest_name ?: 'Unknown';
    if ($num_dice === 1) {
        $message = "$display_name rolled {$rolls[0]} (1d$num_sides)";
    } else {
        $rolls_str = implode(', ', $rolls);
        $message = "$display_name rolled $total [$rolls_str] ({$num_dice}d$num_sides)";
    }
    
    error_log("ROLL MESSAGE: " . $message);
}
// Check for /do command (environmental action)
else if (substr($message, 0, 4) === '/do ') {
    $is_special_message = true;
    $message_type = 'do';
    $message = substr($message, 4);
    error_log("DO MESSAGE: " . $message);
}
// Check for /flip command (coin flip)
else if (strtolower(trim($message)) === '/flip') {
    $is_special_message = true;
    $message_type = 'flip';
    
    $result = rand(0, 1) === 0 ? 'Heads' : 'Tails';
    $display_name = $username ?: $guest_name ?: 'Unknown';
    $message = "$display_name flipped: $result";
    
    error_log("FLIP MESSAGE: " . $message);
}
// Check for /8ball command (magic 8 ball)
else if (preg_match('/^\/8ball\s+(.+)$/i', $message, $matches)) {
    $is_special_message = true;
    $message_type = 'eightball';
    
    $question = $matches[1];
    $responses = [
        'It is certain',
        'Without a doubt',
        'You may rely on it',
        'Yes definitely',
        'It is decidedly so',
        'As I see it, yes',
        'Most likely',
        'Yes',
        'Signs point to yes',
        'Reply hazy try again',
        'Ask again later',
        'Better not tell you now',
        'Cannot predict now',
        'Concentrate and ask again',
        'Don\'t count on it',
        'My reply is no',
        'My sources say no',
        'Outlook not so good',
        'Very doubtful'
    ];
    
    $response = $responses[array_rand($responses)];
    $display_name = $username ?: $guest_name ?: 'Unknown';
    $message = "🎱 $display_name asked: \"$question\" — $response";
    
    error_log("8BALL MESSAGE: " . $message);
}
// Check for /npc command (NPC dialogue)
else if (preg_match('/^\/npc\s+([^:]+):\s*(.+)$/i', $message, $matches)) {
    $is_special_message = true;
    $message_type = 'npc';
    
    $npc_name = trim($matches[1]);
    $npc_text = trim($matches[2]);
    $controller = $username ?: $guest_name ?: 'Unknown';
    
    $message = "**$npc_name:** \"$npc_text\" *(by $controller)*";
    
    error_log("NPC MESSAGE: " . $message);
}
// Check for /nar command (narrator - host only)
else if (substr($message, 0, 5) === '/nar ') {
    $host_check = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    $host_check->bind_param("is", $room_id, $user_id_string);
    $host_check->execute();
    $host_result = $host_check->get_result();
    $is_host = $host_result->num_rows > 0 && $host_result->fetch_assoc()['is_host'] == 1;
    $host_check->close();
    
    if ($is_host) {
        $is_special_message = true;
        $message_type = 'narrator';
        $message = substr($message, 5);
        error_log("NARRATOR MESSAGE: " . $message);
    } else {
        $message = "[Error: Only the host can use /nar]";
    }
}

error_log("Message type: $message_type, Is special: " . ($is_special_message ? 'YES' : 'NO'));

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
            $afk_data = $afk_result->fetch_assoc();
            $display_name = $afk_data['username'] ?: $afk_data['guest_name'] ?: 'Unknown User';
            
            error_log("Clearing AFK status for user sending message: $display_name");
            
            $clear_afk_stmt = $conn->prepare("UPDATE chatroom_users SET is_afk = 0, manual_afk = 0, afk_since = NULL, last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
            if ($clear_afk_stmt) {
                $clear_afk_stmt->bind_param("is", $room_id, $user_id_string);
                $clear_afk_stmt->execute();
                $clear_afk_stmt->close();
                
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
    
    // Update user activity
    $activity_stmt = $conn->prepare("UPDATE chatroom_users SET last_activity = NOW() WHERE room_id = ? AND user_id_string = ?");
    if ($activity_stmt) {
        $activity_stmt->bind_param("is", $room_id, $user_id_string);
        $activity_stmt->execute();
        $activity_stmt->close();
    }
    
    // Insert the actual message - FIXED TO USE $is_special_message
    if ($is_special_message) {
        error_log("Using SPECIAL MESSAGE INSERT with type='$message_type'");
        $stmt = $conn->prepare("
            INSERT INTO messages (
                room_id, user_id, username, guest_name, message, avatar, user_id_string, 
                color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation,
                reply_to_message_id, mentions, type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        error_log("Binding with type = '$message_type'");
        $stmt->bind_param(
            "iissssssiiiiiss", 
            $room_id, $user_id, $username, $guest_name, $sanitized_message, $avatar, $user_id_string, 
            $color, $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation,
            $reply_to_message_id, $mentionsJson, $message_type
        );
    } else {
        error_log("Using REGULAR INSERT without type field");
        $stmt = $conn->prepare("
            INSERT INTO messages (
                room_id, user_id, username, guest_name, message, avatar, user_id_string, 
                color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation,
                reply_to_message_id, mentions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param(
            "iissssssiiiiis", 
            $room_id, $user_id, $username, $guest_name, $sanitized_message, $avatar, $user_id_string, 
            $color, $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation,
            $reply_to_message_id, $mentionsJson
        );
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $message_id = $conn->insert_id;
    $stmt->close();
    
    // Create mention notifications
    if (!empty($mentions)) {
        foreach ($mentions as $mention) {
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
        $reply_stmt = $conn->prepare("SELECT user_id_string FROM messages WHERE id = ?");
        if ($reply_stmt) {
            $reply_stmt->bind_param("i", $reply_to_message_id);
            $reply_stmt->execute();
            $reply_result = $reply_stmt->get_result();
            
            if ($reply_result->num_rows > 0) {
                $original_author = $reply_result->fetch_assoc();
                
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
    
    // Check for ghost hunt match
    $ghost_result = checkGhostHuntMatch($conn, $room_id, $message, $user_id_string, $_SESSION['user']['type'], $user_id);
    
    if ($ghost_result) {
        $balance_stmt = $conn->prepare("SELECT event_currency FROM users WHERE id = ?");
        $balance_stmt->bind_param("i", $user_id);
        $balance_stmt->execute();
        $balance_result = $balance_stmt->get_result();
        if ($balance_result->num_rows > 0) {
            $new_balance = $balance_result->fetch_assoc()['event_currency'];
            $_SESSION['user']['event_currency'] = $new_balance;
            $ghost_result['new_balance'] = $new_balance;
        }
        $balance_stmt->close();
    }

    $event_data = json_encode([
    'message_id' => $message_id,
    'type' => $message_type ?? 'message' // or 'rp', 'system', etc.
]);

$message_event_stmt = $conn->prepare("INSERT INTO message_events (room_id, event_type, event_data, created_at) VALUES (?, 'message', ?, NOW())");
if ($message_event_stmt) {
    $message_event_stmt->bind_param("is", $room_id, $event_data);
    $message_event_stmt->execute();
    $message_event_stmt->close();
}
    
    $conn->commit();
    
    $response = [
        'status' => 'success', 
        'message_id' => $message_id,
        'afk_cleared' => isset($afk_data) ? true : false
    ];
    
    if ($ghost_result) {
        $response['ghost_caught'] = true;
        $response['ghost_reward'] = $ghost_result['reward'];
        $response['new_event_currency'] = $ghost_result['new_balance'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Send message error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
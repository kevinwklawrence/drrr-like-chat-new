<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

function sanitizeMarkup($message) {
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    $patterns = [
        '/\*\*(.*?)\*\*/' => '<strong>$1</strong>',
        '/\*(.*?)\*/' => '<em>$1</em>',
        '/__(.*?)__/' => '<u>$1</u>',
        '/~~(.*?)~~/' => '<del>$1</del>',
        '/`(.*?)`/' => '<code style="background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 3px;">$1</code>'
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $message = preg_replace($pattern, $replacement, $message);
    }
    
    $message = preg_replace('/\n/', '<br>', $message);
    return $message;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$room_id = (int)$_SESSION['room_id'];
$user_id_string = $_SESSION['user']['user_id'] ?? '';

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    switch($action) {
        case 'send':
            $recipient_user_id_string = $_POST['recipient_user_id_string'] ?? '';
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message) || empty($recipient_user_id_string)) {
                echo json_encode(['status' => 'error', 'message' => 'Message and recipient required']);
                exit;
            }
            
            // FIXED: Use BINARY for case-sensitive comparison or LOWER for case-insensitive
            $stmt = $conn->prepare("SELECT user_id_string FROM chatroom_users WHERE room_id = ? AND LOWER(user_id_string) = LOWER(?)");
            $stmt->bind_param("is", $room_id, $recipient_user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                error_log("WHISPER ERROR: Recipient not found in room. room_id=$room_id, recipient=$recipient_user_id_string");
                echo json_encode(['status' => 'error', 'message' => 'Recipient not in room']);
                exit;
            }
            $stmt->close();
            
            // Ensure columns exist
            $check_color_col = $conn->query("SHOW COLUMNS FROM room_whispers LIKE 'color'");
            if ($check_color_col->num_rows === 0) {
                $conn->query("ALTER TABLE room_whispers ADD COLUMN color VARCHAR(50) DEFAULT 'blue'");
            }

            $check_hue_col = $conn->query("SHOW COLUMNS FROM room_whispers LIKE 'avatar_hue'");
            if ($check_hue_col->num_rows === 0) {
                $conn->query("ALTER TABLE room_whispers ADD COLUMN avatar_hue INT DEFAULT 0");
            }

            $check_sat_col = $conn->query("SHOW COLUMNS FROM room_whispers LIKE 'avatar_saturation'");
            if ($check_sat_col->num_rows === 0) {
                $conn->query("ALTER TABLE room_whispers ADD COLUMN avatar_saturation INT DEFAULT 100");
            }

            $check_bubble_hue_col = $conn->query("SHOW COLUMNS FROM room_whispers LIKE 'bubble_hue'");
            if ($check_bubble_hue_col->num_rows === 0) {
                $conn->query("ALTER TABLE room_whispers ADD COLUMN bubble_hue INT DEFAULT 0");
            }

            $check_bubble_sat_col = $conn->query("SHOW COLUMNS FROM room_whispers LIKE 'bubble_saturation'");
            if ($check_bubble_sat_col->num_rows === 0) {
                $conn->query("ALTER TABLE room_whispers ADD COLUMN bubble_saturation INT DEFAULT 100");
            }

            $check_avatar_col = $conn->query("SHOW COLUMNS FROM room_whispers LIKE 'sender_avatar'");
            if ($check_avatar_col->num_rows === 0) {
                $conn->query("ALTER TABLE room_whispers ADD COLUMN sender_avatar VARCHAR(255) DEFAULT 'default_avatar.jpg'");
            }

            $check_name_col = $conn->query("SHOW COLUMNS FROM room_whispers LIKE 'sender_name'");
            if ($check_name_col->num_rows === 0) {
                $conn->query("ALTER TABLE room_whispers ADD COLUMN sender_name VARCHAR(255) DEFAULT 'Unknown'");
            }
            
            $sanitized_message = sanitizeMarkup($message);
            $color = $_SESSION['user']['color'] ?? 'blue';
            $avatar_hue = (int)($_SESSION['user']['avatar_hue'] ?? 0);
            $avatar_saturation = (int)($_SESSION['user']['avatar_saturation'] ?? 100);
            $bubble_hue = (int)($_SESSION['user']['bubble_hue'] ?? 0);
            $bubble_saturation = (int)($_SESSION['user']['bubble_saturation'] ?? 100);
            $sender_avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
            $sender_name = $_SESSION['user']['username'] ?? $_SESSION['user']['name'] ?? 'Unknown';
            
            $stmt = $conn->prepare("INSERT INTO room_whispers (room_id, sender_user_id_string, recipient_user_id_string, message, color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation, sender_avatar, sender_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssiiiiss", $room_id, $user_id_string, $recipient_user_id_string, $sanitized_message, $color, $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation, $sender_avatar, $sender_name);

            if ($stmt->execute()) {
                error_log("Whisper sent successfully: room=$room_id, from=$user_id_string, to=$recipient_user_id_string");
                echo json_encode(['status' => 'success', 'message' => 'Whisper sent']);
            } else {
                error_log("Failed to insert whisper: " . $stmt->error);
                echo json_encode(['status' => 'error', 'message' => 'Failed to send whisper']);
            }
            $stmt->close();
            break;
            
        case 'get':
            $other_user_id_string = $_GET['other_user_id_string'] ?? '';
            
            if (empty($other_user_id_string)) {
                echo json_encode(['status' => 'error', 'message' => 'Other user required']);
                exit;
            }
            
            $stmt = $conn->prepare("
                SELECT * FROM room_whispers 
                WHERE room_id = ? 
                AND ((LOWER(sender_user_id_string) = LOWER(?) AND LOWER(recipient_user_id_string) = LOWER(?)) 
                     OR (LOWER(sender_user_id_string) = LOWER(?) AND LOWER(recipient_user_id_string) = LOWER(?)))
                ORDER BY created_at ASC
            ");
            $stmt->bind_param("issss", $room_id, $user_id_string, $other_user_id_string, $other_user_id_string, $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $row['sender_username'] = $row['sender_name'] ?? 'Unknown';
                $row['sender_guest_name'] = $row['sender_name'] ?? 'Unknown';
                $row['sender_avatar'] = $row['sender_avatar'] ?? 'default_avatar.jpg';
                $row['bubble_hue'] = (int)($row['bubble_hue'] ?? 0);
                $row['bubble_saturation'] = (int)($row['bubble_saturation'] ?? 100);
                
                $recipient_stmt = $conn->prepare("
                    SELECT cu.username, cu.guest_name, cu.avatar, cu.guest_avatar
                    FROM chatroom_users cu 
                    WHERE cu.room_id = ? AND LOWER(cu.user_id_string) = LOWER(?)
                ");
                $recipient_stmt->bind_param("is", $room_id, $row['recipient_user_id_string']);
                $recipient_stmt->execute();
                $recipient_result = $recipient_stmt->get_result();

                if ($recipient_result->num_rows > 0) {
                    $recipient_data = $recipient_result->fetch_assoc();
                    $row['recipient_username'] = $recipient_data['username'];
                    $row['recipient_guest_name'] = $recipient_data['guest_name'];
                    $row['recipient_avatar'] = $recipient_data['avatar'] ?: $recipient_data['guest_avatar'];
                } else {
                    $row['recipient_username'] = 'User Left';
                    $row['recipient_guest_name'] = 'User Left';
                    $row['recipient_avatar'] = 'default_avatar.jpg';
                }
                $recipient_stmt->close();
                
                $row['sender_color'] = $row['color'] ?? 'blue';
                $row['sender_avatar_hue'] = (int)($row['avatar_hue'] ?? 0);
                $row['sender_avatar_saturation'] = (int)($row['avatar_saturation'] ?? 100);
                $row['recipient_color'] = 'blue';
                $row['recipient_avatar_hue'] = 0;
                $row['recipient_avatar_saturation'] = 100;
                
                $messages[] = $row;
            }
            $stmt->close();
            
            $update_stmt = $conn->prepare("UPDATE room_whispers SET is_read = 1 WHERE room_id = ? AND LOWER(sender_user_id_string) = LOWER(?) AND LOWER(recipient_user_id_string) = LOWER(?)");
            $update_stmt->bind_param("iss", $room_id, $other_user_id_string, $user_id_string);
            $update_stmt->execute();
            $update_stmt->close();
            
            echo json_encode(['status' => 'success', 'messages' => $messages]);
            break;
            
        case 'get_conversations':
            $stmt = $conn->prepare("
                SELECT DISTINCT 
                    CASE WHEN LOWER(sender_user_id_string) = LOWER(?) THEN recipient_user_id_string ELSE sender_user_id_string END as other_user_id_string
                FROM room_whispers 
                WHERE room_id = ? AND (LOWER(sender_user_id_string) = LOWER(?) OR LOWER(recipient_user_id_string) = LOWER(?))
            ");
            $stmt->bind_param("siss", $user_id_string, $room_id, $user_id_string, $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $conversations = [];
            while ($row = $result->fetch_assoc()) {
                $other_user_id = $row['other_user_id_string'];
                
                $user_stmt = $conn->prepare("SELECT username, guest_name, avatar, guest_avatar FROM chatroom_users WHERE room_id = ? AND LOWER(user_id_string) = LOWER(?)");
                $user_stmt->bind_param("is", $room_id, $other_user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                
                if ($user_result->num_rows > 0) {
                    $user_data = $user_result->fetch_assoc();
                    
                    $msg_stmt = $conn->prepare("
                        SELECT message FROM room_whispers 
                        WHERE room_id = ? AND ((LOWER(sender_user_id_string) = LOWER(?) AND LOWER(recipient_user_id_string) = LOWER(?)) OR (LOWER(sender_user_id_string) = LOWER(?) AND LOWER(recipient_user_id_string) = LOWER(?)))
                        ORDER BY created_at DESC LIMIT 1
                    ");
                    $msg_stmt->bind_param("issss", $room_id, $user_id_string, $other_user_id, $other_user_id, $user_id_string);
                    $msg_stmt->execute();
                    $msg_result = $msg_stmt->get_result();
                    $last_message = $msg_result->num_rows > 0 ? $msg_result->fetch_assoc()['message'] : '';
                    $msg_stmt->close();
                    
                    $unread_stmt = $conn->prepare("
                        SELECT COUNT(*) as count FROM room_whispers 
                        WHERE room_id = ? AND LOWER(sender_user_id_string) = LOWER(?) AND LOWER(recipient_user_id_string) = LOWER(?) AND is_read = 0
                    ");
                    $unread_stmt->bind_param("iss", $room_id, $other_user_id, $user_id_string);
                    $unread_stmt->execute();
                    $unread_result = $unread_stmt->get_result();
                    $unread_count = $unread_result->fetch_assoc()['count'];
                    $unread_stmt->close();
                    
                    $conversations[] = [
                        'other_user_id_string' => $other_user_id,
                        'username' => $user_data['username'],
                        'guest_name' => $user_data['guest_name'],
                        'avatar' => $user_data['avatar'] ?: $user_data['guest_avatar'] ?: 'default_avatar.jpg',
                        'last_message' => $last_message,
                        'unread_count' => $unread_count
                    ];
                }
                $user_stmt->close();
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'conversations' => $conversations]);
            break;
    }
} catch (Exception $e) {
    error_log("Error in room_whispers.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred']);
}

$conn->close();
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json");

if (!isset($_SESSION["user"]) || !isset($_SESSION["room_id"])) {
    echo json_encode(["status" => "error", "message" => "Not authorized"]);
    exit;
}

// Try multiple paths for db_connect.php
$db_paths = ["../db_connect.php", "db_connect.php", "./db_connect.php"];
$db_connected = false;

foreach ($db_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected || !$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$room_id = (int)$_SESSION["room_id"];
$user_id_string = $_SESSION["user"]["user_id"] ?? "";

if (empty($user_id_string)) {
    echo json_encode(["status" => "error", "message" => "Invalid user session"]);
    exit;
}

try {
    $notifications = [];
    
    // Smart query based on your database structure
    $stmt = $conn->prepare("
        SELECT 
            um.id,
            um.message_id,
            um.mention_type as type,
            um.created_at,
            m.message,
            m.user_id_string as sender_user_id_string,
            m.guest_name as sender_guest_name,
            m.avatar as sender_avatar
        FROM user_mentions um
        LEFT JOIN messages m ON um.message_id = m.id
        WHERE um.room_id = ? 
        AND um.mentioned_user_id_string = ? 
        AND um.is_read = FALSE
        ORDER BY um.created_at DESC
        LIMIT 10
    ");
    
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Extract sender info from available fields
            $sender_name = "Unknown";
            if (isset($row["sender_username"]) && !empty($row["sender_username"])) {
                $sender_name = $row["sender_username"];
            } elseif (isset($row["sender_guest_name"]) && !empty($row["sender_guest_name"])) {
                $sender_name = $row["sender_guest_name"];
            } elseif (isset($row["sender_user_id_string"])) {
                $sender_name = $row["sender_user_id_string"];
            }
            
            $sender_avatar = isset($row["sender_avatar"]) ? $row["sender_avatar"] : "default_avatar.jpg";
            $message_content = isset($row["message"]) ? $row["message"] : "Message content unavailable";
            
            $notifications[] = [
                "id" => "mention_" . $row["id"],
                "type" => $row["type"],
                "notification_type" => "mention", 
                "message_id" => $row["message_id"],
                "title" => $row["type"] === "reply" ? "Reply to your message" : "Mentioned you",
                "message" => $message_content,
                "sender_name" => $sender_name,
                "sender_avatar" => $sender_avatar,
                "timestamp" => $row["created_at"],
                "action_data" => ["mention_id" => $row["id"]]
            ];
        }
        $stmt->close();
    }
    
    echo json_encode([
        "status" => "success",
        "notifications" => $notifications,
        "total_count" => count($notifications)
    ]);
    
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
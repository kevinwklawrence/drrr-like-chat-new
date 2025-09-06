<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json");

if (!isset($_SESSION["user"]) || $_SERVER["REQUEST_METHOD"] !== "POST") {
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

$action = $_POST["action"] ?? "";
$notification_id = $_POST["notification_id"] ?? "";
$user_id_string = $_SESSION["user"]["user_id"] ?? "";
$room_id = (int)($_SESSION["room_id"] ?? 0);

try {
    if ($action === "mark_read" && strpos($notification_id, "mention_") === 0) {
        $mention_id = str_replace("mention_", "", $notification_id);
        $stmt = $conn->prepare("
            UPDATE user_mentions 
            SET is_read = TRUE 
            WHERE id = ? 
            AND room_id = ? 
            AND mentioned_user_id_string = ?
        ");
        $stmt->bind_param("iis", $mention_id, $room_id, $user_id_string);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(["status" => "success", "message" => "Notification marked as read"]);
    } elseif ($action === "mark_all_read") {
        $stmt = $conn->prepare("
            UPDATE user_mentions 
            SET is_read = TRUE 
            WHERE room_id = ? 
            AND mentioned_user_id_string = ? 
            AND is_read = FALSE
        ");
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode(["status" => "success", "message" => "Marked $affected notifications as read"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
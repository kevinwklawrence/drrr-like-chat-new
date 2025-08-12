<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$guest_name = $_POST['guest_name'] ?? '';
$avatar = $_POST['avatar'] ?? '';
$color = $_POST['color'] ?? ''; // ADDED: Handle color selection

if (empty($guest_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Guest name required']);
    exit;
}

// UPDATED: Use correct default avatar path
if (empty($avatar)) {
    $avatar = 'default/u0.png'; // Updated default avatar path
}

// ADDED: Validate color selection
$valid_colors = [
    'black', 'blue', 'purple', 'pink', 'cyan', 'mint', 'orange', 
    'lavender', 'peach', 'green', 'yellow', 'red', 'teal', 
    'indigo', 'emerald', 'rose'
];

if (!in_array($color, $valid_colors)) {
    $color = ''; // Default fallback
}

// Generate encrypted user_id for guest using their IP address
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$encryption_key = 'drrr_guest_key_2025'; // Simple encryption key for demo purposes
$encrypted_ip = base64_encode(hash('sha256', $ip_address . $encryption_key, true));
// Make it shorter and more readable - take first 12 characters
$guest_user_id = 'GUEST_' . substr($encrypted_ip, 0, 12);

// UPDATED: Create guest session with color
$_SESSION['user'] = [
    'type' => 'guest',
    'name' => $guest_name,
    'user_id' => $guest_user_id,
    'avatar' => $avatar,
    'color' => $color, // ADDED: Store color in session
    'ip_address' => $ip_address
];

// ADDED: Store in global_users table with color
try {
    $stmt = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, color, guest_avatar, is_admin, ip_address) VALUES (?, NULL, ?, ?, ?, ?, 0, ?) ON DUPLICATE KEY UPDATE guest_name = VALUES(guest_name), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), color = VALUES(color), ip_address = VALUES(ip_address), last_activity = CURRENT_TIMESTAMP");
    
    if ($stmt) {
        $stmt->bind_param("ssssss", $guest_user_id, $guest_name, $avatar, $color, $avatar, $ip_address);
        $stmt->execute();
        $stmt->close();
        error_log("Guest stored in global_users with color: $color");
    }
} catch (Exception $e) {
    error_log("Failed to store guest in global_users: " . $e->getMessage());
}

error_log("Guest joined: name=$guest_name, user_id=$guest_user_id, avatar=$avatar, color=$color, ip=$ip_address");
echo json_encode(['status' => 'success']);
?>
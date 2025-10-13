<?php
session_start();
header('Content-Type: application/json'); // Set JSON header early

include '../db_connect.php';
include '../check_site_ban.php';

// Check for site ban before processing - with JSON response
try {
    checkSiteBan($conn, true); // Pass true to get JSON response instead of HTML
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Security check failed']);
    exit;
}

// ENHANCED duplicate submission prevention
$submission_id = $_POST['submission_id'] ?? null;
$processed_submissions = $_SESSION['processed_guest_submissions'] ?? [];

// Clean old submissions (older than 5 minutes)
$current_time = time();
$processed_submissions = array_filter($processed_submissions, function($timestamp) use ($current_time) {
    return ($current_time - $timestamp) < 300; // 5 minutes
});

if ($submission_id) {
    if (isset($processed_submissions[$submission_id])) {
        error_log("🛑 DUPLICATE GUEST SUBMISSION BLOCKED: ID $submission_id already processed at " . date('Y-m-d H:i:s', $processed_submissions[$submission_id]));
        echo json_encode(['status' => 'error', 'message' => 'Duplicate submission blocked']);
        exit;
    }
    
    // Mark this submission as processed
    $processed_submissions[$submission_id] = $current_time;
    $_SESSION['processed_guest_submissions'] = $processed_submissions;
    error_log("✅ NEW GUEST SUBMISSION: Processing ID $submission_id");
} else {
    error_log("⚠️ Guest submission without ID - likely duplicate or invalid request");
    // Still process but with warning
}

// Enhanced debugging
error_log("=== GUEST JOIN_LOUNGE DEBUG START ===");
error_log("Submission ID: " . ($submission_id ?? 'NONE'));
error_log("Request time: " . date('Y-m-d H:i:s.u'));
error_log("Thread ID: " . getmypid());
error_log("Session ID: " . session_id());
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
error_log("HTTP Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'none'));
error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
error_log("Raw POST count: " . count($_POST));
error_log("POST keys: " . implode(', ', array_keys($_POST)));

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

// Check if this is a complete guest registration with customization data
$has_customization_data = isset($_POST['avatar_hue']) && isset($_POST['avatar_saturation']);

if (!$has_customization_data) {
    error_log("⚠️ WARNING: Guest registration without avatar customization data - using defaults");
    $avatar_hue = 0;
    $avatar_saturation = 100;
    $bubble_hue = 0; // Default bubble hue
    $bubble_saturation = 100; // Default bubble saturation
} else {
    $avatar_hue = (int)$_POST['avatar_hue'];
    $avatar_saturation = (int)$_POST['avatar_saturation'];
    $bubble_hue = (int)($_POST['bubble_hue'] ?? 0);
    $bubble_saturation = (int)($_POST['bubble_saturation'] ?? 100);
    error_log("✅ Guest registration with customization - hue: $avatar_hue, sat: $avatar_saturation");
}

// Debug logging
error_log("Final guest values - avatar_hue: $avatar_hue, avatar_saturation: $avatar_saturation");
error_log("Full POST data: " . print_r($_POST, true));

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
    'black', 'policeman2','negative','gray','tan','blue','cobalt','lavender','lavender2',
    'teal2','navy','purple','pink','orange','orange2','peach','green','urban','mudgreen',
    'palegreen','red','toyred','spooky','rose','yellow','bbyellow','brown','deepbrown',
    'forest', 'rust', 'babyblue', 'sepia', 'chiipink', 'cnegative', 'cyan', 'caution', 'darkgray',
    'spooky2', 'spooky3', 'spooky4', 'spooky5', 'spooky6',
    'lenn', 'kisin'
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
    'avatar_hue' => $avatar_hue,
    'avatar_saturation' => $avatar_saturation,
    'bubble_hue' => $bubble_hue,
    'bubble_saturation' => $bubble_saturation,
    'ip_address' => $ip_address
];

// ADDED: Store in global_users table with color and avatar customization
// After the existing avatar customization logging, make sure the INSERT includes these fields:
try {
    // Check if columns exist first
    $columns_check = $conn->query("SHOW COLUMNS FROM global_users");
    $available_columns = [];
    while ($row = $columns_check->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Add columns if they don't exist
    if (!in_array('avatar_hue', $available_columns)) {
        $conn->query("ALTER TABLE global_users ADD COLUMN avatar_hue INT DEFAULT 0 NOT NULL");
    }
    if (!in_array('avatar_saturation', $available_columns)) {
        $conn->query("ALTER TABLE global_users ADD COLUMN avatar_saturation INT DEFAULT 100 NOT NULL");
    }
    
    $stmt = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, color, guest_avatar, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation, is_admin, ip_address) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?) ON DUPLICATE KEY UPDATE guest_name = VALUES(guest_name), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), color = VALUES(color), avatar_hue = VALUES(avatar_hue), avatar_saturation = VALUES(avatar_saturation), bubble_hue = VALUES(bubble_hue), bubble_saturation = VALUES(bubble_saturation), ip_address = VALUES(ip_address), last_activity = CURRENT_TIMESTAMP");

    if ($stmt) {
        $stmt->bind_param("sssssiiiis", $guest_user_id, $guest_name, $avatar, $color, $avatar, $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation, $ip_address);
        $stmt->execute();
        $stmt->close();
        error_log("Guest stored in global_users with avatar customization: hue=$avatar_hue, sat=$avatar_saturation");
    }
} catch (Exception $e) {
    error_log("Failed to store guest in global_users: " . $e->getMessage());
}

error_log("Guest joined: name=$guest_name, user_id=$guest_user_id, avatar=$avatar, color=$color, ip=$ip_address");
echo json_encode(['status' => 'success']);
?>
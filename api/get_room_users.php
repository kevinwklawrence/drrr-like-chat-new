<?php
// api/get_room_users.php - Enhanced with AFK status
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if ($room_id <= 0) {
    error_log("Invalid room_id in get_room_users.php: room_id=$room_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit;
}

error_log("Fetching users for room_id=$room_id");

// Check what columns exist in tables
$cu_columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
$cu_columns = [];
while ($row = $cu_columns_query->fetch_assoc()) {
    $cu_columns[] = $row['Field'];
}

$users_columns_query = $conn->query("SHOW COLUMNS FROM users");
$users_columns = [];
while ($row = $users_columns_query->fetch_assoc()) {
    $users_columns[] = $row['Field'];
}

// Build select fields for chatroom_users
$select_fields = [
    'cu.user_id',
    'cu.user_id_string', 
    'cu.guest_name', 
    'cu.avatar as guest_avatar',
    'cu.is_host',
    'cu.last_activity'
];

// Add AFK fields if they exist
if (in_array('is_afk', $cu_columns)) {
    $select_fields[] = 'cu.is_afk';
} else {
    $select_fields[] = '0 as is_afk';
}

if (in_array('manual_afk', $cu_columns)) {
    $select_fields[] = 'cu.manual_afk';
} else {
    $select_fields[] = '0 as manual_afk';
}

if (in_array('afk_since', $cu_columns)) {
    $select_fields[] = 'cu.afk_since';
} else {
    $select_fields[] = 'NULL as afk_since';
}

// Add stored username from chatroom_users if it exists
if (in_array('username', $cu_columns)) {
    $select_fields[] = 'cu.username';
} else {
    $select_fields[] = 'NULL as cu_username';
}

// Add avatar customization fields
if (in_array('avatar_hue', $cu_columns)) {
    $select_fields[] = 'cu.avatar_hue';
} else {
    $select_fields[] = '0 as avatar_hue';
}

if (in_array('avatar_saturation', $cu_columns)) {
    $select_fields[] = 'cu.avatar_saturation';
} else {
    $select_fields[] = '100 as avatar_saturation';
}

if (in_array('color', $cu_columns)) {
    $select_fields[] = 'cu.color';
} else {
    $select_fields[] = "'blue' as color";
}

// Add user fields if they exist
if (in_array('username', $users_columns)) {
    $select_fields[] = 'u.username as registered_username';
} else {
    $select_fields[] = 'NULL as registered_username';
}

if (in_array('avatar', $users_columns)) {
    $select_fields[] = 'u.avatar as registered_avatar';
} else {
    $select_fields[] = 'NULL as registered_avatar';
}

if (in_array('is_admin', $users_columns)) {
    $select_fields[] = 'u.is_admin';
} else {
    $select_fields[] = '0 as is_admin';
}

if (in_array('is_moderator', $users_columns)) {
    $select_fields[] = 'u.is_moderator';
} else {
    $select_fields[] = '0 as is_moderator';
}

$sql = "SELECT " . implode(', ', $select_fields) . "
        FROM chatroom_users cu 
        LEFT JOIN users u ON cu.user_id = u.id 
        WHERE cu.room_id = ? 
        ORDER BY cu.is_host DESC, cu.last_activity DESC";

error_log("Room users query: " . $sql);

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed in get_room_users.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$users = [];

while ($row = $result->fetch_assoc()) {
    // Determine user type and names
    $is_registered = !empty($row['user_id']) && $row['user_id'] > 0;
    $display_name = '';
    $username = null;
    $guest_name = null;
    $avatar = 'default_avatar.jpg';
    
    if ($is_registered) {
        $username = $row['registered_username'] ?: '';
        $display_name = $username;
        $avatar = $row['registered_avatar'] ?: 'default_avatar.jpg';
    } else {
        $guest_name = $row['guest_name'] ?: '';
        $username = $row['username'] ?: ''; // From cu.username (stored username in chatroom)
        $display_name = $username ?: $guest_name;
        $avatar = $row['guest_avatar'] ?: 'default_avatar.jpg';
    }
    
    // Calculate AFK duration if user is AFK
    $afk_duration_minutes = 0;
    if ($row['is_afk'] && !empty($row['afk_since'])) {
        $afk_since = new DateTime($row['afk_since']);
        $now = new DateTime();
        $afk_duration_minutes = floor(($now->getTimestamp() - $afk_since->getTimestamp()) / 60);
    }
    
    $user_data = [
        'user_id' => $row['user_id'],
        'user_id_string' => $row['user_id_string'],
        'username' => $username,
        'guest_name' => $guest_name,
        'display_name' => $display_name,
        'avatar' => $avatar,
        'guest_avatar' => $row['guest_avatar'],
        'is_host' => (int)$row['is_host'],
        'is_admin' => (int)$row['is_admin'],
        'is_moderator' => (int)$row['is_moderator'],
        'user_type' => $is_registered ? 'registered' : 'guest',
        'last_activity' => $row['last_activity'],
        
        // Avatar customization
        'avatar_hue' => (int)($row['avatar_hue'] ?? 0),
        'avatar_saturation' => (int)($row['avatar_saturation'] ?? 100),
        'color' => $row['color'] ?? 'blue',
        
        // AFK Status
        'is_afk' => (bool)$row['is_afk'],
        'manual_afk' => (bool)$row['manual_afk'],
        'afk_since' => $row['afk_since'],
        'afk_duration_minutes' => $afk_duration_minutes
    ];

    
    
    // ADMIN/MODERATOR AUTO-HOST PRIVILEGES
// Only check for registered users (guest users don't have is_admin/is_moderator)
if ($is_registered && $row['user_id'] > 0) {
    $is_admin = (int)($row['is_admin'] ?? 0);
    $is_moderator = (int)($row['is_moderator'] ?? 0);
    
    if ($is_admin == 1 || $is_moderator == 1) {
        // Automatically grant host privileges to admins/moderators
        $user_data['is_host'] = 1;
        $user_data['has_elevated_privileges'] = true; // Flag for UI (optional)
    }
}

$users[] = $user_data;
}
$stmt->close();

error_log("Retrieved " . count($users) . " users for room_id=$room_id");
echo json_encode($users);
?>
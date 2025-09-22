<?php
session_start();
header('Content-Type: application/json');

// Turn off error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Get form data (existing)
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$capacity = (int)($_POST['capacity'] ?? 10);
$background = $_POST['background'] ?? '';
$has_password = (int)($_POST['has_password'] ?? 0);
$password = $_POST['password'] ?? '';
$allow_knocking = (int)($_POST['allow_knocking'] ?? 1);

// NEW: Get new feature data
$is_rp = (int)($_POST['is_rp'] ?? 0);
$youtube_enabled = (int)($_POST['youtube_enabled'] ?? 0);
$theme = trim($_POST['theme'] ?? 'default');
$friends_only = (int)($_POST['friends_only'] ?? 0);
$invite_only = (int)($_POST['invite_only'] ?? 0);
$members_only = (int)($_POST['members_only'] ?? 0);
$disappearing_messages = (int)($_POST['disappearing_messages'] ?? 0);
$message_lifetime_minutes = (int)($_POST['message_lifetime_minutes'] ?? 0);

// NEW: Add permanent room setting (only for admins/moderators)
$permanent = 0;
if (isset($_POST['permanent']) && (int)$_POST['permanent'] === 1) {
    // Check if user is admin or moderator
    $is_admin = false;
    $is_moderator = false;
    
    if ($_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['id'])) {
        $admin_check_stmt = $conn->prepare("SELECT is_admin, is_moderator FROM users WHERE id = ?");
        if ($admin_check_stmt) {
            $admin_check_stmt->bind_param("i", $_SESSION['user']['id']);
            $admin_check_stmt->execute();
            $admin_result = $admin_check_stmt->get_result();
            if ($admin_result->num_rows > 0) {
                $admin_data = $admin_result->fetch_assoc();
                $is_admin = ($admin_data['is_admin'] == 1);
                $is_moderator = ($admin_data['is_moderator'] == 1);
            }
            $admin_check_stmt->close();
        }
    }
    
    if ($is_admin || $is_moderator) {
        $permanent = 1;
        error_log("CREATE_ROOM_DEBUG: Permanent room authorized by admin/moderator");
    } else {
        error_log("CREATE_ROOM_DEBUG: Permanent room denied - user not admin/moderator");
        echo json_encode(['status' => 'error', 'message' => 'Only administrators and moderators can create permanent rooms']);
        exit;
    }
}

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$host_user_id = $_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;


// Debug log
error_log("CREATE_ROOM_DEBUG: Received data:");
error_log("  - has_password: $has_password");
error_log("  - password: '" . $password . "' (length: " . strlen($password) . ")");
error_log("  - is_rp: $is_rp");
error_log("  - youtube_enabled: $youtube_enabled");
error_log("  - theme: $theme");
error_log("  - friends_only: $friends_only");
error_log("  - invite_only: $invite_only");
error_log("  - members_only: $members_only");

// Validation
if (empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Room name is required']);
    exit;
}

if (strlen($name) > 50) {
    echo json_encode(['status' => 'error', 'message' => 'Room name is too long (max 50 characters)']);
    exit;
}

// FIXED: Password validation and hashing with better error handling
$hashed_password = null;
if ($has_password) {
    if (empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Password is required when password protection is enabled']);
        exit;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    if ($hashed_password === false) {
        error_log("CREATE_ROOM_DEBUG: Password hashing failed");
        echo json_encode(['status' => 'error', 'message' => 'Failed to hash password']);
        exit;
    }
    
    error_log("CREATE_ROOM_DEBUG: Password hashed successfully, length: " . strlen($hashed_password) . ", hash: " . substr($hashed_password, 0, 20) . "...");
} else {
    error_log("CREATE_ROOM_DEBUG: No password protection requested");
}

// NEW: Validate friends_only for guests
if ($friends_only && $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Only registered users can create friends-only rooms']);
    exit;
}

// NEW: Validate disappearing messages
if ($disappearing_messages && ($message_lifetime_minutes < 1 || $message_lifetime_minutes > 1440)) {
    echo json_encode(['status' => 'error', 'message' => 'Message lifetime must be between 1 and 1440 minutes']);
    exit;
}

if (!in_array($capacity, [5, 10, 20, 50])) {
    $capacity = 10;
}

try {
    $conn->begin_transaction();
    
    // NEW: Generate invite code if needed
    $invite_code = null;
    if ($invite_only) {
        $invite_code = bin2hex(random_bytes(16)); // 32 character hex string
        error_log("CREATE_ROOM_DEBUG: Generated invite code: $invite_code");
    }
    
    // Check what columns exist in chatrooms table
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    if (!$columns_query) {
        throw new Exception('Cannot check table structure: ' . $conn->error);
    }
    
    $chatroom_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $chatroom_columns[] = $row['Field'];
    }
    
    error_log("CREATE_ROOM_DEBUG: Available chatrooms columns: " . implode(', ', $chatroom_columns));
    
    // Build INSERT query based on available columns
    $insert_fields = ['name', 'description', 'capacity', 'created_at'];
    $insert_values = ['?', '?', '?', 'NOW()'];
    $param_types = 'ssi';
    $param_values = [$name, $description, $capacity];
    
// Add has_password field with case-insensitive check
$has_password_column = null;
foreach ($chatroom_columns as $col) {
    if (strtolower($col) === 'has_password') {
        $has_password_column = $col;
        break;
    }
}

if ($has_password_column) {
    $insert_fields[] = $has_password_column;
    $insert_values[] = '?';
    $param_types .= 'i';
    $param_values[] = $has_password;
    error_log("CREATE_ROOM_DEBUG: Added $has_password_column = $has_password");
}
    
// Add password field - CRITICAL FIX for case sensitivity
$password_column_name = null;
foreach ($chatroom_columns as $col) {
    if (strtolower($col) === 'password') {
        $password_column_name = $col;
        break;
    }
}

if ($password_column_name) {
    $insert_fields[] = $password_column_name; // Use the actual column name (PASSWORD or password)
    $insert_values[] = '?';
    $param_types .= 's';
    $param_values[] = $has_password ? $hashed_password : null;
    error_log("CREATE_ROOM_DEBUG: Added password field '$password_column_name' with value: " . ($has_password && $hashed_password ? 'HASHED_PASSWORD(' . strlen($hashed_password) . ')' : 'NULL'));
}

    // In the existing column checking section, add permanent to the list:
if (in_array('permanent', $chatroom_columns)) {
    $insert_fields[] = 'permanent';
    $insert_values[] = '?';
    $param_types .= 'i';
    $param_values[] = $permanent;
    error_log("CREATE_ROOM_DEBUG: Adding permanent = $permanent");
}
    
    // Add other existing fields
    if (in_array('background', $chatroom_columns)) {
        $insert_fields[] = 'background';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $background;
    }
    
    if (in_array('allow_knocking', $chatroom_columns)) {
        $insert_fields[] = 'allow_knocking';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $allow_knocking;
    }
    
    if (in_array('host_user_id', $chatroom_columns)) {
        $insert_fields[] = 'host_user_id';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $host_user_id;
    }
    
    if (in_array('host_user_id_string', $chatroom_columns)) {
        $insert_fields[] = 'host_user_id_string';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $user_id_string;
    }
    
    // NEW: Add new feature fields (only if columns exist)
    if (in_array('is_rp', $chatroom_columns)) {
        $insert_fields[] = 'is_rp';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $is_rp;
        error_log("CREATE_ROOM_DEBUG: Adding is_rp = $is_rp");
    }
    
    if (in_array('youtube_enabled', $chatroom_columns)) {
        $insert_fields[] = 'youtube_enabled';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $youtube_enabled;
        error_log("CREATE_ROOM_DEBUG: Adding youtube_enabled = $youtube_enabled");
    }
    
    if (in_array('theme', $chatroom_columns)) {
        $insert_fields[] = 'theme';
        $insert_values[] = '?';
        $param_types .= 's';
        $param_values[] = $theme;
        error_log("CREATE_ROOM_DEBUG: Adding theme = $theme");
    }
    
    if (in_array('friends_only', $chatroom_columns)) {
        $insert_fields[] = 'friends_only';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $friends_only;
        error_log("CREATE_ROOM_DEBUG: Adding friends_only = $friends_only");
    }
    
    if (in_array('invite_only', $chatroom_columns)) {
        $insert_fields[] = 'invite_only';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $invite_only;
        error_log("CREATE_ROOM_DEBUG: Adding invite_only = $invite_only");
    }
    
    if (in_array('invite_code', $chatroom_columns)) {
    $invite_code = bin2hex(random_bytes(16));
    $insert_fields[] = 'invite_code';
    $insert_values[] = '?';
    $param_types .= 's';
    $param_values[] = $invite_code;
    error_log("CREATE_ROOM_DEBUG: Generated invite code for all rooms: $invite_code");
}
    
    if (in_array('members_only', $chatroom_columns)) {
        $insert_fields[] = 'members_only';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $members_only;
        error_log("CREATE_ROOM_DEBUG: Adding members_only = $members_only");
    }
    
    if (in_array('disappearing_messages', $chatroom_columns)) {
        $insert_fields[] = 'disappearing_messages';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $disappearing_messages;
        error_log("CREATE_ROOM_DEBUG: Adding disappearing_messages = $disappearing_messages");
    }
    
    if (in_array('message_lifetime_minutes', $chatroom_columns)) {
        $insert_fields[] = 'message_lifetime_minutes';
        $insert_values[] = '?';
        $param_types .= 'i';
        $param_values[] = $message_lifetime_minutes;
        error_log("CREATE_ROOM_DEBUG: Adding message_lifetime_minutes = $message_lifetime_minutes");
    }
    
    $insert_sql = "INSERT INTO chatrooms (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_values) . ")";
    
    error_log("CREATE_ROOM_DEBUG: Final SQL: $insert_sql");
    error_log("CREATE_ROOM_DEBUG: Param types: $param_types");
    error_log("CREATE_ROOM_DEBUG: Param count: " . count($param_values));
    
    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param($param_types, ...$param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create room: ' . $stmt->error);
    }
    
    $room_id = $conn->insert_id;
    $stmt->close();
    
    error_log("CREATE_ROOM_DEBUG: Room created with ID: $room_id");
    
// VERIFICATION: Check if password was actually saved
if ($has_password && $hashed_password) {
    // Add a small delay to ensure database commit
    usleep(200000); // 0.2 second delay
    
    $verify_stmt = $conn->prepare("SELECT password, has_password FROM chatrooms WHERE id = ?");
    if ($verify_stmt) {
        $verify_stmt->bind_param("i", $room_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $verify_data = $verify_result->fetch_assoc();
            $stored_password = $verify_data['password'];
            $stored_has_password = $verify_data['has_password'];
            
            error_log("CREATE_ROOM_DEBUG: VERIFICATION - has_password: $stored_has_password, stored_password_length: " . strlen($stored_password ?? ''));
            
            // Check if the data was saved correctly
            if ($stored_has_password != 1) {
                error_log("CREATE_ROOM_DEBUG: ERROR - has_password not set correctly");
                $verify_stmt->close();
                throw new Exception("Password protection flag was not saved correctly");
            }
            
            if (empty($stored_password) || strlen($stored_password) < 20) {
                error_log("CREATE_ROOM_DEBUG: ERROR - password hash not saved correctly. Expected length ~60, got: " . strlen($stored_password ?? ''));
                $verify_stmt->close();
                throw new Exception("Password was not saved correctly. Please try again.");
            }
            
            error_log("CREATE_ROOM_DEBUG: Password verification successful");
        } else {
            error_log("CREATE_ROOM_DEBUG: Room not found in verification");
        }
        $verify_stmt->close();
    }
}
    
    // Add host to chatroom_users - USE YOUR ORIGINAL WORKING CODE
    $user_columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    if (!$user_columns_query) {
        throw new Exception('Cannot check user table structure: ' . $conn->error);
    }
    
    $user_columns = [];
    while ($row = $user_columns_query->fetch_assoc()) {
        $user_columns[] = $row['Field'];
    }
    
    error_log("CREATE_ROOM_DEBUG: Available chatroom_users columns: " . implode(', ', $user_columns));
    
    // Build INSERT query for chatroom_users based on available columns
    $user_insert_fields = ['room_id', 'user_id_string', 'is_host'];
    $user_insert_values = ['?', '?', '1'];
    $user_param_types = 'is';
    $user_param_values = [$room_id, $user_id_string];
    
    if (in_array('user_id', $user_columns)) {
        $user_insert_fields[] = 'user_id';
        $user_insert_values[] = '?';
        $user_param_types .= 'i';
        $user_param_values[] = $host_user_id;
    }
    
    if (in_array('guest_name', $user_columns)) {
        $guest_name = $_SESSION['user']['name'] ?? null;
        $user_insert_fields[] = 'guest_name';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $guest_name;
    }
    
    if (in_array('username', $user_columns)) {
        $username = $_SESSION['user']['username'] ?? null;
        $user_insert_fields[] = 'username';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $username;
    }
    
    if (in_array('guest_avatar', $user_columns)) {
        $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
        $user_insert_fields[] = 'guest_avatar';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $avatar;
    }
    
    if (in_array('avatar', $user_columns)) {
        $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
        $user_insert_fields[] = 'avatar';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $avatar;
    }
    
    if (in_array('ip_address', $user_columns)) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_insert_fields[] = 'ip_address';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $ip_address;
    }
    
    if (in_array('color', $user_columns)) {
        $color = $_SESSION['user']['color'] ?? 'blue';
        $user_insert_fields[] = 'color';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $color;
    }

    if (in_array('avatar_hue', $user_columns)) {
        $avatar_hue = (int)($_SESSION['user']['avatar_hue'] ?? 0);
        $user_insert_fields[] = 'avatar_hue';
        $user_insert_values[] = '?';
        $user_param_types .= 'i';
        $user_param_values[] = $avatar_hue;
    }

    if (in_array('avatar_saturation', $user_columns)) {
        $avatar_saturation = (int)($_SESSION['user']['avatar_saturation'] ?? 100);
        $user_insert_fields[] = 'avatar_saturation';
        $user_insert_values[] = '?';
        $user_param_types .= 'i';
        $user_param_values[] = $avatar_saturation;
    }

    if (in_array('bubble_hue', $user_columns)) {
        $bubble_hue = (int)($_SESSION['user']['bubble_hue'] ?? 0);
        $user_insert_fields[] = 'bubble_hue';
        $user_insert_values[] = '?';
        $user_param_types .= 'i';
        $user_param_values[] = $bubble_hue;
    }

    if (in_array('bubble_saturation', $user_columns)) {
        $bubble_saturation = (int)($_SESSION['user']['bubble_saturation'] ?? 100);
        $user_insert_fields[] = 'bubble_saturation';
        $user_insert_values[] = '?';
        $user_param_types .= 'i';
        $user_param_values[] = $bubble_saturation;
    }
    
    $user_insert_sql = "INSERT INTO chatroom_users (" . implode(', ', $user_insert_fields) . ") VALUES (" . implode(', ', $user_insert_values) . ")";
    
    error_log("CREATE_ROOM_DEBUG: User SQL: $user_insert_sql");
    
    $stmt = $conn->prepare($user_insert_sql);
    if (!$stmt) {
        throw new Exception('Database prepare error for user: ' . $conn->error);
    }
    
    $stmt->bind_param($user_param_types, ...$user_param_values);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add host to room: ' . $stmt->error);
    }
    $stmt->close();
    
    // Add room creation message
    $display_name = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Host';
    $password_status = $has_password ? ' (password protected)' : '';
    $creation_message = "Room '{$name}' has been created by {$display_name}{$password_status}";
if ($permanent) {
    $creation_message .= " (permanent room)";
}
    
    // Check if messages table has the right columns
    $message_columns_query = $conn->query("SHOW COLUMNS FROM messages");
    if ($message_columns_query) {
        $message_columns = [];
        while ($row = $message_columns_query->fetch_assoc()) {
            $message_columns[] = $row['Field'];
        }
        
        if (in_array('is_system', $message_columns)) {
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, ?, ?, 1, NOW(), ?, 'system')");
            if ($stmt) {
                $avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
                $stmt->bind_param("isss", $room_id, $user_id_string, $creation_message, $avatar);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
    // Set session room_id so user is automatically in the room
    $_SESSION['room_id'] = $room_id;
    
    $conn->commit();
    
    error_log("CREATE_ROOM_DEBUG: Success! Room ID=$room_id, Name=$name, HasPassword=$has_password");
    
    $response = [
        'status' => 'success',
        'message' => 'Room created successfully',
        'room_id' => $room_id
    ];
    
    // NEW: Include invite code in response if invite_only is enabled
    if ($invite_code) {
    $response['invite_code'] = $invite_code;
    $response['invite_link'] = "lounge.php?invite=" . $invite_code;
}
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("CREATE_ROOM_DEBUG: Error - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to create room: ' . $e->getMessage()]);
}

$conn->close();
?>
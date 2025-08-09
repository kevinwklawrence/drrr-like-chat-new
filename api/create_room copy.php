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

// Get form data
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$capacity = (int)($_POST['capacity'] ?? 10);
$background = $_POST['background'] ?? '';
$has_password = (int)($_POST['has_password'] ?? 0);
$password = $_POST['password'] ?? '';
$allow_knocking = (int)($_POST['allow_knocking'] ?? 1);

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$host_user_id = $_SESSION['user']['type'] === 'registered' ? $_SESSION['user']['user_id'] : null;

error_log("CREATE_ROOM_FIXED: Starting room creation");
error_log("CREATE_ROOM_FIXED: has_password=$has_password, password_length=" . strlen($password));

// Validation
if (empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Room name is required']);
    exit;
}

if (strlen($name) > 50) {
    echo json_encode(['status' => 'error', 'message' => 'Room name is too long (max 50 characters)']);
    exit;
}

// FIXED: Better password validation logic
if ($has_password) {
    if (empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Password is required when password protection is enabled']);
        exit;
    }
    if (strlen($password) < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 1 character long']);
        exit;
    }
} else {
    // If no password protection, clear the password
    $password = '';
}

if (!in_array($capacity, [5, 10, 20, 50])) {
    $capacity = 10;
}

error_log("CREATE_ROOM_FIXED: Validation passed");

try {
    $conn->begin_transaction();
    
    // FIXED: Simplified approach - always include password and has_password fields
    // Hash password if provided
    $hashed_password = null;
    if ($has_password && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        error_log("CREATE_ROOM_FIXED: Password hashed successfully");
    } else {
        error_log("CREATE_ROOM_FIXED: No password to hash");
    }
    
    // Build INSERT query with all required fields
    $stmt = $conn->prepare("
        INSERT INTO chatrooms (
            name, 
            description, 
            capacity, 
            background, 
            password, 
            has_password, 
            allow_knocking, 
            host_user_id, 
            host_user_id_string, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    error_log("CREATE_ROOM_FIXED: Binding parameters...");
    error_log("CREATE_ROOM_FIXED: has_password value: $has_password");
    error_log("CREATE_ROOM_FIXED: hashed_password: " . ($hashed_password ? 'SET' : 'NULL'));
    
    $stmt->bind_param(
        "ssissiiis", 
        $name, 
        $description, 
        $capacity, 
        $background, 
        $hashed_password, 
        $has_password, 
        $allow_knocking, 
        $host_user_id, 
        $user_id_string
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create room: ' . $stmt->error);
    }
    
    $room_id = $conn->insert_id;
    $stmt->close();
    
    error_log("CREATE_ROOM_FIXED: Room created with ID: $room_id");
    
    // Verify the password was saved correctly
    $verify_stmt = $conn->prepare("SELECT password, has_password FROM chatrooms WHERE id = ?");
    $verify_stmt->bind_param("i", $room_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    $verify_stmt->close();
    
    error_log("CREATE_ROOM_FIXED: Verification - has_password: {$verify_data['has_password']}, password: " . (!empty($verify_data['password']) ? 'SET' : 'EMPTY'));
    
    // If password was provided but not saved correctly, try fallback method
    if ($has_password && empty($verify_data['password'])) {
        error_log("CREATE_ROOM_FIXED: Password not saved correctly, using fallback method");
        
        $fallback_stmt = $conn->prepare("UPDATE chatrooms SET password = ?, has_password = ? WHERE id = ?");
        $fallback_stmt->bind_param("sii", $hashed_password, $has_password, $room_id);
        
        if ($fallback_stmt->execute()) {
            error_log("CREATE_ROOM_FIXED: Fallback password update successful");
        } else {
            error_log("CREATE_ROOM_FIXED: Fallback password update failed: " . $fallback_stmt->error);
        }
        $fallback_stmt->close();
    }
    
    // Add host to room - use dynamic column checking for chatroom_users
    // Check what columns exist in chatroom_users table
    $user_columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
    if (!$user_columns_query) {
        throw new Exception('Cannot check user table structure: ' . $conn->error);
    }
    
    $user_columns = [];
    while ($row = $user_columns_query->fetch_assoc()) {
        $user_columns[] = $row['Field'];
    }
    
    error_log("CREATE_ROOM_FIXED: Available chatroom_users columns: " . implode(', ', $user_columns));
    
    // Build INSERT query for chatroom_users based on available columns
    $user_insert_fields = ['room_id', 'user_id_string', 'is_host'];
    $user_insert_values = ['?', '?', '1'];
    $user_param_types = 'is';
    $user_param_values = [$room_id, $user_id_string];
    
    // Add optional fields if they exist
    if (in_array('user_id', $user_columns)) {
        $user_insert_fields[] = 'user_id';
        $user_insert_values[] = '?';
        $user_param_types .= 'i';
        $user_param_values[] = $host_user_id;
    }
    
    if (in_array('username', $user_columns)) {
        $user_insert_fields[] = 'username';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $_SESSION['user']['username'] ?? null;
    }
    
    if (in_array('guest_name', $user_columns)) {
        $user_insert_fields[] = 'guest_name';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $_SESSION['user']['name'] ?? null;
    }
    
    if (in_array('avatar', $user_columns)) {
        $user_insert_fields[] = 'avatar';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
    }
    
    if (in_array('guest_avatar', $user_columns)) {
        $user_insert_fields[] = 'guest_avatar';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
    }
    
    if (in_array('ip_address', $user_columns)) {
        $user_insert_fields[] = 'ip_address';
        $user_insert_values[] = '?';
        $user_param_types .= 's';
        $user_param_values[] = $_SERVER['REMOTE_ADDR'];
    }
    
    $user_insert_sql = "INSERT INTO chatroom_users (" . implode(', ', $user_insert_fields) . ") VALUES (" . implode(', ', $user_insert_values) . ")";
    
    error_log("CREATE_ROOM_FIXED: User insert SQL: $user_insert_sql");
    error_log("CREATE_ROOM_FIXED: User insert params: " . print_r($user_param_values, true));
    
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
    
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), ?, 'system')");
    if ($stmt) {
        $stmt->bind_param("iss", $room_id, $creation_message, $avatar);
        $stmt->execute();
        $stmt->close();
    }
    
    // Set session room_id so user is automatically in the room
    $_SESSION['room_id'] = $room_id;
    
    $conn->commit();
    
    error_log("CREATE_ROOM_FIXED: Room created successfully - ID=$room_id, Name=$name, HasPassword=$has_password, AllowKnocking=$allow_knocking");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Room created successfully',
        'room_id' => $room_id,
        'debug' => [
            'has_password' => $has_password,
            'password_set' => !empty($hashed_password),
            'verification' => $verify_data
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("CREATE_ROOM_FIXED: Error - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to create room: ' . $e->getMessage()]);
}

$conn->close();
?>
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

// Handle POST request for login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $selected_avatar = $_POST['avatar'] ?? ''; // Allow empty avatar selection
    $selected_color = $_POST['color'] ?? ''; // Handle color selection
// Check if this is a complete login request with customization data
$has_customization_data = isset($_POST['avatar_hue']) && isset($_POST['avatar_saturation']);

if (!$has_customization_data) {
    error_log("⚠️ WARNING: Login request without avatar customization data - ignoring to prevent override");
    // If no customization data, check if user already has values and preserve them
    $existing_stmt = $conn->prepare("SELECT avatar_hue, avatar_saturation, bubble_hue, bubble_saturation FROM users WHERE username = ?");
    $existing_stmt->bind_param("s", $username);
    $existing_stmt->execute();
    $existing_result = $existing_stmt->get_result();
    if ($existing_result->num_rows > 0) {
        $existing_data = $existing_result->fetch_assoc();
        $avatar_hue = $existing_data['avatar_hue'] ?? 0;
        $avatar_saturation = $existing_data['avatar_saturation'] ?? 100;
        $bubble_hue = $existing_data['bubble_hue'] ?? 0;
        $bubble_saturation = $existing_data['bubble_saturation'] ?? 100;
        error_log("Using existing DB values - hue: $avatar_hue, sat: $avatar_saturation");
    } else {
        $avatar_hue = 0;
        $avatar_saturation = 100;
        $bubble_hue = 0;
        $bubble_saturation = 100;
    }
    $existing_stmt->close();
} else {
    // Normal processing with form data
    $avatar_hue = (int)$_POST['avatar_hue'];
    $avatar_saturation = (int)$_POST['avatar_saturation'];
    $bubble_hue = (int)($_POST['bubble_hue'] ?? 0);
    $bubble_saturation = (int)($_POST['bubble_saturation'] ?? 100);
    error_log("Using form values - hue: $avatar_hue, sat: $avatar_saturation, bubble_hue: $bubble_hue, bubble_sat: $bubble_saturation");
}


    if (empty($username) || empty($password)) {
        error_log("Missing username or password in login.php");
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // Validate color selection
    $valid_colors = [
        'black', 'blue', 'purple', 'pink', 'cyan', 'mint', 'orange', 
        'lavender', 'peach', 'green', 'yellow', 'red', 'teal', 
        'indigo', 'emerald', 'rose'
    ];

    if (!empty($selected_color) && !in_array($selected_color, $valid_colors)) {
        $selected_color = 'black'; // Default fallback
    }

    // Updated query to include color
    $stmt = $conn->prepare("SELECT id, username, user_id, email, password, is_admin, avatar, custom_av, avatar_memory, color FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("Prepare failed in login.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            
            // NEW: Determine the final avatar based on priority
            $final_avatar = null;
            $should_update_avatar_memory = false;
            
            if (!empty($selected_avatar)) {
                // User selected an avatar - use it and update avatar_memory
                $final_avatar = $selected_avatar;
                $should_update_avatar_memory = true;
                error_log("User selected avatar: $selected_avatar");
            } else {
                // No avatar selected - determine fallback
                if (!empty($user['custom_av'])) {
                    // Use custom avatar if available
                    $final_avatar = $user['custom_av'];
                    error_log("Using custom avatar: {$user['custom_av']} for user: $username");
                } elseif (!empty($user['avatar_memory'])) {
                    // Use remembered avatar
                    $final_avatar = $user['avatar_memory'];
                    error_log("Using remembered avatar: {$user['avatar_memory']} for user: $username");
                } else {
                    // UPDATED: Use correct default fallback path
                    $final_avatar = 'default/u0.png';
                    error_log("Using default avatar for user: $username");
                }
            }
            
           // Always update color and avatar customization
if (!empty($selected_color)) {
    $user['color'] = $selected_color;
}
$user['avatar_hue'] = $avatar_hue;
$user['avatar_saturation'] = $avatar_saturation;
$user['bubble_hue'] = $bubble_hue;
$user['bubble_saturation'] = $bubble_saturation;

// FORCE UPDATE - Always update the database regardless of current values
error_log("FORCING database update with hue: $avatar_hue, saturation: $avatar_saturation, bubble_hue: $bubble_hue, bubble_sat: $bubble_saturation");

// Always update avatar, avatar_memory, color, and customization data
$update_stmt = $conn->prepare("UPDATE users SET avatar = ?, avatar_memory = ?, color = ?, avatar_hue = ?, avatar_saturation = ?, bubble_hue = ?, bubble_saturation = ? WHERE id = ?");
if ($update_stmt) {
    $update_avatar_memory = $should_update_avatar_memory ? $selected_avatar : $user['avatar_memory'];
    $update_color = !empty($selected_color) ? $selected_color : $user['color'];
    
    error_log("Updating user DB: avatar=$final_avatar, avatar_memory=$update_avatar_memory, color=$update_color, hue=$avatar_hue, sat=$avatar_saturation, bubble_hue=$bubble_hue, bubble_sat=$bubble_saturation");
    error_log("DEBUG: bubble_hue=$bubble_hue, bubble_saturation=$bubble_saturation, type=" . gettype($bubble_saturation));
$update_stmt->bind_param("sssiiiii", $final_avatar, $update_avatar_memory, $update_color, $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation, $user['id']);
    if ($update_stmt->execute()) {
        error_log("✅ SUCCESS: All user data updated in database");
        
        // Update local user array with new values
        $user['avatar'] = $final_avatar;
        $user['avatar_memory'] = $update_avatar_memory;
        $user['color'] = $update_color;
        $user['avatar_hue'] = $avatar_hue;
        $user['avatar_saturation'] = $avatar_saturation;
        
    } else {
        error_log("❌ FAILED to update user data: " . $update_stmt->error);
    }
    $update_stmt->close();
} else {
    error_log("❌ FAILED to prepare user update statement: " . $conn->error);
}
            
            // ADDED: Update global_users table for registered users
try {
$stmt_global = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, guest_avatar, color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation, is_admin, ip_address, last_activity) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE username = VALUES(username), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), color = VALUES(color), avatar_hue = VALUES(avatar_hue), avatar_saturation = VALUES(avatar_saturation), bubble_hue = VALUES(bubble_hue), bubble_saturation = VALUES(bubble_saturation), is_admin = VALUES(is_admin), ip_address = VALUES(ip_address), last_activity = NOW()");    
    if ($stmt_global) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
$final_avatar_hue = $user['avatar_hue'] ?? 0;
$final_avatar_saturation = $user['avatar_saturation'] ?? 0;
// Use the form values for global_users too
error_log("Updating global_users with hue: $avatar_hue, sat: $avatar_saturation");
$stmt_global->bind_param("sssssiiiiis", $user['user_id'], $user['username'], $final_avatar, $final_avatar, $user['color'], $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation, $user['is_admin'], $ip_address);
 if ($stmt_global->execute()) {
                        error_log("Registered user stored/updated in global_users with color: " . $user['color']);
                    } else {
                        error_log("Failed to update global_users: " . $stmt_global->error);
                    }
                    $stmt_global->close();
                } else {
                    error_log("Failed to prepare global_users statement: " . $conn->error);
                }
            } catch (Exception $e) {
                error_log("Failed to store registered user in global_users: " . $e->getMessage());
            }
            
            // FIXED: Create user session with final avatar and color (simplified logic)
            $_SESSION['user'] = [
                'type' => 'user',
                'id' => $user['id'],
                'username' => $user['username'],
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'is_admin' => $user['is_admin'],
                'avatar' => $final_avatar,
                'color' => $user['color'], // Use the updated color from $user array
'avatar_hue' => $avatar_hue,
'avatar_saturation' => $avatar_saturation,
'bubble_hue' => $bubble_hue,
'bubble_saturation' => $bubble_saturation,
                'ip' => $_SERVER['REMOTE_ADDR']
            ];
            
            // Debug log to ensure user_id and color are set correctly
            error_log("User logged in with user_id: " . ($user['user_id'] ?? 'NULL') . ", final_avatar: " . $final_avatar . ", color: " . $user['color']);
            
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    $stmt->close();
    exit;
}
error_log("🎯 FINAL SESSION VALUES - hue: " . ($_SESSION['user']['avatar_hue'] ?? 'NULL') . ", sat: " . ($_SESSION['user']['avatar_saturation'] ?? 'NULL'));
error_log("=== END LOGIN AVATAR CUSTOMIZATION DEBUG ===");
?>
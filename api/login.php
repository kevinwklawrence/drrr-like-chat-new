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
    $has_color_selection = !empty($selected_color);

    if (empty($username) || empty($password)) {
        error_log("Missing username or password in login.php");
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // Stop people from using Lenn's color.
    if (($username !== 'Lenn') && ($selected_color === 'lenn')) {
        error_log("Invalid selection");
        echo json_encode(['status' => 'error', 'message' => 'Designated username and color do not match.']);
        exit;
    }

    // Validate color selection if provided
    $valid_colors = [
    'black', 'policeman2','negative','gray','tan','blue','cobalt','lavender','lavender2',
    'teal2','navy','purple','pink','orange','orange2','peach','green','urban','mudgreen',
    'palegreen','red','toyred','spooky','rose','yellow','bbyellow','brown','deepbrown',
    'forest', 'rust', 'babyblue', 'sepia', 'chiipink', 'cnegative', 'cyan', 'caution', 'darkgray',
    'spooky2', 'spooky3', 'spooky4', 'spooky5', 'spooky6',
    'lenn', 'kisin'
];

    if (!empty($selected_color) && !in_array($selected_color, $valid_colors)) {
        $selected_color = 'black'; // Default fallback
    }

    // Updated query to include all customization fields
$stmt = $conn->prepare("SELECT id, username, user_id, email, password, is_admin, is_moderator, avatar, custom_av, avatar_memory, color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation, dura, tokens, event_currency FROM users WHERE username = ?");
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
            
            // ENHANCED: Handle all customization fields with memory
            $final_avatar = null;
            $final_color = null;
            $final_avatar_hue = 0;
            $final_avatar_saturation = 100;
            $final_bubble_hue = 0;
            $final_bubble_saturation = 100;
            $should_update_avatar_memory = false;

            // === AVATAR LOGIC ===
            if (!empty($selected_avatar)) {
                // User selected an avatar - use it and update avatar_memory
                $final_avatar = $selected_avatar;
                $should_update_avatar_memory = true;
                error_log("User selected avatar: $selected_avatar");
            } else {
                // No avatar selected - determine fallback
                if (!empty($user['custom_av'])) {
                    $final_avatar = $user['custom_av'];
                    error_log("Using custom avatar: {$user['custom_av']} for user: $username");
                } elseif (!empty($user['avatar_memory'])) {
                    $final_avatar = $user['avatar_memory'];
                    error_log("Using remembered avatar: {$user['avatar_memory']} for user: $username");
                } else {
                    $final_avatar = 'default/u0.png';
                    error_log("Using default avatar for user: $username");
                }
            }

            // === COLOR LOGIC ===
            if ($has_color_selection) {
                // User selected a color - use it
                $final_color = $selected_color;
                error_log("User selected color: $selected_color");
            } else {
                // No color selected - use saved color or default
                $final_color = !empty($user['color']) ? $user['color'] : 'black';
                error_log("Using saved color: $final_color for user: $username");
            }

            // === AVATAR CUSTOMIZATION LOGIC ===
            if ($has_customization_data) {
                // User provided customization data - use it
                $final_avatar_hue = (int)$_POST['avatar_hue'];
                $final_avatar_saturation = (int)$_POST['avatar_saturation'];
                $final_bubble_hue = (int)($_POST['bubble_hue'] ?? 0);
                $final_bubble_saturation = (int)($_POST['bubble_saturation'] ?? 100);
                error_log("Using form customization values - avatar_hue: $final_avatar_hue, avatar_sat: $final_avatar_saturation, bubble_hue: $final_bubble_hue, bubble_sat: $final_bubble_saturation");
            } else {
                // No customization data - preserve existing values
                $final_avatar_hue = (int)($user['avatar_hue'] ?? 0);
                $final_avatar_saturation = (int)($user['avatar_saturation'] ?? 100);
                $final_bubble_hue = (int)($user['bubble_hue'] ?? 0);
                $final_bubble_saturation = (int)($user['bubble_saturation'] ?? 100);
                error_log("Preserving existing customization values - avatar_hue: $final_avatar_hue, avatar_sat: $final_avatar_saturation, bubble_hue: $final_bubble_hue, bubble_sat: $final_bubble_saturation");
            }

            // === DATABASE UPDATE ===
            // Always update all fields to ensure consistency
            $update_avatar_memory = $should_update_avatar_memory ? $selected_avatar : $user['avatar_memory'];
            
            error_log("Updating user DB: avatar=$final_avatar, avatar_memory=$update_avatar_memory, color=$final_color, hue=$final_avatar_hue, sat=$final_avatar_saturation, bubble_hue=$final_bubble_hue, bubble_sat=$final_bubble_saturation");
            
            $update_stmt = $conn->prepare("UPDATE users SET avatar = ?, avatar_memory = ?, color = ?, avatar_hue = ?, avatar_saturation = ?, bubble_hue = ?, bubble_saturation = ? WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("sssiiiii", $final_avatar, $update_avatar_memory, $final_color, $final_avatar_hue, $final_avatar_saturation, $final_bubble_hue, $final_bubble_saturation, $user['id']);
                if ($update_stmt->execute()) {
                    error_log("✅ SUCCESS: All user data updated in database");
                } else {
                    error_log("❌ FAILED to update user data: " . $update_stmt->error);
                }
                $update_stmt->close();
            } else {
                error_log("❌ FAILED to prepare user update statement: " . $conn->error);
            }
            
            // === GLOBAL_USERS UPDATE ===
            try {
                $stmt_global = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, guest_avatar, color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation, is_admin, ip_address, last_activity) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE username = VALUES(username), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), color = VALUES(color), avatar_hue = VALUES(avatar_hue), avatar_saturation = VALUES(avatar_saturation), bubble_hue = VALUES(bubble_hue), bubble_saturation = VALUES(bubble_saturation), is_admin = VALUES(is_admin), ip_address = VALUES(ip_address), last_activity = NOW()");    
                if ($stmt_global) {
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    error_log("Updating global_users with final values - color: $final_color, hue: $final_avatar_hue, sat: $final_avatar_saturation, bubble_hue: $final_bubble_hue, bubble_sat: $final_bubble_saturation");
                    $stmt_global->bind_param("sssssiiiiis", $user['user_id'], $user['username'], $final_avatar, $final_avatar, $final_color, $final_avatar_hue, $final_avatar_saturation, $final_bubble_hue, $final_bubble_saturation, $user['is_admin'], $ip_address);
                    if ($stmt_global->execute()) {
                        error_log("Registered user stored/updated in global_users");
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
            
            // === SESSION CREATION ===
            $_SESSION['user'] = [
    'type' => 'user',
    'id' => $user['id'],
    'username' => $user['username'],
    'user_id' => $user['user_id'],
    'email' => $user['email'],
    'is_admin' => $user['is_admin'],
    'is_moderator' => $user['is_moderator'],
    'avatar' => $final_avatar,
    'color' => $final_color,
    'avatar_hue' => $final_avatar_hue,
    'avatar_saturation' => $final_avatar_saturation,
    'bubble_hue' => $final_bubble_hue,
    'bubble_saturation' => $final_bubble_saturation,
    'dura' => $user['dura'] ?? 0,
'tokens' => $user['tokens'] ?? 20,
'event_currency' => $user['event_currency'] ?? 0,
    'ip' => $_SERVER['REMOTE_ADDR']
];
            
            error_log("🎯 FINAL SESSION VALUES - avatar: $final_avatar, color: $final_color, hue: $final_avatar_hue, sat: $final_avatar_saturation, bubble_hue: $final_bubble_hue, bubble_sat: $final_bubble_saturation");
            
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
?>
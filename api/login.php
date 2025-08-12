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
            
            // Update user's avatar, avatar_memory, and color in database if needed
            $updates_needed = [];
            $update_params = [];
            $param_types = '';
            
            // Always update current avatar if it's different
            if ($final_avatar !== $user['avatar']) {
                $updates_needed[] = 'avatar = ?';
                $update_params[] = $final_avatar;
                $param_types .= 's';
            }
            
            // Update avatar_memory if user selected an avatar
            if ($should_update_avatar_memory && $selected_avatar !== $user['avatar_memory']) {
                $updates_needed[] = 'avatar_memory = ?';
                $update_params[] = $selected_avatar;
                $param_types .= 's';
            }
            
            // FIXED: Update color if selected (always update, regardless of current color)
            if (!empty($selected_color)) {
                $updates_needed[] = 'color = ?';
                $update_params[] = $selected_color;
                $param_types .= 's';
                $user['color'] = $selected_color; // Update local variable for session
                error_log("Color will be updated to: $selected_color");
            }
            
            // Perform database update if needed
            if (!empty($updates_needed)) {
                $update_sql = "UPDATE users SET " . implode(', ', $updates_needed) . " WHERE id = ?";
                $update_params[] = $user['id'];
                $param_types .= 'i';
                
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param($param_types, ...$update_params);
                    if ($update_stmt->execute()) {
                        error_log("Database update successful for user: $username");
                    } else {
                        error_log("Database update failed: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                } else {
                    error_log("Failed to prepare update statement: " . $conn->error);
                }
            }
            
            // REMOVED: Redundant separate color update logic that was causing conflicts
            
            // ADDED: Update global_users table for registered users
            try {
                $stmt_global = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, guest_avatar, color, is_admin, ip_address, last_activity) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE username = VALUES(username), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), color = VALUES(color), is_admin = VALUES(is_admin), ip_address = VALUES(ip_address), last_activity = NOW()");
                
                if ($stmt_global) {
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $stmt_global->bind_param("sssssis", $user['user_id'], $user['username'], $final_avatar, $final_avatar, $user['color'], $user['is_admin'], $ip_address);
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
?>
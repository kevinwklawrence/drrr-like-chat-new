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

// IMPORTANT: Replace the entire POST handler section in login.php (lines ~20-80) with this:

// Handle POST request for login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $selected_avatar = $_POST['avatar'] ?? ''; // Allow empty avatar selection

    if (empty($username) || empty($password)) {
        error_log("Missing username or password in login.php");
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // UPDATED: Remove old default avatar fallback - let the new logic handle it
    // The old line: if (empty($selected_avatar)) { $selected_avatar = 'u0.png'; }
    // is now removed because we handle fallbacks in the new avatar priority system

    // Updated query to include custom_av and avatar_memory
    $stmt = $conn->prepare("SELECT id, username, user_id, email, password, is_admin, avatar, custom_av, avatar_memory FROM users WHERE username = ?");
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
            
            // Update user's avatar and avatar_memory in database if needed
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
            
            // Perform database update if needed
            if (!empty($updates_needed)) {
                $update_sql = "UPDATE users SET " . implode(', ', $updates_needed) . " WHERE id = ?";
                $update_params[] = $user['id'];
                $param_types .= 'i';
                
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param($param_types, ...$update_params);
                    if ($update_stmt->execute()) {
                        error_log("Updated user data: avatar=$final_avatar" . ($should_update_avatar_memory ? ", avatar_memory=$selected_avatar" : "") . " for user: $username");
                        $user['avatar'] = $final_avatar; // Update local variable
                    } else {
                        error_log("Failed to update user data: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                }
            }
            
            // Create user session with final avatar
            $_SESSION['user'] = [
                'type' => 'user',
                'id' => $user['id'],
                'username' => $user['username'],
                'user_id' => $user['user_id'],  // This is crucial for the host system!
                'email' => $user['email'],
                'is_admin' => $user['is_admin'],
                'avatar' => $final_avatar, // Use the determined final avatar
                'ip' => $_SERVER['REMOTE_ADDR']
            ];
            
            // Debug log to ensure user_id is set
            error_log("User logged in with user_id: " . ($user['user_id'] ?? 'NULL') . ", final_avatar: " . $final_avatar);
            
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
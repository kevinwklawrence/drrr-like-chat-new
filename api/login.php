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

$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$selected_avatar = isset($_POST['avatar']) ? $_POST['avatar'] : '';

if (empty($username) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
    exit;
}

if (empty($selected_avatar)) {
    echo json_encode(['status' => 'error', 'message' => 'Please select an avatar']);
    exit;
}

// Updated query to include user_id and email
$stmt = $conn->prepare("SELECT id, username, user_id, email, password, is_admin, avatar FROM users WHERE username = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        
        // Update user's avatar in database if they selected a new one
        if ($selected_avatar !== $user['avatar']) {
            $update_stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("si", $selected_avatar, $user['id']);
                if ($update_stmt->execute()) {
                    error_log("Updated avatar for user: username=$username, new_avatar=$selected_avatar");
                    $user['avatar'] = $selected_avatar; // Update local variable
                } else {
                    error_log("Failed to update avatar: " . $update_stmt->error);
                }
                $update_stmt->close();
            }
        }
        
        // Create user session with updated avatar
        $_SESSION['user'] = [
            'type' => 'user',
            'id' => $user['id'],
            'username' => $user['username'],
            'user_id' => $user['user_id'],  // This is crucial for the host system!
            'email' => $user['email'],
            'is_admin' => $user['is_admin'],
            'avatar' => $user['avatar'], // Use the updated avatar
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
        
        // Debug log to ensure user_id is set
        error_log("User logged in with user_id: " . ($user['user_id'] ?? 'NULL') . ", avatar: " . $user['avatar']);
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
}
$stmt->close();
?>
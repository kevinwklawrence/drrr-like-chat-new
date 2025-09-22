<?php
session_start();

// Include database connection to clean up user data
if (file_exists('db_connect.php')) {
    include 'db_connect.php';
    
    if (isset($_SESSION['user'])) {
        $user_id_string = $_SESSION['user']['user_id'] ?? '';
        
        // Remove user from all rooms
        if (!empty($user_id_string)) {
            $stmt = $conn->prepare("DELETE FROM chatroom_users WHERE user_id_string = ?");
            if ($stmt) {
                $stmt->bind_param("s", $user_id_string);
                $stmt->execute();
                $stmt->close();
            }
        }
        // Remove user from all rooms
        if (!empty($user_id_string)) {
            $stmt = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
            if ($stmt) {
                $stmt->bind_param("s", $user_id_string);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Preserve firewall session while clearing user data
$preserve_firewall = $_SESSION['firewall_passed'] ?? false;

// Clear user-specific session data only
unset($_SESSION['user']);
unset($_SESSION['room_id']);
unset($_SESSION['pending_invite']);

// Restore firewall session
if ($preserve_firewall) {
    $_SESSION['firewall_passed'] = true;
}

// Redirect to index page (user can now use guest login)
header("Location: /guest");
exit;
?>
<?php
session_start();

// Include database connection to clean up user data
if (file_exists('db_connect.php')) {
    include 'db_connect.php';
    
    if (isset($_SESSION['user'])) {
        $user_id_string = $_SESSION['user']['user_id'] ?? '';
        $user_id = $_SESSION['user']['id'] ?? 0;
        
        // Remove user from all rooms
        if (!empty($user_id_string)) {
            $stmt = $conn->prepare("DELETE FROM chatroom_users WHERE user_id_string = ?");
            if ($stmt) {
                $stmt->bind_param("s", $user_id_string);
                $stmt->execute();
                $stmt->close();
            }
            
            $stmt = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
            if ($stmt) {
                $stmt->bind_param("s", $user_id_string);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Clear remember token from database
        if ($user_id > 0) {
            $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Clear persistent cookie
if (isset($_COOKIE['duranu_remember'])) {
    setcookie('duranu_remember', '', time() - 3600, '/', '', true, true);
}

// Clear all session data
session_destroy();

// Redirect to firewall
header("Location: /firewall");
exit;
?>
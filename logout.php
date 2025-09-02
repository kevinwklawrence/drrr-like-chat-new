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

// Clear all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: firewall.php");


//header("Location: index.php");
exit;
?>
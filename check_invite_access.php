<?php
// check_invite_access.php - Middleware to verify firewall access
// Include at top of protected pages (lounge.php, room.php, etc.)

// Check persistent login cookie if not in session
if (!isset($_SESSION['user']) && isset($_COOKIE['duranu_remember'])) {
    $remember_token = $_COOKIE['duranu_remember'];
    
    $stmt = $conn->prepare("SELECT u.* FROM users u WHERE u.remember_token = ? AND u.remember_token IS NOT NULL");
    $stmt->bind_param("s", $remember_token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Log them in automatically
        $_SESSION['user'] = [
            'type' => 'user',
            'id' => $user['id'],
            'username' => $user['username'],
            'user_id' => $user['user_id'],
            'avatar' => $user['avatar'],
            'is_admin' => $user['is_admin'],
            'is_moderator' => $user['is_moderator'] ?? 0,
            'color' => $user['color'] ?? 'blue',
            'avatar_hue' => $user['avatar_hue'] ?? 0,
            'avatar_saturation' => $user['avatar_saturation'] ?? 100,
            'bubble_hue' => $user['bubble_hue'] ?? 0,
            'bubble_saturation' => $user['bubble_saturation'] ?? 100
        ];
        $_SESSION['firewall_passed'] = true;
        $_SESSION['invite_verified'] = true;
    }
    $stmt->close();
}

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['firewall_passed'])) {
    header("Location: /firewall");
    exit;
}
?>
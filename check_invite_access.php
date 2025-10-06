<?php
// check_invite_access.php - Middleware to verify invite system access
// Include at top of protected pages (lounge.php, room.php, etc.)

if (!isset($_SESSION['firewall_passed']) || !isset($_SESSION['invite_verified'])) {
    header("Location: /firewall");
    exit;
}

// If not logged in as registered user, check if trial expired
if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("SELECT expires_at < NOW() as is_expired, account_created 
        FROM invite_usage 
        WHERE invitee_ip = ? 
        ORDER BY first_used_at DESC LIMIT 1");
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $usage = $result->fetch_assoc();
        
        if (!$usage['account_created'] && $usage['is_expired']) {
            // Trial expired, force registration
            $stmt->close();
            header("Location: /register");
            exit;
        }
    }
    $stmt->close();
}
?>
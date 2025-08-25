<?php
// check_site_ban.php - Include this at the top of main pages
function checkSiteBan($conn, $return_json = false) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_id = null;
    $user_id_string = '';
    
    if (isset($_SESSION['user'])) {
        $user_id = $_SESSION['user']['id'] ?? null;
        $user_id_string = $_SESSION['user']['user_id'] ?? '';
    }
    
    // Check for site ban
    $ban_conditions = ['sb.ip_address = ?'];
    $ban_params = [$ip_address];
    $param_types = 's';
    
    if ($user_id) {
        $ban_conditions[] = 'sb.user_id = ?';
        $ban_params[] = $user_id;
        $param_types .= 'i';
    }
    
    if ($user_id_string) {
        $ban_conditions[] = 'sb.user_id_string = ?';
        $ban_params[] = $user_id_string;
        $param_types .= 's';
    }
    
    $ban_query = "
        SELECT sb.*, 
               CASE 
                   WHEN sb.ban_until IS NULL THEN 1
                   WHEN sb.ban_until > NOW() THEN 1
                   ELSE 0
               END as is_active_ban
        FROM site_bans sb
        WHERE (" . implode(' OR ', $ban_conditions) . ")
        AND (sb.ban_until IS NULL OR sb.ban_until > NOW())
        ORDER BY sb.timestamp DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($ban_query);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$ban_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $ban = $result->fetch_assoc();
            $stmt->close();
            
            // User is banned
            session_destroy();
            
            $ban_message = "You have been banned from this site.";
            if ($ban['ban_until']) {
                $expires_at = new DateTime($ban['ban_until']);
                $now = new DateTime();
                $diff = $now->diff($expires_at);
                
                if ($diff->days > 0) {
                    $ban_message .= " Ban expires in " . $diff->days . " day" . ($diff->days != 1 ? "s" : "") . ".";
                } elseif ($diff->h > 0) {
                    $ban_message .= " Ban expires in " . $diff->h . " hour" . ($diff->h != 1 ? "s" : "") . ".";
                } else {
                    $ban_message .= " Ban expires in " . $diff->i . " minute" . ($diff->i != 1 ? "s" : "") . ".";
                }
            } else {
                $ban_message .= " This is a permanent ban.";
            }
            
            if ($ban['reason']) {
                $ban_message .= "\n\nReason: " . htmlspecialchars($ban['reason']);
            }
            
            // Check if this is an AJAX request (for join_lounge.php)
            if ($return_json || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => $ban_message,
                    'banned' => true,
                    'ban_details' => $ban
                ]);
                exit;
            }
            
            // Show ban page for regular page loads
            showSiteBanPage($ban_message, $ban);
            exit;
        }
        $stmt->close();
    }
    
    return false; // Not banned
}

function showSiteBanPage($message, $ban_details) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Site Banned</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .ban-container {
                max-width: 600px;
                text-align: center;
                padding: 2rem;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 20px;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            }
            .ban-icon {
                font-size: 4rem;
                color: #dc3545;
                margin-bottom: 1rem;
            }
            .ban-message {
                white-space: pre-line;
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            .ban-details {
                background: rgba(0, 0, 0, 0.3);
                padding: 1rem;
                border-radius: 10px;
                margin-top: 1rem;
                text-align: left;
            }
            .detail-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
            }
            .detail-row:last-child {
                margin-bottom: 0;
            }
        </style>
    </head>
    <body>
        <div class="ban-container">
            <i class="fas fa-ban ban-icon"></i>
            <h1 class="h2 mb-4">Access Denied</h1>
            <div class="ban-message"><?php echo nl2br(htmlspecialchars($message)); ?></div>
            
            <div class="ban-details">
                <h6><i class="fas fa-info-circle"></i> Ban Details</h6>
                <?php if ($ban_details['banned_by_username']): ?>
                    <div class="detail-row">
                        <span>Banned by:</span>
                        <span><?php echo htmlspecialchars($ban_details['banned_by_username']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span>Ban date:</span>
                    <span><?php echo date('Y-m-d H:i:s', strtotime($ban_details['timestamp'])); ?></span>
                </div>
                <?php if ($ban_details['ban_until']): ?>
                    <div class="detail-row">
                        <span>Expires:</span>
                        <span><?php echo date('Y-m-d H:i:s', strtotime($ban_details['ban_until'])); ?></span>
                    </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span>Ban ID:</span>
                    <span>#<?php echo $ban_details['id']; ?></span>
                </div>
            </div>
            
            <div class="mt-4">
                <small class="text-muted">
                    If you believe this ban is in error, please contact the site administrators with your Ban ID.
                </small>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Clean up expired bans
function cleanupExpiredSiteBans($conn) {
    $stmt = $conn->prepare("DELETE FROM site_bans WHERE ban_until IS NOT NULL AND ban_until <= NOW()");
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}
?>
<?php
// cron_regenerate_invites.php - Regenerate invite codes monthly
// Run this via cron: 0 0 1 * * /usr/bin/php /path/to/cron_regenerate_invites.php

include __DIR__ . '/db_connect.php';

echo "Starting invite code regeneration...\n";

// Function to generate 8-char alphanumeric code
function generateInviteCode() {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed ambiguous chars
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $characters[mt_rand(0, strlen($characters) - 1)];
    }
    return $code;
}

try {
    // Get all users who need new codes (less than 3 active codes)
    $users = $conn->query("
        SELECT u.id, u.username, u.restricted, COUNT(ic.id) as code_count 
        FROM users u 
        LEFT JOIN invite_codes ic ON u.id = ic.owner_user_id AND ic.is_active = 1 
        GROUP BY u.id 
        HAVING code_count < 3
    ");
    
    $generated = 0;
    while ($user = $users->fetch_assoc()) {
        if ($user['restricted']) {
            echo "  Skipping restricted user: {$user['username']}\n";
            continue;
        }
        
        $needed = 3 - (int)$user['code_count'];
        echo "  User {$user['username']} needs $needed codes\n";
        
        for ($i = 0; $i < $needed; $i++) {
            // Generate unique 8-character code
            $attempts = 0;
            while ($attempts < 50) {
                $code = generateInviteCode();
                
                // Check uniqueness
                $check = $conn->prepare("SELECT id FROM invite_codes WHERE code = ?");
                $check->bind_param("s", $code);
                $check->execute();
                if ($check->get_result()->num_rows == 0) {
                    $check->close();
                    
                    // Insert unique code
                    $stmt = $conn->prepare("INSERT INTO invite_codes (code, owner_user_id) VALUES (?, ?)");
                    $stmt->bind_param("si", $code, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    $generated++;
                    break;
                }
                $check->close();
                $attempts++;
            }
        }
    }
    
    echo "✓ Generated $generated invite codes\n";
    
    // Clean up expired codes (optional - keep for history)
    // $conn->query("DELETE FROM invite_codes WHERE regenerates_at < NOW() AND is_active = 0");
    
    echo "✓ Invite code regeneration complete!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

$conn->close();
?>
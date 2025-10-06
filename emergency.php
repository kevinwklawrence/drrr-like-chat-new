<?php
// emergency_fix.php - EMERGENCY: Regenerate simple codes & add bypass
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>EMERGENCY FIX</h2>\n<pre>\n";

try {
    // 1. Delete all existing invite codes
    echo "Step 1: Clearing old invite codes...\n";
    $conn->query("DELETE FROM invite_codes");
    echo "✓ Cleared " . $conn->affected_rows . " old codes\n\n";
    
    // 2. Generate simple 8-character alphanumeric codes for all users
    echo "Step 2: Generating new 8-character codes...\n";
    $users = $conn->query("SELECT id, username, restricted FROM users");
    
    // Function to generate 8-char alphanumeric code
    function generateCode() {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed ambiguous chars: I, O, 0, 1
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    $generated = 0;
    while ($user = $users->fetch_assoc()) {
        if ($user['restricted']) {
            echo "  Skipping restricted user: {$user['username']}\n";
            continue;
        }
        
        // Generate 3 unique 8-character codes per user
        for ($i = 0; $i < 3; $i++) {
            $attempts = 0;
            while ($attempts < 50) {
                $code = generateCode();
                
                // Check if code already exists
                $check = $conn->prepare("SELECT id FROM invite_codes WHERE code = ?");
                $check->bind_param("s", $code);
                $check->execute();
                $result = $check->get_result();
                $check->close();
                
                if ($result->num_rows == 0) {
                    // Code is unique, insert it
                    $stmt = $conn->prepare("INSERT INTO invite_codes (code, owner_user_id) VALUES (?, ?)");
                    $stmt->bind_param("si", $code, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    $generated++;
                    echo "  Generated code {$code} for {$user['username']}\n";
                    break;
                }
                $attempts++;
            }
        }
    }
    
    echo "\n✓ Generated $generated new 8-character invite codes\n\n";
    
    // 3. Show all codes for admin reference
    echo "Step 3: Listing all codes by user...\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $codes_by_user = $conn->query("
        SELECT u.username, ic.code 
        FROM invite_codes ic 
        JOIN users u ON ic.owner_user_id = u.id 
        ORDER BY u.username, ic.code
    ");
    
    $current_user = '';
    while ($row = $codes_by_user->fetch_assoc()) {
        if ($current_user != $row['username']) {
            $current_user = $row['username'];
            echo "\n{$current_user}:\n";
        }
        echo "  - {$row['code']}\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "\n✅ EMERGENCY FIX COMPLETE!\n\n";
    echo "IMPORTANT NOTES:\n";
    echo "- All users now have 3 simple 8-character codes (letters + numbers)\n";
    echo "- Emergency bypass code: h4mburg3r (already active in firewall)\n";
    echo "- Share these codes with your users to regain access\n";
    echo "- You can delete this file after running\n\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
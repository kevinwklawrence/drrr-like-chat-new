<?php
// setup_invite_system.php - Setup invite-only system
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Invite-Only System Setup</h2>\n<pre>\n";

try {
    // 1. Add restricted column to users
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'restricted'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN restricted TINYINT(1) DEFAULT 0 NOT NULL AFTER is_admin");
        echo "✓ Added 'restricted' column to users\n";
    } else {
        echo "✓ 'restricted' column exists\n";
    }
    
    // 2. Add invite_code_used column to users
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'invite_code_used'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN invite_code_used VARCHAR(32) NULL AFTER restricted");
        echo "✓ Added 'invite_code_used' column to users\n";
    } else {
        echo "✓ 'invite_code_used' column exists\n";
    }
    
    // 3. Create invite_codes table
    $conn->query("CREATE TABLE IF NOT EXISTS invite_codes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        code VARCHAR(32) UNIQUE NOT NULL,
        owner_user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        regenerates_at TIMESTAMP DEFAULT (DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 MONTH)),
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_owner (owner_user_id),
        INDEX idx_code (code)
    )");
    echo "✓ Created invite_codes table\n";
    
    // 4. Create invite_usage table
    $conn->query("CREATE TABLE IF NOT EXISTS invite_usage (
        id INT PRIMARY KEY AUTO_INCREMENT,
        code VARCHAR(32) NOT NULL,
        inviter_user_id INT NOT NULL,
        invitee_ip VARCHAR(45) NOT NULL,
        first_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 WEEK)),
        invitee_user_id INT NULL,
        account_created TINYINT(1) DEFAULT 0,
        FOREIGN KEY (inviter_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (invitee_user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_code (code),
        INDEX idx_ip (invitee_ip),
        INDEX idx_inviter (inviter_user_id)
    )");
    echo "✓ Created invite_usage table\n";
    
    // 5. Create personal_keys table
    $conn->query("CREATE TABLE IF NOT EXISTS personal_keys (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        key_value VARCHAR(64) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_key (key_value),
        INDEX idx_user (user_id)
    )");
    echo "✓ Created personal_keys table\n";
    
    // 6. Generate initial invite codes for existing users (3 per user)
    $users = $conn->query("SELECT id FROM users");
    $count = 0;
    while ($user = $users->fetch_assoc()) {
        for ($i = 0; $i < 3; $i++) {
            // Generate unique 6-digit code
            $attempts = 0;
            while ($attempts < 50) {
                $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Check uniqueness
                $check = $conn->prepare("SELECT id FROM invite_codes WHERE code = ?");
                $check->bind_param("s", $code);
                $check->execute();
                if ($check->get_result()->num_rows == 0) {
                    $check->close();
                    
                    $stmt = $conn->prepare("INSERT INTO invite_codes (code, owner_user_id) VALUES (?, ?)");
                    $stmt->bind_param("si", $code, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    $count++;
                    break;
                }
                $check->close();
                $attempts++;
            }
        }
    }
    echo "✓ Generated $count invite codes for existing users\n";
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Invite-only system is ready!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
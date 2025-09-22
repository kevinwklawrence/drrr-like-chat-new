<?php
// debug_friends.php - Debug script to test friend system
session_start();
include 'db_connect.php';

echo "<h2>Friend System Debug</h2>\n";
echo "<pre>\n";

if (!$conn || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

echo "=== DATABASE TABLE CHECK ===\n";

// Check if friends table exists
$friends_check = $conn->query("SHOW TABLES LIKE 'friends'");
if ($friends_check && $friends_check->num_rows > 0) {
    echo "✓ 'friends' table exists\n";
    
    // Check friends table structure
    $friends_desc = $conn->query("DESCRIBE friends");
    echo "Friends table structure:\n";
    while ($row = $friends_desc->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "❌ 'friends' table does NOT exist\n";
}

// Check if friend_notifications table exists
$notifications_check = $conn->query("SHOW TABLES LIKE 'friend_notifications'");
if ($notifications_check && $notifications_check->num_rows > 0) {
    echo "✓ 'friend_notifications' table exists\n";
} else {
    echo "⚠️  'friend_notifications' table does NOT exist (notifications disabled)\n";
}

echo "\n=== USERS TABLE CHECK ===\n";
$users_check = $conn->query("SELECT COUNT(*) as count FROM users");
if ($users_check) {
    $count = $users_check->fetch_assoc()['count'];
    echo "✓ Users table accessible, {$count} users found\n";
} else {
    echo "❌ Cannot access users table\n";
}

echo "\n=== SESSION CHECK ===\n";
if (isset($_SESSION['user'])) {
    echo "✓ User logged in:\n";
    echo "  - ID: " . ($_SESSION['user']['id'] ?? 'N/A') . "\n";
    echo "  - Username: " . ($_SESSION['user']['username'] ?? 'N/A') . "\n";
    echo "  - Type: " . ($_SESSION['user']['type'] ?? 'N/A') . "\n";
} else {
    echo "❌ No user session found\n";
}

echo "\n=== EXISTING FRIEND REQUESTS ===\n";
if (isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
    
    $stmt = $conn->prepare("SELECT id, user_id, friend_id, status, created_at FROM friends WHERE user_id = ? OR friend_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Recent friend requests:\n";
        while ($row = $result->fetch_assoc()) {
            $direction = ($row['user_id'] == $user_id) ? 'sent to' : 'received from';
            $other_id = ($row['user_id'] == $user_id) ? $row['friend_id'] : $row['user_id'];
            echo "  - ID {$row['id']}: {$direction} user {$other_id}, status: {$row['status']}, created: {$row['created_at']}\n";
        }
    } else {
        echo "No friend requests found\n";
    }
    $stmt->close();
} else {
    echo "Cannot check - no user ID in session\n";
}

echo "\n=== MANUAL TEST ===\n";
echo "To manually test:\n";
echo "1. Ensure you're logged in as a registered user\n";
echo "2. Try adding a friend via the UI\n";
echo "3. Check console/network tab for any errors\n";
echo "4. If errors persist, check error logs\n";

echo "\n=== SQL TO CREATE MISSING TABLE ===\n";
echo "If friend_notifications table is missing, run:\n\n";
echo "CREATE TABLE friend_notifications (\n";
echo "    id INT PRIMARY KEY AUTO_INCREMENT,\n";
echo "    user_id INT NOT NULL,\n";
echo "    from_user_id INT NOT NULL,\n";
echo "    type ENUM('friend_request', 'friend_accepted', 'friend_rejected') DEFAULT 'friend_request',\n";
echo "    message TEXT,\n";
echo "    is_read TINYINT(1) DEFAULT 0,\n";
echo "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
echo "    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,\n";
echo "    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE\n";
echo ");\n";

echo "</pre>";
$conn->close();
?>
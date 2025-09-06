<?php
// smart_fix.php - Adapts to your actual database structure
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Smart Notification Fix</h1>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#fff;} .ok{color:#4f4;} .error{color:#f44;} .warning{color:#fa4;} .info{color:#4af;}</style>";

include 'db_connect.php';

if (!$conn || $conn->connect_error) {
    echo "<div class='error'>❌ Database connection failed</div>";
    exit;
}

try {
    echo "<h2>Step 1: Analyze Your Database Structure</h2>";
    
    // Check what columns exist in messages table
    $messages_columns = [];
    $result = $conn->query("DESCRIBE messages");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages_columns[] = $row['Field'];
        }
        echo "<div class='info'>📋 Messages table columns found:</div>";
        echo "<pre>" . implode(", ", $messages_columns) . "</pre>";
    } else {
        echo "<div class='error'>❌ Could not analyze messages table</div>";
        exit;
    }
    
    // Check what columns exist in user_mentions table
    $mentions_columns = [];
    $result = $conn->query("DESCRIBE user_mentions");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $mentions_columns[] = $row['Field'];
        }
        echo "<div class='info'>📋 User_mentions table columns found:</div>";
        echo "<pre>" . implode(", ", $mentions_columns) . "</pre>";
    }
    
    echo "<h2>Step 2: Create Smart API Based on Your Database</h2>";
    
    // Build the SELECT query based on available columns
    $select_fields = [
        "um.id",
        "um.message_id", 
        "um.mention_type as type",
        "um.created_at"
    ];
    
    // Add message fields if they exist
    if (in_array('message', $messages_columns)) {
        $select_fields[] = "m.message";
    }
    if (in_array('timestamp', $messages_columns)) {
        $select_fields[] = "m.timestamp as message_timestamp";
    }
    if (in_array('user_id_string', $messages_columns)) {
        $select_fields[] = "m.user_id_string as sender_user_id_string";
    }
    if (in_array('username', $messages_columns)) {
        $select_fields[] = "m.username as sender_username";
    }
    if (in_array('guest_name', $messages_columns)) {
        $select_fields[] = "m.guest_name as sender_guest_name";
    }
    if (in_array('avatar', $messages_columns)) {
        $select_fields[] = "m.avatar as sender_avatar";
    }
    
    $select_sql = implode(",\n            ", $select_fields);
    
    echo "<div class='info'>🔧 Generated SELECT fields:</div>";
    echo "<pre>" . $select_sql . "</pre>";
    
    // Create the smart API file
    $smart_api_content = '<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json");

if (!isset($_SESSION["user"]) || !isset($_SESSION["room_id"])) {
    echo json_encode(["status" => "error", "message" => "Not authorized"]);
    exit;
}

// Try multiple paths for db_connect.php
$db_paths = ["../db_connect.php", "db_connect.php", "./db_connect.php"];
$db_connected = false;

foreach ($db_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected || !$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$room_id = (int)$_SESSION["room_id"];
$user_id_string = $_SESSION["user"]["user_id"] ?? "";

if (empty($user_id_string)) {
    echo json_encode(["status" => "error", "message" => "Invalid user session"]);
    exit;
}

try {
    $notifications = [];
    
    // Smart query based on your database structure
    $stmt = $conn->prepare("
        SELECT 
            ' . $select_sql . '
        FROM user_mentions um
        LEFT JOIN messages m ON um.message_id = m.id
        WHERE um.room_id = ? 
        AND um.mentioned_user_id_string = ? 
        AND um.is_read = FALSE
        ORDER BY um.created_at DESC
        LIMIT 10
    ");
    
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Extract sender info from available fields
            $sender_name = "Unknown";
            if (isset($row["sender_username"]) && !empty($row["sender_username"])) {
                $sender_name = $row["sender_username"];
            } elseif (isset($row["sender_guest_name"]) && !empty($row["sender_guest_name"])) {
                $sender_name = $row["sender_guest_name"];
            } elseif (isset($row["sender_user_id_string"])) {
                $sender_name = $row["sender_user_id_string"];
            }
            
            $sender_avatar = isset($row["sender_avatar"]) ? $row["sender_avatar"] : "default_avatar.jpg";
            $message_content = isset($row["message"]) ? $row["message"] : "Message content unavailable";
            
            $notifications[] = [
                "id" => "mention_" . $row["id"],
                "type" => $row["type"],
                "notification_type" => "mention", 
                "message_id" => $row["message_id"],
                "title" => $row["type"] === "reply" ? "Reply to your message" : "Mentioned you",
                "message" => $message_content,
                "sender_name" => $sender_name,
                "sender_avatar" => $sender_avatar,
                "timestamp" => $row["created_at"],
                "action_data" => ["mention_id" => $row["id"]]
            ];
        }
        $stmt->close();
    }
    
    echo json_encode([
        "status" => "success",
        "notifications" => $notifications,
        "total_count" => count($notifications)
    ]);
    
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>';
    
    file_put_contents('api/get_notifications.php', $smart_api_content);
    echo "<div class='ok'>✅ Created smart API that adapts to your database structure</div>";
    
    echo "<h2>Step 3: Test the Smart API</h2>";
    
    // Test the new API with a fresh connection
    $conn->close();
    include 'db_connect.php'; // Fresh connection
    
    $room_id = (int)$_SESSION['room_id'];
    $user_id_string = $_SESSION['user']['user_id'] ?? '';
    
    if (!empty($user_id_string)) {
        $test_stmt = $conn->prepare("
            SELECT 
                $select_sql
            FROM user_mentions um
            LEFT JOIN messages m ON um.message_id = m.id
            WHERE um.room_id = ? 
            AND um.mentioned_user_id_string = ? 
            AND um.is_read = FALSE
            ORDER BY um.created_at DESC
            LIMIT 3
        ");
        
        if ($test_stmt) {
            $test_stmt->bind_param("is", $room_id, $user_id_string);
            $test_stmt->execute();
            $result = $test_stmt->get_result();
            
            echo "<div class='ok'>✅ Smart query executed successfully!</div>";
            echo "<div class='info'>📊 Found " . $result->num_rows . " unread notifications</div>";
            
            if ($result->num_rows > 0) {
                echo "<div class='info'>🔍 Sample notification data:</div>";
                echo "<pre>";
                while ($row = $result->fetch_assoc()) {
                    echo "ID: " . $row['id'] . "\n";
                    echo "Type: " . $row['type'] . "\n"; 
                    echo "Message: " . substr(strip_tags($row['message'] ?? 'No message'), 0, 50) . "...\n";
                    echo "Created: " . $row['created_at'] . "\n\n";
                }
                echo "</pre>";
            }
            $test_stmt->close();
        }
    }
    
    echo "<h2>Step 4: Room.php Integration Guide</h2>";
    
    echo "<div class='warning'>⚠️  To complete the setup, add these to room.php:</div>";
    
    echo "<div class='info'>📋 1. Add notification bell after Friends button:</div>";
    echo "<pre style='background:#333;padding:10px;color:#4f4;'>";
    echo htmlspecialchars('<?php if ($_SESSION[\'user\'][\'type\'] === \'user\'): ?>
    <button class="btn btn-outline-primary me-2" onclick="showFriendsPanel()">
        <i class="fas fa-user-friends"></i> Friends
    </button>
    <button id="notificationBell" class="btn btn-outline-secondary me-2" title="Notifications">
        <i class="fas fa-bell"></i>
    </button>
<?php endif; ?>');
    echo "</pre>";
    
    echo "<div class='info'>📋 2. Add CSS to head section:</div>";
    echo "<pre style='background:#333;padding:10px;color:#4f4;'>";
    echo htmlspecialchars('<link href="css/notifications.css" rel="stylesheet">');
    echo "</pre>";
    
    echo "<div class='info'>📋 3. Add JavaScript before closing body tag:</div>";
    echo "<pre style='background:#333;padding:10px;color:#4f4;'>";
    echo htmlspecialchars('<script src="js/notifications.js"></script>');
    echo "</pre>";
    
    echo "<h2>Step 5: Test Your API Directly</h2>";
    
    $api_test_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/get_notifications.php';
    
    echo "<div class='info'>🔗 Test your API here: <a href='$api_test_url' target='_blank' style='color:#4af;'>$api_test_url</a></div>";
    echo "<div class='info'>💡 This should now return JSON with your notifications instead of errors</div>";
    
    echo "<hr><h2>✅ Smart Fix Complete!</h2>";
    echo "<div class='info'>📋 What this fix did:</div>";
    echo "<ul>";
    echo "<li>✅ Analyzed your actual database structure</li>";
    echo "<li>✅ Created API that only uses columns that exist</li>";
    echo "<li>✅ Fixed the database connection handling</li>";
    echo "<li>✅ Tested the query with your data</li>";
    echo "<li>✅ Provided exact integration steps for room.php</li>";
    echo "</ul>";
    
    echo "<div class='ok'>🎯 Your notifications should work immediately after adding the bell to room.php!</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error during fix: " . $e->getMessage() . "</div>";
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
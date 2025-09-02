<?php
// 1. REPLACE api/get_rooms.php completely with this version:


header('Content-Type: application/json');
include '../db_connect.php';

try {
    // First, ensure permanent column exists
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    $available_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Add permanent column if it doesn't exist
    if (!in_array('permanent', $available_columns)) {
        $conn->query("ALTER TABLE chatrooms ADD COLUMN permanent TINYINT(1) DEFAULT 0");
        error_log("Added permanent column to chatrooms table");
    }
    
    // Build SELECT query with all needed fields
    $select_fields = [
        'id', 'name', 'description', 'capacity', 'created_at',
        'has_password', 'allow_knocking', 'theme', 'background',
        'youtube_enabled', 'is_rp', 'friends_only', 'invite_only',
        'members_only', 'disappearing_messages', 'message_lifetime_minutes',
        'permanent', 'invite_code', 'host_user_id_string'
    ];
    
    // Filter to only include columns that actually exist
    $valid_fields = array_intersect($select_fields, $available_columns);
    
    // Always include id, name, capacity, created_at as minimum
    $required_fields = ['id', 'name', 'capacity', 'created_at'];
    $final_fields = array_unique(array_merge($required_fields, $valid_fields));
    
    // IMPORTANT: Order by permanent DESC first, then by created_at DESC
    $sql = "SELECT " . implode(', ', $final_fields) . " 
            FROM chatrooms 
            ORDER BY permanent DESC, created_at DESC";
    
    error_log("GET_ROOMS SQL: " . $sql);
    
    $result = $conn->query($sql);
    $rooms = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Build room data with safe defaults
            $room = [
                'id' => (int)$row['id'],
                'name' => $row['name'] ?? '',
                'description' => $row['description'] ?? '',
                'capacity' => (int)($row['capacity'] ?? 10),
                'created_at' => $row['created_at'] ?? '',
                'has_password' => (bool)($row['has_password'] ?? 0),
                'allow_knocking' => (bool)($row['allow_knocking'] ?? 1),
                'theme' => $row['theme'] ?? 'default',
                'background' => $row['background'] ?? '',
                'youtube_enabled' => (bool)($row['youtube_enabled'] ?? 0),
                'is_rp' => (bool)($row['is_rp'] ?? 0),
                'friends_only' => (bool)($row['friends_only'] ?? 0),
                'invite_only' => (bool)($row['invite_only'] ?? 0),
                'members_only' => (bool)($row['members_only'] ?? 0),
                'disappearing_messages' => (bool)($row['disappearing_messages'] ?? 0),
                'message_lifetime_minutes' => (int)($row['message_lifetime_minutes'] ?? 0),
                'permanent' => (bool)($row['permanent'] ?? 0), // CRITICAL: Ensure this is set
                'invite_code' => $row['invite_code'] ?? null,
                'host_user_id_string' => $row['host_user_id_string'] ?? null
            ];
            
            // DEBUG: Log permanent status for each room
            if ($room['permanent']) {
                error_log("PERMANENT ROOM FOUND: " . $room['name'] . " (ID: " . $room['id'] . ")");
            }
            
            // Check friends-only access if user is logged in
            if ($room['friends_only']) {
                $room['can_access_friends_only'] = checkFriendsAccess($conn, $room['host_user_id_string']);
            } else {
                $room['can_access_friends_only'] = true;
            }
            
            $rooms[] = $room;
        }
    }
    
    // Final debug log
    $permanent_count = count(array_filter($rooms, function($r) { return $r['permanent']; }));
    error_log("TOTAL ROOMS: " . count($rooms) . ", PERMANENT ROOMS: " . $permanent_count);
    
    echo json_encode($rooms);
    
} catch (Exception $e) {
    error_log("Error in get_rooms.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch rooms: ' . $e->getMessage()
    ]);
}

$conn->close();

// Helper function for friends-only access checking
function checkFriendsAccess($conn, $host_user_id_string) {
    // If no session or not a registered user, deny access
    if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
        return false;
    }
    
    $current_user_id = $_SESSION['user']['id'] ?? null;
    $current_user_id_string = $_SESSION['user']['user_id'] ?? null;
    
    if (!$current_user_id || !$current_user_id_string || !$host_user_id_string) {
        return false;
    }
    
    // If user is the host, allow access
    if ($host_user_id_string === $current_user_id_string) {
        return true;
    }
    
    // Check if friends table exists
    $tables_result = $conn->query("SHOW TABLES LIKE 'friends'");
    if (!$tables_result || $tables_result->num_rows === 0) {
        return false; // No friends system
    }
    
    // Get host's numeric ID
    $user_id_column = 'user_id_string';
    $columns_result = $conn->query("SHOW COLUMNS FROM users LIKE 'user_id_string'");
    if (!$columns_result || $columns_result->num_rows === 0) {
        $user_id_column = 'user_id'; // Fallback
    }
    
    $host_stmt = $conn->prepare("SELECT id FROM users WHERE {$user_id_column} = ?");
    if (!$host_stmt) return false;
    
    $host_stmt->bind_param("s", $host_user_id_string);
    $host_stmt->execute();
    $host_result = $host_stmt->get_result();
    
    if ($host_result->num_rows === 0) {
        $host_stmt->close();
        return false;
    }
    
    $host_data = $host_result->fetch_assoc();
    $host_user_id = (int)$host_data['id'];
    $host_stmt->close();
    
    // Check friendship
    $friend_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM friends 
        WHERE status = 'accepted' 
        AND ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
    ");
    
    if (!$friend_stmt) return false;
    
    $friend_stmt->bind_param("iiii", $current_user_id, $host_user_id, $host_user_id, $current_user_id);
    $friend_stmt->execute();
    $friend_result = $friend_stmt->get_result();
    
    $friendship_count = 0;
    if ($friend_result) {
        $friend_data = $friend_result->fetch_assoc();
        $friendship_count = (int)$friend_data['count'];
    }
    
    $friend_stmt->close();
    return $friendship_count > 0;
}

function findUserIdColumn($conn) {
    $columns_result = $conn->query("SHOW COLUMNS FROM users");
    if (!$columns_result) {
        return null;
    }
    
    $available_columns = [];
    while ($row = $columns_result->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Prefer user_id_string if it exists, fall back to user_id
    if (in_array('user_id_string', $available_columns)) {
        return 'user_id_string';
    } else if (in_array('user_id', $available_columns)) {
        return 'user_id';
    }
    
    return null;
}

function triggerRoomCleanup() {
    $url = 'http://localhost/api/cleanup_rooms.php';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        error_log('Failed to call cleanup_rooms.api');
    }
}
?>
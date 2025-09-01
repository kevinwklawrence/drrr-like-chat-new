<?php
// 1. REPLACE api/get_rooms.php completely with this version:

header('Content-Type: application/json');
include '../db_connect.php';

try {
    // Check what columns exist in the chatrooms table
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    $available_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Build the SELECT query based on available columns - INCLUDE PERMANENT
    $select_fields = ['id', 'name', 'description', 'capacity', 'created_at'];
    
    // Add optional fields if they exist - MAKE SURE PERMANENT IS INCLUDED
    $optional_fields = [
        'background', 'has_password', 'allow_knocking', 'theme', 
        'youtube_enabled', 'is_rp', 'friends_only', 'invite_only', 
        'members_only', 'disappearing_messages', 'message_lifetime_minutes',
        'permanent', 'invite_code', 'host_user_id_string'
    ];
    
    foreach ($optional_fields as $field) {
        if (in_array($field, $available_columns)) {
            $select_fields[] = $field;
        }
    }
    
    // Make sure permanent column exists
    if (!in_array('permanent', $available_columns)) {
        error_log("Adding permanent column to chatrooms table");
        $conn->query("ALTER TABLE chatrooms ADD COLUMN permanent TINYINT(1) DEFAULT 0");
        $select_fields[] = 'permanent';
    }
    
    // Get all rooms - ORDER BY PERMANENT DESC FIRST
    $sql = "SELECT " . implode(', ', $select_fields) . " FROM chatrooms ORDER BY 
            permanent DESC, id DESC";
    
    error_log("GET_ROOMS SQL: " . $sql);
    
    $result = $conn->query($sql);
    $rooms = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Ensure all expected fields exist with default values
            $room = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'capacity' => (int)$row['capacity'],
                'created_at' => $row['created_at'],
                'background' => $row['background'] ?? '',
                'has_password' => isset($row['has_password']) ? (bool)$row['has_password'] : false,
                'allow_knocking' => isset($row['allow_knocking']) ? (bool)$row['allow_knocking'] : true,
                'theme' => $row['theme'] ?? 'default',
                'youtube_enabled' => isset($row['youtube_enabled']) ? (bool)$row['youtube_enabled'] : false,
                'is_rp' => isset($row['is_rp']) ? (bool)$row['is_rp'] : false,
                'friends_only' => isset($row['friends_only']) ? (bool)$row['friends_only'] : false,
                'invite_only' => isset($row['invite_only']) ? (bool)$row['invite_only'] : false,
                'members_only' => isset($row['members_only']) ? (bool)$row['members_only'] : false,
                'disappearing_messages' => isset($row['disappearing_messages']) ? (bool)$row['disappearing_messages'] : false,
                'message_lifetime_minutes' => isset($row['message_lifetime_minutes']) ? (int)$row['message_lifetime_minutes'] : 0,
                'permanent' => isset($row['permanent']) ? (bool)$row['permanent'] : false,
                'invite_code' => $row['invite_code'] ?? null,
                'host_user_id_string' => $row['host_user_id_string'] ?? null
            ];
            
            // DEBUG LOG
            error_log("Room {$room['name']} permanent status: " . ($room['permanent'] ? 'TRUE' : 'FALSE'));
            
            $rooms[] = $room;
        }
    }
    
    error_log("Total rooms returned: " . count($rooms));
    echo json_encode($rooms);
    
} catch (Exception $e) {
    error_log("Error in get_rooms.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch rooms: ' . $e->getMessage()]);
}

$conn->close();


// ==================== HELPER FUNCTIONS ====================

function getAvailableColumns($conn, $table) {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM {$table}");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

function buildRoomsQuery($columns) {
    // Core required columns
    $select_fields = [
        'id', 
        'name', 
        'description', 
        'capacity',
        'created_at',
        'has_password',
        'COALESCE(allow_knocking, 1) as allow_knocking'
    ];
    
    // Add optional columns with defaults
    $optional_columns = [
        'is_rp' => 0,
        'youtube_enabled' => 0,
        'theme' => "'default'",
        'friends_only' => 0,
        'invite_only' => 0,
        'members_only' => 0,
        'disappearing_messages' => 0,
        'message_lifetime_minutes' => 0,
        'host_user_id_string' => "''"
    ];
    
    foreach ($optional_columns as $column => $default) {
        if (in_array($column, $columns)) {
            $select_fields[] = "COALESCE({$column}, {$default}) as {$column}";
        }
    }
    
    return "SELECT " . implode(', ', $select_fields) . " FROM chatrooms ORDER BY created_at DESC";
}

function processRoom($conn, $row) {
    // Get user count and host info
    $room_stats = getRoomStats($conn, $row['id']);
    
    // Check friends-only access
    $can_access = checkFriendsOnlyAccess($conn, $row);
    
    // Cleanup empty rooms
    if ($room_stats['user_count'] === 0) {
        triggerRoomCleanup();
    }
    
    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'description' => $row['description'] ?: 'No description',
        'capacity' => (int)$row['capacity'],
        'user_count' => $room_stats['user_count'],
        'has_password' => (int)($row['has_password'] ?? 0),
        'allow_knocking' => (int)($row['allow_knocking'] ?? 1),
        'background' => null,
        'host_name' => $room_stats['host_name'],
        'created_at' => $row['created_at'],
        'permanent' => 0,
        'is_rp' => (int)($row['is_rp'] ?? 0),
        'youtube_enabled' => (int)($row['youtube_enabled'] ?? 0),
        'theme' => $row['theme'] ?? 'default',
        'friends_only' => (int)($row['friends_only'] ?? 0),
        'invite_only' => (int)($row['invite_only'] ?? 0),
        'members_only' => (int)($row['members_only'] ?? 0),
        'disappearing_messages' => (int)($row['disappearing_messages'] ?? 0),
        'message_lifetime_minutes' => (int)($row['message_lifetime_minutes'] ?? 0),
        'can_access_friends_only' => $can_access
    ];
}

function getRoomStats($conn, $room_id) {
    // Get user count
    $user_count = 0;
    $count_result = $conn->query("SELECT COUNT(*) as count FROM chatroom_users WHERE room_id = " . (int)$room_id);
    if ($count_result) {
        $count_data = $count_result->fetch_assoc();
        $user_count = (int)$count_data['count'];
    }
    
    // Get host name
    $host_name = 'Unknown';
    $host_result = $conn->query("SELECT guest_name FROM chatroom_users WHERE room_id = " . (int)$room_id . " AND is_host = 1 LIMIT 1");
    if ($host_result && $host_result->num_rows > 0) {
        $host_data = $host_result->fetch_assoc();
        $host_name = $host_data['guest_name'] ?: 'Host';
    }
    
    return [
        'user_count' => $user_count,
        'host_name' => $host_name
    ];
}

function checkFriendsOnlyAccess($conn, $room) {
    // For non-friends-only rooms, everyone can access
    if (!isset($room['friends_only']) || $room['friends_only'] != 1) {
        return true; // Allow access (boolean true)
    }
    
    // For friends-only rooms, default is to block
    $can_access = false;
    
    // Must be logged in user (not guest) to potentially access friends-only rooms
    if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
        return false; // Block guests (boolean false)
    }
    
    $current_user_id = $_SESSION['user']['id'] ?? null;
    $current_user_id_string = $_SESSION['user']['user_id'] ?? null;
    $host_user_id_string = $room['host_user_id_string'] ?? '';
    
    if (!$current_user_id || !$current_user_id_string || !$host_user_id_string) {
        return false; // Block if missing required data (boolean false)
    }
    
    // Check if user is the room host
    if ($host_user_id_string === $current_user_id_string) {
        return true; // Host can always access their own room (boolean true)
    }
    
    // Check if users are friends
    return checkFriendship($conn, $current_user_id, $host_user_id_string);
}

function checkFriendship($conn, $current_user_id, $host_user_id_string) {
    // Check if friends table exists
    $tables_result = $conn->query("SHOW TABLES LIKE 'friends'");
    if (!$tables_result || $tables_result->num_rows === 0) {
        return false; // No friends system (boolean false)
    }
    
    // Find the correct user ID column in users table
    $user_id_column = findUserIdColumn($conn);
    if (!$user_id_column) {
        return false; // Can't find user ID column (boolean false)
    }
    
    // Get host's numeric user ID
    $host_stmt = $conn->prepare("SELECT id FROM users WHERE {$user_id_column} = ?");
    if (!$host_stmt) {
        return false;
    }
    
    $host_stmt->bind_param("s", $host_user_id_string);
    $host_stmt->execute();
    $host_result = $host_stmt->get_result();
    
    if ($host_result->num_rows === 0) {
        $host_stmt->close();
        return false; // Host not found (boolean false)
    }
    
    $host_data = $host_result->fetch_assoc();
    $host_user_id = (int)$host_data['id'];
    $host_stmt->close();
    
    // Check friendship
    $friend_stmt = $conn->prepare("
        SELECT COUNT(*) as friendship_count 
        FROM friends 
        WHERE status = 'accepted' 
        AND ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
    ");
    
    if (!$friend_stmt) {
        return false;
    }
    
    $friend_stmt->bind_param("iiii", $current_user_id, $host_user_id, $host_user_id, $current_user_id);
    $friend_stmt->execute();
    $friend_result = $friend_stmt->get_result();
    
    $friendship_count = 0;
    if ($friend_result) {
        $friend_data = $friend_result->fetch_assoc();
        $friendship_count = (int)$friend_data['friendship_count'];
    }
    
    $friend_stmt->close();
    return $friendship_count > 0; // Return boolean true if friends, false if not
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
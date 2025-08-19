<?php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

try {
    // First check what columns exist
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    $existing_columns = [];
    while ($row = $columns_query->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    // Build SELECT query with only existing columns
    $select_fields = [
        'id', 
        'name', 
        'description', 
        'capacity',
        'created_at',
        'has_password',
        'COALESCE(allow_knocking, 1) as allow_knocking'
    ];
    
    // Add new columns only if they exist
    if (in_array('is_rp', $existing_columns)) {
        $select_fields[] = 'COALESCE(is_rp, 0) as is_rp';
    }
    if (in_array('youtube_enabled', $existing_columns)) {
        $select_fields[] = 'COALESCE(youtube_enabled, 0) as youtube_enabled';
    }
    if (in_array('theme', $existing_columns)) {
        $select_fields[] = 'COALESCE(theme, \'default\') as theme';
    }
    if (in_array('friends_only', $existing_columns)) {
        $select_fields[] = 'COALESCE(friends_only, 0) as friends_only';
    }
    if (in_array('invite_only', $existing_columns)) {
        $select_fields[] = 'COALESCE(invite_only, 0) as invite_only';
    }
    if (in_array('members_only', $existing_columns)) {
        $select_fields[] = 'COALESCE(members_only, 0) as members_only';
    }
    if (in_array('disappearing_messages', $existing_columns)) {
        $select_fields[] = 'COALESCE(disappearing_messages, 0) as disappearing_messages';
    }
    if (in_array('message_lifetime_minutes', $existing_columns)) {
        $select_fields[] = 'COALESCE(message_lifetime_minutes, 0) as message_lifetime_minutes';
    }
    if (in_array('host_user_id_string', $existing_columns)) {
        $select_fields[] = 'host_user_id_string';
    }
    
    $sql = "SELECT " . implode(', ', $select_fields) . " FROM chatrooms ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        // Get user count for this room
        $user_count = 0;
        $host_name = 'Unknown';
        
        $user_count_query = $conn->query("SELECT COUNT(*) as count FROM chatroom_users WHERE room_id = " . (int)$row['id']);
        if ($user_count_query) {
            $count_data = $user_count_query->fetch_assoc();
            $user_count = (int)$count_data['count'];
        }
        
        // Get host name
        $host_query = $conn->query("SELECT guest_name FROM chatroom_users WHERE room_id = " . (int)$row['id'] . " AND is_host = 1 LIMIT 1");
        if ($host_query && $host_query->num_rows > 0) {
            $host_data = $host_query->fetch_assoc();
            $host_name = $host_data['guest_name'] ?: 'Host';
        }
        
        // Check if current user can access friends-only rooms (only if column exists)
        $can_access_friends_only = true;
        if (isset($row['friends_only']) && $row['friends_only'] && isset($_SESSION['user'])) {
            $current_user_id = $_SESSION['user']['id'] ?? null;
            $host_user_id_string = $row['host_user_id_string'] ?? '';
            
            if ($current_user_id && $_SESSION['user']['type'] === 'user' && $host_user_id_string) {
                // Simplified friend check - only if friends table exists
                $tables_query = $conn->query("SHOW TABLES LIKE 'friends'");
                if ($tables_query && $tables_query->num_rows > 0) {
                    $friend_check = $conn->prepare("
                        SELECT COUNT(*) as is_friend 
                        FROM friends f
                        LEFT JOIN users u ON (f.user_id = u.id OR f.friend_user_id = u.id)
                        WHERE f.status = 'accepted' 
                        AND ((f.user_id = ? AND u.user_id_string = ?) 
                             OR (f.friend_user_id = ? AND u.user_id_string = ?))
                    ");
                    if ($friend_check) {
                        $friend_check->bind_param("isis", $current_user_id, $host_user_id_string, $current_user_id, $host_user_id_string);
                        $friend_check->execute();
                        $friend_result = $friend_check->get_result();
                        if ($friend_result) {
                            $friend_data = $friend_result->fetch_assoc();
                            $can_access_friends_only = ($friend_data['is_friend'] > 0);
                        }
                        $friend_check->close();
                    }
                } else {
                    $can_access_friends_only = false; // Friends table doesn't exist
                }
            } else {
                $can_access_friends_only = false; // Guest or no host info
            }
        }
        
        $room = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'] ?: 'No description',
            'capacity' => (int)$row['capacity'],
            'user_count' => $user_count,
            'has_password' => (int)($row['has_password'] ?? 0),
            'allow_knocking' => (int)($row['allow_knocking'] ?? 1),
            'background' => null,
            'host_name' => $host_name,
            'created_at' => $row['created_at'],
            'permanent' => 0,
            // New features (with defaults if columns don't exist)
            'is_rp' => (int)($row['is_rp'] ?? 0),
            'youtube_enabled' => (int)($row['youtube_enabled'] ?? 0),
            'theme' => $row['theme'] ?? 'default',
            'friends_only' => (int)($row['friends_only'] ?? 0),
            'invite_only' => (int)($row['invite_only'] ?? 0),
            'members_only' => (int)($row['members_only'] ?? 0),
            'disappearing_messages' => (int)($row['disappearing_messages'] ?? 0),
            'message_lifetime_minutes' => (int)($row['message_lifetime_minutes'] ?? 0),
            'can_access_friends_only' => $can_access_friends_only
        ];

        // Cleanup empty rooms
        if ($user_count === 0) {
            $url = 'http://localhost/api/cleanup_rooms.php';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === false) {
                error_log('Failed to call cleanup_rooms.api');
            } else {
                error_log('cleanup_rooms.api called successfully: ' . $response);
            }
        }
        
        $rooms[] = $room;
    }
    
    echo json_encode($rooms);
    
} catch (Exception $e) {
    error_log("Get rooms error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
<?php
// api/get_online_users.php - Get truly active users with better filtering
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

include '../db_connect.php';

try {
    // Only show users active within the last 2 minutes (120 seconds)
    // This ensures we only show users who are actually online
    $active_threshold = 2; // minutes
    
    // First check if avatar customization columns exist
    $columns_check = $conn->query("SHOW COLUMNS FROM global_users");
    $available_columns = [];
    while ($row = $columns_check->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Build the select fields
    $select_fields = [
        'user_id_string',
        'username', 
        'guest_name', 
        'avatar', 
        'guest_avatar',
        'is_admin',
        'color',
        'last_activity',
        'TIMESTAMPDIFF(SECOND, last_activity, NOW()) as seconds_since_activity'
    ];
    
    // Add avatar customization fields if they exist
    if (in_array('avatar_hue', $available_columns)) {
        $select_fields[] = 'avatar_hue';
    } else {
        $select_fields[] = '0 as avatar_hue';
    }
    
    if (in_array('avatar_saturation', $available_columns)) {
        $select_fields[] = 'avatar_saturation';
    } else {
        $select_fields[] = '100 as avatar_saturation';
    }
    
    $sql = "SELECT " . implode(', ', $select_fields) . "
            FROM global_users 
            WHERE last_activity >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY last_activity DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $active_threshold);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Additional filtering: skip users with empty or null user_id_string
        if (empty($row['user_id_string'])) {
            continue;
        }
        
        // Determine display name preference
        $display_name = '';
        if (!empty($row['guest_name'])) {
            $display_name = $row['guest_name'];
        } elseif (!empty($row['username'])) {
            $display_name = $row['username'];
        } else {
            $display_name = 'Unknown User';
        }
        
        // Determine avatar preference
        $avatar = 'default_avatar.jpg';
        if (!empty($row['avatar'])) {
            $avatar = $row['avatar'];
        } elseif (!empty($row['guest_avatar'])) {
            $avatar = $row['guest_avatar'];
        }
        
        // Calculate activity status
        $seconds_since = (int)$row['seconds_since_activity'];
        $activity_status = 'online';
        if ($seconds_since > 60) {
            $activity_status = 'away';
        }
        
        $users[] = [
            'user_id_string' => $row['user_id_string'],
            'username' => $row['username'],
            'guest_name' => $row['guest_name'],
            'display_name' => $display_name,
            'avatar' => $avatar,
            'guest_avatar' => $row['guest_avatar'],
            'is_admin' => (int)$row['is_admin'],
            'color' => $row['color'] ?? 'black',
            'last_activity' => $row['last_activity'],
            'seconds_since_activity' => $seconds_since,
            'activity_status' => $activity_status,
            'avatar_hue' => (int)($row['avatar_hue'] ?? 0),
            'avatar_saturation' => (int)($row['avatar_saturation'] ?? 100)
        ];
    }
    
    $stmt->close();
    
    // Log the count for debugging (but don't expose in response)
    error_log("Online users query returned " . count($users) . " active users (threshold: {$active_threshold} minutes)");
    
    echo json_encode($users);
    
} catch (Exception $e) {
    error_log("Error getting online users: " . $e->getMessage());
    echo json_encode([]);
}
?>
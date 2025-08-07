<?php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

try {
    // Simple query first - just get basic room info
    $sql = "SELECT 
        id, 
        name, 
        description, 
        capacity,
        created_at,
        CASE WHEN password IS NOT NULL AND password != '' THEN 1 ELSE 0 END as has_password,
        allow_knocking
    FROM chatrooms 
    ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        // Get user count and users for this room
        $user_count = 0;
        $host_name = 'Unknown';
        $users = [];
        
        // Check what columns exist in chatroom_users table
        $columns_query = $conn->query("SHOW COLUMNS FROM chatroom_users");
        $available_columns = [];
        while ($col_row = $columns_query->fetch_assoc()) {
            $available_columns[] = $col_row['Field'];
        }
        
        // Build query based on available columns
        $select_fields = ['cu.user_id_string', 'cu.is_host'];
        $joins = '';
        
        if (in_array('user_id', $available_columns)) {
            $select_fields[] = 'cu.user_id';
            $joins = 'LEFT JOIN users u ON cu.user_id = u.id';
            $select_fields[] = 'u.username';
            $select_fields[] = 'u.is_admin';
            $select_fields[] = 'u.avatar as user_avatar';
        }
        
        if (in_array('guest_name', $available_columns)) {
            $select_fields[] = 'cu.guest_name';
        }
        
        if (in_array('guest_avatar', $available_columns)) {
            $select_fields[] = 'cu.guest_avatar';
        }
        
        if (in_array('avatar', $available_columns)) {
            $select_fields[] = 'cu.avatar';
        }
        
        if (in_array('username', $available_columns)) {
            $select_fields[] = 'cu.username as chatroom_username';
        }
        
        $user_sql = "SELECT " . implode(', ', $select_fields) . " FROM chatroom_users cu " . $joins . " WHERE cu.room_id = ?";
        
        $user_stmt = $conn->prepare($user_sql);
        if ($user_stmt) {
            $user_stmt->bind_param("i", $row['id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            while ($user_row = $user_result->fetch_assoc()) {
                $user_count++;
                
                // Determine the best name to use
                $display_name = 'Unknown';
                if (!empty($user_row['username'])) {
                    $display_name = $user_row['username'];
                } elseif (!empty($user_row['chatroom_username'])) {
                    $display_name = $user_row['chatroom_username'];
                } elseif (!empty($user_row['guest_name'])) {
                    $display_name = $user_row['guest_name'];
                }
                
                // Determine the best avatar to use
                $avatar = 'default_avatar.jpg';
                if (!empty($user_row['user_avatar'])) {
                    $avatar = $user_row['user_avatar'];
                } elseif (!empty($user_row['guest_avatar'])) {
                    $avatar = $user_row['guest_avatar'];
                } elseif (!empty($user_row['avatar'])) {
                    $avatar = $user_row['avatar'];
                }
                
                // Set host name
                if ($user_row['is_host']) {
                    $host_name = $display_name;
                }
                
                // Add to users array
                $users[] = [
                    'user_id_string' => $user_row['user_id_string'],
                    'display_name' => $display_name,
                    'avatar' => $avatar,
                    'is_host' => (int)$user_row['is_host'],
                    'is_admin' => isset($user_row['is_admin']) ? (int)$user_row['is_admin'] : 0,
                    'user_type' => isset($user_row['user_id']) && $user_row['user_id'] ? 'registered' : 'guest'
                ];
            }
            $user_stmt->close();
        }
        
        $room = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'] ?: 'No description',
            'capacity' => (int)$row['capacity'],
            'user_count' => $user_count,
            'has_password' => (int)$row['has_password'],
            'allow_knocking' => isset($row['allow_knocking']) ? (int)$row['allow_knocking'] : 1,
            'background' => null,
            'host_name' => $host_name,
            'created_at' => $row['created_at'],
            'permanent' => 0,
            'users' => $users
        ];
        
        $rooms[] = $room;
    }
    
    echo json_encode($rooms);
    
} catch (Exception $e) {
    error_log("Get rooms error: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
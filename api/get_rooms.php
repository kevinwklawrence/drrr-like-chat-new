<?php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

try {
    // Use the existing has_password column directly instead of calculating it
    $sql = "SELECT 
        id, 
        name, 
        description, 
        capacity,
        created_at,
        has_password,
        COALESCE(allow_knocking, 1) as allow_knocking
    FROM chatrooms 
    ORDER BY created_at DESC";
    
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
        
        $room = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'] ?: 'No description',
            'capacity' => (int)$row['capacity'],
            'user_count' => $user_count,
            'has_password' => (int)$row['has_password'], // Use the existing column directly
            'allow_knocking' => (int)$row['allow_knocking'],
            'background' => null,
            'host_name' => $host_name,
            'created_at' => $row['created_at'],
            'permanent' => 0
        ];
        
        // Debug log to verify the data
        error_log("Room {$row['id']} ({$row['name']}): has_password = {$row['has_password']}");
        
        $rooms[] = $room;
    }
    
    // Debug log the final output
    error_log("Returning " . count($rooms) . " rooms");
    
    echo json_encode($rooms);
    
} catch (Exception $e) {
    error_log("Get rooms error: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>
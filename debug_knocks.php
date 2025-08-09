<?php
// Create a debug script: debug_knocks.php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';

$action = $_GET['action'] ?? 'check_tables';

try {
    switch ($action) {
        case 'check_tables':
            // Check if knock table exists
            $tables = $conn->query("SHOW TABLES LIKE 'room_knocks'");
            $knock_table_exists = $tables->num_rows > 0;
            
            if ($knock_table_exists) {
                $structure = $conn->query("DESCRIBE room_knocks");
                $columns = [];
                while ($row = $structure->fetch_assoc()) {
                    $columns[] = $row;
                }
                
                $knock_count = $conn->query("SELECT COUNT(*) as count FROM room_knocks")->fetch_assoc()['count'];
            } else {
                $columns = [];
                $knock_count = 0;
            }
            
            echo json_encode([
                'knock_table_exists' => $knock_table_exists,
                'knock_table_structure' => $columns,
                'total_knocks' => $knock_count
            ]);
            break;
            
        case 'check_user_host_status':
            $user_id_string = $_SESSION['user']['user_id'] ?? '';
            if (empty($user_id_string)) {
                echo json_encode(['error' => 'No user in session']);
                break;
            }
            
            // Check if user is host of any room
            $stmt = $conn->prepare("SELECT room_id, is_host FROM chatroom_users WHERE user_id_string = ?");
            $stmt->bind_param("s", $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $rooms = [];
            while ($row = $result->fetch_assoc()) {
                $rooms[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'user_id_string' => $user_id_string,
                'rooms_user_is_in' => $rooms,
                'is_host_of_any_room' => !empty(array_filter($rooms, function($r) { return $r['is_host'] == 1; }))
            ]);
            break;
            
        case 'check_knocks_for_user':
            $user_id_string = $_SESSION['user']['user_id'] ?? '';
            if (empty($user_id_string)) {
                echo json_encode(['error' => 'No user in session']);
                break;
            }
            
            // Get knocks for rooms where user is host
            $stmt = $conn->prepare("
                SELECT rk.*, c.name as room_name 
                FROM room_knocks rk 
                JOIN chatrooms c ON rk.room_id = c.id 
                JOIN chatroom_users cu ON c.id = cu.room_id 
                WHERE cu.user_id_string = ? 
                AND cu.is_host = 1 
                ORDER BY rk.created_at DESC
            ");
            $stmt->bind_param("s", $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $knocks = [];
            while ($row = $result->fetch_assoc()) {
                $knocks[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'user_id_string' => $user_id_string,
                'knocks_for_host' => $knocks
            ]);
            break;
            
        case 'all_knocks':
            $stmt = $conn->query("SELECT * FROM room_knocks ORDER BY created_at DESC LIMIT 10");
            $knocks = [];
            while ($row = $stmt->fetch_assoc()) {
                $knocks[] = $row;
            }
            
            echo json_encode([
                'all_recent_knocks' => $knocks
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
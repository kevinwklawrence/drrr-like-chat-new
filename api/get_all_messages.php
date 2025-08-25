<?php
// api/get_all_messages.php - Simple version
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Check if user is moderator or admin
$user_id = $_SESSION['user']['id'];
$is_authorized = false;

$stmt = $conn->prepare("SELECT is_moderator, is_admin FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_authorized = ($user_data['is_moderator'] == 1 || $user_data['is_admin'] == 1);
    }
    $stmt->close();
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Only moderators and admins can view all messages']);
    exit;
}

// Get filter parameters
$type_filter = $_GET['type'] ?? 'all';
$search_filter = $_GET['search'] ?? '';
$user_filter = $_GET['user_filter'] ?? '';
$room_filter = $_GET['room_filter'] ?? '';
$limit = min((int)($_GET['limit'] ?? 50), 200);

try {
    // Build WHERE conditions
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Type filter
    if ($type_filter !== 'all') {
        if ($type_filter === 'chat') {
            $where_conditions[] = "(m.type IS NULL OR m.type = '' OR m.type = 'chat') AND m.user_id_string != 'SYSTEM_ANNOUNCEMENT' AND m.message NOT LIKE '%📢 SITE ANNOUNCEMENT:%'";
        } elseif ($type_filter === 'announcement') {
            $where_conditions[] = "(m.user_id_string = 'SYSTEM_ANNOUNCEMENT' OR m.message LIKE '%📢 SITE ANNOUNCEMENT:%')";
        } else {
            $where_conditions[] = "m.type = ?";
            $params[] = $type_filter;
            $param_types .= 's';
        }
    }
    
    // Search filter
    if (!empty($search_filter)) {
        $where_conditions[] = "m.message LIKE ?";
        $params[] = '%' . $search_filter . '%';
        $param_types .= 's';
    }
    
    // User filter
    if (!empty($user_filter)) {
        $where_conditions[] = "(u.username LIKE ? OR m.guest_name LIKE ? OR m.user_id_string LIKE ?)";
        $params[] = '%' . $user_filter . '%';
        $params[] = '%' . $user_filter . '%';
        $params[] = '%' . $user_filter . '%';
        $param_types .= 'sss';
    }
    
    // Room filter
    if (!empty($room_filter)) {
        $where_conditions[] = "cr.name LIKE ?";
        $params[] = '%' . $room_filter . '%';
        $param_types .= 's';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Simple query focusing just on messages table
    $sql = "
        SELECT 
            m.id,
            m.message,
            m.timestamp,
            m.type,
            m.user_id,
            m.user_id_string,
            m.guest_name,
            m.room_id,
            u.username,
            u.is_admin,
            u.is_moderator,
            COALESCE(cr.name, 'Deleted Room') as room_name,
            gu.ip_address,
            'chat' as message_type
        FROM messages m
        LEFT JOIN users u ON m.user_id = u.id
        LEFT JOIN chatrooms cr ON m.room_id = cr.id
        LEFT JOIN global_users gu ON (m.user_id_string = gu.user_id_string OR (m.guest_name IS NOT NULL AND m.guest_name = gu.guest_name))
        $where_clause
        ORDER BY m.timestamp DESC
        LIMIT ?
    ";
    
    // Add limit to params
    $params[] = $limit;
    $param_types .= 'i';
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'messages' => $messages,
        'count' => count($messages)
    ]);
    
} catch (Exception $e) {
    error_log("Get all messages error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve messages: ' . $e->getMessage()]);
}

$conn->close();
?>
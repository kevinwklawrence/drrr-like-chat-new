<?php
// === api/get_moderator_logs.php ===
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Check if user is admin (only admins can view logs)
$user_id = $_SESSION['user']['id'];
$is_admin = false;

$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_admin = ($user_data['is_admin'] == 1);
    }
    $stmt->close();
}

if (!$is_admin) {
    echo json_encode(['status' => 'error', 'message' => 'Only administrators can view moderator logs']);
    exit;
}

$limit = min((int)($_GET['limit'] ?? 50), 200);
$offset = (int)($_GET['offset'] ?? 0);

try {
    $stmt = $conn->prepare("
        SELECT 
            ml.*,
            u.username as moderator_display_name
        FROM moderator_logs ml
        LEFT JOIN users u ON ml.moderator_id = u.id
        ORDER BY ml.timestamp DESC
        LIMIT ? OFFSET ?
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'logs' => $logs,
        'count' => count($logs)
    ]);
    
} catch (Exception $e) {
    error_log("Get moderator logs error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve logs: ' . $e->getMessage()]);
}

$conn->close();
?>

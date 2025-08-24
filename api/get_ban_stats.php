<?php
// === api/get_ban_stats.php ===
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';
include '../database_helpers.php';

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
    echo json_encode(['status' => 'error', 'message' => 'Only moderators and admins can view ban statistics']);
    exit;
}

try {
    $stats = getSiteBanStats($conn);
    
    echo json_encode([
        'status' => 'success',
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Get ban stats API error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to get ban statistics']);
}

$conn->close();
?>
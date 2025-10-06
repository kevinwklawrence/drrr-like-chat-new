<?php
// api/get_ghost_status.php - Get ghost hunt status
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

try {
    // Get active ghost hunts
    $active_stmt = $conn->prepare("
        SELECT g.*, c.name as room_name 
        FROM ghost_hunt_events g
        JOIN chatrooms c ON g.room_id = c.id
        WHERE g.is_active = 1
        ORDER BY g.spawned_at DESC
    ");
    $active_stmt->execute();
    $active_result = $active_stmt->get_result();
    $active_ghosts = [];
    while ($row = $active_result->fetch_assoc()) {
        $active_ghosts[] = $row;
    }
    $active_stmt->close();
    
    // Get recent claims (last 20)
    $recent_stmt = $conn->prepare("
        SELECT g.*, c.name as room_name, u.username as winner
        FROM ghost_hunt_events g
        JOIN chatrooms c ON g.room_id = c.id
        LEFT JOIN users u ON g.claimed_by_user_id_string = u.user_id
        WHERE g.is_active = 0 AND g.claimed_by_user_id_string IS NOT NULL
        ORDER BY g.claimed_at DESC
        LIMIT 20
    ");
    $recent_stmt->execute();
    $recent_result = $recent_stmt->get_result();
    $recent_claims = [];
    while ($row = $recent_result->fetch_assoc()) {
        $recent_claims[] = $row;
    }
    $recent_stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'active' => $active_ghosts,
        'recent' => $recent_claims
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
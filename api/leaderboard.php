<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

$type = $_POST['type'] ?? '';

if (!in_array($type, ['dura', 'event_currency'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
    exit;
}

try {
    // Use net earnings for leaderboard (received minus sent)
    if ($type === 'dura') {
        // Calculate net dura: received from others minus sent to others
        $sql = "SELECT 
                    u.username,
                    COALESCE(received.total, 0) - COALESCE(sent.total, 0) as amount
                FROM users u
                LEFT JOIN (
                    SELECT to_user_id, SUM(amount) as total 
                    FROM dura_transactions 
                    WHERE type = 'dura' AND from_user_id != 0
                    GROUP BY to_user_id
                ) received ON u.id = received.to_user_id
                LEFT JOIN (
                    SELECT from_user_id, SUM(amount) as total 
                    FROM dura_transactions 
                    WHERE type = 'dura' AND from_user_id != 0
                    GROUP BY from_user_id
                ) sent ON u.id = sent.from_user_id
                WHERE COALESCE(received.total, 0) - COALESCE(sent.total, 0) > 0";
        
        // Add exclusions if any
        $excluded_users = []; // Add user IDs here: [1, 5, 10]
        if (!empty($excluded_users)) {
            $placeholders = str_repeat('?,', count($excluded_users) - 1) . '?';
            $sql .= " AND u.id NOT IN ({$placeholders})";
        }
        
        $sql .= " ORDER BY amount DESC LIMIT 10";
        
        if (!empty($excluded_users)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(str_repeat('i', count($excluded_users)), ...$excluded_users);
        } else {
            $stmt = $conn->prepare($sql);
        }
        
    } else {
        // Event currency can't be transferred, use lifetime total
        $column = 'lifetime_event_currency';
        $excluded_users = [];
        
        if (!empty($excluded_users)) {
            $placeholders = str_repeat('?,', count($excluded_users) - 1) . '?';
            $sql = "SELECT username, {$column} as amount FROM users WHERE {$column} > 0 AND id NOT IN ({$placeholders}) ORDER BY {$column} DESC LIMIT 10";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(str_repeat('i', count($excluded_users)), ...$excluded_users);
        } else {
            $stmt = $conn->prepare("SELECT username, {$column} as amount FROM users WHERE {$column} > 0 ORDER BY {$column} DESC LIMIT 10");
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaderboard = [];
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = [
            'username' => $row['username'],
            'amount' => (int)$row['amount']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'data' => $leaderboard
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
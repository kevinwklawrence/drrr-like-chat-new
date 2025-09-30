<?php
// api/get_user_titles.php - Get equipped titles for a user
header('Content-Type: application/json');
include '../db_connect.php';

$user_id = (int)($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'titles' => []]);
    exit;
}

try {
    // Get equipped titles for the user
    $stmt = $conn->prepare("
        SELECT si.name, si.rarity, si.icon
        FROM user_inventory ui
        JOIN shop_items si ON ui.item_id = si.item_id
        WHERE ui.user_id = ? AND ui.is_equipped = 1 AND si.type = 'title'
        ORDER BY FIELD(si.rarity, 'legendary', 'strange', 'rare', 'common')
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $titles = [];
    while ($row = $result->fetch_assoc()) {
        $titles[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'titles' => $titles]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'titles' => []]);
}

$conn->close();
?>
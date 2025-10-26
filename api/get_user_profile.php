<?php
// api/get_user_profile.php - Get user profile with pets for display
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode(['status' => 'error', 'message' => 'Username required']);
    exit;
}

try {
    // Get user basic info
    $stmt = $conn->prepare("
        SELECT id, username, bio, status, avatar, hyperlinks, color,
               is_admin, is_moderator, avatar_hue, avatar_saturation,
               dura, created_at
        FROM users 
        WHERE username = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        $stmt->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Parse hyperlinks
    $user['hyperlinks'] = json_decode($user['hyperlinks'] ?? '[]', true);
    
    // Get equipped titles
    $stmt = $conn->prepare("
        SELECT si.name, si.rarity, si.icon
        FROM user_inventory ui
        JOIN shop_items si ON ui.item_id = si.item_id
        WHERE ui.user_id = ? AND ui.is_equipped = 1 AND si.type = 'title'
        ORDER BY FIELD(si.rarity, 'event', 'legendary', 'strange', 'rare', 'common')
        LIMIT 5
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $titles = [];
    while ($row = $result->fetch_assoc()) {
        $titles[] = $row;
    }
    $user['titles'] = $titles;
    $stmt->close();
    
    // Get favorited pets for profile display
    $stmt = $conn->prepare("
        SELECT p.custom_name, p.bond_level, pt.name as type_name, pt.image_url
        FROM pets p
        JOIN pet_types pt ON p.pet_type = pt.type_id
        WHERE p.user_id = ? AND p.is_favorited = 1
        ORDER BY p.bond_level DESC, p.created_at ASC
        LIMIT 3
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pets = [];
    while ($row = $result->fetch_assoc()) {
        $pets[] = $row;
    }
    $user['pets'] = $pets;
    $stmt->close();
    
    // Get badges/achievements count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as badge_count
        FROM user_inventory ui
        JOIN shop_items si ON ui.item_id = si.item_id
        WHERE ui.user_id = ? AND si.type = 'badge'
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $badge_result = $stmt->get_result()->fetch_assoc();
    $user['badge_count'] = $badge_result['badge_count'];
    $stmt->close();
    
    // Calculate stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT m.id) as message_count,
            COUNT(DISTINCT cr.id) as rooms_created
        FROM users u
        LEFT JOIN messages m ON u.id = m.user_id
        LEFT JOIN chatrooms cr ON u.id = cr.creator_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $user['stats'] = $stats;
    $stmt->close();
    
    // Remove sensitive data
    unset($user['id']);
    
    echo json_encode(['status' => 'success', 'user' => $user]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$conn->close();
?>
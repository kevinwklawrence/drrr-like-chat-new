<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Must be logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get user's inventory with item details
            $stmt = $conn->prepare("
                SELECT ui.*, si.name, si.description, si.type, si.rarity, si.icon
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ?
                ORDER BY 
                    FIELD(si.rarity, 'event', 'legendary', 'strange', 'rare', 'common'),
                    si.type,
                    ui.acquired_at DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $inventory = [];
            while ($row = $result->fetch_assoc()) {
                $inventory[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'inventory' => $inventory]);
            break;
            
        case 'equip':
            $item_id = $_POST['item_id'] ?? '';
            
            if (empty($item_id)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid item']);
                exit;
            }
            
            // Get item details
            $stmt = $conn->prepare("
                SELECT si.type, si.icon, si.name, si.item_id
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ? AND ui.item_id = ?
            ");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Item not in inventory']);
                $stmt->close();
                exit;
            }
            
            $item = $result->fetch_assoc();
            $stmt->close();
            
            // Block avatar and color equipping - they're managed in profile editor
            if ($item['type'] === 'avatar' || $item['type'] === 'color') {
                echo json_encode(['status' => 'error', 'message' => 'Avatars and colors are managed in the Profile Editor']);
                exit;
            }
            
            // NEW: Handle effect equipping
            if ($item['type'] === 'effect') {
                // Determine effect slot based on item_id prefix
                $parts = explode('_', $item_id);
                if (count($parts) < 3) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid effect ID format']);
                    exit;
                }
                
                $effectType = $parts[1]; // overlay, glow, or bubble
                
                $effectColumn = '';
                if ($effectType === 'overlay') {
                    $effectColumn = 'equipped_avatar_overlay';
                } elseif ($effectType === 'glow') {
                    $effectColumn = 'equipped_avatar_glow';
                } elseif ($effectType === 'bubble') {
                    $effectColumn = 'equipped_bubble_effect';
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid effect type']);
                    exit;
                }
                
                // Extract effect name (everything after effect_type_)
                $effectName = implode('_', array_slice($parts, 2));
                
                // Unequip any currently equipped effect of this type
                $unequipStmt = $conn->prepare("
                    UPDATE user_inventory ui
                    JOIN shop_items si ON ui.item_id = si.item_id
                    SET ui.is_equipped = 0
                    WHERE ui.user_id = ? 
                    AND si.type = 'effect'
                    AND si.item_id LIKE ?
                ");
                $likePattern = "effect_{$effectType}_%";
                $unequipStmt->bind_param("is", $user_id, $likePattern);
                $unequipStmt->execute();
                $unequipStmt->close();
                
                // Update user's equipped effect slot
                $stmt = $conn->prepare("UPDATE users SET $effectColumn = ? WHERE id = ?");
                $stmt->bind_param("si", $effectName, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Mark as equipped in inventory
                $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = ? AND item_id = ?");
                $stmt->bind_param("is", $user_id, $item_id);
                $stmt->execute();
                $stmt->close();
                
                // Update session
                $_SESSION['user'][$effectColumn] = $effectName;
                
                echo json_encode(['status' => 'success', 'message' => 'Effect equipped!', 'item_name' => $item['name']]);
                exit;
            }
            
            // Handle different item types
            if ($item['type'] === 'title') {
                // Titles: Maximum 2 can be equipped
                
                // Get current equipped title count
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_inventory ui 
                    JOIN shop_items si ON ui.item_id = si.item_id 
                    WHERE ui.user_id = ? AND si.type = 'title' AND ui.is_equipped = 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $count_data = $result->fetch_assoc();
                $equipped_count = $count_data['count'];
                $stmt->close();
                
                // Limit to 2 titles
                if ($equipped_count >= 2) {
                    echo json_encode(['status' => 'error', 'message' => 'Maximum 2 titles can be equipped at once. Please unequip a title first.']);
                    exit;
                }
                
                // Equip the title
                $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = ? AND item_id = ?");
                $stmt->bind_param("is", $user_id, $item_id);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode(['status' => 'success', 'message' => 'Title equipped', 'item_name' => $item['name']]);
                
            } elseif ($item['type'] === 'special') {
                // Special items cannot be equipped - they trigger actions on purchase
                echo json_encode(['status' => 'error', 'message' => 'Special items cannot be equipped. Their effects are applied automatically when purchased.']);
                
            } else {
                // Other item types: standard equipping
                $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = ? AND item_id = ?");
                $stmt->bind_param("is", $user_id, $item_id);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode(['status' => 'success', 'message' => 'Item equipped', 'item_name' => $item['name']]);
            }
            break;
            
        case 'unequip':
            $item_id = $_POST['item_id'] ?? '';
            
            if (empty($item_id)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid item']);
                exit;
            }
            
            // Get item type
            $stmt = $conn->prepare("
                SELECT si.type, si.name, si.item_id
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ? AND ui.item_id = ?
            ");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Item not found']);
                $stmt->close();
                exit;
            }
            
            $item = $result->fetch_assoc();
            $stmt->close();
            
            // Block avatar and color unequipping - they're managed in profile editor
            if ($item['type'] === 'avatar' || $item['type'] === 'color') {
                echo json_encode(['status' => 'error', 'message' => 'Avatars and colors are managed in the Profile Editor']);
                exit;
            }
            
            // NEW: Handle effect unequipping
            if ($item['type'] === 'effect') {
                $parts = explode('_', $item_id);
                if (count($parts) < 3) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid effect ID format']);
                    exit;
                }
                
                $effectType = $parts[1];
                
                $effectColumn = '';
                if ($effectType === 'overlay') {
                    $effectColumn = 'equipped_avatar_overlay';
                } elseif ($effectType === 'glow') {
                    $effectColumn = 'equipped_avatar_glow';
                } elseif ($effectType === 'bubble') {
                    $effectColumn = 'equipped_bubble_effect';
                }
                
                // Clear the effect slot
                $stmt = $conn->prepare("UPDATE users SET $effectColumn = NULL WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Mark as unequipped in inventory
                $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 0 WHERE user_id = ? AND item_id = ?");
                $stmt->bind_param("is", $user_id, $item_id);
                $stmt->execute();
                $stmt->close();
                
                // Update session
                $_SESSION['user'][$effectColumn] = null;
                
                echo json_encode(['status' => 'success', 'message' => 'Effect unequipped']);
                exit;
            }
            
            // Unequip the item
            $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 0 WHERE user_id = ? AND item_id = ?");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'message' => 'Item unequipped', 'item_name' => $item['name']]);
            break;
            
        case 'get_equipped':
            // Get user's equipped items
            $stmt = $conn->prepare("
                SELECT ui.*, si.name, si.description, si.type, si.rarity, si.icon
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ? AND ui.is_equipped = 1
                ORDER BY si.type, si.rarity DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $equipped = [];
            while ($row = $result->fetch_assoc()) {
                $equipped[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'equipped' => $equipped]);
            break;
            
        case 'get_purchased_avatars_colors':
            // Get user's purchased avatars and colors
            $stmt = $conn->prepare("
                SELECT ui.item_id, si.name, si.icon, si.type, si.rarity
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ? AND (si.type = 'avatar' OR si.type = 'color')
                ORDER BY si.type, ui.acquired_at DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $avatars = [];
            $colors = [];
            while ($row = $result->fetch_assoc()) {
                if ($row['type'] === 'avatar') {
                    $avatars[] = $row;
                } else if ($row['type'] === 'color') {
                    $colors[] = $row;
                }
            }
            $stmt->close();
            
            echo json_encode([
                'status' => 'success', 
                'avatars' => $avatars,
                'colors' => $colors
            ]);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
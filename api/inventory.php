<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Must be logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
                    FIELD(si.rarity, 'common', 'rare', 'strange', 'legendary') DESC,
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
            
            // Check if user owns the item
            $stmt = $conn->prepare("SELECT id, item_id FROM user_inventory WHERE user_id = ? AND item_id = ?");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Item not found']);
                $stmt->close();
                exit;
            }
            $stmt->close();
            
            // Equip the item
            $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = ? AND item_id = ?");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'message' => 'Item equipped']);
            break;
            
        case 'unequip':
            $item_id = $_POST['item_id'] ?? '';
            
            if (empty($item_id)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid item']);
                exit;
            }
            
            // Unequip the item
            $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 0 WHERE user_id = ? AND item_id = ?");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'message' => 'Item unequipped']);
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
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
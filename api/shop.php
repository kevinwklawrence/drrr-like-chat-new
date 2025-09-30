<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Must be logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'purchase':
            $item_id = $_POST['item_id'] ?? '';
            
            if (empty($item_id)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid item']);
                exit;
            }
            
            // Get item details from shop_items
            $stmt = $conn->prepare("SELECT name, cost, currency, type FROM shop_items WHERE item_id = ? AND is_available = 1");
            $stmt->bind_param("s", $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Item not available']);
                $stmt->close();
                exit;
            }
            
            $item = $result->fetch_assoc();
            $stmt->close();
            
            // Check if user already owns this item
            $stmt = $conn->prepare("SELECT id FROM user_inventory WHERE user_id = ? AND item_id = ?");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'You already own this item']);
                $stmt->close();
                exit;
            }
            $stmt->close();
            
            // Get user balance
            $stmt = $conn->prepare("SELECT dura, tokens FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            $stmt->close();
            
            // Check balance
            $balance = $item['currency'] === 'dura' ? $user_data['dura'] : $user_data['tokens'];
            if ($balance < $item['cost']) {
                echo json_encode(['status' => 'error', 'message' => 'Insufficient balance']);
                exit;
            }
            
            // Deduct currency
            if ($item['currency'] === 'dura') {
                $stmt = $conn->prepare("UPDATE users SET dura = dura - ? WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE users SET tokens = tokens - ? WHERE id = ?");
            }
            $stmt->bind_param("ii", $item['cost'], $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Add to inventory
            $stmt = $conn->prepare("INSERT INTO user_inventory (user_id, item_id) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $stmt->close();
            
            // Log purchase
            $stmt = $conn->prepare("INSERT INTO shop_purchases (user_id, item_id, cost, currency, purchased_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isis", $user_id, $item_id, $item['cost'], $item['currency']);
            $stmt->execute();
            $stmt->close();
            
            // Update session
            if ($item['currency'] === 'dura') {
                $_SESSION['user']['dura'] = $user_data['dura'] - $item['cost'];
            } else {
                $_SESSION['user']['tokens'] = $user_data['tokens'] - $item['cost'];
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Purchase complete!', 'item_name' => $item['name']]);
            break;
            
        case 'get_items':
            // Get all available shop items
            $stmt = $conn->prepare("
                SELECT si.*, 
                       CASE WHEN ui.id IS NOT NULL THEN 1 ELSE 0 END as owned
                FROM shop_items si
                LEFT JOIN user_inventory ui ON si.item_id = ui.item_id AND ui.user_id = ?
                WHERE si.is_available = 1
                ORDER BY 
                    FIELD(si.rarity, 'common', 'rare', 'strange', 'legendary'),
                    si.cost ASC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'items' => $items]);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
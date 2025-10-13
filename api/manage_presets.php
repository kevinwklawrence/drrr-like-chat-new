<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user']['id'];

try {
    switch ($action) {
        case 'save':
            $preset_name = trim($_POST['preset_name'] ?? '');
            $avatar = $_POST['avatar'] ?? '';
            $color = $_POST['color'] ?? '';
            $avatar_hue = (int)($_POST['avatar_hue'] ?? 0);
            $avatar_saturation = (int)($_POST['avatar_saturation'] ?? 100);
            $bubble_hue = (int)($_POST['bubble_hue'] ?? 0);
            $bubble_saturation = (int)($_POST['bubble_saturation'] ?? 100);
            
            if (empty($preset_name) || empty($avatar) || empty($color)) {
                echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
                exit;
            }
            
            // Check preset limit
            $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM profile_presets WHERE user_id = ?");
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $count = $count_stmt->get_result()->fetch_assoc()['count'];
            $count_stmt->close();
            
            if ($count >= 10) {
                echo json_encode(['status' => 'error', 'message' => 'Maximum 10 presets allowed']);
                exit;
            }
            
            $stmt = $conn->prepare("INSERT INTO profile_presets (user_id, preset_name, avatar, color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssiiii", $user_id, $preset_name, $avatar, $color, $avatar_hue, $avatar_saturation, $bubble_hue, $bubble_saturation);
            $stmt->execute();
            $preset_id = $stmt->insert_id;
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'preset_id' => $preset_id]);
            break;
            
        case 'load':
            $stmt = $conn->prepare("SELECT * FROM profile_presets WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $presets = [];
            while ($row = $result->fetch_assoc()) {
                $presets[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'presets' => $presets]);
            break;
            
        case 'delete':
            $preset_id = (int)($_POST['preset_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM profile_presets WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $preset_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['status' => 'success']);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
<?php
// api/claim_pumpkin.php - Claim pumpkin smash reward
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Only registered users can claim pumpkins']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$pumpkin_id = isset($_POST['pumpkin_id']) ? (int)$_POST['pumpkin_id'] : 0;
$room_id = isset($_SESSION['room_id']) ? (int)$_SESSION['room_id'] : 0;

if ($pumpkin_id <= 0 || $room_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get pumpkin event
    $stmt = $conn->prepare("SELECT id, reward_amount, is_active, claimed_by_user_id FROM pumpkin_smash_events WHERE id = ? AND room_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $pumpkin_id, $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Pumpkin not found']);
        exit;
    }
    
    $pumpkin = $result->fetch_assoc();
    $stmt->close();
    
    if (!$pumpkin['is_active'] || $pumpkin['claimed_by_user_id']) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Already claimed!']);
        exit;
    }
    
    $reward = $pumpkin['reward_amount'];
    
    // Mark as claimed
    $claim_stmt = $conn->prepare("UPDATE pumpkin_smash_events SET is_active = 0, claimed_by_user_id = ?, claimed_at = NOW() WHERE id = ?");
    $claim_stmt->bind_param("ii", $user_id, $pumpkin_id);
    $claim_stmt->execute();
    $claim_stmt->close();
    
    // Award currency
    $award_stmt = $conn->prepare("UPDATE users SET event_currency = event_currency + ?, lifetime_event_currency = lifetime_event_currency + ? WHERE id = ?");
$award_stmt->bind_param("iii", $reward, $reward, $user_id);
    $award_stmt->execute();
    $award_stmt->close();
    
    // Get new balance
    $balance_stmt = $conn->prepare("SELECT event_currency, username FROM users WHERE id = ?");
    $balance_stmt->bind_param("i", $user_id);
    $balance_stmt->execute();
    $user_data = $balance_stmt->get_result()->fetch_assoc();
    $balance_stmt->close();
    
    $username = $user_data['username'];
    $new_balance = $user_data['event_currency'];
    
    // Send success message
    $success_message = "💥 <strong style='color: #4CAF50;'>$username smashed the pumpkin!</strong> +$reward 🎃 Event Currency earned!";
    $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, 'PUMPKIN_SMASH', ?, 1, NOW(), 'pumpkin.png', 'system')");
    $msg_stmt->bind_param("is", $room_id, $success_message);
    $msg_stmt->execute();
    $msg_stmt->close();
    
    $conn->commit();
    
    $_SESSION['user']['event_currency'] = $new_balance;
    
    echo json_encode([
        'status' => 'success',
        'reward' => $reward,
        'new_balance' => $new_balance,
        'message' => "You smashed the pumpkin and earned $reward event currency!"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
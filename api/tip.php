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
        case 'grant_tokens':
            // Grant tokens every 12 hours
            $stmt = $conn->prepare("SELECT tokens, last_token_grant FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            $current_time = time();
            $last_grant = $data['last_token_grant'] ? strtotime($data['last_token_grant']) : 0;
            $time_diff = $current_time - $last_grant;
            
            if ($time_diff >= 43200) { // 12 hours in seconds
                $new_tokens = min(20, $data['tokens'] + 10);
                $stmt = $conn->prepare("UPDATE users SET tokens = ?, last_token_grant = NOW() WHERE id = ?");
                $stmt->bind_param("ii", $new_tokens, $user_id);
                $stmt->execute();
                $stmt->close();
                
                $_SESSION['user']['tokens'] = $new_tokens;
                echo json_encode(['status' => 'success', 'tokens' => $new_tokens]);
            } else {
                $hours_left = round((43200 - $time_diff) / 3600, 1);
                echo json_encode(['status' => 'wait', 'hours_left' => $hours_left, 'tokens' => $data['tokens']]);
            }
            break;
            
        case 'tip':
            $to_user_id = (int)$_POST['to_user_id'];
            $amount = (int)$_POST['amount'];
            $tip_type = $_POST['type']; // 'token' or 'dura'
            
            if ($to_user_id === $user_id) {
                echo json_encode(['status' => 'error', 'message' => 'Cannot tip yourself']);
                exit;
            }
            
            if ($amount <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid amount']);
                exit;
            }
            
            // Verify recipient is a registered user (not a guest)
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $to_user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Can only tip registered users']);
                $stmt->close();
                exit;
            }
            $stmt->close();
            
            // Get current user data
            $stmt = $conn->prepare("SELECT dura, tokens FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $from_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($tip_type === 'token') {
                if ($from_data['tokens'] < $amount) {
                    echo json_encode(['status' => 'error', 'message' => 'Not enough tokens']);
                    exit;
                }
                
                // Deduct tokens from sender
                $new_tokens = $from_data['tokens'] - $amount;
                $stmt = $conn->prepare("UPDATE users SET tokens = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_tokens, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Add dura to recipient (1 token = 1 dura)
                $stmt = $conn->prepare("UPDATE users SET dura = dura + ? WHERE id = ?");
                $stmt->bind_param("ii", $amount, $to_user_id);
                $stmt->execute();
                $stmt->close();
                
                $_SESSION['user']['tokens'] = $new_tokens;
                
            } else if ($tip_type === 'dura') {
                if ($from_data['dura'] < $amount) {
                    echo json_encode(['status' => 'error', 'message' => 'Not enough Dura']);
                    exit;
                }
                
                // Deduct dura from sender
                $new_dura = $from_data['dura'] - $amount;
                $stmt = $conn->prepare("UPDATE users SET dura = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_dura, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Add dura to recipient
                $stmt = $conn->prepare("UPDATE users SET dura = dura + ? WHERE id = ?");
                $stmt->bind_param("ii", $amount, $to_user_id);
                $stmt->execute();
                $stmt->close();
                
                $_SESSION['user']['dura'] = $new_dura;
            }
            
            // Log transaction
            $stmt = $conn->prepare("INSERT INTO dura_transactions (from_user_id, to_user_id, amount, type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $user_id, $to_user_id, $amount, $tip_type);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'message' => 'Tip sent!']);
            break;
            
        case 'get_balance':
            $stmt = $conn->prepare("SELECT dura, tokens, last_token_grant FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            // Update session
            $_SESSION['user']['dura'] = $data['dura'];
            $_SESSION['user']['tokens'] = $data['tokens'];
            
            echo json_encode([
                'status' => 'success',
                'dura' => $data['dura'],
                'tokens' => $data['tokens'],
                'last_grant' => $data['last_token_grant']
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
<?php
// api/trick_or_treat.php - Daily trick-or-treat spin
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Must be logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];

try {
    // Check last spin time
    $stmt = $conn->prepare("SELECT last_trick_treat, event_currency FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    $last_spin = $user_data['last_trick_treat'];
    $now = new DateTime();
    
    if ($last_spin) {
        $last_spin_time = new DateTime($last_spin);
        $diff = $now->diff($last_spin_time);
        
        // Check if 24 hours have passed
        if ($diff->days < 1 || ($diff->days === 0 && $diff->h < 24)) {
            $hours_left = 24 - ($diff->h + ($diff->days * 24));
            echo json_encode([
                'status' => 'cooldown',
                'message' => "Come back in $hours_left hours!",
                'hours_remaining' => $hours_left
            ]);
            exit;
        }
    }
    
    // Roll for reward (70% chance to win)
    $roll = rand(1, 100);
    $is_treat = $roll <= 70;
    
    if ($is_treat) {
        // TREAT - give reward
        $rewards = [5, 10, 15, 20, 25, 30, 40, 50, 75, 100];
        $weights = [25, 20, 15, 12, 10, 8, 5, 3, 1, 1]; // Higher chance for lower rewards
        
        $total_weight = array_sum($weights);
        $random = rand(1, $total_weight);
        $reward = 5;
        
        $cumulative = 0;
        foreach ($rewards as $i => $r) {
            $cumulative += $weights[$i];
            if ($random <= $cumulative) {
                $reward = $r;
                break;
            }
        }
        
        // Award currency
        $stmt = $conn->prepare("UPDATE users SET event_currency = event_currency + ?, lifetime_event_currency = lifetime_event_currency + ?, last_trick_treat = NOW() WHERE id = ?");
$stmt->bind_param("iii", $reward, $reward, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $new_balance = $user_data['event_currency'] + $reward;
        $_SESSION['user']['event_currency'] = $new_balance;
        
        echo json_encode([
            'status' => 'success',
            'result' => 'treat',
            'reward' => $reward,
            'new_balance' => $new_balance,
            'message' => "🍬 TREAT! You got $reward event currency!"
        ]);
    } else {
        // TRICK - spooky message, no reward
        $tricks = [
            "👻 A ghost stole your candy!",
            "🕷️ A spider crawled on you!",
            "🦇 Bats flew by!",
            "💀 The skeleton laughed at you!",
            "🧙 The witch cursed you with bad luck!",
            "🎃 The pumpkin made a scary face!"
        ];
        
        $trick_message = $tricks[array_rand($tricks)];
        
        // Update last spin time
        $stmt = $conn->prepare("UPDATE users SET last_trick_treat = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'result' => 'trick',
            'reward' => 0,
            'message' => "🎭 TRICK! " . $trick_message . " No reward this time!"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
<?php
// api/check_ghost_hunt.php - Check if message matches active ghost hunt
// This file is included by send_message.php

function checkGhostHuntMatch($conn, $room_id, $message, $user_id_string, $user_type, $user_id) {
    // Only registered users can claim ghost hunts
    if ($user_type !== 'user') {
        return null;
    }
    
    // Check for active ghost hunt in this room
    $stmt = $conn->prepare("SELECT id, ghost_phrase, reward_amount FROM ghost_hunt_events WHERE room_id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $hunt = $result->fetch_assoc();
    $stmt->close();
    
    // Normalize and compare (case-insensitive, trim whitespace)
    $user_message = strtoupper(trim($message));
    $ghost_phrase = strtoupper(trim($hunt['ghost_phrase']));
    
    if ($user_message === $ghost_phrase) {
        // Correct answer! Award currency
        $hunt_id = $hunt['id'];
        $reward = $hunt['reward_amount'];
        
        // Update ghost hunt as claimed
        $claim_stmt = $conn->prepare("UPDATE ghost_hunt_events SET is_active = 0, claimed_by_user_id_string = ?, claimed_at = NOW() WHERE id = ?");
        $claim_stmt->bind_param("si", $user_id_string, $hunt_id);
        $claim_stmt->execute();
        $claim_stmt->close();
        
        // Award event currency to user
        $award_stmt = $conn->prepare("UPDATE users SET event_currency = event_currency + ?, lifetime_event_currency = lifetime_event_currency + ? WHERE id = ?");
$award_stmt->bind_param("iii", $reward, $reward, $user_id);
        $award_stmt->execute();
        $award_stmt->close();
        
        // Get username
        $username_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $username_stmt->bind_param("i", $user_id);
        $username_stmt->execute();
        $username_result = $username_stmt->get_result();
        $username = $username_result->fetch_assoc()['username'];
        $username_stmt->close();
        
        // Send success system message
        $success_message = "🎉 <strong style='color: #4CAF50;'>$username caught the ghost!</strong> +$reward 🎃 Event Currency earned!";
        $success_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, 'GHOST_HUNT', ?, 1, NOW(), 'ghost.png', 'system')");
        $success_stmt->bind_param("is", $room_id, $success_message);
        $success_stmt->execute();
        $success_stmt->close();
        
        return [
            'ghost_caught' => true,
            'reward' => $reward,
            'new_balance' => null // Will be updated by caller
        ];
    }
    
    return null;
}
?>
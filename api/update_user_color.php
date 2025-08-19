<?php
// api/update_user_color.php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$new_color = $_POST['color'] ?? '';

// Validate color
$valid_colors = [
    'blue', 'purple', 'pink', 'cyan', 'mint', 'orange', 
    'lavender', 'peach', 'green', 'yellow', 'red', 'teal', 
    'indigo', 'emerald', 'rose','spooky', 'bbyellow', 'lavender2', 'toyred', 'mudgreen', 'deepbrown', 'teal2', 'palegreen', 'negative', 'policeman2', 'brown', 'navy'
];

if (!in_array($new_color, $valid_colors)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid color selected']);
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['user_id'] ?? null;
$user_id_string = $user['user_id_string'] ?? null;

try {
    if ($user_id) {
        // Update registered user in users table
        $stmt = $conn->prepare("UPDATE users SET color = ? WHERE id = ?");
        $stmt->bind_param("si", $new_color, $user_id);
        $stmt->execute();
        
        // Also update any chatroom_users entries for this user
        $stmt2 = $conn->prepare("UPDATE chatroom_users SET color = ? WHERE user_id = ?");
        $stmt2->bind_param("si", $new_color, $user_id);
        $stmt2->execute();
        
        $stmt->close();
        $stmt2->close();
        
    } else if ($user_id_string) {
        // Update guest user in chatroom_users table
        $stmt = $conn->prepare("UPDATE chatroom_users SET color = ? WHERE user_id_string = ?");
        $stmt->bind_param("ss", $new_color, $user_id_string);
        $stmt->execute();
        $stmt->close();
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unable to identify user']);
        exit;
    }
    
    // Update session data
    $_SESSION['user']['color'] = $new_color;
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Color updated successfully',
        'new_color' => $new_color
    ]);
    
} catch (Exception $e) {
    error_log("Error updating user color: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>
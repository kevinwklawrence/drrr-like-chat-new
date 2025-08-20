<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Get all customization parameters
$avatar_hue = isset($_POST['avatar_hue']) ? (int)$_POST['avatar_hue'] : null;
$avatar_saturation = isset($_POST['avatar_saturation']) ? (int)$_POST['avatar_saturation'] : null;
$bubble_hue = isset($_POST['bubble_hue']) ? (int)$_POST['bubble_hue'] : null;
$bubble_saturation = isset($_POST['bubble_saturation']) ? (int)$_POST['bubble_saturation'] : null;

$user_id_string = $_SESSION['user']['user_id'] ?? '';

// Validate ranges
if ($avatar_hue !== null) {
    $avatar_hue = max(0, min(360, $avatar_hue));
}
if ($avatar_saturation !== null) {
    $avatar_saturation = max(0, min(200, $avatar_saturation));
}
if ($bubble_hue !== null) {
    $bubble_hue = max(0, min(360, $bubble_hue));
}
if ($bubble_saturation !== null) {
    $bubble_saturation = max(0, min(200, $bubble_saturation));
}

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

try {
    // Check what customization fields exist in global_users
    $columns_check = $conn->query("SHOW COLUMNS FROM global_users");
    $available_columns = [];
    while ($row = $columns_check->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
    
    // Add missing columns if they don't exist
    if (!in_array('avatar_hue', $available_columns)) {
        $conn->query("ALTER TABLE global_users ADD COLUMN avatar_hue INT DEFAULT 0 NOT NULL");
    }
    if (!in_array('avatar_saturation', $available_columns)) {
        $conn->query("ALTER TABLE global_users ADD COLUMN avatar_saturation INT DEFAULT 100 NOT NULL");
    }
    if (!in_array('bubble_hue', $available_columns)) {
        $conn->query("ALTER TABLE global_users ADD COLUMN bubble_hue INT DEFAULT 0 NOT NULL");
    }
    if (!in_array('bubble_saturation', $available_columns)) {
        $conn->query("ALTER TABLE global_users ADD COLUMN bubble_saturation INT DEFAULT 100 NOT NULL");
    }
    
    // Build update query for global_users
    $update_fields = [];
    $update_params = [];
    $param_types = '';
    
    if ($avatar_hue !== null) {
        $update_fields[] = 'avatar_hue = ?';
        $update_params[] = $avatar_hue;
        $param_types .= 'i';
        $_SESSION['user']['avatar_hue'] = $avatar_hue;
    }
    
    if ($avatar_saturation !== null) {
        $update_fields[] = 'avatar_saturation = ?';
        $update_params[] = $avatar_saturation;
        $param_types .= 'i';
        $_SESSION['user']['avatar_saturation'] = $avatar_saturation;
    }
    
    if ($bubble_hue !== null) {
        $update_fields[] = 'bubble_hue = ?';
        $update_params[] = $bubble_hue;
        $param_types .= 'i';
        $_SESSION['user']['bubble_hue'] = $bubble_hue;
    }
    
    if ($bubble_saturation !== null) {
        $update_fields[] = 'bubble_saturation = ?';
        $update_params[] = $bubble_saturation;
        $param_types .= 'i';
        $_SESSION['user']['bubble_saturation'] = $bubble_saturation;
    }
    
    if (!empty($update_fields)) {
        // Update global_users table
        $update_sql = "UPDATE global_users SET " . implode(', ', $update_fields) . " WHERE user_id_string = ?";
        $update_params[] = $user_id_string;
        $param_types .= 's';
        
        $stmt = $conn->prepare($update_sql);
        if ($stmt) {
            $stmt->bind_param($param_types, ...$update_params);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update chatroom_users table for any active room sessions
        $chatroom_stmt = $conn->prepare("UPDATE chatroom_users SET " . implode(', ', $update_fields) . " WHERE user_id_string = ?");
        if ($chatroom_stmt) {
            // Remove the last parameter (user_id_string) from update_params for chatroom_users
            $chatroom_params = array_slice($update_params, 0, -1);
            $chatroom_params[] = $user_id_string;
            $chatroom_stmt->bind_param($param_types, ...$chatroom_params);
            $chatroom_stmt->execute();
            $chatroom_stmt->close();
        }
        
        // Update users table if it's a registered user
        if ($_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['id'])) {
            // Check what columns exist in users table
            $users_columns_check = $conn->query("SHOW COLUMNS FROM users");
            $users_available_columns = [];
            while ($row = $users_columns_check->fetch_assoc()) {
                $users_available_columns[] = $row['Field'];
            }
            
            // Add missing columns to users table if they don't exist
            if (!in_array('avatar_hue', $users_available_columns)) {
                $conn->query("ALTER TABLE users ADD COLUMN avatar_hue INT DEFAULT 0 NOT NULL");
            }
            if (!in_array('avatar_saturation', $users_available_columns)) {
                $conn->query("ALTER TABLE users ADD COLUMN avatar_saturation INT DEFAULT 100 NOT NULL");
            }
            if (!in_array('bubble_hue', $users_available_columns)) {
                $conn->query("ALTER TABLE users ADD COLUMN bubble_hue INT DEFAULT 0 NOT NULL");
            }
            if (!in_array('bubble_saturation', $users_available_columns)) {
                $conn->query("ALTER TABLE users ADD COLUMN bubble_saturation INT DEFAULT 100 NOT NULL");
            }
            
            // Update users table
            $users_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $users_params = array_slice($update_params, 0, -1); // Remove user_id_string
            $users_params[] = $_SESSION['user']['id'];
            $users_param_types = substr($param_types, 0, -1) . 'i'; // Replace 's' with 'i' for user id
            
            $users_stmt = $conn->prepare($users_sql);
            if ($users_stmt) {
                $users_stmt->bind_param($users_param_types, ...$users_params);
                $users_stmt->execute();
                $users_stmt->close();
            }
        }
    }
    
    $response = [
        'status' => 'success',
        'message' => 'Customization updated successfully'
    ];
    
    // Include updated values in response
    if ($avatar_hue !== null) {
        $response['avatar_hue'] = $avatar_hue;
    }
    if ($avatar_saturation !== null) {
        $response['avatar_saturation'] = $avatar_saturation;
    }
    if ($bubble_hue !== null) {
        $response['bubble_hue'] = $bubble_hue;
    }
    if ($bubble_saturation !== null) {
        $response['bubble_saturation'] = $bubble_saturation;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Update customization error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update customization: ' . $e->getMessage()]);
}

$conn->close();
?>
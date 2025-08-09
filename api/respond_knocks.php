<?php
session_start();
header('Content-Type: application/json');

// Enhanced error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

$knock_id = (int)($_POST['knock_id'] ?? 0);
$response = $_POST['response'] ?? '';
$current_user_id_string = $_SESSION['user']['user_id'] ?? '';

error_log("KNOCK_RESPONSE_FIXED: Starting - knock_id: $knock_id, response: $response, user: $current_user_id_string");

if ($knock_id <= 0 || !in_array($response, ['accepted', 'denied']) || empty($current_user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // First, ensure room_keys column exists
    $columns_check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'room_keys'");
    $room_keys_column_exists = $columns_check->num_rows > 0;
    error_log("KNOCK_RESPONSE_FIXED: room_keys column exists: " . ($room_keys_column_exists ? 'YES' : 'NO'));
    
    if (!$room_keys_column_exists) {
        error_log("KNOCK_RESPONSE_FIXED: Creating room_keys column...");
        $create_column = $conn->query("ALTER TABLE chatrooms ADD COLUMN room_keys TEXT DEFAULT NULL");
        if (!$create_column) {
            throw new Exception('Failed to create room_keys column: ' . $conn->error);
        }
        $room_keys_column_exists = true;
        error_log("KNOCK_RESPONSE_FIXED: room_keys column created successfully");
    }
    
    // Get knock details and verify user is host
    $stmt = $conn->prepare("
        SELECT rk.*, c.name as room_name, c.has_password, c.room_keys
        FROM room_knocks rk 
        JOIN chatrooms c ON rk.room_id = c.id 
        JOIN chatroom_users cu ON c.id = cu.room_id 
        WHERE rk.id = ? 
        AND cu.user_id_string = ? 
        AND cu.is_host = 1 
        AND rk.status = 'pending'
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $knock_id, $current_user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Knock request not found or you are not authorized']);
        $stmt->close();
        $conn->rollback();
        exit;
    }
    
    $knock = $result->fetch_assoc();
    $stmt->close();
    
    error_log("KNOCK_RESPONSE_FIXED: Found knock - room_id: {$knock['room_id']}, user: {$knock['user_id_string']}, has_password: {$knock['has_password']}");
    
    // Update knock status first
    $stmt = $conn->prepare("UPDATE room_knocks SET status = ?, responded_by = ?, responded_at = NOW() WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ssi", $response, $current_user_id_string, $knock_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update knock status: ' . $stmt->error);
    }
    $stmt->close();
    
    error_log("KNOCK_RESPONSE_FIXED: Updated knock status to $response");
    
    if ($response === 'accepted') {
        // Only create room key if room has password
        if ($knock['has_password'] == 1) {
            error_log("KNOCK_RESPONSE_FIXED: Room has password, creating room key...");
            
            // Get current room keys - handle both NULL and empty string cases
            $current_room_keys = $knock['room_keys'] ?? null;
            $room_keys = [];
            
            if (!empty($current_room_keys) && $current_room_keys !== 'null') {
                $decoded_keys = json_decode($current_room_keys, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_keys)) {
                    $room_keys = $decoded_keys;
                    error_log("KNOCK_RESPONSE_FIXED: Existing room keys loaded: " . count($room_keys) . " keys");
                } else {
                    error_log("KNOCK_RESPONSE_FIXED: Failed to decode existing room keys, starting fresh");
                }
            } else {
                error_log("KNOCK_RESPONSE_FIXED: No existing room keys, starting fresh");
            }
            
            // Create new room key
            $expires_at = time() + (2 * 60 * 60); // 2 hours
            $new_key = [
                'granted_by' => $current_user_id_string,
                'granted_at' => time(),
                'expires_at' => $expires_at,
                'knock_id' => $knock_id,
                'room_id' => $knock['room_id']
            ];
            
            $room_keys[$knock['user_id_string']] = $new_key;
            
            error_log("KNOCK_RESPONSE_FIXED: New room key created for {$knock['user_id_string']}, expires: " . date('Y-m-d H:i:s', $expires_at));
            error_log("KNOCK_RESPONSE_FIXED: Total room keys now: " . count($room_keys));
            
            // Encode and save room keys
            $room_keys_json = json_encode($room_keys, JSON_UNESCAPED_SLASHES);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to encode room keys: ' . json_last_error_msg());
            }
            
            error_log("KNOCK_RESPONSE_FIXED: JSON to save: $room_keys_json");
            
            $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("si", $room_keys_json, $knock['room_id']);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update room keys: ' . $stmt->error);
            }
            
            $affected_rows = $stmt->affected_rows;
            error_log("KNOCK_RESPONSE_FIXED: Room keys update affected $affected_rows rows");
            $stmt->close();
            
            // Verify the save worked
            $verify_stmt = $conn->prepare("SELECT room_keys FROM chatrooms WHERE id = ?");
            $verify_stmt->bind_param("i", $knock['room_id']);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            error_log("KNOCK_RESPONSE_FIXED: Verification - saved room_keys: " . substr($verify_data['room_keys'] ?? 'NULL', 0, 100) . "...");
            
            // Double-check that our key was saved
            if (!empty($verify_data['room_keys'])) {
                $saved_keys = json_decode($verify_data['room_keys'], true);
                if (isset($saved_keys[$knock['user_id_string']])) {
                    error_log("KNOCK_RESPONSE_FIXED: ✅ Room key successfully saved and verified");
                } else {
                    error_log("KNOCK_RESPONSE_FIXED: ❌ Room key was not found in saved data");
                }
            } else {
                error_log("KNOCK_RESPONSE_FIXED: ❌ No room keys found in verification");
            }
            
        } else {
            error_log("KNOCK_RESPONSE_FIXED: Room has no password, skipping room key creation");
        }
        
        // Add system message about accepted knock
        $knocker_name = $knock['username'] ?: $knock['guest_name'] ?: 'Unknown User';
        $accept_message = $knocker_name . " was granted access to the room";
        
        $msg_columns_query = $conn->query("SHOW COLUMNS FROM messages");
        $msg_columns = [];
        while ($row = $msg_columns_query->fetch_assoc()) {
            $msg_columns[] = $row['Field'];
        }
        
        if (in_array('is_system', $msg_columns)) {
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'key.png', 'system')");
        } else {
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, timestamp, avatar) VALUES (?, '', ?, NOW(), 'key.png')");
        }
        
        if ($stmt) {
            $stmt->bind_param("is", $knock['room_id'], $accept_message);
            $stmt->execute();
            $stmt->close();
        }
        
        error_log("KNOCK_RESPONSE_FIXED: Knock accepted and processed successfully");
    } else {
        error_log("KNOCK_RESPONSE_FIXED: Knock denied");
    }
    
    $conn->commit();
    
    $message = $response === 'accepted' ? 
        'Knock request accepted - user can now join the room' : 
        'Knock request denied';
        
    echo json_encode([
        'status' => 'success', 
        'message' => $message,
        'knock_id' => $knock_id,
        'user_id_string' => $knock['user_id_string'],
        'room_id' => $knock['room_id'],
        'debug' => [
            'room_keys_column_exists' => $room_keys_column_exists,
            'room_has_password' => $knock['has_password'] == 1,
            'should_create_key' => ($knock['has_password'] == 1),
            'response_type' => $response
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("KNOCK_RESPONSE_FIXED: Error - " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to respond to knock request: ' . $e->getMessage()]);
}

$conn->close();
?>
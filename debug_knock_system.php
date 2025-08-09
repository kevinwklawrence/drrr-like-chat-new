<?php
// Create this as: debug_knock_system.php
session_start();
include 'db_connect.php';

$room_id = $_GET['room_id'] ?? ($_SESSION['room_id'] ?? 113);
$user_id_string = $_GET['user'] ?? ($_SESSION['user']['user_id'] ?? '');

echo "<h2>Knock System Debug for Room $room_id</h2>";

try {
    // 1. Check if room_keys column exists
    echo "<h3>1. Database Structure Check</h3>";
    $columns_check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'room_keys'");
    $room_keys_column_exists = $columns_check->num_rows > 0;
    echo "<p><strong>room_keys column exists:</strong> " . ($room_keys_column_exists ? '✅ YES' : '❌ NO') . "</p>";
    
    if (!$room_keys_column_exists) {
        echo "<p style='color: red;'><strong>ISSUE FOUND:</strong> room_keys column doesn't exist! Creating it...</p>";
        $create_column = $conn->query("ALTER TABLE chatrooms ADD COLUMN room_keys TEXT DEFAULT NULL");
        if ($create_column) {
            echo "<p style='color: green;'>✅ room_keys column created successfully!</p>";
            $room_keys_column_exists = true;
        } else {
            echo "<p style='color: red;'>❌ Failed to create room_keys column: " . $conn->error . "</p>";
        }
    }
    
    // 2. Get room information
    echo "<h3>2. Room Information</h3>";
    $stmt = $conn->prepare("SELECT id, name, has_password, password, room_keys FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        echo "<p style='color: red;'>❌ Room not found!</p>";
        exit;
    }
    
    echo "<p><strong>Room Name:</strong> {$room['name']}</p>";
    echo "<p><strong>Has Password:</strong> " . ($room['has_password'] ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>Password Hash:</strong> " . ($room['password'] ? substr($room['password'], 0, 20) . '...' : 'NULL') . "</p>";
    echo "<p><strong>Room Keys (raw):</strong> " . ($room['room_keys'] ?: 'NULL') . "</p>";
    
    if ($room['room_keys']) {
        $room_keys = json_decode($room['room_keys'], true);
        echo "<p><strong>Room Keys (parsed):</strong></p>";
        echo "<pre>" . print_r($room_keys, true) . "</pre>";
    }
    
    // 3. Test password if provided
    if (isset($_GET['test_password'])) {
        echo "<h3>3. Password Test</h3>";
        $test_password = $_GET['test_password'];
        echo "<p><strong>Testing password:</strong> '$test_password'</p>";
        
        if ($room['password']) {
            $is_valid = password_verify($test_password, $room['password']);
            echo "<p><strong>Password verification:</strong> " . ($is_valid ? '✅ VALID' : '❌ INVALID') . "</p>";
            
            if (!$is_valid) {
                // Test variations
                $variations = [
                    trim($test_password),
                    rtrim($test_password),
                    ltrim($test_password)
                ];
                
                echo "<p><strong>Testing variations:</strong></p>";
                foreach ($variations as $i => $variation) {
                    $test_result = password_verify($variation, $room['password']);
                    echo "<p>  Variation $i ('$variation'): " . ($test_result ? '✅ VALID' : '❌ INVALID') . "</p>";
                }
            }
        } else {
            echo "<p>❌ Room has no password set</p>";
        }
    }
    
    // 4. Check pending knocks
    echo "<h3>4. Pending Knocks</h3>";
    $knock_stmt = $conn->prepare("SELECT * FROM room_knocks WHERE room_id = ? AND status = 'pending' ORDER BY created_at DESC");
    $knock_stmt->bind_param("i", $room_id);
    $knock_stmt->execute();
    $knock_result = $knock_stmt->get_result();
    
    if ($knock_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Username/Guest</th><th>Status</th><th>Created</th><th>Actions</th></tr>";
        while ($knock = $knock_result->fetch_assoc()) {
            $display_name = $knock['username'] ?: $knock['guest_name'] ?: 'Unknown';
            echo "<tr>";
            echo "<td>{$knock['id']}</td>";
            echo "<td>{$knock['user_id_string']}</td>";
            echo "<td>$display_name</td>";
            echo "<td>{$knock['status']}</td>";
            echo "<td>{$knock['created_at']}</td>";
            echo "<td>";
            echo "<a href='?room_id=$room_id&action=accept_knock&knock_id={$knock['id']}' style='color: green;'>Accept</a> | ";
            echo "<a href='?room_id=$room_id&action=deny_knock&knock_id={$knock['id']}' style='color: red;'>Deny</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No pending knocks</p>";
    }
    $knock_stmt->close();
    
    // 5. Handle knock actions
    if (isset($_GET['action']) && isset($_GET['knock_id'])) {
        echo "<h3>5. Knock Action Test</h3>";
        $action = $_GET['action'];
        $knock_id = (int)$_GET['knock_id'];
        
        if ($action === 'accept_knock') {
            echo "<p>Testing knock acceptance for knock ID: $knock_id</p>";
            
            // Simulate the accept process
            $test_response = testKnockAcceptance($conn, $knock_id, $room_id, $user_id_string);
            echo "<pre>" . print_r($test_response, true) . "</pre>";
        }
    }
    
    // 6. Show current user info
    if (!empty($user_id_string)) {
        echo "<h3>6. Current User Info</h3>";
        echo "<p><strong>User ID String:</strong> $user_id_string</p>";
        
        // Check if user has a room key
        if ($room['room_keys']) {
            $room_keys = json_decode($room['room_keys'], true) ?: [];
            if (isset($room_keys[$user_id_string])) {
                $user_key = $room_keys[$user_id_string];
                echo "<p><strong>User has room key:</strong> ✅ YES</p>";
                echo "<p><strong>Key expires:</strong> " . date('Y-m-d H:i:s', $user_key['expires_at']) . "</p>";
                echo "<p><strong>Key valid:</strong> " . ($user_key['expires_at'] > time() ? '✅ YES' : '❌ EXPIRED') . "</p>";
            } else {
                echo "<p><strong>User has room key:</strong> ❌ NO</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test function for knock acceptance
function testKnockAcceptance($conn, $knock_id, $room_id, $current_user_id_string) {
    $debug = ['step' => 'start'];
    
    try {
        // Get knock details
        $stmt = $conn->prepare("SELECT * FROM room_knocks WHERE id = ?");
        $stmt->bind_param("i", $knock_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $knock = $result->fetch_assoc();
        $stmt->close();
        
        if (!$knock) {
            return ['error' => 'Knock not found'];
        }
        
        $debug['knock'] = $knock;
        
        // Get room info
        $stmt = $conn->prepare("SELECT room_keys, has_password FROM chatrooms WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $room = $result->fetch_assoc();
        $stmt->close();
        
        $debug['room'] = $room;
        
        // Check if room has password
        if ($room['has_password'] == 1) {
            $debug['step'] = 'creating_room_key';
            
            // Get current room keys
            $room_keys = [];
            if (!empty($room['room_keys'])) {
                $room_keys = json_decode($room['room_keys'], true) ?: [];
            }
            
            $debug['existing_keys'] = $room_keys;
            
            // Create new room key
            $expires_at = time() + (2 * 60 * 60); // 2 hours
            $room_keys[$knock['user_id_string']] = [
                'granted_by' => $current_user_id_string,
                'granted_at' => time(),
                'expires_at' => $expires_at,
                'knock_id' => $knock_id,
                'room_id' => $room_id
            ];
            
            $debug['new_keys'] = $room_keys;
            
            // Update room keys in database
            $room_keys_json = json_encode($room_keys);
            $debug['json_to_save'] = $room_keys_json;
            
            $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $stmt->bind_param("si", $room_keys_json, $room_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $debug['affected_rows'] = $stmt->affected_rows;
            $stmt->close();
            
            // Verify the save
            $verify_stmt = $conn->prepare("SELECT room_keys FROM chatrooms WHERE id = ?");
            $verify_stmt->bind_param("i", $room_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            $debug['saved_keys'] = $verify_data['room_keys'];
            $debug['step'] = 'room_key_created';
        } else {
            $debug['step'] = 'no_password_no_key_needed';
        }
        
        // Update knock status
        $stmt = $conn->prepare("UPDATE room_knocks SET status = 'accepted', responded_by = ?, responded_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $current_user_id_string, $knock_id);
        $stmt->execute();
        $stmt->close();
        
        $debug['step'] = 'complete';
        
    } catch (Exception $e) {
        $debug['error'] = $e->getMessage();
    }
    
    return $debug;
}

echo "<hr>";
echo "<h3>Quick Actions</h3>";
echo "<p>";
echo "<a href='?room_id=$room_id'>Refresh</a> | ";
echo "<a href='?room_id=$room_id&test_password=test'>Test Password 'test'</a> | ";
echo "<a href='?room_id=$room_id&test_password=password'>Test Password 'password'</a>";
echo "</p>";

if (!empty($user_id_string)) {
    echo "<p>";
    echo "<a href='?room_id=$room_id&user=$user_id_string'>Check Current User</a>";
    echo "</p>";
}
?>
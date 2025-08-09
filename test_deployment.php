<?php
// Test deployment script for knock system fixes
// Run this after deploying the fixed files
session_start();
include 'db_connect.php';

$test_room_id = $_GET['room_id'] ?? 113;
$step = $_GET['step'] ?? 1;

echo "<h1>Knock System Deployment Test</h1>";
echo "<p>Testing Room ID: $test_room_id</p>";

function checkResult($condition, $message) {
    echo "<p>" . ($condition ? "✅" : "❌") . " $message</p>";
    return $condition;
}

try {
    echo "<h2>Step $step: " . getStepName($step) . "</h2>";
    
    switch ($step) {
        case 1:
            testDatabaseStructure($conn, $test_room_id);
            break;
        case 2:
            testPasswordSystem($conn, $test_room_id);
            break;
        case 3:
            testRoomKeySystem($conn, $test_room_id);
            break;
        case 4:
            testKnockFlow($conn, $test_room_id);
            break;
        case 5:
            testCompleteFlow($conn, $test_room_id);
            break;
        default:
            echo "<p>Invalid step</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

function getStepName($step) {
    $steps = [
        1 => "Database Structure Check",
        2 => "Password System Test",
        3 => "Room Key System Test", 
        4 => "Knock Flow Test",
        5 => "Complete Flow Test"
    ];
    return $steps[$step] ?? "Unknown";
}

function testDatabaseStructure($conn, $room_id) {
    global $test_room_id;
    
    // Check room_keys column
    $columns_check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'room_keys'");
    $room_keys_exists = checkResult($columns_check->num_rows > 0, "room_keys column exists");
    
    // Check has_password column
    $password_check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE 'has_password'");
    $has_password_exists = checkResult($password_check->num_rows > 0, "has_password column exists");
    
    // Check room_knocks table
    $knocks_check = $conn->query("SHOW TABLES LIKE 'room_knocks'");
    $knocks_table_exists = checkResult($knocks_check->num_rows > 0, "room_knocks table exists");
    
    // Check if test room exists
    $stmt = $conn->prepare("SELECT id, name FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room_exists = checkResult($result->num_rows > 0, "Test room (ID: $room_id) exists");
    $stmt->close();
    
    if (!$room_exists) {
        echo "<p><strong>Creating test room...</strong></p>";
        $stmt = $conn->prepare("INSERT INTO chatrooms (id, name, description, capacity, has_password, password, allow_knocking) VALUES (?, 'Test Room', 'Test room for knock system', 10, 1, ?, 1)");
        $test_password_hash = password_hash('test', PASSWORD_DEFAULT);
        $stmt->bind_param("is", $room_id, $test_password_hash);
        if ($stmt->execute()) {
            checkResult(true, "Test room created successfully");
        } else {
            checkResult(false, "Failed to create test room: " . $stmt->error);
        }
        $stmt->close();
    }
    
    echo "<p><a href='?step=2&room_id=$test_room_id'>Next: Test Password System</a></p>";
}

function testPasswordSystem($conn, $room_id) {
    global $test_room_id;
    
    // Get room info
    $stmt = $conn->prepare("SELECT password, has_password FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    checkResult($room !== null, "Room data retrieved");
    checkResult($room['has_password'] == 1, "Room has password enabled");
    checkResult(!empty($room['password']), "Room has password hash");
    
    // Test password verification
    $test_password = 'test';
    $password_valid = password_verify($test_password, $room['password']);
    checkResult($password_valid, "Password '$test_password' verifies correctly");
    
    if (!$password_valid) {
        echo "<p><strong>Setting test password...</strong></p>";
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE chatrooms SET password = ?, has_password = 1 WHERE id = ?");
        $stmt->bind_param("si", $new_hash, $room_id);
        if ($stmt->execute()) {
            checkResult(true, "Test password set successfully");
        } else {
            checkResult(false, "Failed to set test password");
        }
        $stmt->close();
    }
    
    echo "<p><a href='?step=3&room_id=$test_room_id'>Next: Test Room Key System</a></p>";
}

function testRoomKeySystem($conn, $room_id) {
    global $test_room_id;
    
    // Test room key creation
    $test_user_id = 'TEST_USER_123';
    $test_room_keys = [
        $test_user_id => [
            'granted_by' => 'HOST_USER',
            'granted_at' => time(),
            'expires_at' => time() + 3600,
            'knock_id' => 999,
            'room_id' => $room_id
        ]
    ];
    
    $room_keys_json = json_encode($test_room_keys);
    checkResult(json_last_error() === JSON_ERROR_NONE, "Room keys JSON encoding works");
    
    // Save room keys
    $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
    $stmt->bind_param("si", $room_keys_json, $room_id);
    $save_success = $stmt->execute();
    checkResult($save_success, "Room keys saved to database");
    $stmt->close();
    
    // Verify room keys were saved
    $stmt = $conn->prepare("SELECT room_keys FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $saved_data = $result->fetch_assoc();
    $stmt->close();
    
    checkResult(!empty($saved_data['room_keys']), "Room keys data exists in database");
    
    $decoded_keys = json_decode($saved_data['room_keys'], true);
    checkResult(json_last_error() === JSON_ERROR_NONE, "Room keys JSON decoding works");
    checkResult(isset($decoded_keys[$test_user_id]), "Test user key exists in saved data");
    
    echo "<p><a href='?step=4&room_id=$test_room_id'>Next: Test Knock Flow</a></p>";
}

function testKnockFlow($conn, $room_id) {
    global $test_room_id;
    
    // Create test knock
    $test_user_id = 'KNOCK_TEST_USER_456';
    $stmt = $conn->prepare("INSERT INTO room_knocks (room_id, user_id_string, guest_name, status) VALUES (?, ?, 'Test Knocker', 'pending') ON DUPLICATE KEY UPDATE status = 'pending', created_at = NOW()");
    $stmt->bind_param("is", $room_id, $test_user_id);
    $knock_created = $stmt->execute();
    checkResult($knock_created, "Test knock created");
    $knock_id = $conn->insert_id ?: 1;
    $stmt->close();
    
    // Test knock acceptance (simulate respond_knocks.php)
    echo "<p><strong>Simulating knock acceptance...</strong></p>";
    
    // Get room info
    $stmt = $conn->prepare("SELECT room_keys, has_password FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if ($room['has_password'] == 1) {
        // Create room key for the knocking user
        $room_keys = [];
        if (!empty($room['room_keys'])) {
            $room_keys = json_decode($room['room_keys'], true) ?: [];
        }
        
        $room_keys[$test_user_id] = [
            'granted_by' => 'TEST_HOST',
            'granted_at' => time(),
            'expires_at' => time() + 7200,
            'knock_id' => $knock_id,
            'room_id' => $room_id
        ];
        
        $room_keys_json = json_encode($room_keys);
        $stmt = $conn->prepare("UPDATE chatrooms SET room_keys = ? WHERE id = ?");
        $stmt->bind_param("si", $room_keys_json, $room_id);
        $key_saved = $stmt->execute();
        checkResult($key_saved, "Room key created for knocking user");
        $stmt->close();
        
        // Update knock status
        $stmt = $conn->prepare("UPDATE room_knocks SET status = 'accepted', responded_by = 'TEST_HOST', responded_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $knock_id);
        $knock_updated = $stmt->execute();
        checkResult($knock_updated, "Knock status updated to accepted");
        $stmt->close();
    }
    
    echo "<p><a href='?step=5&room_id=$test_room_id'>Next: Complete Flow Test</a></p>";
}

function testCompleteFlow($conn, $room_id) {
    global $test_room_id;
    
    echo "<h3>Complete Flow Test Summary</h3>";
    
    // Check final state
    $stmt = $conn->prepare("SELECT * FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    checkResult($room['has_password'] == 1, "Room has password protection");
    checkResult($room['allow_knocking'] == 1, "Room allows knocking");
    checkResult(!empty($room['room_keys']), "Room has active room keys");
    
    if ($room['room_keys']) {
        $room_keys = json_decode($room['room_keys'], true);
        checkResult(is_array($room_keys) && count($room_keys) > 0, "Room keys are valid and non-empty");
        
        foreach ($room_keys as $user_id => $key_data) {
            $is_valid = $key_data['expires_at'] > time();
            checkResult($is_valid, "Room key for $user_id is valid (expires: " . date('Y-m-d H:i:s', $key_data['expires_at']) . ")");
        }
    }
    
    // Check knocks
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM room_knocks WHERE room_id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $knock_count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    checkResult($knock_count > 0, "Knock requests exist in database");
    
    echo "<h3>🎉 Deployment Test Complete!</h3>";
    echo "<p>If all checks passed, your knock system should be working correctly.</p>";
    
    echo "<h4>Next Steps:</h4>";
    echo "<ul>";
    echo "<li><a href='debug_knock_system.php?room_id=$test_room_id'>Use Debug Tool</a></li>";
    echo "<li><a href='test_password_system.php?room_id=$test_room_id'>Use Password Test Tool</a></li>";
    echo "<li>Test with real users in your application</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Test Navigation</h3>";
echo "<p>";
for ($i = 1; $i <= 5; $i++) {
    echo "<a href='?step=$i&room_id=$test_room_id'>Step $i: " . getStepName($i) . "</a>";
    if ($i < 5) echo " | ";
}
echo "</p>";
?>
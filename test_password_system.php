<?php
// Create this as: test_password_system.php
include 'db_connect.php';

$room_id = $_GET['room_id'] ?? 113;
$action = $_GET['action'] ?? 'test';

echo "<h2>Password System Test for Room $room_id</h2>";

try {
    // Get room info
    $stmt = $conn->prepare("SELECT id, name, password, has_password FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        echo "<p style='color: red;'>❌ Room not found!</p>";
        exit;
    }
    
    echo "<h3>Room Information</h3>";
    echo "<p><strong>Room Name:</strong> {$room['name']}</p>";
    echo "<p><strong>Has Password:</strong> " . ($room['has_password'] ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>Password Hash:</strong> " . ($room['password'] ? substr($room['password'], 0, 30) . '...' : 'NULL') . "</p>";
    
    if ($action === 'set_password') {
        $new_password = $_GET['password'] ?? 'test';
        echo "<h3>Setting New Password</h3>";
        echo "<p>Setting password to: '$new_password'</p>";
        
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        echo "<p>Generated hash: $hashed</p>";
        
        $stmt = $conn->prepare("UPDATE chatrooms SET password = ?, has_password = 1 WHERE id = ?");
        $stmt->bind_param("si", $hashed, $room_id);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Password updated successfully!</p>";
            
            // Test the password immediately
            $test_result = password_verify($new_password, $hashed);
            echo "<p>Immediate verification test: " . ($test_result ? '✅ PASS' : '❌ FAIL') . "</p>";
            
        } else {
            echo "<p style='color: red;'>❌ Failed to update password: " . $stmt->error . "</p>";
        }
        $stmt->close();
        
        // Refresh room data
        $stmt = $conn->prepare("SELECT password, has_password FROM chatrooms WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $room = $result->fetch_assoc();
        $stmt->close();
    }
    
    if ($room['password'] && $action === 'test') {
        echo "<h3>Password Testing</h3>";
        
        $test_passwords = [
            'test',
            'password',
            'admin',
            '123456',
            'guest'
        ];
        
        foreach ($test_passwords as $test_pass) {
            $is_valid = password_verify($test_pass, $room['password']);
            echo "<p>Testing '$test_pass': " . ($is_valid ? '✅ VALID' : '❌ INVALID') . "</p>";
            
            if ($is_valid) {
                echo "<p style='color: green;'><strong>✅ CORRECT PASSWORD FOUND: '$test_pass'</strong></p>";
                break;
            }
        }
        
        // Test custom password if provided
        if (isset($_GET['test_password'])) {
            $custom_test = $_GET['test_password'];
            echo "<h4>Custom Password Test</h4>";
            $is_valid = password_verify($custom_test, $room['password']);
            echo "<p>Testing '$custom_test': " . ($is_valid ? '✅ VALID' : '❌ INVALID') . "</p>";
            
            // Test variations
            $variations = [
                trim($custom_test),
                rtrim($custom_test),
                ltrim($custom_test),
                strtolower($custom_test),
                strtoupper($custom_test)
            ];
            
            echo "<p><strong>Testing variations:</strong></p>";
            foreach ($variations as $i => $variation) {
                if ($variation !== $custom_test) {
                    $test_result = password_verify($variation, $room['password']);
                    echo "<p>  Variation $i ('$variation'): " . ($test_result ? '✅ VALID' : '❌ INVALID') . "</p>";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Quick Actions</h3>";
echo "<p>";
echo "<a href='?room_id=$room_id&action=test'>Test Passwords</a> | ";
echo "<a href='?room_id=$room_id&action=set_password&password=test'>Set Password to 'test'</a> | ";
echo "<a href='?room_id=$room_id&action=set_password&password=password'>Set Password to 'password'</a> | ";
echo "<a href='?room_id=$room_id&action=test&test_password=test'>Test Password 'test'</a>";
echo "</p>";

if (isset($_GET['test_password'])) {
    $test_pass = $_GET['test_password'];
    echo "<p>";
    echo "<a href='?room_id=$room_id&action=test&test_password=" . urlencode($test_pass) . "'>Test '$test_pass' again</a>";
    echo "</p>";
}
?>
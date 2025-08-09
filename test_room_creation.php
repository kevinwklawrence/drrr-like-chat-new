<?php
// Test room creation to debug password issues
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user'])) {
    echo "<p>Please log in first</p>";
    exit;
}

$action = $_GET['action'] ?? 'form';

if ($action === 'create') {
    echo "<h2>Testing Room Creation</h2>";
    
    // Simulate room creation with test data
    $test_data = [
        'name' => 'Test Room ' . date('H:i:s'),
        'description' => 'Test room for password debugging',
        'capacity' => 10,
        'background' => '',
        'has_password' => 1,
        'password' => 'test123',
        'allow_knocking' => 1
    ];
    
    echo "<h3>Test Data:</h3>";
    echo "<pre>" . print_r($test_data, true) . "</pre>";
    
    // Test the creation process step by step
    try {
        $conn->begin_transaction();
        
        echo "<h3>Step 1: Hash Password</h3>";
        $hashed_password = password_hash($test_data['password'], PASSWORD_DEFAULT);
        echo "<p>Original password: '{$test_data['password']}'</p>";
        echo "<p>Hashed password: $hashed_password</p>";
        echo "<p>Hash verification test: " . (password_verify($test_data['password'], $hashed_password) ? '✅ PASS' : '❌ FAIL') . "</p>";
        
        echo "<h3>Step 2: Insert Room</h3>";
        $stmt = $conn->prepare("
            INSERT INTO chatrooms (
                name, 
                description, 
                capacity, 
                background, 
                password, 
                has_password, 
                allow_knocking, 
                host_user_id_string, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $user_id_string = $_SESSION['user']['user_id'] ?? '';
        
        $stmt->bind_param(
            "ssissiis",
            $test_data['name'],
            $test_data['description'],
            $test_data['capacity'],
            $test_data['background'],
            $hashed_password,
            $test_data['has_password'],
            $test_data['allow_knocking'],
            $user_id_string
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $room_id = $conn->insert_id;
        $stmt->close();
        
        echo "<p>✅ Room created with ID: $room_id</p>";
        
        echo "<h3>Step 3: Verify Data</h3>";
        $verify_stmt = $conn->prepare("SELECT id, name, password, has_password, allow_knocking FROM chatrooms WHERE id = ?");
        $verify_stmt->bind_param("i", $room_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $saved_room = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        echo "<h4>Saved Room Data:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
        echo "<tr><td>ID</td><td>{$saved_room['id']}</td><td>✅</td></tr>";
        echo "<tr><td>Name</td><td>{$saved_room['name']}</td><td>✅</td></tr>";
        echo "<tr><td>has_password</td><td>{$saved_room['has_password']}</td><td>" . ($saved_room['has_password'] == 1 ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>password (length)</td><td>" . strlen($saved_room['password']) . " chars</td><td>" . (!empty($saved_room['password']) ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>allow_knocking</td><td>{$saved_room['allow_knocking']}</td><td>" . ($saved_room['allow_knocking'] == 1 ? '✅' : '❌') . "</td></tr>";
        echo "</table>";
        
        echo "<h3>Step 4: Test Password Verification</h3>";
        if (!empty($saved_room['password'])) {
            $verification_test = password_verify($test_data['password'], $saved_room['password']);
            echo "<p>Password verification: " . ($verification_test ? '✅ PASS' : '❌ FAIL') . "</p>";
            
            if (!$verification_test) {
                echo "<p style='color: red;'>❌ PASSWORD VERIFICATION FAILED!</p>";
                echo "<p>This means the password was saved but cannot be verified.</p>";
            } else {
                echo "<p style='color: green;'>✅ PASSWORD SYSTEM WORKING CORRECTLY!</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ NO PASSWORD SAVED!</p>";
            echo "<p>The password field is empty in the database.</p>";
        }
        
        // Don't commit - this is just a test
        $conn->rollback();
        echo "<p><em>Transaction rolled back - test room not actually created.</em></p>";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
    
} else if ($action === 'check_columns') {
    echo "<h2>Database Structure Check</h2>";
    
    $columns_query = $conn->query("SHOW COLUMNS FROM chatrooms");
    echo "<h3>Chatrooms Table Columns:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $columns_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if specific columns exist
    $required_columns = ['password', 'has_password', 'allow_knocking', 'room_keys'];
    echo "<h3>Required Columns Check:</h3>";
    foreach ($required_columns as $col) {
        $check = $conn->query("SHOW COLUMNS FROM chatrooms LIKE '$col'");
        echo "<p>$col: " . ($check->num_rows > 0 ? '✅ EXISTS' : '❌ MISSING') . "</p>";
    }
    
} else {
    // Show form
    echo "<h2>Room Creation Testing Tool</h2>";
    echo "<p>Current user: " . ($_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Unknown') . "</p>";
    
    echo "<h3>Quick Tests:</h3>";
    echo "<p>";
    echo "<a href='?action=check_columns'>Check Database Structure</a> | ";
    echo "<a href='?action=create'>Test Room Creation</a>";
    echo "</p>";
    
    echo "<h3>Manual Test Form:</h3>";
    echo "<form method='POST' action='api/create_room.php'>";
    echo "<table>";
    echo "<tr><td>Room Name:</td><td><input type='text' name='name' value='Manual Test Room' required></td></tr>";
    echo "<tr><td>Description:</td><td><input type='text' name='description' value='Manual test'></td></tr>";
    echo "<tr><td>Capacity:</td><td><select name='capacity'><option value='10'>10</option></select></td></tr>";
    echo "<tr><td>Has Password:</td><td><input type='checkbox' name='has_password' value='1' checked> Yes</td></tr>";
    echo "<tr><td>Password:</td><td><input type='password' name='password' value='test123'></td></tr>";
    echo "<tr><td>Allow Knocking:</td><td><input type='checkbox' name='allow_knocking' value='1' checked> Yes</td></tr>";
    echo "</table>";
    echo "<button type='submit'>Create Room</button>";
    echo "</form>";
    
    echo "<h3>Recent Rooms:</h3>";
    $recent = $conn->query("SELECT id, name, has_password, password, created_at FROM chatrooms ORDER BY created_at DESC LIMIT 5");
    if ($recent->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Has Password</th><th>Password Set</th><th>Created</th></tr>";
        while ($row = $recent->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['name']}</td>";
            echo "<td>" . ($row['has_password'] ? 'YES' : 'NO') . "</td>";
            echo "<td>" . (!empty($row['password']) ? 'YES' : 'NO') . "</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No rooms found</p>";
    }
}
?>
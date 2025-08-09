<?php
// Compare password handling between create_room and update_room
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user'])) {
    echo "<p>Please log in first</p>";
    exit;
}

echo "<h1>Password Logic Comparison</h1>";
echo "<p>This tool shows the differences between room creation and room update password handling.</p>";

$test_password = 'test123';
$room_id = $_GET['room_id'] ?? null;

echo "<h2>1. Room Creation Logic (BROKEN)</h2>";
echo "<div style='background: #ffe6e6; padding: 10px; border-left: 5px solid #ff0000;'>";
echo "<h3>Original create_room.php approach:</h3>";
echo "<pre>";
echo "// Dynamic column checking (problematic)
\$columns_query = \$conn->query(\"SHOW COLUMNS FROM chatrooms\");
\$chatroom_columns = [];
while (\$row = \$columns_query->fetch_assoc()) {
    \$chatroom_columns[] = \$row['Field'];
}

// Conditional password handling
if (in_array('password', \$chatroom_columns)) {
    \$hashed_password = \$has_password ? password_hash(\$password, PASSWORD_DEFAULT) : null;
    \$insert_fields[] = 'password';
    \$insert_values[] = '?';
    \$param_types .= 's';
    \$param_values[] = \$hashed_password;
}

if (in_array('has_password', \$chatroom_columns)) {
    \$insert_fields[] = 'has_password';
    \$insert_values[] = '?';
    \$param_types .= 'i';
    \$param_values[] = \$has_password;
}

// Dynamic SQL building
\$insert_sql = \"INSERT INTO chatrooms (\" . implode(', ', \$insert_fields) . \") 
               VALUES (\" . implode(', ', \$insert_values) . \")\";
";
echo "</pre>";
echo "<p><strong>Problems:</strong></p>";
echo "<ul>";
echo "<li>Dynamic SQL building can cause field order issues</li>";
echo "<li>Complex parameter binding prone to errors</li>";
echo "<li>Conditional logic may skip password handling</li>";
echo "<li>No verification of saved data</li>";
echo "</ul>";
echo "</div>";

echo "<h2>2. Room Update Logic (WORKING)</h2>";
echo "<div style='background: #e6ffe6; padding: 10px; border-left: 5px solid #00aa00;'>";
echo "<h3>update_room.php approach:</h3>";
echo "<pre>";
echo "// Simple, direct approach
\$hashed_password = null;
if (!empty(\$password)) {
    \$hashed_password = password_hash(\$password, PASSWORD_DEFAULT);
}

// Direct SQL with known fields
if (empty(\$password)) {
    \$stmt = \$conn->prepare(\"UPDATE chatrooms SET name = ?, description = ?, 
                             capacity = ?, background = ?, permanent = ? WHERE id = ?\");
    \$stmt->bind_param(\"ssissi\", \$name, \$description, \$capacity, \$background, \$permanent, \$room_id);
} else {
    \$stmt = \$conn->prepare(\"UPDATE chatrooms SET name = ?, description = ?, password = ?, 
                             capacity = ?, background = ?, permanent = ? WHERE id = ?\");
    \$stmt->bind_param(\"sssisii\", \$name, \$description, \$hashed_password, \$capacity, \$background, \$permanent, \$room_id);
}
";
echo "</pre>";
echo "<p><strong>Why this works:</strong></p>";
echo "<ul>";
echo "<li>Direct SQL statements with known field order</li>";
echo "<li>Simple conditional logic</li>";
echo "<li>Clear parameter binding</li>";
echo "<li>Direct field access, no dynamic building</li>";
echo "</ul>";
echo "</div>";

echo "<h2>3. Fixed Room Creation Logic</h2>";
echo "<div style='background: #e6f3ff; padding: 10px; border-left: 5px solid #0066cc;'>";
echo "<h3>New create_room.php approach:</h3>";
echo "<pre>";
echo "// Simple validation and hashing
if (\$has_password && !empty(\$password)) {
    \$hashed_password = password_hash(\$password, PASSWORD_DEFAULT);
} else {
    \$hashed_password = null;
}

// Direct SQL statement with all fields
\$stmt = \$conn->prepare(\"
    INSERT INTO chatrooms (
        name, description, capacity, background, 
        password, has_password, allow_knocking, 
        host_user_id, host_user_id_string, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
\");

\$stmt->bind_param(
    \"ssissiiis\", 
    \$name, \$description, \$capacity, \$background, 
    \$hashed_password, \$has_password, \$allow_knocking, 
    \$host_user_id, \$user_id_string
);

// Verification step
\$verify_stmt = \$conn->prepare(\"SELECT password, has_password FROM chatrooms WHERE id = ?\");
// ... check if password was saved correctly
";
echo "</pre>";
echo "<p><strong>Improvements:</strong></p>";
echo "<ul>";
echo "<li>Fixed SQL structure, no dynamic building</li>";
echo "<li>Clear parameter order and types</li>";
echo "<li>Verification step to ensure data was saved</li>";
echo "<li>Fallback update if initial save fails</li>";
echo "<li>Enhanced logging for debugging</li>";
echo "</ul>";
echo "</div>";

if ($room_id) {
    echo "<h2>4. Test Current Room</h2>";
    $stmt = $conn->prepare("SELECT id, name, password, has_password, created_at FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if ($room) {
        echo "<h3>Room Data:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
        echo "<tr><td>ID</td><td>{$room['id']}</td><td>✅</td></tr>";
        echo "<tr><td>Name</td><td>{$room['name']}</td><td>✅</td></tr>";
        echo "<tr><td>has_password</td><td>{$room['has_password']}</td><td>" . ($room['has_password'] ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>password</td><td>" . (!empty($room['password']) ? 'SET (' . strlen($room['password']) . ' chars)' : 'EMPTY') . "</td><td>" . (!empty($room['password']) ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>created_at</td><td>{$room['created_at']}</td><td>✅</td></tr>";
        echo "</table>";
        
        if (!empty($room['password'])) {
            echo "<h3>Password Test:</h3>";
            $test_result = password_verify($test_password, $room['password']);
            echo "<p>Testing password '$test_password': " . ($test_result ? '✅ VALID' : '❌ INVALID') . "</p>";
        }
    } else {
        echo "<p>Room not found</p>";
    }
}

echo "<h2>5. Quick Actions</h2>";
echo "<p>";
echo "<a href='test_room_creation.php'>Test Room Creation Process</a> | ";
echo "<a href='debug_knock_system.php'>Debug Knock System</a> | ";
echo "<a href='test_password_system.php'>Test Password System</a>";
echo "</p>";

if (!$room_id) {
    echo "<p>Add ?room_id=X to URL to test a specific room</p>";
}
?>
<?php
// Create this as: test_password.php
include 'db_connect.php';

$room_id = $_GET['room_id'] ?? 113;
$test_password = $_GET['password'] ?? '';

if (empty($test_password)) {
    echo "Usage: test_password.php?room_id=113&password=yourpassword";
    exit;
}

$stmt = $conn->prepare("SELECT password FROM chatrooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    echo "Room not found";
    exit;
}

$stored_hash = $room['password'];

echo "<h3>Password Test for Room $room_id</h3>";
echo "<p><strong>Testing password:</strong> '$test_password'</p>";
echo "<p><strong>Stored hash:</strong> $stored_hash</p>";
echo "<p><strong>Hash info:</strong> " . print_r(password_get_info($stored_hash), true) . "</p>";

$is_valid = password_verify($test_password, $stored_hash);
echo "<p><strong>Password verification result:</strong> " . ($is_valid ? "✅ VALID" : "❌ INVALID") . "</p>";

// Test some common variations
$variations = [
    $test_password,
    trim($test_password),
    rtrim($test_password),
    ltrim($test_password),
    strtolower($test_password),
    strtoupper($test_password)
];

echo "<h4>Testing variations:</h4>";
foreach ($variations as $i => $variation) {
    $test_result = password_verify($variation, $stored_hash);
    echo "<p>Variation $i ('$variation'): " . ($test_result ? "✅ VALID" : "❌ INVALID") . "</p>";
}
?>
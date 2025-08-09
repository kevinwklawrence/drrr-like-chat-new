<?php
// Create this as: debug_room_keys.php
session_start();
include 'db_connect.php';

$room_id = $_GET['room_id'] ?? 113;
$user_id_string = $_GET['user'] ?? $_SESSION['user']['user_id'] ?? '';

echo "<h3>Room Keys Debug for Room $room_id</h3>";

try {
    // Get room info
    $stmt = $conn->prepare("SELECT name, room_keys, has_password FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        echo "<p>Room not found</p>";
        exit;
    }
    
    echo "<p><strong>Room Name:</strong> {$room['name']}</p>";
    echo "<p><strong>Has Password:</strong> " . ($room['has_password'] ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . " (" . time() . ")</p>";
    
    if (!empty($user_id_string)) {
        echo "<p><strong>Checking for User:</strong> $user_id_string</p>";
    }
    
    if (empty($room['room_keys'])) {
        echo "<p><strong>Room Keys:</strong> No keys found</p>";
    } else {
        $room_keys = json_decode($room['room_keys'], true);
        echo "<p><strong>Room Keys:</strong></p>";
        echo "<pre>" . print_r($room_keys, true) . "</pre>";
        
        if (!empty($user_id_string) && isset($room_keys[$user_id_string])) {
            $user_key = $room_keys[$user_id_string];
            $expires_at = $user_key['expires_at'];
            $is_valid = $expires_at > time();
            
            echo "<h4>Key for User $user_id_string:</h4>";
            echo "<p><strong>Granted At:</strong> " . date('Y-m-d H:i:s', $user_key['granted_at']) . "</p>";
            echo "<p><strong>Expires At:</strong> " . date('Y-m-d H:i:s', $expires_at) . "</p>";
            echo "<p><strong>Status:</strong> " . ($is_valid ? '<span style="color: green;">✅ VALID</span>' : '<span style="color: red;">❌ EXPIRED</span>') . "</p>";
            echo "<p><strong>Granted By:</strong> {$user_key['granted_by']}</p>";
            
            if ($is_valid) {
                $time_left = $expires_at - time();
                $hours_left = floor($time_left / 3600);
                $minutes_left = floor(($time_left % 3600) / 60);
                echo "<p><strong>Time Remaining:</strong> {$hours_left}h {$minutes_left}m</p>";
            }
        } else if (!empty($user_id_string)) {
            echo "<p><strong>No key found for user:</strong> $user_id_string</p>";
        }
    }
    
    // Show recent knocks for this room
    echo "<h4>Recent Knocks for this Room:</h4>";
    $stmt = $conn->prepare("SELECT * FROM room_knocks WHERE room_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>User</th><th>Status</th><th>Created</th><th>Responded</th></tr>";
        while ($knock = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$knock['id']}</td>";
            echo "<td>{$knock['user_id_string']}</td>";
            echo "<td>{$knock['status']}</td>";
            echo "<td>{$knock['created_at']}</td>";
            echo "<td>" . ($knock['responded_at'] ?: 'Not yet') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No knocks found</p>";
    }
    $stmt->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Usage:</strong></p>";
echo "<ul>";
echo "<li><a href='?room_id=$room_id'>Refresh this room</a></li>";
echo "<li><a href='?room_id=$room_id&user=465#708661'>Check for user 465#708661</a></li>";
echo "<li><a href='?room_id=$room_id&user=" . ($_SESSION['user']['user_id'] ?? '') . "'>Check for current user</a></li>";
echo "</ul>";
?>
<?php
// Check which version of join_room.php is being used
echo "<h1>join_room.php Version Check</h1>";

$join_room_path = 'api/join_room.php';
$backup_path = 'api/join_room_backup.php';

if (file_exists($join_room_path)) {
    echo "<p>✅ join_room.php file exists</p>";
    
    $content = file_get_contents($join_room_path);
    $file_size = strlen($content);
    
    echo "<p><strong>File size:</strong> $file_size bytes</p>";
    
    // Check for specific markers
    $checks = [
        'JOIN_ROOM_FIXED' => 'Fixed version marker',
        'room_keys' => 'Room keys logic',
        'password_verify' => 'Password verification',
        'user_id_string' => 'User ID string handling',
        'room_keys_column_exists' => 'Column existence check',
        'expires_at' => 'Key expiration check',
        'json_decode' => 'JSON handling'
    ];
    
    echo "<h2>Feature Check</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Feature</th><th>Status</th><th>Description</th></tr>";
    
    foreach ($checks as $marker => $description) {
        $found = strpos($content, $marker) !== false;
        $status = $found ? '✅ Present' : '❌ Missing';
        $color = $found ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td><code>$marker</code></td>";
        echo "<td style='color: $color;'>$status</td>";
        echo "<td>$description</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Determine version
    if (strpos($content, 'JOIN_ROOM_FIXED') !== false) {
        echo "<h2 style='color: green;'>✅ You have the FIXED version</h2>";
        echo "<p>Your join_room.php should support room keys properly.</p>";
        
        if (strpos($content, 'room_keys') === false) {
            echo "<p style='color: orange;'>⚠ Warning: Fixed version marker found but room_keys logic missing.</p>";
        }
        
    } else if (strpos($content, 'room_keys') !== false) {
        echo "<h2 style='color: orange;'>⚠ You have a PARTIAL fix</h2>";
        echo "<p>Room keys logic is present but this may not be the complete fixed version.</p>";
        
    } else {
        echo "<h2 style='color: red;'>❌ You have the ORIGINAL version</h2>";
        echo "<p style='color: red;'><strong>This is why room keys aren't working!</strong></p>";
        echo "<p>The original version doesn't include room key checking logic.</p>";
        
        echo "<h3>Fix Required</h3>";
        echo "<p>You need to replace your join_room.php with the fixed version that includes room key support.</p>";
    }
    
    // Show first few lines to identify version
    echo "<h2>File Preview (First 10 Lines)</h2>";
    $lines = explode("\n", $content);
    echo "<pre>";
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]) . "\n";
    }
    echo "</pre>";
    
    // Check for room key logic specifically
    echo "<h2>Room Key Logic Check</h2>";
    $room_key_patterns = [
        'room_keys_column_exists' => 'Checks if room_keys column exists',
        'json_decode.*room_keys' => 'Decodes room_keys JSON',
        'expires_at.*time()' => 'Checks key expiration',
        'used_room_key' => 'Tracks if room key was used'
    ];
    
    foreach ($room_key_patterns as $pattern => $description) {
        $found = preg_match('/' . $pattern . '/i', $content);
        $status = $found ? '✅ Found' : '❌ Missing';
        $color = $found ? 'green' : 'red';
        
        echo "<p style='color: $color;'>$status <strong>$description</strong></p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ join_room.php file not found!</p>";
}

echo "<hr>";
echo "<h2>Next Steps</h2>";

if (file_exists($join_room_path)) {
    $content = file_get_contents($join_room_path);
    
    if (strpos($content, 'JOIN_ROOM_FIXED') === false) {
        echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff0000;'>";
        echo "<h3>🚨 Action Required</h3>";
        echo "<p><strong>Your join_room.php doesn't have room key support!</strong></p>";
        echo "<p>Steps to fix:</p>";
        echo "<ol>";
        echo "<li>Backup your current join_room.php</li>";
        echo "<li>Replace it with the fixed version from the artifacts I provided</li>";
        echo "<li>Test room key functionality</li>";
        echo "</ol>";
        echo "</div>";
        
        // Offer to create backup
        if (!file_exists($backup_path)) {
            echo "<p><a href='?action=backup'>Click here to backup current version</a></p>";
        } else {
            echo "<p>✅ Backup already exists at $backup_path</p>";
        }
    } else {
        echo "<div style='background: #e6ffe6; padding: 15px; border: 1px solid #00aa00;'>";
        echo "<h3>✅ Good News</h3>";
        echo "<p>You have the fixed version of join_room.php</p>";
        echo "<p>If room keys still aren't working, the issue might be:</p>";
        echo "<ul>";
        echo "<li>User ID string mismatch</li>";
        echo "<li>Expired room keys</li>";
        echo "<li>JSON encoding/decoding issues</li>";
        echo "<li>Browser caching</li>";
        echo "</ul>";
        echo "<p><a href='debug_room_key_join.php'>Use the Room Key Join Debug tool</a> to investigate further.</p>";
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>You need to upload/create the join_room.php file first.</p>";
}

// Handle backup action
if (isset($_GET['action']) && $_GET['action'] === 'backup' && file_exists($join_room_path)) {
    if (copy($join_room_path, $backup_path)) {
        echo "<p style='color: green;'>✅ Backup created successfully at $backup_path</p>";
        echo "<p>You can now safely replace join_room.php with the fixed version.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create backup</p>";
    }
}

echo "<h3>Useful Tools</h3>";
echo "<p>";
echo "<a href='debug_room_key_join.php'>Debug Room Key Join Process</a> | ";
echo "<a href='debug_knock_system.php'>General Knock System Debug</a> | ";
echo "<a href='check_table_structure.php'>Check Table Structure</a>";
echo "</p>";
?>
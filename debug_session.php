<?php
// Simple debug page to check session and host status
session_start();
include 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Session and Host Status Debug</h2>";

// Show current session
echo "<h3>Current Session:</h3>";
echo "<pre>" . htmlspecialchars(print_r($_SESSION, true)) . "</pre>";

if (isset($_SESSION['user'])) {
    // Get user_id_string
    $user_id_string = '';
    if ($_SESSION['user']['type'] === 'user') {
        $user_id_string = $_SESSION['user']['user_id'] ?? 'MISSING';
    } else {
        $user_id_string = $_SESSION['user']['user_id'] ?? 'MISSING';
    }
    
    echo "<h3>Calculated user_id_string: <strong>" . htmlspecialchars($user_id_string) . "</strong></h3>";
    
    // If in a room, check host status
    if (isset($_SESSION['room_id'])) {
        $room_id = $_SESSION['room_id'];
        echo "<h3>Current Room ID: $room_id</h3>";
        
        // Check if user is host
        $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
        if ($stmt) {
            $stmt->bind_param("is", $room_id, $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $is_host = $row['is_host'];
                echo "<h3>Host Status: " . ($is_host ? '<span style="color: green;">HOST</span>' : '<span style="color: red;">NOT HOST</span>') . "</h3>";
            } else {
                echo "<h3 style='color: red;'>User not found in room!</h3>";
            }
            $stmt->close();
        }
        
        // Show all users in current room
        echo "<h3>All Users in Current Room:</h3>";
        $stmt = $conn->prepare("
            SELECT cu.*, u.username 
            FROM chatroom_users cu 
            LEFT JOIN users u ON cu.user_id = u.id 
            WHERE cu.room_id = ?
        ");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<table border='1'>";
        echo "<tr><th>User ID String</th><th>Username</th><th>Guest Name</th><th>Is Host</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['user_id_string']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td>" . htmlspecialchars($row['guest_name']) . "</td>";
            echo "<td style='background-color: " . ($row['is_host'] ? 'lightgreen' : 'lightcoral') . "'>" . $row['is_host'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        $stmt->close();
    } else {
        echo "<h3>Not currently in a room</h3>";
    }
    
    // Add test button to manually trigger leave room check
    if (isset($_SESSION['room_id'])) {
        echo "<h3>Test Leave Room (Host Check):</h3>";
        echo "<button onclick='testLeaveRoom()'>Test Leave Room Check</button>";
        echo "<div id='testResult'></div>";
        
        echo "<script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>";
    // Add test button to manually trigger leave room check
    if (isset($_SESSION['room_id'])) {
        echo "<h3>Test Leave Room (Host Check):</h3>";
        echo "<button onclick='testLeaveRoom()'>Test Leave Room Check</button>";
        echo "<div id='testResult'></div>";
        
        echo "<h3>Quick Actions:</h3>";
        echo "<button onclick='addTestUser()'>Add Test Guest User</button> ";
        echo "<button onclick='removeAllUsers()'>Clear All Users</button>";
        echo "<div id='actionResult'></div>";
        
        echo "<script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>";
        echo "<script>
        function testLeaveRoom() {
            console.log('Testing leave room with action: check_options');
            $.ajax({
                url: 'api/leave_room.php',
                method: 'POST',
                data: { 
                    room_id: " . $_SESSION['room_id'] . ",
                    action: 'check_options'
                },
                success: function(response) {
                    console.log('Test response (raw):', response);
                    try {
                        let parsed = JSON.parse(response);
                        console.log('Test response (parsed):', parsed);
                        $('#testResult').html('<h4>Response:</h4><pre>' + JSON.stringify(parsed, null, 2) + '</pre>');
                        
                        if (parsed.status === 'host_leaving') {
                            $('#testResult').append('<p style=\"color: green;\"><strong>SUCCESS: Modal should appear!</strong></p>');
                        } else if (parsed.status === 'success') {
                            $('#testResult').append('<p style=\"color: orange;\"><strong>Normal leave - probably no other users in room</strong></p>');
                        } else {
                            $('#testResult').append('<p style=\"color: red;\"><strong>ISSUE: Unexpected status: ' + parsed.status + '</strong></p>');
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                        $('#testResult').html('<h4>Parse Error:</h4><pre style=\"color: red;\">' + e.message + '</pre><h4>Raw Response:</h4><pre>' + response + '</pre>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Test error:', error, xhr.responseText);
                    $('#testResult').html('<h4>AJAX Error:</h4><pre style=\"color: red;\">Status: ' + status + '\\nError: ' + error + '\\nResponse: ' + xhr.responseText + '</pre>');
                }
            });
        }
        
        function addTestUser() {
            // Manually add a fake guest user to test host leaving with other users
            $.ajax({
                url: 'debug_add_test_user.php',
                method: 'POST',
                data: { room_id: " . $_SESSION['room_id'] . " },
                success: function(response) {
                    $('#actionResult').html('<p>Test user added: ' + response + '</p>');
                    location.reload(); // Refresh to see updated user list
                },
                error: function(xhr, status, error) {
                    $('#actionResult').html('<p style=\"color: red;\">Error adding test user: ' + error + '</p>');
                }
            });
        }
        
        function removeAllUsers() {
            if (confirm('Remove all users except yourself?')) {
                $.ajax({
                    url: 'debug_clear_users.php',
                    method: 'POST',
                    data: { room_id: " . $_SESSION['room_id'] . " },
                    success: function(response) {
                        $('#actionResult').html('<p>Users cleared: ' + response + '</p>');
                        location.reload(); // Refresh to see updated user list
                    },
                    error: function(xhr, status, error) {
                        $('#actionResult').html('<p style=\"color: red;\">Error clearing users: ' + error + '</p>');
                    }
                });
            }
        }
        </script>";
    }}
} else {
    echo "<h3 style='color: red;'>No user session found!</h3>";
}

echo "<br><br><a href='lounge.php'>Back to Lounge</a>";
if (isset($_SESSION['room_id'])) {
    echo " | <a href='room.php'>Back to Room</a>";
}
?>
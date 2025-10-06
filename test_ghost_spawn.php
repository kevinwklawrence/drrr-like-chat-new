<?php
// test_ghost_spawn.php - Run this in browser to test ghost spawning
session_start();

// Check if user is admin or moderator
$is_authorized = false;
if (isset($_SESSION['user']) && $_SESSION['user']['type'] === 'user') {
    include 'db_connect.php';
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT is_admin, is_moderator FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $is_authorized = ($user_data['is_admin'] == 1 || $user_data['is_moderator'] == 1);
        }
        $stmt->close();
    }
}

if (!$is_authorized) {
    die("Access denied. Moderators and Admins only.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ghost Spawn Test</title>
    <style>
        body { 
            font-family: monospace; 
            padding: 20px; 
            background: #1a1a1a; 
            color: #0f0; 
        }
        .output { 
            background: #000; 
            border: 1px solid #0f0; 
            padding: 20px; 
            white-space: pre-wrap;
            margin: 20px 0;
        }
        .error { color: #f00; }
        .success { color: #0f0; }
        .info { color: #ff0; }
        .btn {
            background: #0f0;
            color: #000;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            font-family: monospace;
            font-size: 16px;
            margin: 5px;
        }
        .btn:hover { background: #0c0; }
    </style>
</head>
<body>
    <h1>👻 Ghost Spawn Testing Tool</h1>
    
    <div>
        <button class="btn" onclick="testDatabase()">1. Test Database Connection</button>
        <button class="btn" onclick="testTable()">2. Test Ghost Table</button>
        <button class="btn" onclick="testRooms()">3. Check Active Rooms</button>
        <button class="btn" onclick="spawnGhost()">4. Spawn Ghost (Debug)</button>
        <button class="btn" onclick="viewLog()">5. View Debug Log</button>
    </div>
    
    <div class="output" id="output">Click a button to start testing...</div>
    
    <script>
    function log(message, type = 'info') {
        const output = document.getElementById('output');
        const timestamp = new Date().toLocaleTimeString();
        output.innerHTML += `<span class="${type}">[${timestamp}] ${message}</span>\n`;
    }
    
    function clearOutput() {
        document.getElementById('output').innerHTML = '';
    }
    
    function testDatabase() {
        clearOutput();
        log('Testing database connection...', 'info');
        
        fetch('api/test_ghost_connection.php')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    log('✓ Database connected', 'success');
                    log(`  Connection type: ${data.connection_type}`, 'info');
                } else {
                    log('✗ Database error: ' + data.message, 'error');
                }
            })
            .catch(e => log('✗ Fetch error: ' + e, 'error'));
    }
    
    function testTable() {
        clearOutput();
        log('Checking ghost_hunt_events table...', 'info');
        
        fetch('api/test_ghost_connection.php?check=table')
            .then(r => r.json())
            .then(data => {
                if (data.table_exists) {
                    log('✓ Table exists', 'success');
                    log(`  Total events: ${data.total_events}`, 'info');
                    log(`  Active events: ${data.active_events}`, 'info');
                } else {
                    log('✗ Table does not exist!', 'error');
                    log('  Run setup_ghost_hunt.php first', 'info');
                }
            })
            .catch(e => log('✗ Error: ' + e, 'error'));
    }
    
    function testRooms() {
        clearOutput();
        log('Checking active chatrooms...', 'info');
        
        fetch('api/test_ghost_connection.php?check=rooms')
            .then(r => r.json())
            .then(data => {
                if (data.room_count > 0) {
                    log(`✓ Found ${data.room_count} active room(s)`, 'success');
                    data.rooms.forEach(room => {
                        log(`  - ${room.name} (ID: ${room.id})`, 'info');
                    });
                } else {
                    log('⚠ No active rooms found', 'error');
                    log('  Ghosts can only spawn in active rooms', 'info');
                }
            })
            .catch(e => log('✗ Error: ' + e, 'error'));
    }
    
    function spawnGhost() {
        clearOutput();
        log('Running debug spawn script...', 'info');
        log('This will spawn with 100% chance for testing', 'info');
        
        fetch('api/spawn_ghost_debug.php')
            .then(r => r.text())
            .then(data => {
                log('--- Debug Output ---', 'info');
                log(data, 'success');
                log('--- End Output ---', 'info');
            })
            .catch(e => log('✗ Error: ' + e, 'error'));
    }
    
    function viewLog() {
        clearOutput();
        log('Fetching debug log...', 'info');
        
        fetch('logs/ghost_debug.log')
            .then(r => r.text())
            .then(data => {
                if (data.trim()) {
                    log('--- Last 50 Lines ---', 'info');
                    const lines = data.split('\n').slice(-50);
                    lines.forEach(line => {
                        if (line.includes('ERROR')) {
                            log(line, 'error');
                        } else if (line.includes('SPAWNING')) {
                            log(line, 'success');
                        } else {
                            log(line, 'info');
                        }
                    });
                } else {
                    log('Log file is empty or not found', 'info');
                }
            })
            .catch(e => log('Could not read log file', 'error'));
    }
    </script>
</body>
</html>
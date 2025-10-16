<?php
// admin/sse_stress_test.php - Comprehensive SSE Diagnostic & Stress Testing Tool
session_start();

// ADMIN ONLY ACCESS
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    http_response_code(403);
    die('Access Denied: Admin Only');
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'db_status':
            include '../db_connect.php';
            $status = [
                'connected' => $conn->ping(),
                'threads' => [],
                'variables' => []
            ];
            
            // Get connection stats
            $result = $conn->query("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Max_used_connections', 'Aborted_connects')");
            while ($row = $result->fetch_assoc()) {
                $status['threads'][$row['Variable_name']] = $row['Value'];
            }
            
            // Get max connections limit
            $result = $conn->query("SHOW VARIABLES WHERE Variable_name IN ('max_connections', 'max_user_connections', 'wait_timeout')");
            while ($row = $result->fetch_assoc()) {
                $status['variables'][$row['Variable_name']] = $row['Value'];
            }
            
            // Get current processlist
            $result = $conn->query("SELECT COUNT(*) as count, State, Command FROM information_schema.PROCESSLIST GROUP BY State, Command");
            $status['processlist'] = [];
            while ($row = $result->fetch_assoc()) {
                $status['processlist'][] = $row;
            }
            
            echo json_encode($status);
            exit;
            
        case 'query_performance':
            include '../db_connect.php';
            
            $stats = [];
            
            // Test key queries with timing
            $queries = [
                'messages' => "SELECT COUNT(*) FROM messages WHERE room_id = 1",
                'events' => "SELECT COUNT(*) FROM message_events WHERE room_id = 1",
                'users' => "SELECT COUNT(*) FROM chatroom_users WHERE room_id = 1",
                'event_lookup' => "SELECT id FROM message_events WHERE room_id = 1 ORDER BY id DESC LIMIT 1"
            ];
            
            foreach ($queries as $name => $sql) {
                $start = microtime(true);
                $result = $conn->query($sql);
                $time = (microtime(true) - $start) * 1000;
                
                $stats[$name] = [
                    'time_ms' => round($time, 2),
                    'status' => $result ? 'success' : 'failed'
                ];
            }
            
            echo json_encode($stats);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSE Stress Test - Admin Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f0f0f;
            color: #e0e0e0;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #ff6b6b; margin-bottom: 10px; }
        .subtitle { color: #888; margin-bottom: 30px; }
        
        .controls {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        
        .control-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            color: #999;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        input[type="number"], input[type="text"], select {
            width: 100%;
            padding: 10px;
            background: #0f0f0f;
            border: 1px solid #333;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 14px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
            min-width: 120px;
        }
        
        .btn-start {
            background: #4CAF50;
            color: white;
        }
        .btn-start:hover { background: #45a049; }
        .btn-start:disabled { background: #2d5f2f; cursor: not-allowed; }
        
        .btn-stop {
            background: #f44336;
            color: white;
        }
        .btn-stop:hover { background: #da190b; }
        
        .btn-clear {
            background: #333;
            color: white;
        }
        .btn-clear:hover { background: #444; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #333;
        }
        
        .stat-label {
            color: #888;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .stat-value.warning { color: #ff9800; }
        .stat-value.error { color: #f44336; }
        
        .log-container {
            background: #0f0f0f;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            max-height: 500px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .log-entry {
            padding: 5px 0;
            border-bottom: 1px solid #222;
        }
        
        .log-time {
            color: #666;
            margin-right: 10px;
        }
        
        .log-success { color: #4CAF50; }
        .log-error { color: #f44336; }
        .log-warning { color: #ff9800; }
        .log-info { color: #2196F3; }
        
        .connection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 8px;
            margin: 20px 0;
        }
        
        .connection-dot {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 50%;
            border: 2px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            transition: all 0.3s;
        }
        
        .connection-dot.connecting { background: #ff9800; border-color: #ff9800; }
        .connection-dot.connected { background: #4CAF50; border-color: #4CAF50; }
        .connection-dot.error { background: #f44336; border-color: #f44336; }
        .connection-dot.closed { background: #666; border-color: #666; }
        
        h2 {
            color: #fff;
            margin: 30px 0 15px;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .button-group { flex-direction: column; }
            button { min-width: 100%; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔬 SSE Stress Test & Diagnostic</h1>
        <p class="subtitle">Simulate multiple concurrent SSE connections to identify bottlenecks</p>
        
        <div class="controls">
            <div class="control-group">
                <label>Number of Connections</label>
                <input type="number" id="connectionCount" value="12" min="1" max="50">
            </div>
            
            <div class="control-group">
                <label>Target Room ID</label>
                <input type="number" id="roomId" value="1" min="1">
            </div>
            
            <div class="control-group">
                <label>Test Duration (seconds)</label>
                <input type="number" id="duration" value="60" min="10" max="600">
            </div>
            
            <div class="control-group">
                <label>Connection Delay (ms between connections)</label>
                <input type="number" id="delay" value="100" min="0" max="5000">
            </div>
            
            <div class="button-group">
                <button class="btn-start" id="startBtn" onclick="startTest()">Start Test</button>
                <button class="btn-stop" id="stopBtn" onclick="stopTest()" disabled>Stop Test</button>
                <button class="btn-clear" onclick="clearLogs()">Clear Logs</button>
                <button class="btn-clear" onclick="exportLogs()">📥 Export Results</button>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active Connections</div>
                <div class="stat-value" id="activeConnections">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Messages Received</div>
                <div class="stat-value" id="messagesReceived">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Failed Connections</div>
                <div class="stat-value error" id="failedConnections">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Response Time</div>
                <div class="stat-value" id="avgResponseTime">0ms</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">DB Threads Connected</div>
                <div class="stat-value" id="dbThreads">-</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Test Runtime</div>
                <div class="stat-value" id="runtime">0s</div>
            </div>
        </div>
        
        <h2>Connection Status</h2>
        <div class="connection-grid" id="connectionGrid"></div>
        
        <h2>Event Log</h2>
        <div class="log-container" id="logContainer"></div>
    </div>
    
    <script>
        let connections = [];
        let testActive = false;
        let startTime = 0;
        let runtimeInterval = null;
        let dbPollInterval = null;
        let stats = {
            active: 0,
            messages: 0,
            failed: 0,
            responseTimes: []
        };
        let logs = [];
        let dbStats = [];
        
        function log(message, type = 'info') {
            const container = document.getElementById('logContainer');
            const time = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.innerHTML = `<span class="log-time">[${time}]</span>${message}`;
            container.insertBefore(entry, container.firstChild);
            
            // Store for export
            logs.push({
                timestamp: new Date().toISOString(),
                time: time,
                type: type,
                message: message
            });
        }
        
        function updateStats() {
            document.getElementById('activeConnections').textContent = stats.active;
            document.getElementById('messagesReceived').textContent = stats.messages;
            document.getElementById('failedConnections').textContent = stats.failed;
            
            const avgTime = stats.responseTimes.length > 0 
                ? Math.round(stats.responseTimes.reduce((a, b) => a + b, 0) / stats.responseTimes.length)
                : 0;
            document.getElementById('avgResponseTime').textContent = avgTime + 'ms';
            
            const avgElement = document.getElementById('avgResponseTime');
            avgElement.className = 'stat-value';
            if (avgTime > 1000) avgElement.classList.add('error');
            else if (avgTime > 500) avgElement.classList.add('warning');
        }
        
        function updateConnectionDot(index, state) {
            const dot = document.getElementById(`conn-${index}`);
            if (dot) {
                dot.className = `connection-dot ${state}`;
            }
        }
        
        async function pollDatabaseStats() {
            try {
                const response = await fetch('?action=db_status');
                const data = await response.json();
                
                // Store for export
                dbStats.push({
                    timestamp: new Date().toISOString(),
                    data: data
                });
                
                if (data.threads && data.threads.Threads_connected) {
                    document.getElementById('dbThreads').textContent = data.threads.Threads_connected;
                    
                    const maxConn = parseInt(data.variables.max_connections || 151);
                    const current = parseInt(data.threads.Threads_connected);
                    const usage = (current / maxConn) * 100;
                    
                    const element = document.getElementById('dbThreads');
                    element.className = 'stat-value';
                    if (usage > 80) {
                        element.classList.add('error');
                        log(`⚠️ HIGH DB CONNECTION USAGE: ${usage.toFixed(1)}% (${current}/${maxConn})`, 'warning');
                    } else if (usage > 60) {
                        element.classList.add('warning');
                    }
                }
            } catch (error) {
                console.error('Failed to poll DB stats:', error);
            }
        }
        
        async function startTest() {
            const count = parseInt(document.getElementById('connectionCount').value);
            const roomId = parseInt(document.getElementById('roomId').value);
            const duration = parseInt(document.getElementById('duration').value) * 1000;
            const delay = parseInt(document.getElementById('delay').value);
            
            // Reset
            connections = [];
            stats = { active: 0, messages: 0, failed: 0, responseTimes: [] };
            logs = [];
            dbStats = [];
            testActive = true;
            startTime = Date.now();
            
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            
            // Create connection grid
            const grid = document.getElementById('connectionGrid');
            grid.innerHTML = '';
            for (let i = 0; i < count; i++) {
                const dot = document.createElement('div');
                dot.id = `conn-${i}`;
                dot.className = 'connection-dot';
                dot.textContent = i + 1;
                grid.appendChild(dot);
            }
            
            log(`🚀 Starting stress test with ${count} connections to room ${roomId}`, 'success');
            log(`⏱️ Test duration: ${duration/1000}s | Connection delay: ${delay}ms`);
            
            // Poll DB stats
            dbPollInterval = setInterval(pollDatabaseStats, 2000);
            pollDatabaseStats();
            
            // Runtime counter
            runtimeInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                document.getElementById('runtime').textContent = elapsed + 's';
            }, 1000);
            
            // Create connections with delay
            for (let i = 0; i < count; i++) {
                await new Promise(resolve => setTimeout(resolve, delay));
                
                if (!testActive) break;
                
                createConnection(i, roomId);
            }
            
            // Auto-stop after duration
            setTimeout(() => {
                if (testActive) {
                    log(`⏱️ Test duration completed`, 'info');
                    stopTest();
                }
            }, duration);
        }
        
        function createConnection(index, roomId) {
            updateConnectionDot(index, 'connecting');
            
            const startTime = Date.now();
            const url = `../api/sse_room_data.php?room_id=${roomId}&test_id=${index}`;
            const eventSource = new EventSource(url);
            
            eventSource.onopen = () => {
                const responseTime = Date.now() - startTime;
                stats.active++;
                stats.responseTimes.push(responseTime);
                updateConnectionDot(index, 'connected');
                log(`✅ Connection #${index + 1} established (${responseTime}ms)`, 'success');
                updateStats();
            };
            
            eventSource.onmessage = (event) => {
                stats.messages++;
                updateStats();
                
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'connected') {
                        log(`📡 Connection #${index + 1} confirmed by server`, 'info');
                    }
                } catch (error) {
                    // Ignore parse errors for this test
                }
            };
            
            eventSource.onerror = (error) => {
                const state = eventSource.readyState;
                
                if (state === EventSource.CLOSED) {
                    stats.active--;
                    updateConnectionDot(index, 'closed');
                    log(`🔌 Connection #${index + 1} closed`, 'warning');
                } else {
                    stats.failed++;
                    updateConnectionDot(index, 'error');
                    log(`❌ Connection #${index + 1} error (ReadyState: ${state})`, 'error');
                }
                
                updateStats();
            };
            
            connections.push(eventSource);
        }
        
        function stopTest() {
            testActive = false;
            
            log(`🛑 Stopping test and closing ${connections.length} connections...`, 'info');
            
            connections.forEach((conn, index) => {
                if (conn && conn.readyState !== EventSource.CLOSED) {
                    conn.close();
                    updateConnectionDot(index, 'closed');
                }
            });
            
            stats.active = 0;
            updateStats();
            
            if (runtimeInterval) clearInterval(runtimeInterval);
            if (dbPollInterval) clearInterval(dbPollInterval);
            
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            
            log(`✅ Test completed. Total messages: ${stats.messages}, Failed: ${stats.failed}`, 'success');
        }
        
        function clearLogs() {
            document.getElementById('logContainer').innerHTML = '';
        }
        
        function exportLogs() {
            const testConfig = {
                connectionCount: parseInt(document.getElementById('connectionCount').value),
                roomId: parseInt(document.getElementById('roomId').value),
                duration: parseInt(document.getElementById('duration').value),
                delay: parseInt(document.getElementById('delay').value)
            };
            
            const exportData = {
                test_info: {
                    export_time: new Date().toISOString(),
                    test_config: testConfig,
                    test_start: startTime > 0 ? new Date(startTime).toISOString() : null,
                    test_duration_seconds: startTime > 0 ? Math.floor((Date.now() - startTime) / 1000) : 0
                },
                final_stats: {
                    active_connections: stats.active,
                    total_messages: stats.messages,
                    failed_connections: stats.failed,
                    avg_response_time_ms: stats.responseTimes.length > 0 
                        ? Math.round(stats.responseTimes.reduce((a, b) => a + b, 0) / stats.responseTimes.length)
                        : 0,
                    all_response_times: stats.responseTimes,
                    min_response_time: stats.responseTimes.length > 0 ? Math.min(...stats.responseTimes) : 0,
                    max_response_time: stats.responseTimes.length > 0 ? Math.max(...stats.responseTimes) : 0
                },
                logs: logs,
                database_stats: dbStats,
                browser_info: {
                    user_agent: navigator.userAgent,
                    platform: navigator.platform
                }
            };
            
            // Create download
            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `sse_stress_test_${new Date().toISOString().replace(/:/g, '-').split('.')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            log('✅ Results exported successfully', 'success');
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (testActive) stopTest();
        });
    </script>
</body>
</html>
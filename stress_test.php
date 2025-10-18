<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polling System Stress Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #0a0a0a;
            color: #00ff00;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #00ff00;
            text-shadow: 0 0 10px #00ff00;
        }
        
        .controls {
            background: #1a1a1a;
            border: 2px solid #00ff00;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .control-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .control-item {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #00ff00;
            font-weight: bold;
        }
        
        input[type="number"],
        input[type="text"] {
            width: 100%;
            padding: 8px;
            background: #000;
            border: 1px solid #00ff00;
            color: #00ff00;
            font-family: 'Courier New', monospace;
        }
        
        button {
            background: #00ff00;
            color: #000;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            margin-right: 10px;
            border-radius: 4px;
        }
        
        button:hover {
            background: #00cc00;
        }
        
        button:disabled {
            background: #333;
            color: #666;
            cursor: not-allowed;
        }
        
        button.stop {
            background: #ff0000;
            color: #fff;
        }
        
        button.stop:hover {
            background: #cc0000;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #1a1a1a;
            border: 2px solid #00ff00;
            padding: 15px;
            border-radius: 8px;
        }
        
        .stat-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #00ff00;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #00ff00;
        }
        
        .stat-label {
            font-size: 12px;
            color: #00aa00;
            margin-top: 5px;
        }
        
        .chart-container {
            background: #1a1a1a;
            border: 2px solid #00ff00;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            height: 300px;
            position: relative;
        }
        
        .chart {
            width: 100%;
            height: 100%;
        }
        
        .log {
            background: #000;
            border: 2px solid #00ff00;
            padding: 15px;
            height: 400px;
            overflow-y: auto;
            font-size: 12px;
            border-radius: 8px;
        }
        
        .log-entry {
            margin-bottom: 5px;
            padding: 2px 0;
        }
        
        .log-entry.success {
            color: #00ff00;
        }
        
        .log-entry.error {
            color: #ff0000;
        }
        
        .log-entry.warning {
            color: #ffaa00;
        }
        
        .log-entry.info {
            color: #00aaff;
        }
        
        .status {
            text-align: center;
            font-size: 18px;
            margin: 20px 0;
            padding: 10px;
            background: #1a1a1a;
            border: 2px solid #00ff00;
            border-radius: 8px;
        }
        
        .status.running {
            border-color: #00ff00;
            color: #00ff00;
        }
        
        .status.stopped {
            border-color: #ff0000;
            color: #ff0000;
        }
        
        canvas {
            width: 100% !important;
            height: 100% !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 POLLING SYSTEM STRESS TEST 🚀</h1>
        
        <div class="controls">
            <div class="control-group">
                <div class="control-item">
                    <label>API Endpoint:</label>
                    <input type="text" id="apiEndpoint" value="/api/poll_room_data.php" placeholder="/api/poll_room_data.php">
                </div>
                <div class="control-item">
                    <label>Simulated Users:</label>
                    <input type="number" id="userCount" value="10" min="1" max="100">
                </div>
                <div class="control-item">
                    <label>Poll Interval (ms):</label>
                    <input type="number" id="pollInterval" value="500" min="100" max="5000" step="100">
                </div>
                <div class="control-item">
                    <label>Message Frequency (per user/min):</label>
                    <input type="number" id="messageFreq" value="2" min="0" max="20">
                </div>
            </div>
            <div class="control-group">
                <button id="startBtn" onclick="startTest()">▶ START TEST</button>
                <button id="stopBtn" onclick="stopTest()" class="stop" disabled>⏹ STOP TEST</button>
                <button onclick="clearLog()">🗑 CLEAR LOG</button>
                <button onclick="exportResults()">💾 EXPORT RESULTS</button>
            </div>
        </div>
        
        <div class="status" id="status">
            <span>⏸ Ready to start</span>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3>TOTAL REQUESTS</h3>
                <div class="stat-value" id="totalRequests">0</div>
                <div class="stat-label">Polling attempts</div>
            </div>
            <div class="stat-card">
                <h3>SUCCESSFUL</h3>
                <div class="stat-value" id="successCount">0</div>
                <div class="stat-label">Completed requests</div>
            </div>
            <div class="stat-card">
                <h3>FAILED</h3>
                <div class="stat-value" id="errorCount">0</div>
                <div class="stat-label">Error responses</div>
            </div>
            <div class="stat-card">
                <h3>AVG RESPONSE TIME</h3>
                <div class="stat-value" id="avgResponseTime">0</div>
                <div class="stat-label">milliseconds</div>
            </div>
            <div class="stat-card">
                <h3>REQUESTS/SEC</h3>
                <div class="stat-value" id="requestsPerSec">0</div>
                <div class="stat-label">Current rate</div>
            </div>
            <div class="stat-card">
                <h3>ACTIVE USERS</h3>
                <div class="stat-value" id="activeUsers">0</div>
                <div class="stat-label">Polling now</div>
            </div>
        </div>
        
        <div class="chart-container">
            <h3 style="margin-bottom: 10px;">Response Time Chart (Last 50 requests)</h3>
            <canvas id="responseChart"></canvas>
        </div>
        
        <div class="log" id="log">
            <div class="log-entry info">📋 Stress test ready. Configure settings and click START TEST.</div>
        </div>
    </div>

    <script>
        let testRunning = false;
        let users = [];
        let stats = {
            totalRequests: 0,
            successCount: 0,
            errorCount: 0,
            responseTimes: [],
            startTime: null,
            lastSecondRequests: 0,
            lastSecondTime: Date.now()
        };
        
        let chartData = {
            labels: [],
            values: []
        };
        
        let chart = null;
        
        class SimulatedUser {
            constructor(id, pollInterval, messageFreq) {
                this.id = id;
                this.pollInterval = pollInterval;
                this.messageFreq = messageFreq;
                this.lastEventId = 0;
                this.pollTimer = null;
                this.messageTimer = null;
                this.active = false;
            }
            
            start() {
                this.active = true;
                this.startPolling();
                this.startMessaging();
            }
            
            stop() {
                this.active = false;
                if (this.pollTimer) clearInterval(this.pollTimer);
                if (this.messageTimer) clearInterval(this.messageTimer);
            }
            
            startPolling() {
                // Initial poll
                this.poll();
                
                // Set up interval
                this.pollTimer = setInterval(() => {
                    if (this.active) {
                        this.poll();
                    }
                }, this.pollInterval);
            }
            
            async poll() {
                const endpoint = document.getElementById('apiEndpoint').value;
                const startTime = performance.now();
                
                // Increment active users
                stats.totalRequests++;
                updateActiveUsers(1);
                
                try {
                    const response = await fetch(`${endpoint}?last_event_id=${this.lastEventId}&message_limit=50`, {
                        method: 'GET',
                        credentials: 'include'
                    });
                    
                    const endTime = performance.now();
                    const responseTime = Math.round(endTime - startTime);
                    
                    if (response.ok) {
                        const data = await response.json();
                        stats.successCount++;
                        stats.responseTimes.push(responseTime);
                        
                        if (data.last_event_id) {
                            this.lastEventId = data.last_event_id;
                        }
                        
                        logEntry(`User ${this.id}: Poll success (${responseTime}ms) - ${data.status}`, 'success');
                        
                        // Update chart
                        updateChart(responseTime);
                    } else {
                        stats.errorCount++;
                        logEntry(`User ${this.id}: Poll failed - HTTP ${response.status}`, 'error');
                    }
                    
                    updateStats();
                } catch (error) {
                    stats.errorCount++;
                    logEntry(`User ${this.id}: Poll error - ${error.message}`, 'error');
                    updateStats();
                } finally {
                    // Always decrement active users, even on error
                    updateActiveUsers(-1);
                }
            }
            
            startMessaging() {
                if (this.messageFreq === 0) return;
                
                const intervalMs = (60 * 1000) / this.messageFreq; // Convert per minute to ms
                
                this.messageTimer = setInterval(() => {
                    if (this.active) {
                        this.sendMessage();
                    }
                }, intervalMs);
            }
            
            async sendMessage() {
                try {
                    const messages = [
                        "Test message from stress test",
                        "Hello from user " + this.id,
                        "Checking system performance",
                        "This is a simulated message",
                        "Testing polling system"
                    ];
                    
                    const randomMessage = messages[Math.floor(Math.random() * messages.length)];
                    
                    // Note: This won't actually send without proper session
                    // but it simulates the load
                    logEntry(`User ${this.id}: Would send message "${randomMessage}"`, 'info');
                } catch (error) {
                    logEntry(`User ${this.id}: Message send error - ${error.message}`, 'warning');
                }
            }
        }
        
        function startTest() {
            if (testRunning) return;
            
            const userCount = parseInt(document.getElementById('userCount').value);
            const pollInterval = parseInt(document.getElementById('pollInterval').value);
            const messageFreq = parseInt(document.getElementById('messageFreq').value);
            
            testRunning = true;
            stats = {
                totalRequests: 0,
                successCount: 0,
                errorCount: 0,
                responseTimes: [],
                startTime: Date.now(),
                lastSecondRequests: 0,
                lastSecondTime: Date.now()
            };
            
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('status').className = 'status running';
            document.getElementById('status').innerHTML = '<span>🟢 TEST RUNNING</span>';
            
            logEntry(`🚀 Starting stress test with ${userCount} users`, 'success');
            logEntry(`⚙️ Poll interval: ${pollInterval}ms | Messages: ${messageFreq}/min per user`, 'info');
            
            // Create and start simulated users
            users = [];
            for (let i = 1; i <= userCount; i++) {
                const user = new SimulatedUser(i, pollInterval, messageFreq);
                users.push(user);
                
                // Stagger user starts to avoid spike
                setTimeout(() => {
                    user.start();
                    logEntry(`User ${i} started polling`, 'info');
                }, i * 50);
            }
            
            // Start stats updater
            setInterval(() => {
                if (testRunning) {
                    updateRequestsPerSecond();
                }
            }, 1000);
            
            initChart();
        }
        
        function stopTest() {
            if (!testRunning) return;
            
            testRunning = false;
            
            users.forEach(user => user.stop());
            users = [];
            
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('status').className = 'status stopped';
            document.getElementById('status').innerHTML = '<span>🔴 TEST STOPPED</span>';
            
            const duration = ((Date.now() - stats.startTime) / 1000).toFixed(1);
            logEntry(`⏹ Test stopped after ${duration} seconds`, 'warning');
            logEntry(`📊 Final stats: ${stats.totalRequests} requests, ${stats.successCount} success, ${stats.errorCount} errors`, 'info');
            
            document.getElementById('activeUsers').textContent = '0';
        }
        
        let activeUserCount = 0;
        function updateActiveUsers(change) {
            activeUserCount += change;
            document.getElementById('activeUsers').textContent = activeUserCount;
        }
        
        function updateStats() {
            document.getElementById('totalRequests').textContent = stats.totalRequests;
            document.getElementById('successCount').textContent = stats.successCount;
            document.getElementById('errorCount').textContent = stats.errorCount;
            
            if (stats.responseTimes.length > 0) {
                const avg = stats.responseTimes.reduce((a, b) => a + b, 0) / stats.responseTimes.length;
                document.getElementById('avgResponseTime').textContent = Math.round(avg) + 'ms';
            }
        }
        
        function updateRequestsPerSecond() {
            const now = Date.now();
            const elapsed = (now - stats.lastSecondTime) / 1000;
            const newRequests = stats.totalRequests - stats.lastSecondRequests;
            const rps = (newRequests / elapsed).toFixed(1);
            
            document.getElementById('requestsPerSec').textContent = rps;
            
            stats.lastSecondRequests = stats.totalRequests;
            stats.lastSecondTime = now;
        }
        
        function logEntry(message, type = 'info') {
            const log = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.textContent = `[${timestamp}] ${message}`;
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
            
            // Limit log entries
            while (log.children.length > 200) {
                log.removeChild(log.firstChild);
            }
        }
        
        function clearLog() {
            document.getElementById('log').innerHTML = '<div class="log-entry info">📋 Log cleared.</div>';
        }
        
        function initChart() {
            const canvas = document.getElementById('responseChart');
            const ctx = canvas.getContext('2d');
            
            chartData = {
                labels: [],
                values: []
            };
            
            drawChart(ctx);
        }
        
        function updateChart(responseTime) {
            chartData.values.push(responseTime);
            chartData.labels.push(chartData.values.length);
            
            // Keep only last 50
            if (chartData.values.length > 50) {
                chartData.values.shift();
                chartData.labels.shift();
            }
            
            const canvas = document.getElementById('responseChart');
            const ctx = canvas.getContext('2d');
            drawChart(ctx);
        }
        
        function drawChart(ctx) {
            const canvas = ctx.canvas;
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            if (chartData.values.length === 0) return;
            
            const maxValue = Math.max(...chartData.values, 100);
            const padding = 40;
            const chartWidth = canvas.width - padding * 2;
            const chartHeight = canvas.height - padding * 2;
            
            // Draw grid
            ctx.strokeStyle = '#1a1a1a';
            ctx.lineWidth = 1;
            for (let i = 0; i <= 5; i++) {
                const y = padding + (chartHeight / 5) * i;
                ctx.beginPath();
                ctx.moveTo(padding, y);
                ctx.lineTo(canvas.width - padding, y);
                ctx.stroke();
            }
            
            // Draw labels
            ctx.fillStyle = '#00aa00';
            ctx.font = '10px Courier New';
            ctx.textAlign = 'right';
            for (let i = 0; i <= 5; i++) {
                const value = Math.round(maxValue - (maxValue / 5) * i);
                const y = padding + (chartHeight / 5) * i;
                ctx.fillText(value + 'ms', padding - 5, y + 3);
            }
            
            // Draw line
            ctx.strokeStyle = '#00ff00';
            ctx.lineWidth = 2;
            ctx.beginPath();
            
            chartData.values.forEach((value, index) => {
                const x = padding + (chartWidth / 49) * index;
                const y = padding + chartHeight - (value / maxValue) * chartHeight;
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            
            ctx.stroke();
            
            // Draw points
            ctx.fillStyle = '#00ff00';
            chartData.values.forEach((value, index) => {
                const x = padding + (chartWidth / 49) * index;
                const y = padding + chartHeight - (value / maxValue) * chartHeight;
                ctx.beginPath();
                ctx.arc(x, y, 3, 0, Math.PI * 2);
                ctx.fill();
            });
        }
        
        function exportResults() {
            const results = {
                timestamp: new Date().toISOString(),
                config: {
                    users: document.getElementById('userCount').value,
                    pollInterval: document.getElementById('pollInterval').value,
                    messageFreq: document.getElementById('messageFreq').value
                },
                stats: {
                    totalRequests: stats.totalRequests,
                    successCount: stats.successCount,
                    errorCount: stats.errorCount,
                    avgResponseTime: stats.responseTimes.length > 0 
                        ? Math.round(stats.responseTimes.reduce((a, b) => a + b, 0) / stats.responseTimes.length)
                        : 0,
                    duration: stats.startTime ? ((Date.now() - stats.startTime) / 1000).toFixed(1) + 's' : '0s'
                }
            };
            
            const blob = new Blob([JSON.stringify(results, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `stress-test-${Date.now()}.json`;
            a.click();
            
            logEntry('📊 Results exported', 'success');
        }
        
        // Auto-resize chart
        window.addEventListener('resize', () => {
            if (testRunning) {
                const canvas = document.getElementById('responseChart');
                const ctx = canvas.getContext('2d');
                drawChart(ctx);
            }
        });
    </script>
</body>
</html>
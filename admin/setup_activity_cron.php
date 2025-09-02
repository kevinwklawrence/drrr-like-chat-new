<?php
// admin/setup_activity_cron.php - Web-based cron setup for the activity system
session_start();

// Security check - only allow admins
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die('Access denied. Admin privileges required.');
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity System Cron Setup</title>
    <style>
        body { 
            font-family: 'Courier New', monospace; 
            background: #1e1e1e; 
            color: #e0e0e0; 
            margin: 20px;
            line-height: 1.6;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: #2a2a2a; 
            padding: 20px; 
            border-radius: 8px;
        }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        .info { color: #2196F3; }
        .code-block { 
            background: #333; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 4px; 
            border-left: 4px solid #2196F3;
            overflow-x: auto;
        }
        .step { 
            background: #333; 
            padding: 15px; 
            margin: 15px 0; 
            border-radius: 4px;
            border-left: 4px solid #4CAF50;
        }
        .button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .button:hover { background: #45a049; }
        .button.test { background: #2196F3; }
        .button.test:hover { background: #1976D2; }
        .output {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Activity System Cron Setup</h1>
        
        <?php
        $projectRoot = dirname(__DIR__);
        $cronDir = $projectRoot . '/cron';
        $logDir = $projectRoot . '/logs';
        $disconnectScript = $projectRoot . '/api/check_disconnects.php';
        
        // Check prerequisites
        echo "<h2>📋 System Check</h2>";
        
        $checks = [];
        
        // Check if disconnect script exists
        if (file_exists($disconnectScript)) {
            echo "<div class='success'>✅ Disconnect script found: $disconnectScript</div>";
            $checks['script'] = true;
        } else {
            echo "<div class='error'>❌ Disconnect script not found: $disconnectScript</div>";
            $checks['script'] = false;
        }
        
        // Check PHP path
        $phpPath = trim(shell_exec('which php 2>/dev/null') ?: 'php');
        if ($phpPath) {
            echo "<div class='success'>✅ PHP found: $phpPath</div>";
            $checks['php'] = true;
        } else {
            echo "<div class='warning'>⚠️ PHP path not found via 'which', using 'php'</div>";
            $phpPath = 'php';
            $checks['php'] = false;
        }
        
        // Test the disconnect script
        echo "<h3>🧪 Testing Disconnect Script</h3>";
        if (isset($_POST['test_script'])) {
            echo "<div class='output'>";
            $output = [];
            $return_var = 0;
            exec("cd $projectRoot && $phpPath api/check_disconnects.php 2>&1", $output, $return_var);
            
            if ($return_var === 0) {
                echo "<span class='success'>✅ Script executed successfully!</span>\n\n";
            } else {
                echo "<span class='error'>❌ Script failed with exit code: $return_var</span>\n\n";
            }
            
            echo implode("\n", $output);
            echo "</div>";
        } else {
            echo "<form method='post'><button type='submit' name='test_script' class='button test'>🧪 Test Disconnect Script</button></form>";
        }
        
        // Create directories
        echo "<h2>📁 Directory Setup</h2>";
        
        if (!is_dir($cronDir)) {
            if (mkdir($cronDir, 0755, true)) {
                echo "<div class='success'>✅ Created cron directory: $cronDir</div>";
            } else {
                echo "<div class='error'>❌ Failed to create cron directory: $cronDir</div>";
            }
        } else {
            echo "<div class='success'>✅ Cron directory exists: $cronDir</div>";
        }
        
        if (!is_dir($logDir)) {
            if (mkdir($logDir, 0755, true)) {
                echo "<div class='success'>✅ Created logs directory: $logDir</div>";
            } else {
                echo "<div class='error'>❌ Failed to create logs directory: $logDir</div>";
            }
        } else {
            echo "<div class='success'>✅ Logs directory exists: $logDir</div>";
        }
        
        // Create wrapper script
        $wrapperScript = $cronDir . '/activity_cron.sh';
        $wrapperContent = "#!/bin/bash
# activity_cron.sh - Wrapper script for activity system cron jobs

# Set working directory to project root
cd \"$projectRoot\"

# Set environment variables if needed
export PATH=/usr/local/bin:/usr/bin:/bin

# Log file for cron output
LOG_FILE=\"$logDir/activity_cron.log\"

# Function to log with timestamp
log_message() {
    echo \"\$(date '+%Y-%m-%d %H:%M:%S') - \$1\" >> \"\$LOG_FILE\"
}

# Run the disconnect checker
log_message \"Starting activity system check\"

# Run the PHP script and capture output
OUTPUT=\$($phpPath \"$disconnectScript\" 2>&1)
EXIT_CODE=\$?

# Log the output
if [ \$EXIT_CODE -eq 0 ]; then
    log_message \"Activity check completed successfully\"
    echo \"\$OUTPUT\" >> \"\$LOG_FILE\"
else
    log_message \"Activity check failed with exit code \$EXIT_CODE\"
    log_message \"Error output: \$OUTPUT\"
fi

# Rotate log file if it gets too big (keep last 1000 lines)
if [ -f \"\$LOG_FILE\" ] && [ \$(wc -l < \"\$LOG_FILE\") -gt 1000 ]; then
    tail -n 500 \"\$LOG_FILE\" > \"\${LOG_FILE}.tmp\"
    mv \"\${LOG_FILE}.tmp\" \"\$LOG_FILE\"
    log_message \"Log file rotated\"
fi";
        
        if (file_put_contents($wrapperScript, $wrapperContent)) {
            chmod($wrapperScript, 0755);
            echo "<div class='success'>✅ Created wrapper script: $wrapperScript</div>";
        } else {
            echo "<div class='error'>❌ Failed to create wrapper script</div>";
        }
        
        // Show cron setup instructions
        echo "<h2>⏰ Cron Job Setup</h2>";
        
        $cronEntry = "*/2 * * * * $wrapperScript >/dev/null 2>&1";
        
        echo "<div class='step'>";
        echo "<h3>📝 Manual Cron Setup Instructions</h3>";
        echo "<p>Since this is a web interface, you'll need to set up the cron job manually. Here are several methods:</p>";
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h4>Method 1: cPanel/Control Panel</h4>";
        echo "<p>If you're using shared hosting with cPanel:</p>";
        echo "<ol>";
        echo "<li>Go to your hosting control panel</li>";
        echo "<li>Find 'Cron Jobs' or 'Scheduled Tasks'</li>";
        echo "<li>Add a new cron job with these settings:</li>";
        echo "</ol>";
        echo "<div class='code-block'>";
        echo "Interval: Every 2 minutes (*/2 * * * *)\n";
        echo "Command: $wrapperScript\n";
        echo "Or: $phpPath $disconnectScript";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h4>Method 2: Command Line (SSH Access)</h4>";
        echo "<p>If you have SSH access to your server:</p>";
        echo "<div class='code-block'>";
        echo "# Edit crontab\n";
        echo "crontab -e\n\n";
        echo "# Add this line:\n";
        echo "$cronEntry";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h4>Method 3: Web-based Cron Service</h4>";
        echo "<p>If you can't set up server cron jobs, use a web cron service like:</p>";
        echo "<ul>";
        echo "<li><strong>cron-job.org</strong> (free)</li>";
        echo "<li><strong>webcron.org</strong></li>";
        echo "<li><strong>easycron.com</strong></li>";
        echo "</ul>";
        echo "<p>Set them to call this URL every 2 minutes:</p>";
        echo "<div class='code-block'>";
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
        echo "$baseUrl/web_cron.php";
        echo "</div>";
        echo "</div>";
        
        // Create web cron endpoint
        $webCronFile = dirname(__DIR__) . '/web_cron.php';
        $webCronContent = "<?php
// web_cron.php - Web endpoint for external cron services
// This allows external cron services to trigger the activity system

header('Content-Type: application/json');

// Optional: Add basic security
\$allowedIPs = [
    // Add your cron service IPs here if needed
    // '1.2.3.4',
];

\$clientIP = \$_SERVER['REMOTE_ADDR'] ?? '';

// Simple security check - you might want to add a secret key
\$secret = \$_GET['secret'] ?? '';
\$expectedSecret = 'your-secret-key-here'; // Change this!

if (\$secret !== \$expectedSecret && !empty(\$allowedIPs) && !in_array(\$clientIP, \$allowedIPs)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Include and run the disconnect checker
ob_start();
include __DIR__ . '/api/check_disconnects.php';
\$output = ob_get_clean();

// Try to parse JSON response
\$result = json_decode(\$output, true);

if (\$result) {
    // Return the result as JSON
    echo \$output;
} else {
    // Return error if not valid JSON
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid response from disconnect checker',
        'output' => \$output
    ]);
}
?>";
        
        if (file_put_contents($webCronFile, $webCronContent)) {
            echo "<div class='success'>✅ Created web cron endpoint: $webCronFile</div>";
            echo "<div class='info'>📝 Don't forget to change the secret key in web_cron.php for security!</div>";
        }
        
        // Show monitoring information
        echo "<h2>📊 Monitoring</h2>";
        
        echo "<div class='step'>";
        echo "<h4>Log File Monitoring</h4>";
        echo "<p>Monitor the activity system with:</p>";
        echo "<div class='code-block'>";
        echo "Log file: $logDir/activity_cron.log\n";
        echo "Command: tail -f $logDir/activity_cron.log";
        echo "</div>";
        echo "</div>";
        
        // Show current log if it exists
        $logFile = $logDir . '/activity_cron.log';
        if (file_exists($logFile)) {
            echo "<h3>📄 Current Log (Last 20 lines)</h3>";
            $logLines = file($logFile);
            $lastLines = array_slice($logLines, -20);
            echo "<div class='output'>" . htmlspecialchars(implode('', $lastLines)) . "</div>";
        }
        
        echo "<div class='step'>";
        echo "<h4>System Configuration</h4>";
        echo "<p>The activity system is configured with these timings:</p>";
        echo "<ul>";
        echo "<li><strong>AFK Timeout:</strong> 20 minutes of inactivity</li>";
        echo "<li><strong>Disconnect Timeout:</strong> 80 minutes total (20min + 60min AFK)</li>";
        echo "<li><strong>Session Timeout:</strong> 60 minutes for lounge users</li>";
        echo "<li><strong>Cron Frequency:</strong> Every 2 minutes</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<h2>✅ Next Steps</h2>";
        echo "<ol>";
        echo "<li>Set up the cron job using one of the methods above</li>";
        echo "<li>Wait a few minutes and check the log file</li>";
        echo "<li>Test the system by going AFK in a room</li>";
        echo "<li>Monitor the active users list for accuracy</li>";
        echo "</ol>";
        
        echo "<div class='info'>";
        echo "<h4>🔧 Troubleshooting</h4>";
        echo "<ul>";
        echo "<li>If cron jobs don't work, try the web cron service method</li>";
        echo "<li>Check PHP error logs if the script fails</li>";
        echo "<li>Verify database permissions</li>";
        echo "<li>Make sure the activity tracker is working in room.js</li>";
        echo "</ul>";
        echo "</div>";
        ?>
        
        <div class="step">
            <h4>🎯 Testing the Complete System</h4>
            <p>Once the cron job is running:</p>
            <ol>
                <li>Join a room and send messages (should track activity)</li>
                <li>Leave the browser idle for 20 minutes (should go AFK)</li>
                <li>Send a message while AFK (should return from AFK)</li>
                <li>Check the active users list in the lounge</li>
                <li>Monitor the log file for cron activity</li>
            </ol>
        </div>
    </div>
</body>
</html>
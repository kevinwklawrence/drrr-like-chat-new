<?php
// admin/setup_activity_cron_windows.php - Windows/XAMPP version
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
    <title>Activity System Cron Setup (Windows)</title>
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
        <h1>🔧 Activity System Cron Setup (Windows/XAMPP)</h1>
        
        <?php
        $projectRoot = dirname(__DIR__);
        $cronDir = $projectRoot . '/cron';
        $logDir = $projectRoot . '/logs';
        $disconnectScript = $projectRoot . '/api/check_disconnects.php';
        
        // Detect XAMPP installation
        $xamppPaths = [
            'C:\\xampp\\php\\php.exe',
            'C:\\xampp\\htdocs\\php\\php.exe',
            'D:\\xampp\\php\\php.exe',
            'E:\\xampp\\php\\php.exe'
        ];
        
        $phpPath = null;
        foreach ($xamppPaths as $path) {
            if (file_exists($path)) {
                $phpPath = $path;
                break;
            }
        }
        
        // Try to find PHP in common locations if XAMPP not found
        if (!$phpPath) {
            $commonPaths = [
                'C:\\php\\php.exe',
                'C:\\Program Files\\PHP\\php.exe',
                'C:\\wamp64\\bin\\php\\php8.0.0\\php.exe',
                'C:\\laragon\\bin\\php\\php8.0.0\\php.exe'
            ];
            
            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    $phpPath = $path;
                    break;
                }
            }
        }
        
        echo "<h2>📋 Windows System Check</h2>";
        
        // Check if disconnect script exists
        if (file_exists($disconnectScript)) {
            echo "<div class='success'>✅ Disconnect script found: $disconnectScript</div>";
        } else {
            echo "<div class='error'>❌ Disconnect script not found: $disconnectScript</div>";
        }
        
        // Check PHP path
        if ($phpPath && file_exists($phpPath)) {
            echo "<div class='success'>✅ PHP found: $phpPath</div>";
            
            // Test PHP version
            $version = shell_exec("\"$phpPath\" -v 2>nul");
            if ($version) {
                $versionLine = explode("\n", $version)[0];
                echo "<div class='success'>✅ PHP Version: $versionLine</div>";
            }
        } else {
            echo "<div class='error'>❌ PHP executable not found in common locations</div>";
            echo "<div class='warning'>⚠️ Please check your PHP installation path</div>";
        }
        
        // Test the disconnect script
        echo "<h3>🧪 Testing Disconnect Script</h3>";
        if (isset($_POST['test_script']) && $phpPath) {
            echo "<div class='output'>";
            $command = "\"$phpPath\" \"$disconnectScript\" 2>&1";
            $output = shell_exec($command);
            
            if ($output) {
                echo "<span class='success'>✅ Script executed successfully!</span>\n\n";
                echo htmlspecialchars($output);
            } else {
                echo "<span class='error'>❌ No output from script</span>\n";
            }
            echo "</div>";
        } elseif (isset($_POST['test_script']) && !$phpPath) {
            echo "<div class='error'>❌ Cannot test script - PHP path not found</div>";
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
        
        // Create Windows batch script
        if ($phpPath) {
            $batchScript = $cronDir . '/activity_cron.bat';
            $logFile = $logDir . '/activity_cron.log';
            
            $batchContent = "@echo off
REM activity_cron.bat - Windows batch script for activity system
REM Set the working directory to the project root
cd /d \"$projectRoot\"

REM Create log directory if it doesn't exist
if not exist \"$logDir\" mkdir \"$logDir\"

REM Log the start time
echo %date% %time% - Starting activity system check >> \"$logFile\"

REM Run the PHP script and capture output
\"$phpPath\" \"$disconnectScript\" 2>&1 >> \"$logFile\"

REM Log completion
echo %date% %time% - Activity check completed (Exit Code: %ERRORLEVEL%) >> \"$logFile\"

REM Keep only last 1000 lines of log (simple rotation)
if exist \"$logFile\" (
    powershell -command \"Get-Content '$logFile' | Select-Object -Last 500 | Set-Content '$logFile.tmp'; Move-Item '$logFile.tmp' '$logFile' -Force\" 2>nul
)";
            
            if (file_put_contents($batchScript, $batchContent)) {
                echo "<div class='success'>✅ Created Windows batch script: $batchScript</div>";
            } else {
                echo "<div class='error'>❌ Failed to create batch script</div>";
            }
        }
        
        // Create PowerShell script alternative
        if ($phpPath) {
            $psScript = $cronDir . '/activity_cron.ps1';
            $psContent = "# activity_cron.ps1 - PowerShell script for activity system
Set-Location \"$projectRoot\"

# Ensure log directory exists
if (-not (Test-Path \"$logDir\")) {
    New-Item -ItemType Directory -Path \"$logDir\" -Force | Out-Null
}

# Log start
\$timestamp = Get-Date -Format \"yyyy-MM-dd HH:mm:ss\"
Add-Content -Path \"$logFile\" -Value \"\$timestamp - Starting activity system check\"

try {
    # Run PHP script
    \$output = & \"$phpPath\" \"$disconnectScript\" 2>&1
    
    # Log output
    Add-Content -Path \"$logFile\" -Value \"\$timestamp - Activity check completed successfully\"
    Add-Content -Path \"$logFile\" -Value \$output
    
} catch {
    Add-Content -Path \"$logFile\" -Value \"\$timestamp - Activity check failed: \$(\$_.Exception.Message)\"
}

# Rotate log file (keep last 500 lines)
if (Test-Path \"$logFile\") {
    \$lines = Get-Content \"$logFile\"
    if (\$lines.Count -gt 1000) {
        \$lines | Select-Object -Last 500 | Set-Content \"$logFile\"
        Add-Content -Path \"$logFile\" -Value \"\$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Log file rotated\"
    }
}";
            
            if (file_put_contents($psScript, $psContent)) {
                echo "<div class='success'>✅ Created PowerShell script: $psScript</div>";
            } else {
                echo "<div class='error'>❌ Failed to create PowerShell script</div>";
            }
        }
        
        // Windows Task Scheduler instructions
        echo "<h2>⏰ Windows Task Scheduler Setup</h2>";
        
        echo "<div class='step'>";
        echo "<h3>📝 Task Scheduler Instructions</h3>";
        echo "<p><strong>Option 1: Using Batch File (Recommended)</strong></p>";
        echo "<ol>";
        echo "<li>Open Task Scheduler (taskschd.msc)</li>";
        echo "<li>Click 'Create Basic Task' in the right panel</li>";
        echo "<li>Name: 'Activity System Checker'</li>";
        echo "<li>Trigger: Daily</li>";
        echo "<li>Start time: Set to current time</li>";
        echo "<li>Repeat task every: 2 minutes</li>";
        echo "<li>Action: Start a program</li>";
        echo "<li>Program/script: <code>$batchScript</code></li>";
        echo "<li>Start in: <code>$projectRoot</code></li>";
        echo "</ol>";
        echo "</div>";
        
        if ($phpPath) {
            echo "<div class='step'>";
            echo "<h4>Option 2: Direct PHP Execution</h4>";
            echo "<p>If you prefer to call PHP directly:</p>";
            echo "<div class='code-block'>";
            echo "Program/script: \"$phpPath\"\n";
            echo "Arguments: \"$disconnectScript\"\n";
            echo "Start in: \"$projectRoot\"";
            echo "</div>";
            echo "</div>";
        }
        
        echo "<div class='step'>";
        echo "<h4>Option 3: PowerShell Script</h4>";
        echo "<p>For more advanced logging:</p>";
        echo "<div class='code-block'>";
        echo "Program/script: powershell.exe\n";
        echo "Arguments: -ExecutionPolicy Bypass -File \"$psScript\"\n";
        echo "Start in: \"$projectRoot\"";
        echo "</div>";
        echo "</div>";
        
        // Show current log if it exists
        $logFile = $logDir . '/activity_cron.log';
        if (file_exists($logFile)) {
            echo "<h3>📄 Current Log (Last 20 lines)</h3>";
            $logContent = file_get_contents($logFile);
            $logLines = explode("\n", $logContent);
            $lastLines = array_slice($logLines, -20);
            echo "<div class='output'>" . htmlspecialchars(implode("\n", $lastLines)) . "</div>";
        } else {
            echo "<div class='info'>📝 Log file will appear here after first run: $logFile</div>";
        }
        
        // Test current setup
        echo "<h2>🔧 Quick Test</h2>";
        if (isset($_POST['run_batch']) && file_exists($batchScript)) {
            echo "<div class='output'>";
            echo "Running batch script...\n\n";
            $batchOutput = shell_exec("\"$batchScript\" 2>&1");
            echo htmlspecialchars($batchOutput ?: "Batch script executed (check log file for output)");
            echo "</div>";
        } else {
            if (file_exists($batchScript)) {
                echo "<form method='post'><button type='submit' name='run_batch' class='button test'>🚀 Test Batch Script</button></form>";
            }
        }
        
        echo "<div class='step'>";
        echo "<h4>🎯 Testing the Complete System</h4>";
        echo "<p>Once the task is scheduled:</p>";
        echo "<ol>";
        echo "<li>Wait 2-3 minutes and check the log file</li>";
        echo "<li>Join a room and go idle for 20+ minutes</li>";
        echo "<li>Check if you're marked as AFK</li>";
        echo "<li>Stay idle for 80+ minutes total</li>";
        echo "<li>Check if you're disconnected from the room</li>";
        echo "</ol>";
        echo "</div>";
        
        // Show configuration
        echo "<h2>⚙️ System Configuration</h2>";
        echo "<div class='code-block'>";
        echo "AFK Timeout: 20 minutes\n";
        echo "Disconnect Timeout: 80 minutes\n";
        echo "Session Timeout: 60 minutes\n";
        echo "Check Frequency: Every 2 minutes\n";
        echo "\nPaths:\n";
        echo "PHP: " . ($phpPath ?: 'NOT FOUND') . "\n";
        echo "Project: $projectRoot\n";
        echo "Logs: $logDir\n";
        echo "Script: $disconnectScript";
        echo "</div>";
        
        echo "<div class='warning'>";
        echo "<h4>⚠️ Important Notes for Windows</h4>";
        echo "<ul>";
        echo "<li>Task Scheduler may not run if your computer is asleep</li>";
        echo "<li>Make sure Windows is set to prevent sleep during critical hours</li>";
        echo "<li>Check Task History in Task Scheduler for execution details</li>";
        echo "<li>Log files will help you debug any issues</li>";
        echo "</ul>";
        echo "</div>";
        ?>
    </div>
</body>
</html>
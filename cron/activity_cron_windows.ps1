# activity_cron_windows.ps1 - PowerShell script for activity system
# This script should be run every 2 minutes via Windows Task Scheduler

# Configuration - UPDATE THESE PATHS FOR YOUR SYSTEM
$phpPath = "C:\xampp\php\php.exe"
$projectRoot = "C:\xampp\htdocs"
$scriptPath = "$projectRoot\api\check_disconnects.php"
$logPath = "$projectRoot\logs\activity_cron.log"
$errorLogPath = "$projectRoot\logs\activity_errors.log"

# Function to write timestamped log entries
function Write-TimestampedLog {
    param(
        [string]$Message,
        [string]$LogFile = $logPath,
        [string]$Level = "INFO"
    )
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "$timestamp [$Level] - $Message"
    Add-Content -Path $LogFile -Value $logEntry
    
    # Also output to console for debugging
    Write-Host $logEntry
}

try {
    # Ensure log directory exists
    $logDir = Split-Path $logPath -Parent
    if (-not (Test-Path $logDir)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
        Write-TimestampedLog "Created log directory: $logDir"
    }
    
    # Change to project directory
    Set-Location $projectRoot
    Write-TimestampedLog "Starting activity system check from $projectRoot"
    
    # Validate PHP path
    if (-not (Test-Path $phpPath)) {
        $errorMsg = "PHP not found at $phpPath. Please update the `$phpPath variable in this script."
        Write-TimestampedLog $errorMsg $errorLogPath "ERROR"
        
        # Try to auto-detect PHP
        $commonPaths = @(
            "C:\xampp\php\php.exe",
            "C:\wamp64\bin\php\php8.0.0\php.exe",
            "C:\laragon\bin\php\php8.0.0\php.exe",
            "C:\php\php.exe"
        )
        
        foreach ($path in $commonPaths) {
            if (Test-Path $path) {
                Write-TimestampedLog "Found PHP at alternative location: $path" $logPath "WARN"
                $phpPath = $path
                break
            }
        }
        
        if (-not (Test-Path $phpPath)) {
            throw "PHP executable not found in any common location"
        }
    }
    
    # Validate script path
    if (-not (Test-Path $scriptPath)) {
        $errorMsg = "Disconnect script not found at $scriptPath"
        Write-TimestampedLog $errorMsg $errorLogPath "ERROR"
        throw $errorMsg
    }
    
    # Get PHP version for logging
    try {
        $phpVersion = & $phpPath -v 2>$null
        if ($phpVersion) {
            $versionLine = ($phpVersion -split "`n")[0]
            Write-TimestampedLog "Using PHP: $versionLine"
        }
    } catch {
        Write-TimestampedLog "Could not get PHP version, but proceeding..." $logPath "WARN"
    }
    
    # Run the PHP script
    Write-TimestampedLog "Executing disconnect checker script..."
    
    $processInfo = New-Object System.Diagnostics.ProcessStartInfo
    $processInfo.FileName = $phpPath
    $processInfo.Arguments = "`"$scriptPath`""
    $processInfo.WorkingDirectory = $projectRoot
    $processInfo.RedirectStandardOutput = $true
    $processInfo.RedirectStandardError = $true
    $processInfo.UseShellExecute = $false
    $processInfo.CreateNoWindow = $true
    
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $processInfo
    
    $process.Start() | Out-Null
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    
    $exitCode = $process.ExitCode
    
    # Log results
    if ($exitCode -eq 0) {
        Write-TimestampedLog "Activity check completed successfully (exit code: $exitCode)"
        
        # Try to parse JSON output for summary
        try {
            $jsonResult = $stdout | ConvertFrom-Json
            if ($jsonResult.status -eq "success" -and $jsonResult.summary) {
                $summary = $jsonResult.summary
                $summaryMsg = "Results: AFK($($summary.users_marked_afk)) Disconnected($($summary.users_disconnected)) Transfers($($summary.hosts_transferred)) Deleted($($summary.rooms_deleted))"
                Write-TimestampedLog $summaryMsg
            }
        } catch {
            Write-TimestampedLog "Script output (first 200 chars): $($stdout.Substring(0, [Math]::Min(200, $stdout.Length)))"
        }
        
        # Log full output
        if ($stdout) {
            Add-Content -Path $logPath -Value "--- Script Output ---"
            Add-Content -Path $logPath -Value $stdout
            Add-Content -Path $logPath -Value "--- End Output ---"
        }
        
    } else {
        Write-TimestampedLog "Activity check failed with exit code: $exitCode" $errorLogPath "ERROR"
        
        if ($stderr) {
            Write-TimestampedLog "Error output: $stderr" $errorLogPath "ERROR"
        }
        
        if ($stdout) {
            Write-TimestampedLog "Standard output: $stdout" $errorLogPath "ERROR"
        }
    }
    
    # Rotate log files if they get too large
    foreach ($logFile in @($logPath, $errorLogPath)) {
        if (Test-Path $logFile) {
            $lines = Get-Content $logFile
            if ($lines.Count -gt 1000) {
                $lines | Select-Object -Last 500 | Set-Content $logFile
                Write-TimestampedLog "Rotated log file: $(Split-Path $logFile -Leaf)"
            }
        }
    }
    
} catch {
    $errorMsg = "Script execution failed: $($_.Exception.Message)"
    Write-TimestampedLog $errorMsg $errorLogPath "ERROR"
    Write-TimestampedLog "Stack trace: $($_.ScriptStackTrace)" $errorLogPath "ERROR"
    
    # Exit with error code
    exit 1
}

Write-TimestampedLog "Activity cron job completed"
exit 0
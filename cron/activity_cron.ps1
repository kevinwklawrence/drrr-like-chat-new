# activity_cron.ps1 - PowerShell script for activity system
Set-Location "C:\xampp\htdocs"

# Ensure log directory exists
if (-not (Test-Path "C:\xampp\htdocs/logs")) {
    New-Item -ItemType Directory -Path "C:\xampp\htdocs/logs" -Force | Out-Null
}

# Log start
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
Add-Content -Path "C:\xampp\htdocs/logs/activity_cron.log" -Value "$timestamp - Starting activity system check"

try {
    # Run PHP script
    $output = & "C:\xampp\php\php.exe" "C:\xampp\htdocs/api/check_disconnects.php" 2>&1
    
    # Log output
    Add-Content -Path "C:\xampp\htdocs/logs/activity_cron.log" -Value "$timestamp - Activity check completed successfully"
    Add-Content -Path "C:\xampp\htdocs/logs/activity_cron.log" -Value $output
    
} catch {
    Add-Content -Path "C:\xampp\htdocs/logs/activity_cron.log" -Value "$timestamp - Activity check failed: $($_.Exception.Message)"
}

# Rotate log file (keep last 500 lines)
if (Test-Path "C:\xampp\htdocs/logs/activity_cron.log") {
    $lines = Get-Content "C:\xampp\htdocs/logs/activity_cron.log"
    if ($lines.Count -gt 1000) {
        $lines | Select-Object -Last 500 | Set-Content "C:\xampp\htdocs/logs/activity_cron.log"
        Add-Content -Path "C:\xampp\htdocs/logs/activity_cron.log" -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Log file rotated"
    }
}
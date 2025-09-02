@echo off
REM activity_cron.bat - Windows batch script for activity system
REM Set the working directory to the project root
cd /d "C:\xampp\htdocs"

REM Create log directory if it doesn't exist
if not exist "C:\xampp\htdocs/logs" mkdir "C:\xampp\htdocs/logs"

REM Log the start time
echo %date% %time% - Starting activity system check >> "C:\xampp\htdocs/logs/activity_cron.log"

REM Run the PHP script and capture output
"C:\xampp\php\php.exe" "C:\xampp\htdocs/api/check_disconnects.php" 2>&1 >> "C:\xampp\htdocs/logs/activity_cron.log"

REM Log completion
echo %date% %time% - Activity check completed (Exit Code: %ERRORLEVEL%) >> "C:\xampp\htdocs/logs/activity_cron.log"

REM Keep only last 1000 lines of log (simple rotation)
if exist "C:\xampp\htdocs/logs/activity_cron.log" (
    powershell -command "Get-Content 'C:\xampp\htdocs/logs/activity_cron.log' | Select-Object -Last 500 | Set-Content 'C:\xampp\htdocs/logs/activity_cron.log.tmp'; Move-Item 'C:\xampp\htdocs/logs/activity_cron.log.tmp' 'C:\xampp\htdocs/logs/activity_cron.log' -Force" 2>nul
)
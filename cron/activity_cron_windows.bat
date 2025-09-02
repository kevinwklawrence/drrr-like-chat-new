@echo off
REM activity_cron_windows.bat - Windows batch script for activity system
REM This script should be run every 2 minutes via Windows Task Scheduler

REM Configuration - UPDATE THESE PATHS FOR YOUR SYSTEM
set PHP_PATH=C:\xampp\php\php.exe
set PROJECT_ROOT=C:\xampp\htdocs
set SCRIPT_PATH=%PROJECT_ROOT%\api\check_disconnects.php
set LOG_PATH=%PROJECT_ROOT%\logs\activity_cron.log

REM Create logs directory if it doesn't exist
if not exist "%PROJECT_ROOT%\logs" mkdir "%PROJECT_ROOT%\logs"

REM Change to project directory
cd /d "%PROJECT_ROOT%"

REM Log the start time
echo %date% %time% - Starting activity system check >> "%LOG_PATH%"

REM Check if PHP exists
if not exist "%PHP_PATH%" (
    echo %date% %time% - ERROR: PHP not found at %PHP_PATH% >> "%LOG_PATH%"
    echo Please update PHP_PATH in this batch file >> "%LOG_PATH%"
    exit /b 1
)

REM Check if script exists
if not exist "%SCRIPT_PATH%" (
    echo %date% %time% - ERROR: Script not found at %SCRIPT_PATH% >> "%LOG_PATH%"
    exit /b 1
)

REM Run the PHP script and capture output
"%PHP_PATH%" "%SCRIPT_PATH%" >> "%LOG_PATH%" 2>&1
set EXIT_CODE=%ERRORLEVEL%

REM Log completion
if %EXIT_CODE%==0 (
    echo %date% %time% - Activity check completed successfully >> "%LOG_PATH%"
) else (
    echo %date% %time% - Activity check failed with exit code %EXIT_CODE% >> "%LOG_PATH%"
)

REM Simple log rotation - keep last 1000 lines
if exist "%LOG_PATH%" (
    for /f %%i in ('find /c /v "" "%LOG_PATH%"') do set LINE_COUNT=%%i
    if !LINE_COUNT! GTR 1000 (
        echo %date% %time% - Rotating log file >> "%LOG_PATH%"
        REM Keep last 500 lines (using PowerShell if available)
        powershell -command "Get-Content '%LOG_PATH%' | Select-Object -Last 500 | Set-Content '%LOG_PATH%.tmp'; Move-Item '%LOG_PATH%.tmp' '%LOG_PATH%' -Force" 2>nul
        if errorlevel 1 (
            REM Fallback if PowerShell fails
            copy "%LOG_PATH%" "%LOG_PATH%.backup" >nul
            echo Log rotated - check .backup file for older entries >> "%LOG_PATH%"
        )
    )
)

exit /b %EXIT_CODE%
#!/bin/bash
# activity_cron.sh - Wrapper script for activity system cron jobs

# Set working directory to project root
cd "C:\xampp\htdocs"

# Set environment variables if needed
export PATH=/usr/local/bin:/usr/bin:/bin

# Log file for cron output
LOG_FILE="C:\xampp\htdocs/logs/activity_cron.log"

# Function to log with timestamp
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Run the disconnect checker
log_message "Starting activity system check"

# Run the PHP script and capture output
OUTPUT=$(php "C:\xampp\htdocs/api/check_disconnects.php" 2>&1)
EXIT_CODE=$?

# Log the output
if [ $EXIT_CODE -eq 0 ]; then
    log_message "Activity check completed successfully"
    echo "$OUTPUT" >> "$LOG_FILE"
else
    log_message "Activity check failed with exit code $EXIT_CODE"
    log_message "Error output: $OUTPUT"
fi

# Rotate log file if it gets too big (keep last 1000 lines)
if [ -f "$LOG_FILE" ] && [ $(wc -l < "$LOG_FILE") -gt 1000 ]; then
    tail -n 500 "$LOG_FILE" > "${LOG_FILE}.tmp"
    mv "${LOG_FILE}.tmp" "$LOG_FILE"
    log_message "Log file rotated"
fi
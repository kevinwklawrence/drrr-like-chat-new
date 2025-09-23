<?php
// cron/disconnect_cron.php - Cron job script for automatic disconnect checking
// This script should be run via cron every 2-5 minutes

// Set up error reporting for cron environment
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/disconnect_cron.log');

// Create logs directory if it doesn't exist
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log start time
error_log("DISCONNECT_CRON: Starting at " . date('Y-m-d H:i:s'));

// Include the main disconnect checker
$disconnect_script = __DIR__ . '/../api/check_disconnects.php';

if (!file_exists($disconnect_script)) {
    error_log("DISCONNECT_CRON: ERROR - check_disconnects.php not found at: $disconnect_script");
    exit(1);
}

// Capture the output from the disconnect script
ob_start();
include $disconnect_script;
$output = ob_get_clean();

// Log the results
error_log("DISCONNECT_CRON: Output - " . $output);

// Try to decode JSON response
$result = json_decode($output, true);
if ($result && isset($result['status'])) {
    if ($result['status'] === 'success') {
        $summary = $result['summary'] ?? [];
        error_log("DISCONNECT_CRON: SUCCESS - Users: {$summary['users_disconnected']}, Transfers: {$summary['hosts_transferred']}, Deleted: {$summary['rooms_deleted']}");
    } else {
        error_log("DISCONNECT_CRON: ERROR - " . ($result['message'] ?? 'Unknown error'));
    }
} else {
    error_log("DISCONNECT_CRON: WARNING - Invalid JSON response: " . substr($output, 0, 200));
}

error_log("DISCONNECT_CRON: Completed at " . date('Y-m-d H:i:s'));
?>
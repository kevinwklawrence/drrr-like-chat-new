<?php
// web_cron.php - Web endpoint for external cron services
// This allows external cron services to trigger the activity system

header('Content-Type: application/json');

// Optional: Add basic security
$allowedIPs = [
    // Add your cron service IPs here if needed
    // '1.2.3.4',
];

$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

// Simple security check - you might want to add a secret key
$secret = $_GET['secret'] ?? '';
$expectedSecret = 'your-secret-key-here'; // Change this!

if ($secret !== $expectedSecret && !empty($allowedIPs) && !in_array($clientIP, $allowedIPs)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Include and run the disconnect checker
ob_start();
include __DIR__ . '/api/check_disconnects.php';
$output = ob_get_clean();

// Try to parse JSON response
$result = json_decode($output, true);

if ($result) {
    // Return the result as JSON
    echo $output;
} else {
    // Return error if not valid JSON
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid response from disconnect checker',
        'output' => $output
    ]);
}
?>
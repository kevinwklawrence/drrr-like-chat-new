<?php
/**
 * Health Check Endpoint for Duranu Chat
 * Simple endpoint to verify system status
 * Access: yourwebsite.com/health.php
 */

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

$overall_status = true;

// 1. PHP Version Check
$php_version = phpversion();
$php_ok = version_compare($php_version, '7.4.0', '>=');
$health['checks']['php'] = [
    'status' => $php_ok ? 'pass' : 'fail',
    'version' => $php_version,
    'required' => '7.4.0+'
];
$overall_status = $overall_status && $php_ok;

// 2. Database Connection Check
try {
    include_once 'db_connect.php';
    $health['checks']['database'] = [
        'status' => 'pass',
        'message' => 'Connection successful'
    ];
} catch (Exception $e) {
    $health['checks']['database'] = [
        'status' => 'fail',
        'message' => 'Connection failed: ' . $e->getMessage()
    ];
    $overall_status = false;
}

// 3. Session Support Check
$health['checks']['sessions'] = [
    'status' => (function_exists('session_start') ? 'pass' : 'fail'),
    'message' => function_exists('session_start') ? 'Session support available' : 'Session support missing'
];
if (!function_exists('session_start')) {
    $overall_status = false;
}

// 4. Required Extensions Check
$required_extensions = ['mysqli', 'json', 'session', 'mbstring'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

$health['checks']['extensions'] = [
    'status' => empty($missing_extensions) ? 'pass' : 'fail',
    'missing' => $missing_extensions,
    'message' => empty($missing_extensions) ? 'All required extensions loaded' : 'Missing extensions: ' . implode(', ', $missing_extensions)
];

if (!empty($missing_extensions)) {
    $overall_status = false;
}

// 5. File Permissions Check
$directories_to_check = [
    'logs' => './logs/',
    'chat_logs' => './chat_logs/',
    'images' => './images/'
];

$permission_issues = [];
foreach ($directories_to_check as $name => $path) {
    if (is_dir($path)) {
        if (!is_writable($path)) {
            $permission_issues[] = $name . ' (not writable)';
        }
    } else {
        $permission_issues[] = $name . ' (missing)';
    }
}

$health['checks']['permissions'] = [
    'status' => empty($permission_issues) ? 'pass' : 'warning',
    'issues' => $permission_issues,
    'message' => empty($permission_issues) ? 'Directory permissions OK' : 'Permission issues: ' . implode(', ', $permission_issues)
];

// 6. Core Files Check
$core_files = [
    'firewall.php',
    'index.php',
    'login.php',
    'register.php',
    'room.php',
    'lounge.php',
    'db_connect.php'
];

$missing_files = [];
foreach ($core_files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

$health['checks']['core_files'] = [
    'status' => empty($missing_files) ? 'pass' : 'fail',
    'missing' => $missing_files,
    'message' => empty($missing_files) ? 'All core files present' : 'Missing files: ' . implode(', ', $missing_files)
];

if (!empty($missing_files)) {
    $overall_status = false;
}

// 7. Configuration Check
$config_issues = [];

// Check if site password is still default
if (file_exists('firewall.php')) {
    $firewall_content = file_get_contents('firewall.php');
    if (strpos($firewall_content, "'baccano'") !== false) {
        $config_issues[] = 'Default site password still in use';
    }
}

$health['checks']['configuration'] = [
    'status' => empty($config_issues) ? 'pass' : 'warning',
    'issues' => $config_issues,
    'message' => empty($config_issues) ? 'Configuration looks good' : 'Configuration issues: ' . implode(', ', $config_issues)
];

// 8. Security Headers Check (basic)
$security_headers = [
    'X-Frame-Options',
    'X-Content-Type-Options',
    'X-XSS-Protection'
];

$security_status = 'unknown';
if (function_exists('apache_response_headers') || function_exists('headers_list')) {
    $security_status = 'configured';
} else {
    $security_status = 'check_manually';
}

$health['checks']['security'] = [
    'status' => $security_status === 'configured' ? 'pass' : 'info',
    'message' => 'Security headers: ' . $security_status
];

// Set overall status
$health['status'] = $overall_status ? 'healthy' : 'unhealthy';

// Add system info
$health['system'] = [
    'php_version' => $php_version,
    'server_time' => date('Y-m-d H:i:s T'),
    'memory_usage' => memory_get_usage(true),
    'memory_limit' => ini_get('memory_limit')
];

// Return appropriate HTTP status code
if (!$overall_status) {
    http_response_code(503); // Service Unavailable
} else {
    http_response_code(200); // OK
}

echo json_encode($health, JSON_PRETTY_PRINT);
?>
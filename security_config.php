<?php
/**
 * Security Configuration for Duranu Chat - MINIMAL VERSION
 * Just fixes the core production detection issue
 */

// Prevent direct access
if (!defined('DURANU_SECURITY_LOADED')) {
    define('DURANU_SECURITY_LOADED', true);
}

// FIXED: Correct production detection
$is_production = ($_SERVER['SERVER_NAME'] === 'duranu.net' || $_SERVER['SERVER_NAME'] === 'www.duranu.net');
$is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || $_SERVER['SERVER_NAME'] === 'localhost';

if ($is_production) {
    // PRODUCTION: Hide errors
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    
    // Basic security headers (non-breaking)
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    
    // Remove server signature
    header_remove('X-Powered-By');
    
} else {
    // DEVELOPMENT: Show errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// Session security (basic)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $is_production ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

// Basic PHP security
ini_set('expose_php', 0);
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);

// Keep your existing rate limiting function (simplified)
function checkRateLimit($action = 'general', $max_attempts = 10, $time_window = 300) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'last_attempt' => time()
        ];
    }
    
    $rate_data = &$_SESSION[$key];
    
    // Reset counter if time window has passed
    if (time() - $rate_data['last_attempt'] > $time_window) {
        $rate_data['attempts'] = 0;
    }
    
    $rate_data['attempts']++;
    $rate_data['last_attempt'] = time();
    
    if ($rate_data['attempts'] > $max_attempts) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Rate limit exceeded. Please try again later.'
        ]);
        exit;
    }
}

?>
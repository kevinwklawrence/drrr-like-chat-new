<?php
/**
 * Security Configuration for Duranu Chat
 * Include this file at the top of your main files for production security
 */

// Prevent direct access
if (!defined('DURANU_SECURITY_LOADED')) {
    define('DURANU_SECURITY_LOADED', true);
}

// Production Security Settings
if ($_SERVER['SERVER_NAME'] !== 'localhost' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    
    // Disable error reporting in production
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    
    // Enhanced security headers
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // Remove server signature
    header_remove('X-Powered-By');
    header_remove('Server');
    
} else {
    // Development settings
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

// General PHP security
ini_set('expose_php', 0);
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);

// File upload security (if needed)
ini_set('file_uploads', 1);
ini_set('upload_max_filesize', '5M');
ini_set('max_file_uploads', 3);

/**
 * Rate limiting helper function
 * Simple rate limiting based on IP address
 */
function checkRateLimit($action = 'general', $max_attempts = 10, $time_window = 300) {
    session_start();
    
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
        // Rate limit exceeded
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Rate limit exceeded. Please try again later.'
        ]);
        exit;
    }
}

/**
 * Input sanitization helper
 */
function sanitizeInput($input, $type = 'string') {
    if (is_array($input)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $input);
    }
    
    switch ($type) {
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * CSRF Token functions
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Basic SQL injection prevention reminder
 * Always use prepared statements in your database queries!
 */
function logSecurityEvent($event, $details = '') {
    $log_entry = date('Y-m-d H:i:s') . " - {$event} - IP: {$_SERVER['REMOTE_ADDR']} - {$details}" . PHP_EOL;
    
    // Only log in production if logs directory exists
    if (is_dir(__DIR__ . '/logs')) {
        file_put_contents(__DIR__ . '/logs/security.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Block common attack patterns
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$suspicious_patterns = [
    '/\.\.\//',           // Directory traversal
    '/\<script/',         // XSS attempts
    '/union.*select/i',   // SQL injection
    '/base64_decode/i',   // Code injection
    '/eval\(/i',          // PHP code injection
];

foreach ($suspicious_patterns as $pattern) {
    if (preg_match($pattern, $request_uri) || preg_match($pattern, serialize($_GET)) || preg_match($pattern, serialize($_POST))) {
        logSecurityEvent('SUSPICIOUS_REQUEST', "Pattern: {$pattern}");
        http_response_code(403);
        exit('Access Denied');
    }
}

// Auto-include for production (uncomment in main files if needed)
// require_once __DIR__ . '/security_config.php';
?>
<?php
/**
 * AGGRESSIVE AUTO INPUT SANITIZER
 * This MUST be included in db_connect.php for it to work
 * 
 * Add to END of db_connect.php:
 * require_once __DIR__ . '/auto_sanitize.php';
 */

// Function to sanitize values
function sanitize_value($value, $max_length = 3000) {
    if (is_array($value)) {
        return array_map('sanitize_value', $value);
    }
    
    if ($value === null || $value === '') {
        return $value;
    }
    
    // Convert to string
    $value = (string)$value;
    
    // Remove null bytes
    $value = str_replace("\0", '', $value);
    
    // AGGRESSIVELY strip ALL HTML tags
    $value = strip_tags($value);
    
    // Double protection: escape any remaining < >
    $value = str_replace(['<', '>'], ['&lt;', '&gt;'], $value);
    
    // Triple protection: htmlspecialchars
    $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    
    // Limit length
    if (mb_strlen($value, 'UTF-8') > $max_length) {
        $value = mb_substr($value, 0, $max_length, 'UTF-8');
    }
    
    return $value;
}

// IMMEDIATELY sanitize all inputs when this file loads
foreach ($_POST as $key => $value) {
    // Skip password fields and message fields (messages have their own sanitization)
    if (stripos($key, 'password') === false && 
        stripos($key, 'message') === false) {
        $_POST[$key] = sanitize_value($value);
    }
}

foreach ($_GET as $key => $value) {
    $_GET[$key] = sanitize_value($value, 500);
}

// Update REQUEST too
$_REQUEST = array_merge($_GET, $_POST);

// Log that sanitizer ran (remove this line after confirming it works)
error_log("AUTO_SANITIZE: Sanitizer ran at " . date('Y-m-d H:i:s'));

?>
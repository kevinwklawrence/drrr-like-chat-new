<?php
// config/maintenance.php - Maintenance mode configuration

// === MAINTENANCE MODE SETTINGS ===
define('MAINTENANCE_MODE', false); // Set to true to enable maintenance mode

// Admin credentials for maintenance bypass
define('MAINTENANCE_ADMIN_USERNAME', 'webadmin');
define('MAINTENANCE_ADMIN_PASSWORD', 'admin123'); // CHANGE THIS!

// Maintenance message (optional - can be customized)
define('MAINTENANCE_MESSAGE', 'We\'re currently performing scheduled maintenance to improve your experience. Please check back in a few minutes.');

// Allow specific IPs to bypass maintenance (optional)
$MAINTENANCE_BYPASS_IPS = [
    // '127.0.0.1',     // Localhost
    // '192.168.1.100', // Your IP
];

// Function to check if maintenance mode is active
function isMaintenanceMode() {
    return MAINTENANCE_MODE;
}

// Function to check if current IP is allowed to bypass
function canBypassMaintenance() {
    global $MAINTENANCE_BYPASS_IPS;
    $userIP = $_SERVER['REMOTE_ADDR'];
    return in_array($userIP, $MAINTENANCE_BYPASS_IPS);
}

// Function to check if user should see maintenance page
function shouldShowMaintenance() {
    if (!isMaintenanceMode()) {
        return false;
    }
    
    // Check for admin bypass session
    if (isset($_SESSION['admin_bypass'])) {
        return false;
    }
    
    // Check for IP bypass
    if (canBypassMaintenance()) {
        return false;
    }
    
    return true;
}
?>
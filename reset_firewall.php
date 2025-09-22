<?php
// reset_firewall.php - Simple script to reset firewall session for testing
session_start();

// Clear only the firewall session variable
unset($_SESSION['firewall_passed']);

// Optional: Clear all session data (uncomment if needed)
// session_unset();
// session_destroy();

header("Location: /guest");
?>
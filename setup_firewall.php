<?php
// setup_firewall_ip.php - Create table for storing firewall-passed IPs
session_start();
include 'db_connect.php';

// Check if user is admin (optional - remove if you want to run this manually)
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Firewall IP System Setup</h2>\n";
echo "<pre>\n";

try {
    // Create firewall_passes table
    echo "Creating firewall_passes table...\n";
    $create_table = "CREATE TABLE IF NOT EXISTS firewall_passes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        first_pass TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_access TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ip (ip_address)
    )";
    
    if ($conn->query($create_table)) {
        echo "✓ Created firewall_passes table\n";
    } else {
        echo "✗ Error creating table: " . $conn->error . "\n";
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Firewall IP bypass system is ready!\n";
    echo "\nHow it works:\n";
    echo "- When users pass the firewall, their IP is stored\n";
    echo "- On return visits, IPs in the table bypass the firewall\n";
    echo "- last_access updates automatically on each visit\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
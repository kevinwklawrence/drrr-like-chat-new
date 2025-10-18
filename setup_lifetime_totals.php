<?php
// setup_lifetime_totals.php - Add lifetime total columns for leaderboard
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Lifetime Currency Totals Setup</h2>\n";
echo "<pre>\n";

try {
    $result = $conn->query("SHOW COLUMNS FROM users");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    echo "Current users table columns checked\n\n";
    
    // Add lifetime_dura column
    if (!in_array('lifetime_dura', $columns)) {
        echo "Adding 'lifetime_dura' column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN lifetime_dura INT DEFAULT 0 NOT NULL AFTER dura");
        echo "✓ Added 'lifetime_dura' column\n";
        
        // Initialize with current dura values
        echo "Initializing lifetime_dura with current dura values...\n";
        $conn->query("UPDATE users SET lifetime_dura = dura");
        echo "✓ Initialized lifetime_dura\n";
    } else {
        echo "✓ 'lifetime_dura' column already exists\n";
    }
    
    // Add lifetime_event_currency column
    if (!in_array('lifetime_event_currency', $columns)) {
        echo "Adding 'lifetime_event_currency' column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN lifetime_event_currency INT DEFAULT 0 NOT NULL AFTER event_currency");
        echo "✓ Added 'lifetime_event_currency' column\n";
        
        // Initialize with current event_currency values
        echo "Initializing lifetime_event_currency with current event_currency values...\n";
        $conn->query("UPDATE users SET lifetime_event_currency = event_currency");
        echo "✓ Initialized lifetime_event_currency\n";
    } else {
        echo "✓ 'lifetime_event_currency' column already exists\n";
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Lifetime total tracking is ready!\n";
    echo "\nWhat this does:\n";
    echo "- Tracks total Dura ever earned (never decreases)\n";
    echo "- Tracks total Event Currency ever earned (never decreases)\n";
    echo "- Leaderboard shows lifetime totals instead of current balance\n";
    echo "- Users won't drop on leaderboard when spending currency\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
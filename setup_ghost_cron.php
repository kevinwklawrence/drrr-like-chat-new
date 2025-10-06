<?php
// setup_ghost_cron.php - Setup automated ghost spawning
session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Admin only");
}

echo "<h2>Ghost Hunt Automation Setup</h2>\n";
echo "<pre>\n";

$projectRoot = realpath(__DIR__);
$spawnScript = $projectRoot . '/api/spawn_ghost.php';

// Create wrapper script
$wrapperScript = $projectRoot . '/ghost_spawn_cron.sh';
$wrapperContent = "#!/bin/bash
# Ghost Hunt Spawner - Runs every 5-15 minutes
cd \"$projectRoot\"

# Run spawn script
php \"$spawnScript\" >> \"$projectRoot/logs/ghost_spawner.log\" 2>&1
";

if (file_put_contents($wrapperScript, $wrapperContent)) {
    chmod($wrapperScript, 0755);
    echo "✅ Created wrapper script: $wrapperScript\n\n";
} else {
    echo "❌ Failed to create wrapper script\n\n";
}

// Create log directory
$logDir = $projectRoot . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
    echo "✅ Created logs directory\n";
}

echo "=== CRON SETUP INSTRUCTIONS ===\n\n";
echo "Add ONE of these to your crontab:\n\n";

echo "Option 1: Every 5 minutes (frequent spawns)\n";
echo "*/5 * * * * $wrapperScript\n\n";

echo "Option 2: Every 10 minutes (moderate spawns)\n";
echo "*/10 * * * * $wrapperScript\n\n";

echo "Option 3: Every 15 minutes (less frequent)\n";
echo "*/15 * * * * $wrapperScript\n\n";

echo "To add to crontab, run: crontab -e\n";
echo "Then paste one of the above lines.\n\n";

echo "=== ALTERNATIVE: Manual Trigger ===\n";
echo "You can also trigger ghost spawns manually from the admin panel.\n";
echo "Visit: /admin/trigger_ghost_spawn.php\n\n";

echo "=== TEST ===\n";
if (file_exists($spawnScript)) {
    echo "Testing spawn script...\n";
    $output = shell_exec("php \"$spawnScript\" 2>&1");
    echo "$output\n";
} else {
    echo "⚠️  Spawn script not found at: $spawnScript\n";
}

echo "</pre>";
?>
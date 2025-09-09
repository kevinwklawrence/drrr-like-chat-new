<?php
// debug-session.php - Run this to check your session configuration
session_start();

echo "<h2>PHP Session Debug Info</h2>\n";

echo "<h3>Session Configuration:</h3>\n";
echo "Session Name: " . session_name() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "Session Cookie Domain: " . ini_get('session.cookie_domain') . "\n";
echo "Session Cookie Path: " . ini_get('session.cookie_path') . "\n";

echo "\n<h3>Current Session Data:</h3>\n";
if (isset($_SESSION['user'])) {
    echo "User found in session:\n";
    print_r($_SESSION['user']);
} else {
    echo "No user in session\n";
}

echo "\n<h3>Full Session Contents:</h3>\n";
print_r($_SESSION);

echo "\n<h3>Session Storage Method:</h3>\n";
echo "Session Handler: " . ini_get('session.save_handler') . "\n";

// Check if sessions are stored in database
if (ini_get('session.save_handler') === 'user') {
    echo "Using custom session handler (likely database)\n";
} else {
    echo "Using file-based sessions\n";
}

echo "\n<h3>Recommended Node.js Config:</h3>\n";
echo "session: {\n";
echo "    secret: 'your_session_secret_here',\n";
echo "    name: '" . session_name() . "',\n";
echo "    cookie: {\n";
echo "        domain: '" . (ini_get('session.cookie_domain') ?: 'localhost') . "',\n";
echo "        path: '" . (ini_get('session.cookie_path') ?: '/') . "'\n";
echo "    }\n";
echo "}\n";
?>
<?php
// reset_firewall.php - Simple script to reset firewall session for testing
session_start();

// Clear only the firewall session variable
unset($_SESSION['firewall_passed']);

// Optional: Clear all session data (uncomment if needed)
// session_unset();
// session_destroy();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Firewall Reset</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #1a1a1a; 
            color: #fff; 
            padding: 50px; 
            text-align: center; 
        }
        .success { 
            background: rgba(40, 167, 69, 0.1); 
            border: 1px solid #28a745; 
            padding: 20px; 
            border-radius: 10px; 
            max-width: 500px; 
            margin: 0 auto; 
        }
        a { 
            color: #007bff; 
            text-decoration: none; 
            font-weight: bold; 
        }
        a:hover { 
            text-decoration: underline; 
        }
    </style>
</head>
<body>
    <div class='success'>
        <h2>✅ Firewall Session Reset</h2>
        <p>The firewall session has been cleared successfully!</p>
        <p>You will now need to pass through the firewall again.</p>
        <br>
        <a href='firewall.php'>← Go to Firewall</a> | 
        <a href='index.php'>Go to Index</a>
    </div>
</body>
</html>";
?>
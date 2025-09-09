<?php
// debug-test.php - Simple test to identify the issue

header('Content-Type: application/json');

try {
    // Test 1: Basic JSON output
    $test = [
        'test' => 'Basic JSON output working',
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD']
    ];
    
    // Test 2: Check if we can read POST data
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $test['raw_input'] = $input;
        $test['parsed_input'] = json_decode($input, true);
    }
    
    // Test 3: Check database connection (comment out if no config file)
    
    require_once 'db_connect.php';
    $pdo = new PDO($dsn, $db_username, $db_password);
    $test['database'] = 'Connected successfully';
    
    
    echo json_encode($test, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Exception caught',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>
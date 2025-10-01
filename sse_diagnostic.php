<?php
// api/sse_diagnostic.php - Run this to check if SSE prerequisites are working
session_start();
header('Content-Type: application/json');

$diagnostic = [
    'session' => [],
    'database' => [],
    'environment' => [],
    'errors' => []
];

// Check session
$diagnostic['session']['exists'] = isset($_SESSION);
$diagnostic['session']['user_exists'] = isset($_SESSION['user']);
$diagnostic['session']['room_exists'] = isset($_SESSION['room_id']);

if (isset($_SESSION['user'])) {
    $diagnostic['session']['user_type'] = $_SESSION['user']['type'] ?? 'unknown';
    $diagnostic['session']['user_id'] = $_SESSION['user']['id'] ?? 'none';
    $diagnostic['session']['user_id_string'] = $_SESSION['user']['user_id'] ?? 'none';
}

if (isset($_SESSION['room_id'])) {
    $diagnostic['session']['room_id'] = $_SESSION['room_id'];
}

// Check database connection
$db_path = 'db_connect.php';
if (file_exists($db_path)) {
    $diagnostic['database']['file_exists'] = true;
    
    // Try to include it
    try {
        require_once $db_path;
        
        if (isset($conn)) {
            $diagnostic['database']['connection_exists'] = true;
            
            // Test the connection
            if ($conn->ping()) {
                $diagnostic['database']['connection_active'] = true;
                
                // Try a simple query
                $result = $conn->query("SELECT 1");
                if ($result) {
                    $diagnostic['database']['query_works'] = true;
                    
                    // Check if room exists
                    if (isset($_SESSION['room_id'])) {
                        $room_id = (int)$_SESSION['room_id'];
                        $stmt = $conn->prepare("SELECT id, name FROM chatrooms WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $room_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                $room = $result->fetch_assoc();
                                $diagnostic['database']['room_found'] = true;
                                $diagnostic['database']['room_name'] = $room['name'];
                            } else {
                                $diagnostic['database']['room_found'] = false;
                                $diagnostic['errors'][] = "Room ID {$room_id} not found in database";
                            }
                            $stmt->close();
                        }
                    }
                } else {
                    $diagnostic['database']['query_works'] = false;
                    $diagnostic['errors'][] = "Database query failed: " . $conn->error;
                }
            } else {
                $diagnostic['database']['connection_active'] = false;
                $diagnostic['errors'][] = "Database connection is not active";
            }
        } else {
            $diagnostic['database']['connection_exists'] = false;
            $diagnostic['errors'][] = "\$conn variable not set after including db_connect.php";
        }
    } catch (Exception $e) {
        $diagnostic['errors'][] = "Database connection error: " . $e->getMessage();
    }
} else {
    $diagnostic['database']['file_exists'] = false;
    $diagnostic['errors'][] = "db_connect.php not found at: " . realpath(dirname(__FILE__) . '/' . $db_path);
}

// Check PHP environment
$diagnostic['environment']['php_version'] = PHP_VERSION;
$diagnostic['environment']['output_buffering'] = ini_get('output_buffering');
$diagnostic['environment']['max_execution_time'] = ini_get('max_execution_time');
$diagnostic['environment']['memory_limit'] = ini_get('memory_limit');

// Overall status
$diagnostic['status'] = empty($diagnostic['errors']) ? 'OK' : 'ERRORS_FOUND';

// Output results
echo json_encode($diagnostic, JSON_PRETTY_PRINT);
?>
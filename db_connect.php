<?php
require_once __DIR__ . '/auto_sanitize.php';
$host = 'localhost';
$user = 'duranune_lennzuki';
$password = 'Hazingmars69*';
$database = 'duranune_drrr_clone';
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

?>
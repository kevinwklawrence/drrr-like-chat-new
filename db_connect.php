<?php
$host = 'localhost';
$user = 'duranune_lennzuki';
$password = 'Hazingmars69*';
$database = 'duranune_drrr_clone';
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}
$conn->query("SET SESSION wait_timeout = 60");
$conn->query("SET SESSION interactive_timeout = 60");
$conn->query("SET SESSION max_connections = 200");
?>
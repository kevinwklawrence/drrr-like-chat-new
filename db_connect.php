<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'drrr_clone';
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}
?>
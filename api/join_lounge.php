<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$guest_name = $_POST['guest_name'] ?? '';
$avatar = $_POST['avatar'] ?? '';

if (empty($guest_name) || empty($avatar)) {
    echo json_encode(['status' => 'error', 'message' => 'Guest name and avatar are required']);
    exit;
}

$_SESSION['user'] = [
    'type' => 'guest',
    'name' => $guest_name,
    'avatar' => $avatar
];

error_log("Guest joined: name=$guest_name, avatar=$avatar"); // Debug
echo json_encode(['status' => 'success']);
?>
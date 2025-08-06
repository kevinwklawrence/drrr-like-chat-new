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

// Generate encrypted user_id for guest using their IP address
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$encryption_key = 'drrr_guest_key_2025'; // Simple encryption key for demo purposes
$encrypted_ip = base64_encode(hash('sha256', $ip_address . $encryption_key, true));
// Make it shorter and more readable - take first 12 characters
$guest_user_id = 'GUEST_' . substr($encrypted_ip, 0, 12);

// Create guest session with user_id
$_SESSION['user'] = [
    'type' => 'guest',
    'name' => $guest_name,
    'user_id' => $guest_user_id,
    'avatar' => $avatar,
    'ip_address' => $ip_address
];

error_log("Guest joined: name=$guest_name, user_id=$guest_user_id, avatar=$avatar, ip=$ip_address");
echo json_encode(['status' => 'success']);
?>
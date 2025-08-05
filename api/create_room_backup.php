<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in create_room.php");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';
$capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 10;
$background = isset($_POST['background']) ? $_POST['background'] : '';
$permanent = isset($_POST['permanent']) ? (int)$_POST['permanent'] : 0; // New field

if (empty($name) || $capacity <= 0) {
    error_log("Invalid room data in create_room.php: name=$name, capacity=$capacity");
    echo json_encode(['status' => 'error', 'message' => 'Room name and valid capacity are required']);
    exit;
}

$user_id = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['id'] : null;

$hashed_password = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

$stmt = $conn->prepare("INSERT INTO chatrooms (name, password, description, capacity, background, created_by, permanent) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    error_log("Prepare failed in create_room.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("sssisii", $name, $hashed_password, $description, $capacity, $background, $user_id, $permanent);
//$stmt->bind_param("sssissi", $name, $description, $background, $capacity, $hased_password, $permanent, $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed in create_room.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    exit;
}
$room_id = $conn->insert_id;
$stmt->close();

$_SESSION['room_id'] = $room_id;
error_log("Created room: id=$room_id, name=$name, permanent=$permanent"); // Debug
echo json_encode(['status' => 'success', 'room_id' => $room_id]);
?>
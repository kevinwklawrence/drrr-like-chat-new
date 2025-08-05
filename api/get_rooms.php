<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$stmt = $conn->prepare("SELECT id, name, description, background, capacity, password, (SELECT COUNT(*) FROM chatroom_users WHERE room_id = chatrooms.id) AS current_users FROM chatrooms");
$stmt->execute();
$result = $stmt->get_result();
$rooms = [];
while ($row = $result->fetch_assoc()) {
    $row['password'] = !empty($row['password']); // Convert to boolean
    $rooms[] = $row;
}
$stmt->close();
echo json_encode($rooms);
?>
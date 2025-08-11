<?php
session_start();
header('Content-Type: application/json');

include '../db_connect.php';

$room_id = (int)$_SESSION['room_id'];

// Get current time information
$time_info = [
    'php_time' => date('Y-m-d H:i:s'),
    'php_timezone' => date_default_timezone_get(),
    'mysql_time' => null,
    'mysql_timezone' => null,
    'all_bans' => [],
    'active_bans_old_query' => [],
    'active_bans_new_query' => []
];

// Get MySQL time
$mysql_time_result = $conn->query("SELECT NOW() as current_time, @@session.time_zone as timezone");
if ($mysql_time_result) {
    $time_data = $mysql_time_result->fetch_assoc();
    $time_info['mysql_time'] = $time_data['current_time'];
    $time_info['mysql_timezone'] = $time_data['timezone'];
}

// Get all bans for this room
$all_bans_stmt = $conn->prepare("SELECT *, NOW() as current_mysql_time FROM room_bans WHERE room_id = ?");
$all_bans_stmt->bind_param("i", $room_id);
$all_bans_stmt->execute();
$all_result = $all_bans_stmt->get_result();
while ($row = $all_result->fetch_assoc()) {
    $time_info['all_bans'][] = $row;
}
$all_bans_stmt->close();

// Test old query (the one that's not working)
$old_query_stmt = $conn->prepare("SELECT * FROM room_bans WHERE room_id = ? AND (ban_until IS NULL OR ban_until > NOW())");
$old_query_stmt->bind_param("i", $room_id);
$old_query_stmt->execute();
$old_result = $old_query_stmt->get_result();
while ($row = $old_result->fetch_assoc()) {
    $time_info['active_bans_old_query'][] = $row;
}
$old_query_stmt->close();

// Test modified query
$new_query_stmt = $conn->prepare("SELECT * FROM room_bans WHERE room_id = ? AND (ban_until IS NULL OR ban_until > CURRENT_TIMESTAMP)");
$new_query_stmt->bind_param("i", $room_id);
$new_query_stmt->execute();
$new_result = $new_query_stmt->get_result();
while ($row = $new_result->fetch_assoc()) {
    $time_info['active_bans_new_query'][] = $row;
}
$new_query_stmt->close();

echo json_encode($time_info);
$conn->close();
?>
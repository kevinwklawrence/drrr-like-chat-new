<?php
session_start();
header('Content-Type: application/json');

require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// Get the setting value from POST data
if (!isset($_POST['show_dms_automatically'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameter']);
    exit;
}

$show_dms_automatically = (int)$_POST['show_dms_automatically'];

// Update the setting in the database
$stmt = $conn->prepare("UPDATE users SET show_dms_automatically = ? WHERE id = ?");
$stmt->bind_param("ii", $show_dms_automatically, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'DM settings updated successfully',
        'show_dms_automatically' => (bool)$show_dms_automatically
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update settings: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>

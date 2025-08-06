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

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['type'])) {
    echo json_encode(['status' => 'error', 'message' => 'No valid user session']);
    exit;
}

$knock_id = isset($_POST['knock_id']) ? (int)$_POST['knock_id'] : 0;
$response = isset($_POST['response']) ? $_POST['response'] : ''; // 'accept' or 'deny'

if ($knock_id <= 0 || !in_array($response, ['accept', 'deny'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid knock ID or response']);
    exit;
}

// Get user_id_string from session
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$host_name = ($_SESSION['user']['type'] === 'user') ? $_SESSION['user']['username'] : $_SESSION['user']['name'];

if (empty($user_id_string)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user session']);
    exit;
}

// Get knock details and verify host
$stmt = $conn->prepare("
    SELECT rk.*, c.password, c.name as room_name, cu.is_host
    FROM room_knocks rk
    JOIN chatrooms c ON rk.room_id = c.id
    LEFT JOIN chatroom_users cu ON c.id = cu.room_id AND cu.user_id_string = ?
    WHERE rk.id = ? AND rk.status = 'pending'
");
if (!$stmt) {
    error_log("Prepare failed in respond_knock.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("si", $user_id_string, $knock_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Knock not found or already processed']);
    $stmt->close();
    exit;
}
$knock = $result->fetch_assoc();
$stmt->close();

// Verify user is host
if ($knock['is_host'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'Only hosts can respond to knocks']);
    exit;
}

// Update knock status
$new_status = ($response === 'accept') ? 'accepted' : 'denied';
$stmt = $conn->prepare("UPDATE room_knocks SET status = ?, responded_at = NOW() WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed for knock update in respond_knock.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("si", $new_status, $knock_id);
if (!$stmt->execute()) {
    error_log("Execute failed for knock update in respond_knock.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

$knocker_name = $knock['username'] ?: $knock['guest_name'];

if ($response === 'accept') {
    // If accepted, provide the room password to the knocker or auto-join them
    error_log("Knock accepted: knock_id=$knock_id, knocker=$knocker_name, host=$host_name");
    
    echo json_encode([
        'status' => 'success',
        'message' => "Access granted to $knocker_name",
        'action' => 'accepted',
        'room_password' => $knock['password'],
        'knocker_user_id' => $knock['user_id_string'],
        'knocker_name' => $knocker_name
    ]);
} else {
    // If denied, just update the status
    error_log("Knock denied: knock_id=$knock_id, knocker=$knocker_name, host=$host_name");
    
    echo json_encode([
        'status' => 'success',
        'message' => "Access denied to $knocker_name",
        'action' => 'denied',
        'knocker_user_id' => $knock['user_id_string'],
        'knocker_name' => $knocker_name
    ]);
}
?>
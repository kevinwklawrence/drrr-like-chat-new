<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// Get invite codes
$stmt = $conn->prepare("SELECT code, created_at, regenerates_at, is_active 
    FROM invite_codes 
    WHERE owner_user_id = ? 
    ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$invite_codes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get personal keys
$stmt = $conn->prepare("SELECT id, key_value, created_at, last_used, is_active 
    FROM personal_keys 
    WHERE user_id = ? 
    ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$personal_keys = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get restricted status
$stmt = $conn->prepare("SELECT restricted FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$is_restricted = $result['restricted'];
$stmt->close();

// Get usage stats
$stmt = $conn->prepare("SELECT COUNT(*) as total_invites, 
    SUM(account_created) as accounts_created 
    FROM invite_usage 
    WHERE inviter_user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'invite_codes' => $invite_codes,
    'personal_keys' => $personal_keys,
    'is_restricted' => $is_restricted,
    'stats' => $stats
]);

$conn->close();
?>
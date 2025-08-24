<?php
// api/get_site_bans.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Check if user is moderator or admin
$user_id = $_SESSION['user']['id'];
$is_authorized = false;

$stmt = $conn->prepare("SELECT is_moderator, is_admin FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_authorized = ($user_data['is_moderator'] == 1 || $user_data['is_admin'] == 1);
    }
    $stmt->close();
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Only moderators and admins can view site bans']);
    exit;
}

try {
    // Get all active site bans
    $stmt = $conn->prepare("
        SELECT 
            sb.*,
            CASE 
                WHEN sb.ban_until IS NULL THEN 'Permanent'
                WHEN sb.ban_until > NOW() THEN 'Active'
                ELSE 'Expired'
            END as ban_status,
            CASE 
                WHEN sb.ban_until IS NOT NULL AND sb.ban_until > NOW() 
                THEN TIMESTAMPDIFF(MINUTE, NOW(), sb.ban_until)
                ELSE NULL 
            END as minutes_remaining
        FROM site_bans sb
        WHERE sb.ban_until IS NULL OR sb.ban_until > NOW()
        ORDER BY sb.timestamp DESC
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bans = [];
    while ($row = $result->fetch_assoc()) {
        $bans[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'bans' => $bans,
        'count' => count($bans)
    ]);
    
} catch (Exception $e) {
    error_log("Get site bans error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve site bans: ' . $e->getMessage()]);
}

$conn->close();
?>

---

<?php
// api/remove_site_ban.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

include '../db_connect.php';

// Check if user is moderator or admin
$moderator_id = $_SESSION['user']['id'];
$is_authorized = false;

$stmt = $conn->prepare("SELECT is_moderator, is_admin, username FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $moderator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_authorized = ($user_data['is_moderator'] == 1 || $user_data['is_admin'] == 1);
        $moderator_username = $user_data['username'];
    }
    $stmt->close();
}

if (!$is_authorized) {
    echo json_encode(['status' => 'error', 'message' => 'Only moderators and admins can remove site bans']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$ban_id = (int)($_POST['ban_id'] ?? 0);

if ($ban_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Valid ban ID required']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get ban details before removing
    $get_stmt = $conn->prepare("SELECT user_id, username, user_id_string, ip_address FROM site_bans WHERE id = ?");
    if (!$get_stmt) {
        throw new Exception('Failed to prepare get statement: ' . $conn->error);
    }
    
    $get_stmt->bind_param("i", $ban_id);
    $get_stmt->execute();
    $result = $get_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ban not found']);
        $get_stmt->close();
        exit;
    }
    
    $ban_details = $result->fetch_assoc();
    $get_stmt->close();
    
    // Remove the ban
    $remove_stmt = $conn->prepare("DELETE FROM site_bans WHERE id = ?");
    if (!$remove_stmt) {
        throw new Exception('Failed to prepare remove statement: ' . $conn->error);
    }
    
    $remove_stmt->bind_param("i", $ban_id);
    if (!$remove_stmt->execute()) {
        throw new Exception('Failed to remove ban: ' . $remove_stmt->error);
    }
    $remove_stmt->close();
    
    // Log moderator action
    $log_details = "Removed site ban for: " . ($ban_details['username'] ?: $ban_details['user_id_string']) . " (IP: " . $ban_details['ip_address'] . ")";
    
    $log_stmt = $conn->prepare("INSERT INTO moderator_logs (moderator_id, moderator_username, action_type, target_user_id, target_username, target_ip, details) VALUES (?, ?, 'unban', ?, ?, ?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("ississ", $moderator_id, $moderator_username, $ban_details['user_id'], $ban_details['username'], $ban_details['ip_address'], $log_details);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Site ban removed successfully',
        'unbanned_user' => $ban_details['username'] ?: $ban_details['user_id_string']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Remove site ban error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove site ban: ' . $e->getMessage()]);
}

$conn->close();
?>
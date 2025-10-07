<?php
// api/friends.php - FIXED VERSION
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

include '../db_connect.php';

$user_id = $_SESSION['user']['id'];
$action = $_REQUEST['action'] ?? '';

// FIXED: Optional notification function that won't break if table doesn't exist
function createNotification($conn, $to_user_id, $from_user_id, $type, $message = '') {
    try {
        // Check if friend_notifications table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'friend_notifications'");
        if ($table_check && $table_check->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO friend_notifications (user_id, from_user_id, type, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $to_user_id, $from_user_id, $type, $message);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // Silently fail - don't break friend functionality if notifications fail
        error_log("Notification creation failed: " . $e->getMessage());
    }
}

try {
    switch ($action) {
        case 'add':
            $friend_username = trim($_POST['friend_username'] ?? '');
            
            if (empty($friend_username)) {
                echo json_encode(['status' => 'error', 'message' => 'Username required']);
                exit;
            }
            
            // Get friend's user ID
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $friend_username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'User not found']);
                exit;
            }
            
            $friend = $result->fetch_assoc();
            $friend_id = $friend['id'];
            $stmt->close();
            
            if ($friend_id === $user_id) {
                echo json_encode(['status' => 'error', 'message' => 'Cannot add yourself']);
                exit;
            }
            
            // Check if already friends or pending
            $stmt = $conn->prepare("SELECT * FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Already friends or request pending']);
                exit;
            }
            $stmt->close();
            
            // Add friend request
            $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("ii", $user_id, $friend_id);
            $stmt->execute();
            $stmt->close();
            
            // FIXED: Optional notification creation
            createNotification($conn, $friend_id, $user_id, 'friend_request', $_SESSION['user']['username'] . ' sent you a friend request');
            
            echo json_encode(['status' => 'success', 'message' => 'Friend request sent']);
            break;
            
        case 'accept':
    $request_id = (int)($_POST['friend_id'] ?? 0);
    
    if ($request_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request ID']);
        exit;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Fetch and validate request
        $stmt = $conn->prepare("SELECT user_id, friend_id FROM friends WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'No pending request found']);
            exit;
        }
        
        $request_data = $result->fetch_assoc();
        $sender_id = $request_data['user_id'];
        $receiver_id = $request_data['friend_id'];
        $stmt->close();
        
        // Verify authorization
        if ($receiver_id != $user_id) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
            exit;
        }
        
        // Update original request
        $stmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();
        
        // Add reverse friendship
        $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted') ON DUPLICATE KEY UPDATE status = 'accepted'");
        $stmt->bind_param("ii", $user_id, $sender_id);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Create notification (outside transaction)
        createNotification($conn, $sender_id, $user_id, 'friend_accepted', $_SESSION['user']['username'] . ' accepted your friend request');
        
        echo json_encode(['status' => 'success', 'message' => 'Friend request accepted']);
        
    } catch (Exception $e) {
        if ($conn) $conn->rollback();
        error_log("Accept friend error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
    break;
            
        case 'get_notifications':
            // FIXED: Check if table exists before querying
            try {
                $table_check = $conn->query("SHOW TABLES LIKE 'friend_notifications'");
                if ($table_check && $table_check->num_rows > 0) {
                    // Get unread notification count
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM friend_notifications WHERE user_id = ? AND is_read = 0");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['count'];
                    $stmt->close();
                    
                    // Get recent notifications
                    $stmt = $conn->prepare("
                        SELECT fn.*, u.username as from_username, u.avatar as from_avatar 
                        FROM friend_notifications fn
                        JOIN users u ON fn.from_user_id = u.id
                        WHERE fn.user_id = ?
                        ORDER BY fn.created_at DESC
                        LIMIT 10
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $notifications = [];
                    while ($row = $result->fetch_assoc()) {
                        $notifications[] = $row;
                    }
                    $stmt->close();
                    
                    echo json_encode(['status' => 'success', 'count' => $count, 'notifications' => $notifications]);
                } else {
                    echo json_encode(['status' => 'success', 'count' => 0, 'notifications' => []]);
                }
            } catch (Exception $e) {
                echo json_encode(['status' => 'success', 'count' => 0, 'notifications' => []]);
            }
            break;
            
        case 'mark_read':
            // FIXED: Check if table exists before updating
            try {
                $table_check = $conn->query("SHOW TABLES LIKE 'friend_notifications'");
                if ($table_check && $table_check->num_rows > 0) {
                    $stmt = $conn->prepare("UPDATE friend_notifications SET is_read = 1 WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
                echo json_encode(['status' => 'success']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'success']);
            }
            break;
            
        case 'get':
            $stmt = $conn->prepare("
                SELECT 
                    MIN(f.id) as id,
                    CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END as friend_user_id,
                    u.username, 
                    u.avatar, 
                    u.avatar_hue,
                    u.avatar_saturation,
                    f.status,
                    CASE WHEN f.user_id = ? THEN 'sent' ELSE 'received' END as request_type
                FROM friends f 
                JOIN users u ON (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END = u.id)
                WHERE (f.user_id = ? OR f.friend_id = ?)
                GROUP BY 
                    CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END,
                    u.username, 
                    u.avatar, 
                    u.avatar_hue,
                    u.avatar_saturation,
                    f.status
                ORDER BY MIN(f.created_at) DESC
            ");
            $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $friends = [];
            while ($row = $result->fetch_assoc()) {
                $friends[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'friends' => $friends]);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Friends API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
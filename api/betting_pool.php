<?php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Must be in a room']);
    exit;
}

$user_type = $_SESSION['user']['type'];
$user_id = $_SESSION['user']['id'] ?? null;
$user_id_string = $_SESSION['user']['user_id'];
$username = $_SESSION['user']['username'] ?? $_SESSION['user']['guest_name'] ?? 'User';
$room_id = (int)$_SESSION['room_id'];
$action = $_POST['action'] ?? '';

// Helper function to check if user is host/moderator/admin
function canManagePool($conn, $room_id, $user_id, $user_id_string) {
    // Check if user is admin or moderator
    if (isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
        return true;
    }
    if (isset($_SESSION['user']['is_moderator']) && $_SESSION['user']['is_moderator']) {
        return true;
    }

    // Check if user is host of this room
    $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    $stmt->bind_param("is", $room_id, $user_id_string);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['is_host'] == 1;
    }
    $stmt->close();
    return false;
}

try {
    switch ($action) {
        case 'create_pool':
            // Only hosts/moderators/admins can create pools
            if (!canManagePool($conn, $room_id, $user_id, $user_id_string)) {
                echo json_encode(['status' => 'error', 'message' => 'Only hosts, moderators, or admins can create betting pools']);
                exit;
            }

            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($title)) {
                echo json_encode(['status' => 'error', 'message' => 'Pool title is required']);
                exit;
            }

            // Check if there's already an active pool in this room
            $stmt = $conn->prepare("SELECT id FROM betting_pools WHERE room_id = ? AND status = 'active'");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'There is already an active betting pool in this room']);
                $stmt->close();
                exit;
            }
            $stmt->close();

            // Create the pool
            $stmt = $conn->prepare("INSERT INTO betting_pools (room_id, title, description, created_by, created_by_user_id_string, created_by_username, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("ississ", $room_id, $title, $description, $user_id, $user_id_string, $username);
            $stmt->execute();
            $pool_id = $stmt->insert_id;
            $stmt->close();

            // Add system message
            $system_message = "$username created a betting pool: <strong>$title</strong>";
            if (!empty($description)) {
                $system_message .= " - $description";
            }

            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'default_avatar.jpg', 'system')");
            $stmt->bind_param("is", $room_id, $system_message);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'message' => 'Betting pool created',
                'pool_id' => $pool_id
            ]);
            break;

        case 'place_bet':
            $amount = (int)$_POST['amount'];

            if ($amount <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid bet amount']);
                exit;
            }

            // Check if user is registered (guests can't bet with Dura)
            if ($user_type !== 'user') {
                echo json_encode(['status' => 'error', 'message' => 'Only registered users can place bets']);
                exit;
            }

            // Get active pool for this room
            $stmt = $conn->prepare("SELECT id, title FROM betting_pools WHERE room_id = ? AND status = 'active'");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'No active betting pool in this room']);
                $stmt->close();
                exit;
            }
            $pool = $result->fetch_assoc();
            $pool_id = $pool['id'];
            $pool_title = $pool['title'];
            $stmt->close();

            // Check if user already bet on this pool
            $stmt = $conn->prepare("SELECT bet_amount FROM betting_pool_bets WHERE pool_id = ? AND user_id_string = ?");
            $stmt->bind_param("is", $pool_id, $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'You have already placed a bet on this pool']);
                $stmt->close();
                exit;
            }
            $stmt->close();

            // Get user's current Dura balance
            $stmt = $conn->prepare("SELECT dura FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            $current_dura = $user_data['dura'];
            $stmt->close();

            if ($current_dura < $amount) {
                echo json_encode(['status' => 'error', 'message' => 'Not enough Dura']);
                exit;
            }

            // Deduct Dura from user
            $new_dura = $current_dura - $amount;
            $stmt = $conn->prepare("UPDATE users SET dura = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_dura, $user_id);
            $stmt->execute();
            $stmt->close();

            // Add bet to pool
            $stmt = $conn->prepare("INSERT INTO betting_pool_bets (pool_id, user_id, user_id_string, username, bet_amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $pool_id, $user_id, $user_id_string, $username, $amount);
            $stmt->execute();
            $stmt->close();

            // Update pool total
            $stmt = $conn->prepare("UPDATE betting_pools SET total_pool = total_pool + ? WHERE id = ?");
            $stmt->bind_param("ii", $amount, $pool_id);
            $stmt->execute();
            $stmt->close();

            // Update session
            $_SESSION['user']['dura'] = $new_dura;

            // Add system message
            $system_message = "$username placed a bet of 💎 $amount Dura on \"$pool_title\"";
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'default_avatar.jpg', 'system')");
            $stmt->bind_param("is", $room_id, $system_message);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'message' => 'Bet placed successfully',
                'new_balance' => $new_dura
            ]);
            break;

        case 'select_winner':
            // Only hosts/moderators/admins can select winner
            if (!canManagePool($conn, $room_id, $user_id, $user_id_string)) {
                echo json_encode(['status' => 'error', 'message' => 'Only hosts, moderators, or admins can select winners']);
                exit;
            }

            $winner_user_id_string = $_POST['winner_user_id_string'] ?? '';

            if (empty($winner_user_id_string)) {
                echo json_encode(['status' => 'error', 'message' => 'Winner user ID is required']);
                exit;
            }

            // Get active pool for this room
            $stmt = $conn->prepare("SELECT id, title, total_pool FROM betting_pools WHERE room_id = ? AND status = 'active'");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'No active betting pool in this room']);
                $stmt->close();
                exit;
            }
            $pool = $result->fetch_assoc();
            $pool_id = $pool['id'];
            $pool_title = $pool['title'];
            $total_pool = $pool['total_pool'];
            $stmt->close();

            // Verify winner placed a bet
            $stmt = $conn->prepare("SELECT user_id, username, bet_amount FROM betting_pool_bets WHERE pool_id = ? AND user_id_string = ?");
            $stmt->bind_param("is", $pool_id, $winner_user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Selected user has not placed a bet on this pool']);
                $stmt->close();
                exit;
            }
            $winner_data = $result->fetch_assoc();
            $winner_user_id = $winner_data['user_id'];
            $winner_username = $winner_data['username'];
            $stmt->close();

            // Award Dura to winner (only if registered user)
            if ($winner_user_id) {
                $stmt = $conn->prepare("UPDATE users SET dura = dura + ?, lifetime_dura = lifetime_dura + ? WHERE id = ?");
                $stmt->bind_param("iii", $total_pool, $total_pool, $winner_user_id);
                $stmt->execute();
                $stmt->close();
            }

            // Mark pool as completed
            $stmt = $conn->prepare("UPDATE betting_pools SET status = 'completed', winner_user_id_string = ?, winner_username = ?, closed_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $winner_user_id_string, $winner_username, $pool_id);
            $stmt->execute();
            $stmt->close();

            // Add system message
            $system_message = "🏆 $winner_username won the betting pool \"$pool_title\" and received 💎 $total_pool Dura!";
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'default_avatar.jpg', 'system')");
            $stmt->bind_param("is", $room_id, $system_message);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'message' => 'Winner selected and pool completed',
                'winner' => $winner_username,
                'payout' => $total_pool
            ]);
            break;

        case 'close_pool':
            // Only hosts/moderators/admins can close pools
            if (!canManagePool($conn, $room_id, $user_id, $user_id_string)) {
                echo json_encode(['status' => 'error', 'message' => 'Only hosts, moderators, or admins can close pools']);
                exit;
            }

            // Get active pool for this room
            $stmt = $conn->prepare("SELECT id, title, total_pool FROM betting_pools WHERE room_id = ? AND status = 'active'");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'No active betting pool in this room']);
                $stmt->close();
                exit;
            }
            $pool = $result->fetch_assoc();
            $pool_id = $pool['id'];
            $pool_title = $pool['title'];
            $total_pool = $pool['total_pool'];
            $stmt->close();

            // Refund all bets
            $stmt = $conn->prepare("SELECT user_id, bet_amount FROM betting_pool_bets WHERE pool_id = ? AND user_id IS NOT NULL");
            $stmt->bind_param("i", $pool_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($bet = $result->fetch_assoc()) {
                $refund_user_id = $bet['user_id'];
                $refund_amount = $bet['bet_amount'];

                $update_stmt = $conn->prepare("UPDATE users SET dura = dura + ? WHERE id = ?");
                $update_stmt->bind_param("ii", $refund_amount, $refund_user_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            $stmt->close();

            // Mark pool as closed
            $stmt = $conn->prepare("UPDATE betting_pools SET status = 'closed', closed_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $pool_id);
            $stmt->execute();
            $stmt->close();

            // Add system message
            $system_message = "$username closed the betting pool \"$pool_title\". All bets have been refunded.";
            $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'default_avatar.jpg', 'system')");
            $stmt->bind_param("is", $room_id, $system_message);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'message' => 'Pool closed and all bets refunded'
            ]);
            break;

        case 'get_pool_info':
            // Get active pool for this room
            $stmt = $conn->prepare("SELECT * FROM betting_pools WHERE room_id = ? AND status = 'active'");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'success', 'has_pool' => false]);
                $stmt->close();
                exit;
            }

            $pool = $result->fetch_assoc();
            $pool_id = $pool['id'];
            $stmt->close();

            // Get all bets for this pool
            $stmt = $conn->prepare("SELECT user_id_string, username, bet_amount FROM betting_pool_bets WHERE pool_id = ? ORDER BY bet_amount DESC");
            $stmt->bind_param("i", $pool_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $bets = [];
            while ($bet = $result->fetch_assoc()) {
                $bets[] = $bet;
            }
            $stmt->close();

            // Check if current user can manage this pool
            $can_manage = canManagePool($conn, $room_id, $user_id, $user_id_string);

            // Check if current user has bet
            $user_bet = null;
            foreach ($bets as $bet) {
                if ($bet['user_id_string'] === $user_id_string) {
                    $user_bet = $bet['bet_amount'];
                    break;
                }
            }

            echo json_encode([
                'status' => 'success',
                'has_pool' => true,
                'pool' => [
                    'id' => $pool['id'],
                    'title' => $pool['title'],
                    'description' => $pool['description'],
                    'total_pool' => $pool['total_pool'],
                    'created_by' => $pool['created_by_username'],
                    'created_at' => $pool['created_at']
                ],
                'bets' => $bets,
                'can_manage' => $can_manage,
                'user_bet' => $user_bet
            ]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>

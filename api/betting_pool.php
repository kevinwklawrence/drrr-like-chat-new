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
            $min_bet = (int)($_POST['min_bet'] ?? 0);
            
            error_log("RAW POST data: " . print_r($_POST, true));
            error_log("Options POST value: " . ($_POST['options'] ?? 'NOT SET'));
            
            // Decode HTML entities first, then JSON decode
            $options_string = $_POST['options'] ?? '[]';
            $options_string = htmlspecialchars_decode($options_string);
            $options = json_decode($options_string, true) ?: [];

            error_log("Create pool - Options received: " . print_r($options, true));
            error_log("JSON decode error: " . json_last_error_msg());

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
            $stmt = $conn->prepare("INSERT INTO betting_pools (room_id, title, description, min_bet, created_by, created_by_user_id_string, created_by_username, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            if (!$stmt) {
                error_log("Failed to prepare INSERT: " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param("issiiss", $room_id, $title, $description, $min_bet, $user_id, $user_id_string, $username);
            if (!$stmt->execute()) {
                error_log("Failed to execute INSERT: " . $stmt->error);
                echo json_encode(['status' => 'error', 'message' => 'Failed to create pool: ' . $stmt->error]);
                $stmt->close();
                exit;
            }
            $pool_id = $stmt->insert_id;
            $stmt->close();
            error_log("Pool created with ID: $pool_id");

            // Add options if provided (check if table exists first)
            if (!empty($options)) {
                error_log("Options array is not empty, checking table...");
                $table_check = $conn->query("SHOW TABLES LIKE 'betting_pool_options'");
                error_log("Table check result: " . ($table_check && $table_check->num_rows > 0 ? "EXISTS" : "NOT EXISTS"));
                
                if ($table_check && $table_check->num_rows > 0) {
                    error_log("Inserting " . count($options) . " options into betting_pool_options");
                    $opt_stmt = $conn->prepare("INSERT INTO betting_pool_options (pool_id, option_text, option_order) VALUES (?, ?, ?)");
                    foreach ($options as $index => $option) {
                        $option_text = trim($option);
                        if (!empty($option_text)) {
                            error_log("Inserting option #$index: $option_text for pool_id: $pool_id");
                            $opt_stmt->bind_param("isi", $pool_id, $option_text, $index);
                            $opt_stmt->execute();
                            error_log("Option inserted successfully");
                        }
                    }
                    $opt_stmt->close();
                    error_log("All options inserted");
                }
            } else {
                error_log("Options array is empty");
            }

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
            $option_id = isset($_POST['option_id']) ? (int)$_POST['option_id'] : null;

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
            $stmt = $conn->prepare("SELECT id, title, min_bet FROM betting_pools WHERE room_id = ? AND status = 'active'");
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
            $min_bet = (int)$pool['min_bet'];
            $stmt->close();

            // Validate minimum bet
            if ($min_bet > 0 && $amount < $min_bet) {
                echo json_encode(['status' => 'error', 'message' => "Minimum bet is $min_bet Dura"]);
                exit;
            }

            // Check if pool has options (check if table exists first)
            $has_options = false;
            $table_check = $conn->query("SHOW TABLES LIKE 'betting_pool_options'");
            if ($table_check && $table_check->num_rows > 0) {
                $stmt = $conn->prepare("SELECT COUNT(*) as option_count FROM betting_pool_options WHERE pool_id = ?");
                $stmt->bind_param("i", $pool_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $option_data = $result->fetch_assoc();
                $has_options = $option_data['option_count'] > 0;
                $stmt->close();
            }

            // Validate option selection
            if ($has_options && !$option_id) {
                echo json_encode(['status' => 'error', 'message' => 'You must select an option']);
                exit;
            }

            if ($option_id) {
                $stmt = $conn->prepare("SELECT id FROM betting_pool_options WHERE id = ? AND pool_id = ?");
                $stmt->bind_param("ii", $option_id, $pool_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid option']);
                    $stmt->close();
                    exit;
                }
                $stmt->close();
            }

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

            // Add bet to pool with option
            if ($option_id) {
                $stmt = $conn->prepare("INSERT INTO betting_pool_bets (pool_id, option_id, user_id, user_id_string, username, bet_amount) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiissi", $pool_id, $option_id, $user_id, $user_id_string, $username, $amount);
                $stmt->execute();
                $stmt->close();

                // Update option total
                $stmt = $conn->prepare("UPDATE betting_pool_options SET total_bets = total_bets + ? WHERE id = ?");
                $stmt->bind_param("ii", $amount, $option_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO betting_pool_bets (pool_id, user_id, user_id_string, username, bet_amount) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iissi", $pool_id, $user_id, $user_id_string, $username, $amount);
                $stmt->execute();
                $stmt->close();
            }

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
            $winner_option_id = isset($_POST['winner_option_id']) ? (int)$_POST['winner_option_id'] : 0;

            if (empty($winner_user_id_string) && empty($winner_option_id)) {
                echo json_encode(['status' => 'error', 'message' => 'Winner selection is required']);
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

            // Check if this pool has options
            $has_options = false;
            $table_check = $conn->query("SHOW TABLES LIKE 'betting_pool_options'");
            if ($table_check && $table_check->num_rows > 0) {
                $stmt = $conn->prepare("SELECT COUNT(*) as option_count FROM betting_pool_options WHERE pool_id = ?");
                $stmt->bind_param("i", $pool_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $option_data = $result->fetch_assoc();
                $has_options = $option_data['option_count'] > 0;
                $stmt->close();
            }

            // OPTION-BASED POOL: Split among all who bet on winning option
            if ($has_options && $winner_option_id) {
                // Get option name
                $stmt = $conn->prepare("SELECT option_text, total_bets FROM betting_pool_options WHERE id = ? AND pool_id = ?");
                $stmt->bind_param("ii", $winner_option_id, $pool_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid option selected']);
                    $stmt->close();
                    exit;
                }
                $option_data = $result->fetch_assoc();
                $option_text = $option_data['option_text'];
                $option_total = $option_data['total_bets'];
                $stmt->close();

                // Get all users who bet on this option
                $stmt = $conn->prepare("SELECT user_id, user_id_string, username, bet_amount FROM betting_pool_bets WHERE pool_id = ? AND option_id = ?");
                $stmt->bind_param("ii", $pool_id, $winner_option_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $winners = [];
                while ($bet = $result->fetch_assoc()) {
                    $winners[] = $bet;
                }
                $stmt->close();

                if (empty($winners)) {
                    echo json_encode(['status' => 'error', 'message' => 'No one bet on this option']);
                    exit;
                }

                // Calculate and award proportional winnings
                $winner_names = [];
                $total_awarded = 0;
                
                foreach ($winners as $winner) {
                    if ($winner['user_id']) {
                        // Calculate proportional share: (user_bet / option_total) * total_pool
                        $share = floor(($winner['bet_amount'] / $option_total) * $total_pool);
                        $total_awarded += $share;
                        
                        // Award Dura
                        $stmt = $conn->prepare("UPDATE users SET dura = dura + ?, lifetime_dura = lifetime_dura + ? WHERE id = ?");
                        $stmt->bind_param("iii", $share, $share, $winner['user_id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        $winner_names[] = $winner['username'] . " (💎" . $share . ")";
                    }
                }

                // Mark pool as completed
                $winner_list = implode(", ", $winner_names);
                $stmt = $conn->prepare("UPDATE betting_pools SET status = 'completed', winner_username = ?, closed_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $option_text, $pool_id);
                $stmt->execute();
                $stmt->close();

                // Add system message
                $system_message = "🏆 Option \"$option_text\" won the betting pool \"$pool_title\"! Winners: $winner_list";
                $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, '', ?, 1, NOW(), 'default_avatar.jpg', 'system')");
                $stmt->bind_param("is", $room_id, $system_message);
                $stmt->execute();
                $stmt->close();

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Winners awarded and pool completed',
                    'winner' => $option_text,
                    'payout' => $total_awarded,
                    'winners' => count($winners)
                ]);
                break;
            }

            // TRADITIONAL POOL: Single winner gets all
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

            // Get options for this pool (check if table exists first)
            $options = [];
            $table_check = $conn->query("SHOW TABLES LIKE 'betting_pool_options'");
            if ($table_check && $table_check->num_rows > 0) {
                $stmt = $conn->prepare("SELECT id, option_text, total_bets FROM betting_pool_options WHERE pool_id = ? ORDER BY option_order");
                $stmt->bind_param("i", $pool_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($option = $result->fetch_assoc()) {
                    $options[] = [
                        'id' => $option['id'],
                        'text' => $option['option_text'],
                        'total_bets' => $option['total_bets']
                    ];
                }
                $stmt->close();
            }

            // Get all bets for this pool
            $stmt = $conn->prepare("SELECT user_id_string, username, bet_amount, option_id FROM betting_pool_bets WHERE pool_id = ? ORDER BY bet_amount DESC");
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
            $user_option = null;
            foreach ($bets as $bet) {
                if ($bet['user_id_string'] === $user_id_string) {
                    $user_bet = $bet['bet_amount'];
                    $user_option = $bet['option_id'];
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
                    'min_bet' => $pool['min_bet'],
                    'total_pool' => $pool['total_pool'],
                    'created_by' => $pool['created_by_username'],
                    'created_at' => $pool['created_at']
                ],
                'options' => $options,
                'bets' => $bets,
                'can_manage' => $can_manage,
                'user_bet' => $user_bet,
                'user_option' => $user_option
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
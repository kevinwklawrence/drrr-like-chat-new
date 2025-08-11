<?php
session_start();

// Debug session data
error_log("Session data in room.php: " . print_r($_SESSION, true));

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    error_log("Missing user or room_id in session, redirecting to index.php");
    header("Location: index.php");
    exit;
}

include 'db_connect.php';
$room_id = (int)$_SESSION['room_id'];
error_log("room_id in room.php: $room_id"); // Debug

$stmt = $conn->prepare("SELECT name, background FROM chatrooms WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed in room.php: " . $conn->error);
    header("Location: lounge.php");
    exit;
}
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("No room found for room_id: $room_id");
    header("Location: lounge.php");
    exit;
}
$room = $result->fetch_assoc();
$stmt->close();

// Check if current user is host
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$is_host = false;
if (!empty($user_id_string)) {
    $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $is_host = ($user_data['is_host'] == 1);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatroom: <?php echo htmlspecialchars($room['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/room.css" rel="stylesheet">
</head>
<body>
    <div class="room-container">
        <!-- Room Header -->
        <div class="room-header">
            <div class="d-flex justify-content-between align-items-start">
                <div class="room-title">
                    <i class="fas fa-comments"></i>
                    <?php echo htmlspecialchars($room['name']); ?>
                    <?php if ($is_host): ?>
                        <span class="host-badge">
                            <i class="fas fa-crown"></i> Host
                        </span>
                    <?php endif; ?>
                </div>
                <div class="room-actions">
                    <?php if ($is_host): ?>
                        <button class="btn btn-room-settings" onclick="showRoomSettings()">
                            <i class="fas fa-cog"></i> Room Settings
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-leave-room" onclick="leaveRoom()">
                        <i class="fas fa-sign-out-alt"></i> Leave Room
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Main Chat Area -->
            <div class="chat-main">
                <!-- Messages -->
                <div class="chat-messages" id="chatbox">
                    <div class="loading-messages">
                        <div class="loading-spinner"></div>
                        <span>Loading messages...</span>
                    </div>
                </div>
                
                <!-- Message Input -->
                <div class="chat-input-container">
                    <form id="messageForm" class="chat-input-form">
                        <input type="text" 
                               class="form-control chat-input" 
                               id="message" 
                               placeholder="Type your message..." 
                               required>
                        <button type="submit" class="btn btn-send-message">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <div class="sidebar-header">
                    <div class="sidebar-title">
                        <span><i class="fas fa-users"></i> Users</span>
                        <button class="btn btn-test-user" onclick="createTestUser()">
                            <i class="fas fa-plus"></i> Test User
                        </button>
                    </div>
                </div>
                <div class="sidebar-content" id="userList">
                    <div class="loading-messages">
                        <div class="loading-spinner"></div>
                        <span>Loading users...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Knock notifications will appear here -->
    <div id="knockNotifications"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ensure roomId is set
        const roomId = <?php echo json_encode($room_id); ?>;
        const isAdmin = <?php echo isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] ? 'true' : 'false'; ?>;
        const isHost = <?php echo $is_host ? 'true' : 'false'; ?>;
        const currentUserIdString = <?php echo json_encode($_SESSION['user']['user_id'] ?? ''); ?>;
        
        if (!roomId) {
            console.error('roomId is invalid, redirecting to lounge');
            window.location.href = 'lounge.php';
        }
    </script>
    <script src="js/room.js"></script>
</body>
</html>
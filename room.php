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
    <style>
        body {
            background-color: #1a1a1a;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .room-container {
            background: #222;
            border: 1px solid #333;
            border-radius: 15px;
            margin: 20px auto;
            max-width: 1400px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .room-header {
            background: linear-gradient(135deg, #2a2a2a 0%, #3a3a3a 100%);
            border-bottom: 1px solid #404040;
            padding: 20px;
            color: #ffffff;
        }
        
        .room-title {
            color: #ffffff;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .host-badge {
            background: linear-gradient(45deg, #ffc107, #ff9800);
            color: #000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }
        
        .room-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-room-settings {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-room-settings:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        .btn-leave-room {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-leave-room:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            color: white;
        }
        
        .chat-container {
            display: flex;
            height: calc(100vh - 200px);
            min-height: 600px;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #333;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #1a1a1a;
            position: relative;
        }
        
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: #2a2a2a;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 4px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #666;
        }
        
        /* DRRR-Style Chat Bubbles */
        .chat-message {
            margin-bottom: 16px;
            /*animation: messageSlideIn 0.3s ease-out;*/
        }
        
        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-bubble {
            background: var(--user-gradient);
            border-radius: 18px;
            padding: 12px 16px;
            max-width: 85%;
            margin-left: 60px;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .message-bubble::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 12px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 8px 8px 8px 0;
            border-color: transparent var(--user-gradient) transparent transparent;
            filter: drop-shadow(-1px 0 1px rgba(0,0,0,0.1));
        }
        
        .message-avatar {
            position: absolute;
            left: 0;
            top: 0;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 3px solid var(--user-border-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            background: #2a2a2a;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        
        .message-author {
            font-weight: 600;
            color: var(--user-text-color);
            font-size: 0.9rem;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.7);
            opacity: 0.8;
        }
        
        .message-content {
            color: var(--user-text-color);
            line-height: 1.4;
            word-wrap: break-word;
            font-size: 0.95rem;
        }
        
        .message-badges {
            display: flex;
            gap: 4px;
            margin-top: 4px;
        }
        
        .user-badge {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .badge-admin {
            background: linear-gradient(45deg, #dc3545, #c82333);
            color: white;
        }
        
        .badge-host {
            background: linear-gradient(45deg, #ffc107, #ff9800);
            color: #000;
        }
        
        .badge-verified {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
        
        .badge-guest {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            color: white;
        }
        
        /* System Messages */
        .system-message {
            text-align: center;
            margin: 16px 0;
            padding: 8px 16px;
            background: rgba(103, 126, 234, 0.1);
            border-radius: 20px;
            border: 1px solid rgba(103, 126, 234, 0.2);
            color: #677eea;
            font-style: italic;
            font-size: 0.9rem;
        }
        
        .system-message img {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        /* Chat Input */
        .chat-input-container {
            padding: 20px;
            background: #2a2a2a;
            border-top: 1px solid #404040;
        }
        
        .chat-input-form {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .chat-input {
            flex: 1;
            background: #333 !important;
            border: 2px solid #555 !important;
            color: #fff !important;
            border-radius: 25px !important;
            padding: 12px 20px !important;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .chat-input:focus {
            background: #333 !important;
            border-color: #677eea !important;
            color: #fff !important;
            box-shadow: 0 0 0 0.2rem rgba(103, 126, 234, 0.25) !important;
        }
        
        .btn-send-message {
            background: linear-gradient(45deg, #677eea, #764ba2);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            min-width: 100px;
        }
        
        .btn-send-message:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(103, 126, 234, 0.3);
            color: white;
        }
        
        /* Sidebar */
        .chat-sidebar {
            width: 280px;
            background: #2a2a2a;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #404040;
        }
        
        .sidebar-title {
            color: #ffffff;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .btn-test-user {
            background: #404040;
            border: 1px solid #555;
            color: #e0e0e0;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            transition: all 0.2s ease;
        }
        
        .btn-test-user:hover {
            background: #555;
            color: #e0e0e0;
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        .sidebar-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-content::-webkit-scrollbar-track {
            background: #2a2a2a;
        }
        
        .sidebar-content::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 3px;
        }
        
        /* User List */
        .user-item {
            background: #333;
            border: 1px solid #404040;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
        }
        
        .user-item:hover {
            background: #3a3a3a;
            border-color: #555;
            transform: translateY(-1px);
        }
        
        .user-info-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
            border: 2px solid #555;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            color: #ffffff;
            font-weight: 600;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .user-badges-row {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        
        .user-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        
        .btn-ban-user {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            transition: all 0.2s ease;
        }
        
        .btn-ban-user:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
            color: white;
        }
        
        /* Empty States */
        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #666;
            text-align: center;
        }
        
        .empty-chat i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #444;
        }
        
        .empty-users {
            text-align: center;
            color: #666;
            padding: 20px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .room-container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .room-header {
                padding: 15px;
            }
            
            .room-title {
                font-size: 1.2rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .room-actions {
                width: 100%;
                justify-content: stretch;
            }
            
            .room-actions .btn {
                flex: 1;
            }
            
            .chat-container {
                flex-direction: column;
                height: calc(100vh - 180px);
            }
            
            .chat-sidebar {
                width: 100%;
                max-height: 200px;
                border-right: none;
                border-top: 1px solid #333;
            }
            
            .message-bubble {
                max-width: 90%;
                margin-left: 55px;
            }
            
            .message-avatar {
                width: 40px;
                height: 40px;
            }
            
            .chat-input-form {
                flex-direction: column;
                gap: 10px;
            }
            
            .chat-input {
                width: 100%;
            }
            
            .btn-send-message {
                width: 100%;
            }
        }
        
        /* Loading States */
        .loading-messages {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #666;
        }
        
        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #333;
            border-top-color: #666;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* User Color Variables - These will be set dynamically by JavaScript */
        .user-color-1 { --user-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); --user-text-color: #ffffff; --user-border-color: #667eea; }
        .user-color-2 { --user-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); --user-text-color: #ffffff; --user-border-color: #f093fb; }
        .user-color-3 { --user-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); --user-text-color: #000000; --user-border-color: #4facfe; }
        .user-color-4 { --user-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); --user-text-color: #333333; --user-border-color: #a8edea; }
        .user-color-5 { --user-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); --user-text-color: #333333; --user-border-color: #ffecd2; }
        .user-color-6 { --user-gradient: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); --user-text-color: #ffffff; --user-border-color: #a18cd1; }
        .user-color-7 { --user-gradient: linear-gradient(135deg, #fad0c4 0%, #ffd1ff 100%); --user-text-color: #333333; --user-border-color: #fad0c4; }
        .user-color-8 { --user-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); --user-text-color: #333333; --user-border-color: #84fab0; }
        .user-color-9 { --user-gradient: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%); --user-text-color: #333333; --user-border-color: #ffeaa7; }
        .user-color-10 { --user-gradient: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%); --user-text-color: #ffffff; --user-border-color: #74b9ff; }
        
        /* Knock Notifications */
        .knock-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1070;
            max-width: 400px;
            min-width: 350px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border-left: 4px solid #007bff;
            animation: slideInRight 0.3s ease-out;
            background: rgba(42, 42, 42, 0.98);
            border: 1px solid #404040;
            color: #e0e0e0;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
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
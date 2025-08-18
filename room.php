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

// Get room data including YouTube settings
$stmt = $conn->prepare("SELECT name, background, youtube_enabled FROM chatrooms WHERE id = ?");
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

// Get YouTube enabled status
$youtube_enabled = isset($room['youtube_enabled']) ? (bool)$room['youtube_enabled'] : false;
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
    <link href="css/iframe_styler.css" rel="stylesheet">
    <link href="css/private.css" rel="stylesheet">
    <link href="css/whisper.css" rel="stylesheet">
    <link href="css/profile_system.css" rel="stylesheet">
    <link href="css/profile_editor.css" rel="stylesheet">
    <link href="css/profile_editor_colors.css" rel="stylesheet">

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
                    <?php if ($youtube_enabled): ?>
                        <span class="badge bg-danger ms-2">
                            <i class="fab fa-youtube"></i> YouTube Enabled
                        </span>
                    <?php endif; ?>
                </div>
                <div class="room-actions">
                    <?php if ($_SESSION['user']['type'] === 'user'): ?>
        <button class="btn btn-outline-primary" onclick="showFriendsPanel()">
            <i class="fas fa-user-friends"></i> Friends
        </button>
    <?php endif; ?>
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
        <div class="chat-container <?php echo !$youtube_enabled ? 'no-youtube' : ''; ?>">
            <!-- YouTube Player Section (shown when enabled) -->
        <?php if ($youtube_enabled): ?>
        <div class="youtube-player-container enabled" id="youtube-player-container">
            <!-- Player Header -->
            <div class="youtube-player-header">
                <div class="youtube-player-title">
                    <i class="fab fa-youtube text-danger"></i>
                    <span>YouTube Player</span>
                </div>
                <?php if ($is_host): ?>
                <div class="youtube-player-controls">
                    <button class="btn btn-youtube-control" onclick="playVideo()" title="Play">
                        <i class="fas fa-play"></i>
                    </button>
                    <button class="btn btn-youtube-control" onclick="pauseVideo()" title="Pause">
                        <i class="fas fa-pause"></i>
                    </button>
                    <button class="btn btn-youtube-control" onclick="skipToNextVideo()" title="Skip">
                        <i class="fas fa-step-forward"></i>
                    </button>
                    <button class="btn btn-youtube-control btn-danger" onclick="stopVideo()" title="Stop">
                        <i class="fas fa-stop"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Player Wrapper -->
            <div class="youtube-player-wrapper">
                <!-- Video Section -->
                <div class="youtube-video-section">
                    <div id="youtube-player"></div>
                    <div class="youtube-video-info" id="youtube-video-info">
                        <div class="youtube-video-title">No video playing</div>
                        <div class="youtube-video-meta">
                            <span>Select a video or add one to the queue</span>
                        </div>
                    </div>
                </div>
                
                <!-- Queue Section -->
                <div class="youtube-queue-section">
                    <!-- Queue Tabs -->
                     <ul class="nav nav-tabs youtube-queue-tabs d-md-flex d-none" id="youtube-queue-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="queue-tab" data-bs-toggle="tab" data-bs-target="#youtube-queue-pane" type="button" role="tab">
                <i class="fas fa-list"></i> Queue
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="suggestions-tab" data-bs-toggle="tab" data-bs-target="#youtube-suggestions-pane" type="button" role="tab">
                <i class="fas fa-lightbulb"></i> Suggestions
            </button>
        </li>
    </ul>
    <div class="d-md-none mobile-queue-toggles">
        <button class="btn btn-sm btn-outline-light mobile-queue-btn active" onclick="toggleMobileQueue('queue')">
            <i class="fas fa-list"></i> Queue <i class="fas fa-chevron-down"></i>
        </button>
        <button class="btn btn-sm btn-outline-light mobile-queue-btn" onclick="toggleMobileQueue('suggestions')">
            <i class="fas fa-lightbulb"></i> Suggestions <i class="fas fa-chevron-down"></i>
        </button>
    </div>
                    
                    <!-- Queue Content -->
                    <div class="tab-content" id="youtube-queue-content">
                        <!-- Queue List -->
                        <div class="tab-pane fade show active" id="youtube-queue-pane" role="tabpanel">
                            <div class="youtube-queue-content" id="youtube-queue-list">
                                <div class="youtube-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Loading queue...
                                </div>
                            </div>
                        </div>
                        
                        <!-- Suggestions List -->
                        <div class="tab-pane fade" id="youtube-suggestions-pane" role="tabpanel">
                            <div class="youtube-queue-content" id="youtube-suggestions-list">
                                <div class="youtube-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Loading suggestions...
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Suggest Form -->
                    <div class="youtube-suggest-form">
                        <form id="youtube-suggest-form">
                            <input type="text" 
                                   class="form-control youtube-suggest-input" 
                                   id="youtube-suggest-input" 
                                   placeholder="YouTube URL or Video ID..." 
                                   required>
                            <button type="submit" class="btn btn-suggest-video" id="youtube-suggest-btn">
                                <i class="fas fa-plus"></i> Suggest Video
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
        
        </div>
        <?php endif; ?>
        <?php if ($youtube_enabled): ?>
            <button class="btn youtube-player-toggle" onclick="togglePlayerVisibility()" title="Hide Player">
            <i class="fas fa-video-slash"></i>
        </button>
        <?php endif; ?>
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

<!-- AFTER -->
<div class="chat-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">
            <span><i class="fas fa-users"></i> Users</span>
            <button class="btn btn-test-user" onclick="createTestUser()">
                <i class="fas fa-plus"></i> Test User
            </button>
            <button class="btn btn-sm btn-outline-secondary d-md-none mobile-users-toggle" onclick="toggleMobileUsers()">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-content mobile-collapsible" id="userList">
        
        
        
        <!-- YouTube Player Toggle Button -->
        
    </div>
                
    <!-- Friends Panel -->
<div class="friends-panel" id="friendsPanel" style="display: none;">
    <div style="background: #333; padding: 10px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;">
        <h6 style="margin: 0; color: #e0e0e0;"><i class="fas fa-user-friends"></i> Friends & Messages</h6>
        <button type="button" style="background: none; border: none; color: #999; font-size: 18px; cursor: pointer;" onclick="closeFriendsPanel()">&times;</button>
    </div>
    <div style="padding: 15px; max-height: 350px; overflow-y: auto;" id="friendsList">
        Loading friends...
    </div>
</div>

    <!-- Knock notifications will appear here -->
    <div id="knockNotifications"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Global variables
    const roomId = <?php echo json_encode($room_id); ?>;
    const isAdmin = <?php echo isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] ? 'true' : 'false'; ?>;
    const isHost = <?php echo $is_host ? 'true' : 'false'; ?>;
    const currentUserIdString = <?php echo json_encode($_SESSION['user']['user_id'] ?? ''); ?>;
    const youtubeEnabledGlobal = <?php echo $youtube_enabled ? 'true' : 'false'; ?>;
    
    // Add currentUser for private messaging
    const currentUser = <?php echo json_encode(array_merge($_SESSION['user'], [
        'color' => $_SESSION['user']['color'] ?? 'blue'
    ])); ?>;
    
    if (!roomId) {
        console.error('roomId is invalid, redirecting to lounge');
        window.location.href = 'lounge.php';
    }
    
    // YouTube API callback
    window.onYouTubeIframeAPIReady = function() {
        if (typeof initializeYouTubePlayer === 'function') {
            initializeYouTubePlayer();
        }
    };
</script>
    
    <script src="js/room.js"></script>
    <script src="js/profile_system.js"></script>
</body>
</html>
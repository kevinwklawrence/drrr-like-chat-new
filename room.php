<?php
session_start();

require_once 'security_config.php';


// Debug session data
error_log("Session data in room.php: " . print_r($_SESSION, true));

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    error_log("Missing user or room_id in session, redirecting to index.php");
    header("Location: /lounge");
    exit;
}

// First establish database connection
include 'db_connect.php';

// Then check site ban
include 'check_site_ban.php';
checkSiteBan($conn);

$room_id = (int)$_SESSION['room_id'];
error_log("room_id in room.php: $room_id"); // Debug

// Handle invite-only room access
if (isset($_GET['invite']) && !empty($_GET['invite'])) {
    $invite_code = trim($_GET['invite']);
    
    // Find room by invite code
    $invite_stmt = $conn->prepare("SELECT id, name FROM chatrooms WHERE invite_code = ? AND invite_only = 1");
    if ($invite_stmt) {
        $invite_stmt->bind_param("s", $invite_code);
        $invite_stmt->execute();
        $invite_result = $invite_stmt->get_result();
        
        if ($invite_result->num_rows > 0) {
            $invited_room = $invite_result->fetch_assoc();
            
            // Show invite room modal
            echo '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                showInviteRoomModal(' . json_encode($invited_room) . ', ' . json_encode($invite_code) . ');
            });
            
            function showInviteRoomModal(room, inviteCode) {
                const modalHtml = `
                    <div class="modal fade" id="inviteRoomModal" tabindex="-1" data-bs-backdrop="static">
                        <div class="modal-dialog">
                            <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                                <div class="modal-header" style="background: #333; border-bottom: 1px solid #444;">
                                    <h5 class="modal-title">
                                        <i class="fas fa-link"></i> Room Invitation
                                    </h5>
                                </div>
                                <div class="modal-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-door-open fa-3x text-primary mb-3"></i>
                                    </div>
                                    <h4 class="text-primary">${room.name}</h4>
                                    <p class="text-muted">You have been invited to join this room!</p>
                                </div>
                                <div class="modal-footer justify-content-center" style="border-top: 1px solid #444;">
                                    <button type="button" class="btn btn-secondary" onclick="window.location.href=\'lounge.php\'">
                                        <i class="fas fa-arrow-left"></i> Back to Lounge
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="joinInviteRoom(${room.id}, \'${inviteCode}\')">
                                        <i class="fas fa-sign-in-alt"></i> Join Room
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.insertAdjacentHTML("beforeend", modalHtml);
                new bootstrap.Modal(document.getElementById("inviteRoomModal")).show();
            }
            
            function joinInviteRoom(roomId, inviteCode) {
                fetch("api/join_room.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: "room_id=" + roomId + "&invite_code=" + encodeURIComponent(inviteCode)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        window.location.href = "/room";
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Failed to join room");
                });
            }
            </script>';
            
            // Don't continue with normal room loading
            $invite_stmt->close();
            return;
        }
        $invite_stmt->close();
    }
    
    // Invalid invite code
    header("Location: /lounge?error=invalid_invite");
    exit;
}

$stmt = $conn->prepare("SELECT name, background, youtube_enabled, theme, disappearing_messages, message_lifetime_minutes FROM chatrooms WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed in room.php: " . $conn->error);
    header("Location: /lounge");
    exit;
}
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("No room found for room_id: $room_id");
    header("Location: /lounge");
    exit;
}
$room = $result->fetch_assoc();
$stmt->close();

// Get theme and other features
$room_theme = $room['theme'] ?? 'default';
$disappearing_messages = (bool)($room['disappearing_messages'] ?? false);
$message_lifetime_minutes = (int)($room['message_lifetime_minutes'] ?? 0);
$youtube_enabled = isset($room['youtube_enabled']) ? (bool)$room['youtube_enabled'] : false;

// Check if current user is host, admin, and moderator
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$is_host = false;
$is_admin = false;
$is_moderator = false;
$ghost_mode = false;

if ($room_id > 0 && $user_id_string) {
    $verify = $conn->prepare("SELECT 1 FROM chatroom_users WHERE room_id = ? AND user_id_string = ? LIMIT 1");
    $verify->bind_param("is", $room_id, $user_id_string);
    $verify->execute();
    $result = $verify->get_result();
    $verify->close();
    
    if ($result->num_rows === 0) {
        // User is NOT in room - disconnected/kicked
        unset($_SESSION['room_id']);
        header("Location: /lounge?disconnected=1");
        exit;
    }
} else {
    header("Location: /lounge");
    exit;
}

if (!empty($user_id_string)) {
    // Check host status
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
    
    // Check admin, moderator status, and ghost mode (for registered users only)
    if ($_SESSION['user']['type'] === 'user' && isset($_SESSION['user']['id'])) {
        $stmt = $conn->prepare("SELECT is_admin, is_moderator, ghost_mode FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user']['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                $is_admin = ($user_data['is_admin'] == 1);
                $is_moderator = ($user_data['is_moderator'] == 1);
                $ghost_mode = ($user_data['ghost_mode'] == 1);
                
                // Update session with current ghost mode status
                $_SESSION['user']['ghost_mode'] = $ghost_mode;
            }
            $stmt->close();
        }
    }
}

// Get YouTube enabled status
$youtube_enabled = isset($room['youtube_enabled']) ? (bool)$room['youtube_enabled'] : false;
?>
<?php $versions = include 'config/version.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($room['name']); ?> | Duranu</title>
    <?php include 'fav.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/style.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/room.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/iframe_styler.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/room.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/whisper.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/profile_editor.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
     <link href="css/profile_editor_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
     <link href="css/private.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
     <link href="css/profile_system.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
     <link href="css/bubble_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/color_previews.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/private_bubble_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/moderator.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
        <link href="css/mentions_replies.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
        <link href="css/afk.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
        <link href="css/ghost_mode.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
        <link rel="stylesheet" href="css/notifications.css?v=<?php echo $versions['version']; ?>">
        <link rel="stylesheet" href="css/friend_notifications.css?v=<?php echo $versions['version']; ?>">
        



    <?php if ($room_theme !== 'default'): ?>
    <link href="css/themes/<?php echo htmlspecialchars($room_theme); ?>.css" rel="stylesheet">
<?php endif; ?>

<link href="css/loading.css" rel="stylesheet">
</head>
<body>
    <div class="avatar-loader" id="avatarLoader">
    <div class="loader-content">
        <div>Loading content...<hr>
        This may take a bit the first time. Subsequent loads will be much faster.</div>
        <div class="loader-bar"><div class="loader-progress" id="progress"></div></div>
        <div id="status">0 / 0</div>
    </div> 
</div>
<?php include 'navbar.php'; ?>
    <div class="room-container">
        <!-- Room Header -->
        <div class="room-header">
            <div class="header-display justify-content-between align-items-start">
                <div class="room-title">
                    <?php echo htmlspecialchars($room['name']); ?>
                    <?php if ($is_host): ?>
                        <span class="host-badge">
                            <i class="fas fa-crown"></i> Host
                        </span>
                    <?php endif; ?>
                    <?php if ($is_admin): ?>
                        <span class="admin-badge">
                            <i class="fas fa-shield-alt"></i> Admin
                        </span>
                    <?php endif; ?>
                    <?php if ($is_moderator && !$is_admin): ?>
                        <span class="mod-badge">
                            <i class="fas fa-gavel"></i> Moderator
                        </span>
                    <?php endif; ?>
                    <?php if ($ghost_mode): ?>
                        <span class="ghost-badge" title="You are invisible to other users">
                            <i class="fas fa-ghost"></i> Ghost Mode
                        </span>
                    <?php endif; ?>
                    <?php if ($youtube_enabled): ?>
                        <span class="admin-badge">
                            <i class="fab fa-youtube"></i> YouTube Enabled
                        </span>
                    <?php endif; ?>
                </div>
                <div class="room-actions">
                    <button class="btn btn-toggle-afk btn-outline-warning" onclick="toggleAFK()" title="Toggle AFK Status">
                        <i class="fas fa-plane-departure"></i>
                    </button>
                    
                    <!-- Ghost Mode Toggle for Staff -->
                    <?php if ($is_admin || $is_moderator): ?>
                    <button class="btn <?php echo $ghost_mode ? 'btn-secondary' : 'btn-outline-secondary'; ?> ghost-mode-toggle" onclick="toggleGhostMode()" title="Toggle Ghost Mode - Become invisible to other users">
                        <i class="fas fa-ghost"></i> 
                        <?php echo $ghost_mode ? '' : ''; ?>
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($is_admin || $is_moderator): ?>
                        <button class="btn btn-warning" onclick="showAnnouncementModal()">
                            <i class="fas fa-bullhorn"></i>
                        </button>
                         <button class="btn btn-info">
                        <a href="moderator.php" class="text-dark" target="_blank">
                            <i class="fas fa-shield-alt"></i>
                        </a>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user']['type'] === 'user'): ?>
        <button class="btn friends-btn" onclick="showFriendsPanel()">
    <i class="fas fa-user-friends"></i>
    <span class="notification-badge" style="display:none;">0</span>
</button>
    <?php endif; ?>
      
                    <?php if ($is_host): ?>
                        <button class="btn btn-room-settings" onclick="showRoomSettings()">
                            <i class="fas fa-cog"></i>
                        </button>
                    <?php endif; ?>

                    <button class="btn btn-outline-secondary" onclick="openUserSettings()" title="User Settings" aria-label="User Settings">
    <i class="fas fa-cog"></i>
</button>
                    
                    <button class="btn btn-leave-room" onclick="leaveRoom()">
                        <i class="fas fa-sign-out-alt"></i>
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

     <!-- Mobile User List Modal -->
    <div class="modal fade" id="mobileUsersModal" tabindex="-1" aria-labelledby="mobileUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mobileUsersModalLabel">
                        <i class="fas fa-users"></i> Users in Room
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="mobileUserListContent">
                    <!-- User list will be populated here -->
                </div>
            </div>
        </div>
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
// Room theme and feature data
const roomTheme = <?php echo json_encode($room_theme); ?>;
const disappearingMessages = <?php echo $disappearing_messages ? 'true' : 'false'; ?>;
const messageLifetimeMinutes = <?php echo $message_lifetime_minutes; ?>;

// Apply theme class to body
if (roomTheme !== 'default') {
    document.body.classList.add('theme-' + roomTheme);
}
</script>
    <script>
    // Global variables
    const roomId = <?php echo json_encode($room_id); ?>;
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const isModerator = <?php echo $is_moderator ? 'true' : 'false'; ?>;
    const isHost = <?php echo $is_host ? 'true' : 'false'; ?>;
    const currentUserIdString = <?php echo json_encode($_SESSION['user']['user_id'] ?? ''); ?>;
    const youtubeEnabledGlobal = <?php echo $youtube_enabled ? 'true' : 'false'; ?>;
    
    // Add currentUser for private messaging
    const currentUser = <?php echo json_encode(array_merge($_SESSION['user'], [
        'color' => $_SESSION['user']['color'] ?? 'blue',
        'is_admin' => $is_admin,
        'is_moderator' => $is_moderator,
        'ghost_mode' => $ghost_mode
    ])); ?>;
    
    if (!roomId) {
        console.error('roomId is invalid, redirecting to lounge');
        window.location.href = '/lounge';
    }
    
    // YouTube API callback
    window.onYouTubeIframeAPIReady = function() {
        if (typeof initializeYouTubePlayer === 'function') {
            initializeYouTubePlayer();
        }
    };

    // Ghost Mode Toggle Function
    function toggleGhostMode() {
        <?php if (!$is_admin && !$is_moderator): ?>
        alert('Only moderators and administrators can use ghost mode.');
        return;
        <?php endif; ?>
        
        const button = $('.ghost-mode-toggle');
        const originalText = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: 'api/toggle_ghost_mode.php',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Update UI
                    const isGhost = response.ghost_mode;
                    
                    if (isGhost) {
                        button.removeClass('btn-outline-secondary').addClass('btn-secondary');
                        button.html('<i class="fas fa-ghost"></i> Visible');
                        
                        // Add or update ghost mode badge in room header
                        if ($('.badge:contains("Ghost Mode")').length === 0) {
                            $('.room-title').append('<span class="badge bg-secondary ms-2" title="You are invisible to other users"><i class="fas fa-ghost"></i></span>');
                        }
                    } else {
                        button.removeClass('btn-secondary').addClass('btn-outline-secondary');
                        button.html('<i class="fas fa-ghost"></i>');
                        
                        // Remove ghost mode badge
                        $('.badge:contains("Ghost Mode")').remove();
                    }
                    
                    // Update global currentUser object
                    currentUser.ghost_mode = isGhost;
                    
                    // Show success message
                    showToast(response.message, 'info');
                    
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ghost mode toggle error:', error);
                alert('Failed to toggle ghost mode: ' + error);
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    }

    // Add announcement modal functionality
    function showAnnouncementModal() {
        const modalHtml = `
            <div class="modal fade" id="announcementModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                        <div class="modal-header" style="border-bottom: 1px solid #444;">
                            <h5 class="modal-title">
                                <i class="fas fa-bullhorn"></i> Send Site Announcement
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="announcementMessage" class="form-label">Announcement Message</label>
                                <textarea class="form-control" id="announcementMessage" rows="4" maxlength="500" placeholder="Enter your announcement message..." style="background: #333; border: 1px solid #555; color: #fff;"></textarea>
                                <div class="form-text text-muted">Maximum 500 characters. This will be sent to all active rooms.</div>
                            </div>
                        </div>
                        <div class="modal-footer" style="border-top: 1px solid #444;">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-warning" onclick="sendAnnouncement()">
                                <i class="fas fa-bullhorn"></i> Send Announcement
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#announcementModal').remove();
        $('body').append(modalHtml);
        $('#announcementModal').modal('show');
    }

    function sendAnnouncement() {
        const message = $('#announcementMessage').val().trim();
        
        if (!message) {
            alert('Please enter an announcement message');
            return;
        }
        
        const button = $('#announcementModal .btn-warning');
        const originalText = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        
        $.ajax({
            url: 'api/send_announcement.php',
            method: 'POST',
            data: { message: message },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Announcement sent successfully to all rooms!');
                    $('#announcementModal').modal('hide');
                    // Refresh messages if in a room
                    if (typeof loadMessages === 'function') {
                        setTimeout(loadMessages, 1000);
                    }
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to send announcement: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    }
</script>
    <script>
// Declare mentionAutocomplete in global scope
let mentionAutocomplete = null;

// Add mention autocomplete functionality to message input
$(document).ready(function() {
    const messageInput = $('#message');
    
    // Handle @ symbol for mention autocomplete
    messageInput.on('input', function(e) {
        const cursorPos = this.selectionStart;
        const textBefore = this.value.substring(0, cursorPos);
        const lastAtSymbol = textBefore.lastIndexOf('@');
        
        if (lastAtSymbol >= 0) {
            const query = textBefore.substring(lastAtSymbol + 1);
            
            // Only show autocomplete if query is reasonable length and no spaces
            if (query.length >= 0 && query.length <= 20 && !query.includes(' ')) {
                showMentionAutocomplete(query, lastAtSymbol, cursorPos);
            } else {
                hideMentionAutocomplete();
            }
        } else {
            hideMentionAutocomplete();
        }
    });
    
    // Handle keyboard navigation in autocomplete
    messageInput.on('keydown', function(e) {
        if (mentionAutocomplete && mentionAutocomplete.is(':visible')) {
            const items = mentionAutocomplete.find('.mention-autocomplete-item');
            const active = items.filter('.active');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (active.length === 0) {
                    items.first().addClass('active');
                } else {
                    const next = active.removeClass('active').next();
                    if (next.length > 0) {
                        next.addClass('active');
                    } else {
                        items.first().addClass('active');
                    }
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (active.length === 0) {
                    items.last().addClass('active');
                } else {
                    const prev = active.removeClass('active').prev();
                    if (prev.length > 0) {
                        prev.addClass('active');
                    } else {
                        items.last().addClass('active');
                    }
                }
            } else if (e.key === 'Tab' || e.key === 'Enter') {
                e.preventDefault();
                if (active.length > 0) {
                    active.click();
                }
            } else if (e.key === 'Escape') {
                hideMentionAutocomplete();
            }
        }
    });
    
    // Hide autocomplete when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#message, .mention-autocomplete').length) {
            hideMentionAutocomplete();
        }
    });
});

function showMentionAutocomplete(query, startPos, cursorPos) {
    // Get current users in room for autocomplete
    const users = [];
    $('#userList .user-item').each(function() {
        const userName = $(this).find('.user-name').text().trim();
        const userAvatar = $(this).find('.user-avatar').attr('src');
        const userIdString = $(this).find('.user-avatar').attr('onclick');
        
        if (userName && userName.toLowerCase().includes(query.toLowerCase())) {
            users.push({
                name: userName,
                avatar: userAvatar,
                id: userIdString
            });
        }
    });
    
    if (users.length === 0) {
        hideMentionAutocomplete();
        return;
    }
    
    // Create or update autocomplete dropdown
    if (!mentionAutocomplete || mentionAutocomplete.length === 0) {
        mentionAutocomplete = $(`
            <div class="mention-autocomplete" style="
                position: absolute;
                background: #36393f;
                border: 1px solid #40444b;
                border-radius: 8px;
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                box-shadow: 0 4px 8px rgba(0,0,0,0.3);
                min-width: 200px;
            "></div>
        `);
        $('body').append(mentionAutocomplete);
    }
    
    // Populate with users
    let html = '';
    users.slice(0, 6).forEach(user => {
        html += `
            <div class="mention-autocomplete-item" style="
                padding: 8px 12px;
                cursor: pointer;
                display: flex;
                align-items: center;
                transition: background 0.2s;
                color: #dcddde;
            " onmouseover="$(this).addClass('active').siblings().removeClass('active')" 
               onclick="insertMention('${user.name}', ${startPos}, ${cursorPos})">
                <img src="${user.avatar}" style="width: 20px; height: 20px; border-radius: 10px; margin-right: 8px;">
                <span>${user.name}</span>
            </div>
        `;
    });
    
    mentionAutocomplete.html(html);
    
    // Position the autocomplete
    const messageInput = $('#message');
    const inputPos = messageInput.offset();
    const inputHeight = messageInput.outerHeight();
    
    mentionAutocomplete.css({
        top: inputPos.top - mentionAutocomplete.outerHeight() - 5,
        left: inputPos.left,
        display: 'block'
    });
    
    // Add hover effects
    mentionAutocomplete.find('.mention-autocomplete-item').hover(
        function() { $(this).css('background', '#40444b'); },
        function() { $(this).css('background', 'transparent'); }
    );
}

function hideMentionAutocomplete() {
    if (mentionAutocomplete && mentionAutocomplete.length > 0) {
        mentionAutocomplete.hide();
    }
}

function insertMention(username, startPos, cursorPos) {
    const messageInput = $('#message')[0];
    const currentValue = messageInput.value;
    const beforeAt = currentValue.substring(0, startPos);
    const afterCursor = currentValue.substring(cursorPos);
    
    const newValue = beforeAt + '@' + username + ' ' + afterCursor;
    messageInput.value = newValue;
    
    // Set cursor position after the mention
    const newCursorPos = startPos + username.length + 2;
    messageInput.setSelectionRange(newCursorPos, newCursorPos);
    
    hideMentionAutocomplete();
    messageInput.focus();
}

// Add custom CSS for autocomplete active state
$('<style>').text(`
    .mention-autocomplete-item.active {
        background: #5865f2 !important;
        color: #ffffff !important;
    }
`).appendTo('head');
</script>
    <script src="js/room.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/profile_system.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/loading.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/notifications.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/friend_notifications.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/inactivity_warning.js"></script>
    <script src="js/disconnect_checker.js"></script>
    


    <?php include 'user_settings.php'; ?>
</body>
</html>
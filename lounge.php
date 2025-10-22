<?php
session_start();

require_once 'security_config.php';

include 'db_connect.php';
include 'check_invite_access.php';
include 'check_site_ban.php';
checkSiteBan($conn, true);

if (!isset($_SESSION['user'])) {
    header("Location: /guest");
    exit;
}

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$username = $_SESSION['user']['username'] ?? null;
$guest_name = $_SESSION['user']['name'] ?? null;
$avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
$is_admin = $_SESSION['user']['is_admin'] ?? 0;
$is_moderator = $_SESSION['user']['is_moderator'] ?? 0;
$ip_address = $_SERVER['REMOTE_ADDR'];
$color = $_SESSION['user']['color'] ?? 'black';

// Check ghost mode status for admins/moderators
$ghost_mode = false;
if (($_SESSION['user']['type'] === 'user') && ($is_admin || $is_moderator) && isset($_SESSION['user']['id'])) {
    $ghost_check = $conn->prepare("SELECT ghost_mode FROM users WHERE id = ?");
    if ($ghost_check) {
        $ghost_check->bind_param("i", $_SESSION['user']['id']);
        $ghost_check->execute();
        $ghost_result = $ghost_check->get_result();
        if ($ghost_result->num_rows > 0) {
            $ghost_data = $ghost_result->fetch_assoc();
            $ghost_mode = (bool)$ghost_data['ghost_mode'];
            $_SESSION['user']['ghost_mode'] = $ghost_mode; // Update session
        }
        $ghost_check->close();
    }
}

// Handle invite processing (existing code)
if (isset($_GET['invite']) && !empty($_GET['invite'])) {
    $invite_code = trim($_GET['invite']);
    error_log("INVITE_LINK: Processing invite code: $invite_code");
    
    $invite_stmt = $conn->prepare("SELECT id, name, invite_code FROM chatrooms WHERE invite_code = ?");
    if ($invite_stmt) {
        $invite_stmt->bind_param("s", $invite_code);
        $invite_stmt->execute();
        $invite_result = $invite_stmt->get_result();
        
        if ($invite_result->num_rows > 0) {
            $invited_room = $invite_result->fetch_assoc();
            error_log("INVITE_LINK: Found room: " . $invited_room['name']);
            
            $_SESSION['pending_invite'] = [
                'room_id' => $invited_room['id'],
                'room_name' => $invited_room['name'],
                'invite_code' => $invite_code
            ];
        } else {
            error_log("INVITE_LINK: Invalid invite code: $invite_code");
            header("Location: /lounge?error=invalid_invite");
            exit;
        }
        $invite_stmt->close();
    }
}

// Update global_users (respect ghost mode)
if (!empty($user_id_string)) {
    error_log("LOUNGE.PHP: Updating global_users for user: $user_id_string");
    
    $existing_hue = $_SESSION['user']['avatar_hue'] ?? 0;
    $existing_sat = $_SESSION['user']['avatar_saturation'] ?? 100;
    
    // Only add to global_users if not in ghost mode
    if (!$ghost_mode) {
        $stmt = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, guest_avatar, is_admin, is_moderator, ip_address, color, avatar_hue, avatar_saturation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username = VALUES(username), guest_name = VALUES(guest_name), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), is_admin = VALUES(is_admin), is_moderator = VALUES(is_moderator), ip_address = VALUES(ip_address), color = VALUES(color), last_activity = CURRENT_TIMESTAMP");
        if ($stmt) {
            $stmt->bind_param("ssssissssii", $user_id_string, $username, $guest_name, $avatar, $avatar, $is_admin, $is_moderator, $ip_address, $color, $existing_hue, $existing_sat);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // Remove from global_users if in ghost mode
        $stmt = $conn->prepare("DELETE FROM global_users WHERE user_id_string = ?");
        if ($stmt) {
            $stmt->bind_param("s", $user_id_string);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>
<?php $versions = include 'config/version.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lounge | Duranu</title>
    <?php include 'fav.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/style.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/lounge.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/room.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/profile_editor.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
     <link href="css/profile_editor_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
     <link href="css/private.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
     <link href="css/profile_system.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
     <link href="css/bubble_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/color_previews.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/private_bubble_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/room_stuff.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/moderator.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
<link href="css/loading.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
<link rel="stylesheet" href="css/friend_notifications.css?v=<?php echo $versions['version']; ?>">
<link rel="stylesheet" href="css/friends-sidebar.css?v=<?php echo $versions['version']; ?>">
<link rel="stylesheet" href="css/draggable-dm-modal.css?v=<?php echo $versions['version']; ?>">
<style>
    .notice-link {
        color: #ffe1b3;
    }
    .notice {
        font-size: 12px;
    text-align: center;
    margin-top: 10px;
    margin-bottom: 10px;
    background: #ffa50054;
    border: 2px solid orange;
    border-radius: 12px;
    box-shadow: 0 0 8px 0px orange;
    padding: 12px;
    }
    </style>
</head>
<body<?php if ($_SESSION['user']['type'] === 'user') echo ' class="has-friends-sidebar"'; ?>>
    <div class="avatar-loader" id="avatarLoader">
    <div class="loader-content">
        <div>Loading content...<hr>
        This may take a bit the first time. Subsequent loads will be much faster.</div>
        <div class="loader-bar"><div class="loader-progress" id="progress"></div></div>
        <div id="status">0 / 0</div>
    </div>
</div>
<?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background: #2a2a2a; border: 1px solid #404040;">
            <div class="modal-header" style="border-bottom: 1px solid #404040;">
                <h5 class="modal-title" id="filterModalLabel" style="color: #fff;">
                    <i class="fas fa-filter"></i> Room Filters
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body">
                <!-- Search Inputs -->
                <div class="row mb-3">
                    <div class="col-md-4 col-12 mb-2">
                        <label class="form-label text-muted small">
                            <i class="fas fa-search"></i> Room Name
                        </label>
                        <input type="text" class="form-control" id="filterRoomName" placeholder="Search by room name..." style="background: #333; border: 1px solid #555; color: #fff;">
                    </div>
                    <div class="col-md-4 col-12 mb-2">
                        <label class="form-label text-muted small">
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <input type="text" class="form-control" id="filterDescription" placeholder="Search description..." style="background: #333; border: 1px solid #555; color: #fff;">
                    </div>
                    <div class="col-md-4 col-12 mb-2">
                        <label class="form-label text-muted small">
                            <i class="fas fa-user"></i> User in Room
                        </label>
                        <input type="text" class="form-control" id="filterUsername" placeholder="Search by user..." style="background: #333; border: 1px solid #555; color: #fff;">
                    </div>
                </div>

                <hr style="border-color: #404040;">

                <!-- Tag Filters -->
                <div class="mb-3">
                    <label class="form-label" style="color: #e0e0e0; font-weight: 500;">
                        <i class="fas fa-tags"></i> Room Tags & Features
                    </label>
                    <div class="filter-tags d-flex flex-wrap gap-2">
                        <button class="btn btn-sm filter-tag-btn" data-filter="rp" onclick="toggleTagFilter('rp')">
                            <i class="fas fa-theater-masks"></i> Roleplay
                        </button>
                        <button class="btn btn-sm filter-tag-btn" data-filter="youtube" onclick="toggleTagFilter('youtube')">
                            <i class="fab fa-youtube"></i> YouTube
                        </button>
                        <button class="btn btn-sm filter-tag-btn" data-filter="permanent" onclick="toggleTagFilter('permanent')">
                            <i class="fas fa-star"></i> Permanent
                        </button>
                    </div>
                </div>

                <!-- Room Settings Filters -->
                <div class="mb-3">
                    <label class="form-label" style="color: #e0e0e0; font-weight: 500;">
                        <i class="fas fa-cog"></i> Room Settings
                    </label>
                    <div class="filter-tags d-flex flex-wrap gap-2">
                        <button class="btn btn-sm filter-tag-btn" data-filter="password" onclick="toggleTagFilter('password')">
                            <i class="fas fa-lock"></i> Password Protected
                        </button>
                        <button class="btn btn-sm filter-tag-btn" data-filter="friends" onclick="toggleTagFilter('friends')">
                            <i class="fas fa-user-friends"></i> Friends Only
                        </button>
                        <button class="btn btn-sm filter-tag-btn" data-filter="members" onclick="toggleTagFilter('members')">
                            <i class="fas fa-id-badge"></i> Members Only
                        </button>
                    </div>
                </div>

                <!-- Special Filters (Members Only) -->
                <?php if ($_SESSION['user']['type'] === 'user'): ?>
                <div class="mb-3">
                    <label class="form-label" style="color: #e0e0e0; font-weight: 500;">
                        <i class="fas fa-user-check"></i> Member Filters
                    </label>
                    <div class="filter-tags d-flex flex-wrap gap-2">
                        <button class="btn btn-sm filter-tag-btn" data-filter="with-friends" onclick="toggleTagFilter('with-friends')">
                            <i class="fas fa-users"></i> Rooms with Friends
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <hr style="border-color: #404040;">

                <!-- Invite Code Section -->
                

                <!-- Filter Status -->
                <div class="alert alert-info mb-0" style="background: #1a2332; border: 1px solid #2d5a8f;">
                    <i class="fas fa-info-circle"></i> <span id="filterResultCount">Showing all public rooms</span>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #404040;">
                <button class="btn btn-outline-danger" onclick="clearAllFilters()">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="fas fa-check"></i> Apply Filters
                </button>
            </div>
        </div>
    </div>
</div>
        <div class="lounge-container">
            <!-- Header -->
            <div class="lounge-header">
                <div class="header-centered-logo">
                    <img src="images/duranu.png" alt="Duranu Logo" class="site-logo">
                    <h1 class="lounge-title h4">
                        <i class="fas fa-comments"></i> Chat Lounge
                    </h1>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-12 scary_thing"><p>welcome to my chatroom :D</p></div>
                <!-- User Profile Sidebar -->
                <div class="col-lg-3">
                    <div class="user-profile-card">
                        <div class="text-center">
                            <img src="images/<?php echo htmlspecialchars($avatar); ?>" 
     class="avatar-selector" 
     id="currentAvatar" 
     style="filter: hue-rotate(<?php echo $_SESSION['user']['avatar_hue'] ?? 0; ?>deg) saturate(<?php echo $_SESSION['user']['avatar_saturation'] ?? 100; ?>%);"
     onclick="showProfileEditor()"
     alt="Your avatar">
                            <div class="mt-3">
                                <h5 class="mb-1"><?php echo htmlspecialchars($guest_name ?? $username ?? 'User'); ?></h5>
                                <small class="text-muted">
                                    
                                    <?php echo $_SESSION['user']['type'] === 'guest' ? 'Guest User' : 'Registered User'; ?>
                                    <br>
                                    <?php if ($is_admin): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php endif; ?>
                                    <?php if ($is_moderator && !$is_admin): ?>
                                        <span class="badge bg-warning">Moderator</span>
                                    <?php endif; ?>
                                    <?php if ($ghost_mode): ?>
                                        <span class="badge bg-secondary"><i class="fas fa-ghost"></i> Ghost Mode</span>
                                    <?php endif; ?>
                                    <br>
                                </small>
                            </div>
                        </div>
                                            
                        <div class="profile-action-buttons">
                        <!--<button class="btn change-avatar-btn w-100" onclick="showAvatarSelector()">
                            <i class="fas fa-user-edit"></i>
                        </button>-->
                        <?php if ($_SESSION['user']['type'] === 'user'): ?>
                        <button class="btn friends-btn w-100" onclick="showFriendsPanel()">
    <i class="fas fa-user-friends"></i>
    <span class="notification-badge" style="display:none;">0</span>
</button>
                        <?php endif; ?>
                        <div class="form-text text-muted mt-3">
                                <i class="fas fa-info-circle"></i> <small>Change your appearance and profile by clicking your avatar above.</small>
                            </div>
                        </div>
                    </div>

                    
                    
                    <!-- Online Users -->
                    <div class="card online-users-card">
                        <div class="card-header">
                            <i class="fas fa-users"></i> Active Users
                        </div>
                        <div class="card-body" id="onlineUsersList">
                            <div class="text-center">
                                <div class="loading-spinner me-2"></div>
                                <span class="text-muted">Loading users...</span>
                            </div>
                        </div>
                    </div>
                    <div class="notice"><h4>Notice:</h4><small>Want to support us? Consider donating.
                        <script src='https://storage.ko-fi.com/cdn/scripts/overlay-widget.js'></script>
<script>
  kofiWidgetOverlay.draw('duranu', {
    'type': 'floating-chat',
    'floating-chat.donateButton.text': 'Donate',
    'floating-chat.donateButton.background-color': '#323842',
    'floating-chat.donateButton.text-color': '#fff'
  });
</script>
<br>Contact directly at: <a class="notice-link" href="mailto:admin@duranu.net">admin@duranu.net</a>.
<br>Report bugs at: <a class="notice-link" href="mailto:bugs@duranu.net">bugs@duranu.net</a>.
<br>Send feature requests at: <a class="notice-link" href="mailto:request@duranu.net">request@duranu.net</a>.
</small>

</div>
                </div>
                
                <!-- Rooms List -->
                <div class="col-lg-9">
                    <div class="rooms-section-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="rooms-section-title-container">
                            <h3 class="rooms-section-title">
                                <i class="fas fa-door-open"></i> Rooms
                                <!--<button class="btn btn-outline-secondary refresh-btn" onclick="loadRoomsWithUsers()">
                                <i class="fas fa-sync-alt"></i>
                            </button>-->
                            </h3>
                            
                            </div>
                            <div>
                                <?php if ($is_admin || $is_moderator): ?>
                        <button class="btn <?php echo $ghost_mode ? 'btn-secondary' : 'btn-outline-secondary'; ?> me-2" onclick="toggleGhostMode()">
                            <i class="fas fa-ghost"></i> 
                            <?php echo $ghost_mode ? '' : ''; ?>
                        </button>
                        <?php endif; ?>
                                <?php if ($is_admin || $is_moderator): ?>
            <button class="btn btn-warning me-2" onclick="showAnnouncementModal()">
                <i class="fas fa-bullhorn"></i>
            </button>
            <button class="btn btn-info me-2">
            <a href="moderator.php" class="text-dark">
                <i class="fas fa-shield-alt"></i>
            </a>
            </button>
        <?php endif; ?>

        <button class="btn create-room-btn me-2" onclick="showCreateRoomModal()">
            <i class="fas fa-plus"></i>
        </button>
        <a href="logout.php" class="btn logout-btn">
            <i class="fas fa-sign-out-alt"></i>
        </a>
                            
                            </div>
                        </div>
                    </div>
                    
<div class="mb-3">
                    <label class="form-label" style="color: #e0e0e0; font-weight: 500;">
                        <i class="fas fa-key"></i> Join Room with Invite Code
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="inviteCodeInput" placeholder="Enter invite code or link..." style="background: #333; border: 1px solid #555; color: #fff;">
                        <button class="btn btn-success" onclick="joinRoomByInvite()">
                            <i class="fas fa-sign-in-alt"></i> Join
                        </button>
                        
                    </div>
                    <!--<button class="btn btn-outline-secondary me-2" onclick="showFilterModal()">
                    <i class="fas fa-filter"></i> Filters
                    <span class="badge bg-primary ms-1" id="activeFilterCount" style="display: none;">0</span>
                </button>-->
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> You can paste invite links like: lounge.php?invite=abc123
                    </small>
                    
                </div>
                    <div id="roomsList">
                        <div class="text-center py-5">
                            <div class="loading-spinner mb-3" style="width: 30px; height: 30px;"></div>
                            <p class="text-muted">Loading rooms...</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12 scary_thing2"><p>boo!</p></div>
            </div>
        </div>
    </div>

    <!-- Friends Sidebar (Desktop) - Members Only -->
    <?php if ($_SESSION['user']['type'] === 'user'): ?>
    <aside class="friends-sidebar" id="friendsSidebar">
        <div class="friends-sidebar-header">
            <h5><i class="fas fa-user-friends"></i> Friends & Messages</h5>
        </div>
        <div class="friends-sidebar-content" id="friendsSidebarContent">
            <!-- Friend Requests Section -->
            <div class="sidebar-section" id="friendRequestsSection" style="display: none;">
                <div class="sidebar-section-title">
                    <span><i class="fas fa-user-plus"></i> Friend Requests</span>
                    <span class="sidebar-section-count" id="friendRequestsCount">0</span>
                </div>
                <div id="friendRequestsList"></div>
            </div>

            <!-- Recent Conversations Section -->
            <div class="sidebar-section" id="conversationsSection">
                <div class="sidebar-section-title">
                    <span><i class="fas fa-comments"></i> Conversations</span>
                </div>
                <div id="conversationsList">
                    <div class="sidebar-empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No conversations yet</p>
                    </div>
                </div>
            </div>

            <!-- Friends Section -->
            <div class="sidebar-section" id="friendsSection">
                <div class="sidebar-section-title">
                    <span><i class="fas fa-users"></i> Friends</span>
                    <span class="sidebar-section-count" id="friendsCount">0</span>
                </div>
                <form class="add-friend-form" onsubmit="addFriend(event)">
                    <input type="text" class="add-friend-input" id="addFriendInput" placeholder="Add friend by username..." required>
                    <button type="submit" class="add-friend-btn">
                        <i class="fas fa-user-plus"></i>
                    </button>
                </form>
                <div id="friendsList">
                    <div class="sidebar-empty-state">
                        <i class="fas fa-user-friends"></i>
                        <p>No friends yet</p>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Friends Mobile Modal (Mobile) -->
    <div class="modal fade friends-mobile-modal" id="friendsMobileModal" tabindex="-1" aria-labelledby="friendsMobileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="friendsMobileModalLabel">
                        <i class="fas fa-user-friends"></i> Friends & Messages
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body" id="friendsMobileContent">
                    <!-- Same content as desktop sidebar will be synced here -->
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Draggable DM Modal (For both desktop and mobile) -->
    <?php if ($_SESSION['user']['type'] === 'user'): ?>
    <div class="dm-modal-container hidden" id="dmModal">
        <div class="dm-modal-header">
            <div class="dm-modal-header-left">
                <i class="fas fa-grip-vertical dm-modal-drag-handle"></i>
                <h6 class="dm-modal-title">
                    <i class="fas fa-envelope"></i> Messages
                    <span class="dm-modal-recipient-info" id="dmRecipientInfo"></span>
                </h6>
            </div>
            <div class="dm-modal-header-actions">
                <button class="dm-modal-action-btn minimize" onclick="toggleDMModal()" title="Minimize">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="dm-modal-action-btn close" onclick="closeDMModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="dm-modal-tabs">
            <button class="dm-modal-tab active" data-tab="private-messages" onclick="switchDMTab('private-messages')">
                <i class="fas fa-envelope"></i> Private Messages
                <span class="dm-modal-tab-badge" id="pmUnreadBadge" style="display: none;">0</span>
            </button>
            <button class="dm-modal-tab" data-tab="whispers" onclick="switchDMTab('whispers')">
                <i class="fas fa-comment-dots"></i> Whispers
                <span class="dm-modal-tab-badge" id="whispersUnreadBadge" style="display: none;">0</span>
            </button>
        </div>

        <div class="dm-modal-body">
            <!-- Private Messages Tab -->
            <div class="dm-tab-content active" id="privateMessagesTab">
                <div class="dm-conversations-list" id="dmConversationsList">
                    <div class="dm-empty-state">
                        <i class="fas fa-inbox"></i>
                        <p class="dm-empty-state-title">No conversations</p>
                        <p class="dm-empty-state-text">Start a conversation with a friend!</p>
                    </div>
                </div>

                <!-- Message view (hidden by default) -->
                <div class="dm-messages-container" id="dmMessagesContainer">
                    <div class="dm-messages-header">
                        <button class="dm-back-btn" onclick="closeDMConversation()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <span class="dm-recipient-name" id="dmCurrentRecipient"></span>
                    </div>
                    <div class="dm-messages-list" id="dmMessagesList"></div>
                </div>
            </div>

            <!-- Whispers Tab -->
            <div class="dm-tab-content" id="whispersTab">
                <div class="dm-whispers-list" id="dmWhispersList">
                    <div class="dm-empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <p class="dm-empty-state-title">No whispers</p>
                        <p class="dm-empty-state-text">Whispers are room-specific private messages</p>
                    </div>
                </div>

                <!-- Whisper view (hidden by default) -->
                <div class="dm-messages-container" id="dmWhispersContainer" style="display: none;">
                    <div class="dm-messages-header">
                        <button class="dm-back-btn" onclick="closeWhisperConversation()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <span class="dm-recipient-name" id="whisperCurrentRecipient"></span>
                    </div>
                    <div class="dm-messages-list" id="whisperMessagesList"></div>
                </div>
            </div>
        </div>

        <div class="dm-modal-footer">
            <form class="dm-input-form" onsubmit="sendDMMessage(event)" id="dmInputForm">
                <input type="text" class="dm-input" id="dmMessageInput" placeholder="Type a message..." required>
                <button type="submit" class="dm-send-btn">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast Notifications Container -->
    <div class="notification-toast-container" id="notificationToastContainer"></div>


<!-- Introduction Modal (Add before closing </body> in lounge.php) -->
<div class="modal fade" id="introductionModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: #2a2a2a; color: #e0e0e0; border: 1px solid #444;">
            <div class="modal-header" style="border-bottom: 1px solid #444;">
                <h5 class="modal-title">
                    <i class="fas fa-rocket"></i> Welcome to Duranu Chat!
                </h5>
            </div>
            <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-user-check" style="font-size: 4rem; color: #4CAF50;"></i>
                    <h4 style="margin-top: 15px; color: #4CAF50;">Account Created Successfully!</h4>
                    <p class="text-muted">Let's get you started with the basics</p>
                </div>

                <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                    <h6><i class="fas fa-user-edit"></i> Edit Your Profile</h6>
                    <p style="margin-bottom: 0;">Click the <strong>Settings</strong> button in the navigation to customize your profile. You can:</p>
                    <ul style="line-height: 1.8; margin-top: 10px;">
                        <li>Change your avatar</li>
                        <li>Customize avatar and bubble colors</li>
                        <li>View and manage your invite codes</li>
                        <li>Create personal keys for quick login</li>
                    </ul>
                </div>

                <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                    <h6><i class="fas fa-door-open"></i> Create Your First Room</h6>
                    <p style="margin-bottom: 0;">Click the <strong>Create Room</strong> button to start your own chat room. You can:</p>
                    <ul style="line-height: 1.8; margin-top: 10px;">
                        <li>Set a custom name and description</li>
                        <li>Add password protection</li>
                        <li>Control who can join (friends only, members only)</li>
                        <li>Customize room themes</li>
                    </ul>
                </div>

                <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                    <h6><i class="fas fa-comments"></i> Join Existing Rooms</h6>
                    <p style="margin-bottom: 0;">Browse the room list below and click <strong>Join</strong> to start chatting!</p>
                </div>

                <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px;">
                    <h6><i class="fas fa-user-friends"></i> Make Friends</h6>
                    <p style="margin-bottom: 0;">Click on any user's avatar to view their profile and send them a friend request. Friends can:</p>
                    <ul style="line-height: 1.8; margin-top: 10px;">
                        <li>Send private messages</li>
                        <li>Join friends-only rooms</li>
                        <li>See when each other is online</li>
                    </ul>
                </div>

                <div class="alert" style="background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.3); color: #a5d6a7; margin-top: 20px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Pro Tip:</strong> You received your own personal key during registration. Use it to log in quickly without entering your username and password!
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #444;">
                <button type="button" class="btn btn-success btn-lg w-100" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Let's Go!
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Show introduction modal if flag is set
<?php if (isset($_SESSION['show_introduction']) && $_SESSION['show_introduction']): ?>
$(document).ready(function() {
    $('#introductionModal').modal('show');
    
    // Remove flag after showing
    $.post('api/clear_introduction_flag.php');
});
<?php endif; ?>
</script>
    
    <!-- Knock notifications will appear here -->
    <div id="knockNotifications"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const currentUser = <?php echo json_encode(array_merge($_SESSION['user'], [
        'color' => $color,
        'ghost_mode' => $ghost_mode
    ])); ?>;
    
    // Ghost Mode Toggle Function
    function toggleGhostMode() {
        <?php if (!$is_admin && !$is_moderator): ?>
        alert('Only moderators and administrators can use ghost mode.');
        return;
        <?php endif; ?>
        
        const button = $('button[onclick="toggleGhostMode()"]');
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
                        button.html('<i class="fas fa-ghost"></i>');
                        
                        // Add ghost mode badge if it doesn't exist
                        if ($('.badge:contains("Ghost Mode")').length === 0) {
                            $('.text-muted').append('<span class="badge bg-secondary"><i class="fas fa-ghost"></i> Ghost Mode</span>');
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
                    alert(response.message);
                    
                    // Refresh online users list after a short delay to see the effect
                    setTimeout(() => {
                        loadOnlineUsers();
                    }, 1000);
                    
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
</script>

    <script>
<?php if (isset($_SESSION['pending_invite'])): ?>
// Show invite modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    const inviteData = <?php echo json_encode($_SESSION['pending_invite']); ?>;
    <?php unset($_SESSION['pending_invite']); ?>
    
    joinInviteRoom(inviteData.room_id, inviteData.invite_code);
});

function showInviteModal(inviteData) {
    const modalHtml = `
        <div class="modal fade" id="inviteModal" tabindex="-1" data-bs-backdrop="static">
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
                        <h4 class="text-primary">${inviteData.room_name}</h4>
                        <p class="text-muted">You have been invited to join this private room!</p>
                        <div class="alert alert-info" style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.3);">
                            <i class="fas fa-info-circle"></i> This is an invite-only room that requires a special link to access.
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" onclick="$('#inviteModal').modal('hide')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="joinInviteRoom(${inviteData.room_id}, '${inviteData.invite_code}')">
                            <i class="fas fa-sign-in-alt"></i> Join Room
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modalHtml);
    $('#inviteModal').modal('show');
}

function joinInviteRoom(roomId, inviteCode) {
    console.log('joinInviteRoom called with:', {roomId, inviteCode});
    
    $.ajax({
        url: 'api/join_room.php',
        method: 'POST',
        data: {
            room_id: roomId,
            invite_code: inviteCode
        },
        dataType: 'json',
        success: function(response) {
            console.log('Response:', response);
            if (response.status === 'success') {
                window.location.href = '/room';
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('Failed to join room: ' + error);
        }
    });
}
<?php endif; ?>

// Handle error messages
<?php if (isset($_GET['error'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($_GET['error'] === 'invalid_invite'): ?>
    alert('Invalid or expired invite link. Please check the link and try again.');
    <?php endif; ?>
    
    // Clean up URL
    const url = new URL(window.location);
    url.searchParams.delete('error');
    url.searchParams.delete('invite');
    window.history.replaceState({}, document.title, url);
});
<?php endif; ?>
</script>
    <!-- Include the fixed lounge.js -->
    <script src="js/lounge.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/dura_system.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/inventory_system.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/profile_system.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/loading.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/friend_notifications.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/leaderboard.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/friends_sidebar.js?v=<?php echo $versions['version']; ?>"></script>

<?php include 'user_settings.php'; ?>
</body>
</html>
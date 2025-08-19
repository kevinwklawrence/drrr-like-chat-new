<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
echo '<script>console.log("Bubble values in session:", ' . json_encode($_SESSION['user']['bubble_hue'] ?? 'missing') . ', ' . json_encode($_SESSION['user']['bubble_saturation'] ?? 'missing') . ');</script>';
include 'db_connect.php';

$user_id_string = $_SESSION['user']['user_id'] ?? '';
$username = $_SESSION['user']['username'] ?? null;
$guest_name = $_SESSION['user']['name'] ?? null;
$avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
$is_admin = $_SESSION['user']['is_admin'] ?? 0;
$ip_address = $_SERVER['REMOTE_ADDR'];

// ADD this line after the existing variables:
$color = $_SESSION['user']['color'] ?? 'black';

if (isset($_GET['invite']) && !empty($_GET['invite'])) {
    $invite_code = trim($_GET['invite']);
    error_log("INVITE_LINK: Processing invite code: $invite_code");
    
    // Find room by invite code
    $invite_stmt = $conn->prepare("SELECT id, name, invite_code FROM chatrooms WHERE invite_code = ? AND invite_only = 1");
    if ($invite_stmt) {
        $invite_stmt->bind_param("s", $invite_code);
        $invite_stmt->execute();
        $invite_result = $invite_stmt->get_result();
        
        if ($invite_result->num_rows > 0) {
            $invited_room = $invite_result->fetch_assoc();
            error_log("INVITE_LINK: Found room: " . $invited_room['name']);
            
            // Store invite data in session for modal display
            $_SESSION['pending_invite'] = [
                'room_id' => $invited_room['id'],
                'room_name' => $invited_room['name'],
                'invite_code' => $invite_code
            ];
        } else {
            error_log("INVITE_LINK: Invalid invite code: $invite_code");
            // Redirect with error
            header("Location: lounge.php?error=invalid_invite");
            exit;
        }
        $invite_stmt->close();
    }
}

// FIXED: Include color field in the global_users INSERT/UPDATE
if (!empty($user_id_string)) {
    error_log("LOUNGE.PHP: Updating global_users for user: $user_id_string");
    
    // Preserve existing avatar customization values
    $existing_hue = $_SESSION['user']['avatar_hue'] ?? 0;
    $existing_sat = $_SESSION['user']['avatar_saturation'] ?? 100;
    
    error_log("LOUNGE.PHP: Using session values - hue: $existing_hue, sat: $existing_sat");
    

    // Preserve existing avatar customization values
    $existing_hue = $_SESSION['user']['avatar_hue'] ?? 0;
    $existing_sat = $_SESSION['user']['avatar_saturation'] ?? 100;
    
    $stmt = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, guest_avatar, is_admin, ip_address, color, avatar_hue, avatar_saturation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username = VALUES(username), guest_name = VALUES(guest_name), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), is_admin = VALUES(is_admin), ip_address = VALUES(ip_address), color = VALUES(color), last_activity = CURRENT_TIMESTAMP");
    if ($stmt) {
        $stmt->bind_param("ssssisssii", $user_id_string, $username, $guest_name, $avatar, $avatar, $is_admin, $ip_address, $color, $existing_hue, $existing_sat);
        $stmt->execute();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Lounge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/lounge.css" rel="stylesheet">
    <link href="css/profile_editor.css" rel="stylesheet">
     <link href="css/profile_editor_colors.css" rel="stylesheet">
     <link href="css/private.css" rel="stylesheet">
     <link href="css/profile_system.css" rel="stylesheet">
     <link href="css/bubble_colors.css" rel="stylesheet">
    <link href="css/color_previews.css" rel="stylesheet">
    <link href="css/private_bubble_colors.css" rel="stylesheet">
    <link href="css/room_stuff.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="lounge-container">
            <!-- Header -->
            <div class="lounge-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="lounge-title h3">
                        <i class="fas fa-comments"></i> Chat Lounge
                    </h1>
                    <div>
                        <button class="btn create-room-btn me-3" onclick="showCreateRoomModal()">
                            <i class="fas fa-plus"></i> Create Room
                        </button>
                        <a href="logout.php" class="btn logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- User Profile Sidebar -->
                <div class="col-lg-3">
                    <div class="user-profile-card">
                        <div class="text-center mb-3">
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
                                    <?php if ($is_admin): ?>
                                        <br><span class="badge bg-danger">Admin</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <button class="btn change-avatar-btn w-100" onclick="showAvatarSelector()">
                            <i class="fas fa-user-edit"></i> Change Avatar
                        </button>
                        <button class="btn btn-outline-primary w-100 mt-2" onclick="showFriendsPanel()">
                            <i class="fas fa-user-friends"></i> Friends
                        </button>
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
                </div>
                
                <!-- Rooms List -->
                <div class="col-lg-9">
                    <div class="rooms-section-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="rooms-section-title">
                                <i class="fas fa-door-open"></i> Available Rooms
                            </h3>
                            <button class="btn btn-outline-secondary refresh-btn" onclick="loadRoomsWithUsers()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div id="roomsList">
                        <div class="text-center py-5">
                            <div class="loading-spinner mb-3" style="width: 30px; height: 30px;"></div>
                            <p class="text-muted">Loading rooms...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="friends-panel" id="friendsPanel" style="display: none;">
    <div class="card-header">
        <h6><i class="fas fa-user-friends"></i> Friends</h6>
        <button class="btn-close" style="color:black;" onclick="closeFriendsPanel()">×</button>
    </div>
    <div class="card-body" id="friendsList">
        Loading friends...
    </div>
</div>
    
    <!-- Knock notifications will appear here -->
    <div id="knockNotifications"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const currentUser = <?php echo json_encode(array_merge($_SESSION['user'], [
        'color' => $color
    ])); ?>;
    console.log('Current user:', currentUser);
</script>
    <script>
<?php if (isset($_SESSION['pending_invite'])): ?>
// Show invite modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    const inviteData = <?php echo json_encode($_SESSION['pending_invite']); ?>;
    <?php unset($_SESSION['pending_invite']); // Clear after use ?>
    
    showInviteModal(inviteData);
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
    const button = $('#inviteModal .btn-primary');
    const originalText = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Joining...');
    
    $.ajax({
        url: 'api/join_room.php',
        method: 'POST',
        data: {
            room_id: roomId,
            invite_code: inviteCode
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                window.location.href = 'room.php';
            } else {
                alert('Error: ' + response.message);
                button.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('Failed to join room: ' + error);
            button.prop('disabled', false).html(originalText);
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
    <script src="js/lounge.js"></script>
    <script src="js/profile_system.js"></script>
</body>
</html>
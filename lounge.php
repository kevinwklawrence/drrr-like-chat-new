<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

include 'db_connect.php';

// Add user to global_users table and update their activity
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$username = $_SESSION['user']['username'] ?? null;
$guest_name = $_SESSION['user']['name'] ?? null;
$avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
$is_admin = $_SESSION['user']['is_admin'] ?? 0;
$ip_address = $_SERVER['REMOTE_ADDR'];

if (!empty($user_id_string)) {
    $stmt = $conn->prepare("INSERT INTO global_users (user_id_string, username, guest_name, avatar, guest_avatar, is_admin, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username = VALUES(username), guest_name = VALUES(guest_name), avatar = VALUES(avatar), guest_avatar = VALUES(guest_avatar), is_admin = VALUES(is_admin), ip_address = VALUES(ip_address), last_activity = CURRENT_TIMESTAMP");
    if ($stmt) {
        $stmt->bind_param("sssssis", $user_id_string, $username, $guest_name, $avatar, $avatar, $is_admin, $ip_address);
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
                                 onclick="showAvatarSelector()"
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
    
    <!-- Knock notifications will appear here -->
    <div id="knockNotifications"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentUser = <?php echo json_encode($_SESSION['user']); ?>;
        console.log('Current user:', currentUser);
    </script>
    
    <!-- Include the fixed lounge.js -->
    <script src="js/lounge.js"></script>
</body>
</html>
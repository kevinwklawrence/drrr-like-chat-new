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
    <style>
        body {
            background-color: #1a1a1a;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .container-fluid {
            background-color: #1a1a1a;
            min-height: 100vh;
            padding: 20px;
        }
        
        .lounge-container {
            background: #222;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 20px;
            margin: 0 auto;
            max-width: 1400px;
        }
        
        .user-profile-card {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            color: #e0e0e0;
        }
        
        .avatar-selector {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #555;
            width: 60px;
            height: 60px;
            border-radius: 4px;
        }
        
        .avatar-selector:hover {
            border-color: #777;
            transform: scale(1.05);
        }
        
        .online-users-card {
            border: 1px solid #333;
            border-radius: 8px;
            background: #2a2a2a;
            overflow: hidden;
        }
        
        .online-users-card .card-header {
            background: #333;
            border-bottom: 1px solid #404040;
            color: #e0e0e0;
            padding: 12px 16px;
            font-weight: 500;
        }
        
        .online-users-card .card-body {
            padding: 16px;
            max-height: 300px;
            overflow-y: auto;
            background: #2a2a2a;
        }
        
        .lounge-header {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .lounge-title {
            color: #ffffff;
            font-weight: 600;
            margin: 0;
        }
        
        .create-room-btn {
            background: #28a745;
            border: 1px solid #28a745;
            border-radius: 4px;
            padding: 8px 16px;
            font-weight: 500;
            color: white;
            transition: all 0.2s ease;
        }
        
        .create-room-btn:hover {
            background: #218838;
            border-color: #218838;
            color: white;
        }
        
        .logout-btn {
            border-radius: 4px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid #dc3545;
            color: #dc3545;
            background: transparent;
        }
        
        .logout-btn:hover {
            background: #dc3545;
            color: white;
        }
        
        .refresh-btn {
            border-radius: 4px;
            padding: 6px 12px;
            font-weight: normal;
            transition: all 0.2s ease;
        }
        
        .rooms-section-header {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        
        .rooms-section-title {
            color: #ffffff;
            font-weight: 500;
            margin: 0;
        }
        
        .change-avatar-btn {
            background: #333;
            border: 1px solid #555;
            color: #e0e0e0;
            border-radius: 4px;
            padding: 8px 16px;
            font-weight: normal;
            transition: all 0.2s ease;
        }
        
        .change-avatar-btn:hover {
            background: #404040;
            border-color: #666;
            color: #e0e0e0;
        }
        
        /* Dark scrollbar */
        .online-users-card .card-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .online-users-card .card-body::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        
        .online-users-card .card-body::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 3px;
        }
        
        .online-users-card .card-body::-webkit-scrollbar-thumb:hover {
            background: #666;
        }
        
        /* Form controls dark theme */
        .form-control, .form-select {
            background: #333 !important;
            border: 1px solid #555 !important;
            color: #fff !important;
        }
        
        .form-control:focus, .form-select:focus {
            background: #333 !important;
            border-color: #777 !important;
            color: #fff !important;
            box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.1) !important;
        }
        
        /* Modal dark theme */
        .modal-content {
            background: #2a2a2a !important;
            border: 1px solid #444 !important;
            color: #fff !important;
        }
        
        .modal-header {
            border-bottom: 1px solid #444 !important;
        }
        
        .modal-footer {
            border-top: 1px solid #444 !important;
        }
        
        .btn-close {
            filter: invert(1) !important;
        }
        
        /* Loading spinner dark */
        .loading-spinner {
            border: 2px solid #333;
            border-top-color: #666;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .lounge-header .d-flex {
                flex-direction: column;
                gap: 15px;
                align-items: center !important;
            }
        }
        
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            
            .lounge-container {
                padding: 15px;
            }
            
            .rooms-section-header .d-flex {
                flex-direction: column;
                gap: 10px;
                align-items: center !important;
            }
            
            .refresh-btn {
                width: 100%;
            }
        }
        
        /* Alert styling for dark theme */
        .alert-danger {
            background: #2a2a2a !important;
            border: 1px solid #d32f2f !important;
            color: #f44336 !important;
        }
        
        /* Text colors */
        .text-muted {
            color: #666 !important;
        }
        
        /* Badge dark styling */
        .badge {
            font-weight: normal;
        }
    </style>
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
                            <i class="fas fa-users"></i> Online Users
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
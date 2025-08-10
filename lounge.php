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
    <style>
        body {
          /*  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
           */ min-height: 100vh;
        }
        
        .lounge-container {
            background: rgba(230, 230, 230, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .user-profile-card {
            /*background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%);
            */
            background: rgb(47 47 47);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .avatar-selector {
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .avatar-selector:hover {
            transform: scale(1.1);
        }
        
        .room-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .room-header {
            background: rgb(47 47 47);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 15px 20px;
        }
        
        .password-protected {
            background: linear-gradient(45deg, #fa709a 0%, #fee140 100%);
        }
        
        .knock-available {
            background: linear-gradient(45deg, #a8edea 0%, #fed6e3 100%);
            color: #333;
        }
        
        .create-room-btn {
            background: linear-gradient(45deg, #ff9a9e 0%, #fecfef 100%);
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .create-room-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .knock-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="lounge-container p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="display-4 fw-bold text-dark">
                    <i class="fas fa-comments"></i> Lounge
                </h1>
                <div>
                    <button class="btn btn-success me-3" onclick="showCreateRoomModal()">
                        <i class="fas fa-plus"></i> Create Room
                    </button>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="row">
                <!-- User Profile Sidebar -->
                <div class="col-lg-3">
                    <div class="user-profile-card">
                        <div class="text-center mb-3">
                            <img src="images/<?php echo htmlspecialchars($avatar); ?>" 
                                 width="58" 
                                 class="avatar-selector" 
                                 id="currentAvatar" 
                                 onclick="showAvatarSelector()"
                                 alt="Your avatar">
                            <h5 class="mt-2 mb-0"><?php echo htmlspecialchars($guest_name ?? $username ?? 'User'); ?></h5>
                            <small class="opacity-75">
                                <?php echo $_SESSION['user']['type'] === 'guest' ? 'Guest User' : 'Registered User'; ?>
                                <?php if ($is_admin): ?>
                                    <br><span class="badge bg-light text-dark">Admin</span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <button class="btn btn-light btn-sm w-100" onclick="showAvatarSelector()">
                            <i class="fas fa-user-edit"></i> Change Avatar
                        </button>
                    </div>
                    
                    <!-- Online Users -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <i class="fas fa-users"></i> Online Users
                        </div>
                        <div class="card-body" id="onlineUsersList">
                            <p class="text-muted">Loading...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Rooms List -->
                <div class="col-lg-9">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="text-dark"><i class="fas fa-door-open"></i> Available Rooms</h3>
                        <button class="btn btn-outline-primary" onclick="loadRooms()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div id="roomsList">
                        <p class="text-center text-muted">Loading rooms...</p>
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
        debugLog('Current user:', currentUser);
    </script>
    
    <script src="js/lounge.js"></script>
</body>
</html>
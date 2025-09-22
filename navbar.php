<?php
// navbar.php - Global navigation component
// Include this file in each page after session_start() and database connections

// Determine current page context
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$is_logged_in = isset($_SESSION['user']);
$user_type = $_SESSION['user']['type'] ?? null;
$is_admin = ($is_logged_in && ($_SESSION['user']['is_admin'] ?? false));
$is_moderator = ($is_logged_in && ($_SESSION['user']['is_moderator'] ?? false));
$in_room = isset($_SESSION['room_id']);

// Define navigation items based on page context
$nav_items = [];

if (!$is_logged_in) {
    // Login pages navigation
    $nav_items = [
        ['href' => '/guest', 'icon' => 'fas fa-user-ninja', 'text' => 'Guest Login', 'active' => $current_page === 'guest'],
        ['href' => '/member', 'icon' => 'fas fa-sign-in-alt', 'text' => 'Member Login', 'active' => $current_page === 'member'],
        ['href' => 'register', 'icon' => 'fas fa-user-plus', 'text' => 'Create Account', 'active' => $current_page === 'register'],
        ['href' => 'terms.php', 'icon' => 'fas fa-file-contract', 'text' => 'Terms'],
        ['href' => 'privacy.php', 'icon' => 'fas fa-shield-alt', 'text' => 'Privacy']
    ];
} elseif ($current_page === 'lounge') {
    // Lounge navigation
    $nav_items = [
        ['href' => '#', 'icon' => 'fas fa-plus', 'text' => 'Create Room', 'onclick' => 'showCreateRoomModal()', 'class' => 'btn-success'],
       // ['href' => '#', 'icon' => 'fas fa-user-edit', 'text' => 'Profile', 'onclick' => 'showProfileEditor()'],
       ['href' => '#', 'icon' => 'fas fa-filter', 'text' => 'Filter', 'onclick' => 'showFilterModal()'],
        ['href' => '#', 'icon' => 'fas fa-cog', 'text' => 'Settings', 'onclick' => 'openUserSettings()'],
        ['href' => 'logout.php', 'icon' => 'fas fa-sign-out-alt', 'text' => 'Logout', 'class' => 'btn-danger']
    ];
    
    // Add friends for members only
    if ($user_type === 'user') {
        $friends_item = ['href' => '#', 'icon' => 'fas fa-user-friends', 'text' => 'Friends', 'onclick' => 'showFriendsPanel()'];
        array_splice($nav_items, -2, 0, [$friends_item]);
    }
    
    // Add admin/moderator items
    if ($is_admin || $is_moderator) {
        $moderator_item = ['href' => 'moderator.php', 'icon' => 'fas fa-shield-alt', 'text' => 'Moderator', 'class' => 'btn-warning'];
        $ghost_item = ['href' => '#', 'icon' => 'fas fa-ghost', 'text' => 'Ghost Mode', 'onclick' => 'toggleGhostMode()', 'class' => 'btn-secondary'];
        array_splice($nav_items, -1, 0, [$moderator_item, $ghost_item]);
    }
} elseif ($current_page === 'room') {
    // Room navigation
    $nav_items = [
        ['href' => '#', 'icon' => 'fas fa-plane-departure', 'text' => 'AFK', 'onclick' => 'toggleAFK()', 'class' => 'btn-warning'],
        ['href' => '#', 'icon' => 'fas fa-user', 'text' => 'Users', 'onclick' => 'toggleMobileUsers()'],
      //  ['id' => 'notificationBell', 'href' => '#', 'icon' => 'fas fa-bell', 'text' => 'Notifications', 'onclick' => 'markAllNotificationsRead()'],
        ['href' => '#', 'icon' => 'fas fa-cog', 'text' => 'Settings', 'onclick' => 'openUserSettings()'],
        ['href' => '#', 'icon' => 'fas fa-door-open', 'text' => 'Leave Room', 'onclick' => 'leaveRoom()', 'class' => 'btn-info']
    ];
    
    // Add friends for members only
    if ($user_type === 'user') {
        $friends_item = ['href' => '#', 'icon' => 'fas fa-user-friends', 'text' => 'Friends', 'onclick' => 'showFriendsPanel()'];
        array_splice($nav_items, -2, 0, [$friends_item]);
    }
    
    // Add room settings for hosts
    $is_host_in_room = false;
    if (isset($_SESSION['room_id']) && !empty($_SESSION['user']['user_id']) && isset($conn)) {
        $room_id = (int)$_SESSION['room_id'];
        $user_id_string = $_SESSION['user']['user_id'];
        
        $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
        if ($stmt) {
            $stmt->bind_param("is", $room_id, $user_id_string);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                $is_host_in_room = ($user_data['is_host'] == 1);
            }
            $stmt->close();
        }
    }
    
    // Add room settings for hosts
    if ($is_host_in_room) {
        $room_settings_item = ['href' => '#', 'icon' => 'fas fa-tools', 'text' => 'Room Settings', 'onclick' => 'showRoomSettings()', 'class' => 'btn-primary'];
        array_splice($nav_items, -1, 0, [$room_settings_item]);
    }
    
    // Add admin/moderator items
    if ($is_admin || $is_moderator) {
        $ghost_item = ['href' => '#', 'icon' => 'fas fa-ghost', 'text' => 'Ghost Mode', 'onclick' => 'toggleGhostMode()', 'class' => 'btn-secondary'];
        array_splice($nav_items, -1, 0, [$ghost_item]);
    }
}

// Determine if we need hamburger menu (>4 items)
$use_hamburger = count($nav_items) > 0;

// Debug output (remove after testing)
echo "<!-- Nav items count: " . count($nav_items) . ", Use hamburger: " . ($use_hamburger ? 'true' : 'false') . " -->";
?>
<link href="css/navbar.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
<link href="css/nav_replacer.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
<script src="js/navbar.js?v=<?php echo $versions['version']; ?>"></script>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top" id="globalNavbar">
    <!-- Logo -->
    <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="images/duranu.png" alt="Duranu" class="navbar-logo me-2">
    </a>

   
    
    <!-- Navigation items -->
    <div class="navbar-nav ms-auto">
         <?php if ($current_page === 'room'): ?>
    <button id="notificationBell" class="btn chat-control-btn" title="Notifications" onclick="markAllNotificationsRead()">
        <i class="fas fa-bell"></i>
    </button>
<?php endif; ?>
        <?php if (!$use_hamburger): ?>
            <!-- Show items inline when ≤4 items -->
            <?php foreach ($nav_items as $item): ?>
                <?php
                $btn_class = $item['class'] ?? 'btn-outline-light';
                $active_class = ($item['active'] ?? false) ? ' active' : '';
                $onclick = isset($item['onclick']) ? ' onclick="' . $item['onclick'] . '"' : '';
                ?>
                <a class="nav-link btn <?php echo $btn_class . $active_class; ?> mx-1" 
                   href="<?php echo $item['href']; ?>"<?php echo $onclick; ?>>
                    <i class="<?php echo $item['icon']; ?>"></i>
                    <span class="d-none d-md-inline ms-1"><?php echo $item['text']; ?></span>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Hamburger menu dropdown when >4 items -->
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle btn btn-outline-light" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-bars"></i> <span class="d-none d-md-inline ms-1">Menu</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                    <?php foreach ($nav_items as $item): ?>
                    <li>
                        <?php
                        $active_class = ($item['active'] ?? false) ? ' active' : '';
                        $onclick = isset($item['onclick']) ? ' onclick="' . $item['onclick'] . '"' : '';
                        ?>
                        <a class="dropdown-item<?php echo $active_class; ?>" 
                           href="<?php echo $item['href']; ?>"<?php echo $onclick; ?>>
                            <i class="<?php echo $item['icon']; ?> me-2"></i>
                            <?php echo $item['text']; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- User Avatar (only show when logged in and not on auth pages) -->
    <?php if ($is_logged_in && !in_array($current_page, ['index', 'login', 'register'])): ?>
        <?php 
        $user_avatar = $_SESSION['user']['avatar'] ?? 'default_avatar.jpg';
        $user_name = $_SESSION['user']['username'] ?? $_SESSION['user']['name'] ?? 'User';
        ?>
        <div class="navbar-avatar ms-3">
            <img src="images/<?php echo htmlspecialchars($user_avatar); ?>" 
                 alt="<?php echo htmlspecialchars($user_name); ?>" 
                 class="navbar-avatar-img" 
                 onclick="showProfileEditor()" 
                 title="Click to edit profile">
        </div>
    <?php endif; ?>
</nav>

<!-- Add padding to body to account for fixed navbar -->
<style>
.navbar-logo {
    height: 32px;
    width: auto;
}

body {
    padding-top: 70px;
}

.navbar-avatar {
    display: flex;
    align-items: center;
}

.navbar-avatar-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid #495057;
    cursor: pointer;
    transition: all 0.3s ease;
    object-fit: cover;
}

.navbar-avatar-img:hover {
    border-color: #ffffff;
    transform: scale(1.1);
    box-shadow: 0 0 10px rgba(255,255,255,0.3);
}

.navbar-nav .nav-link.btn {
    border: 1px solid transparent;
    border-radius: 0.375rem;
    padding: 0.375rem 0.75rem;
    margin: 0 0.125rem;
    transition: all 0.15s ease-in-out;
}

.navbar-nav .nav-link.btn:hover {
    transform: translateY(-1px);
}

.dropdown-menu-dark {
    background-color: #212529;
    border: 1px solid #495057;
}

.dropdown-item:hover {
    background-color: #495057;
}

@media (max-width: 767.98px) {
    .navbar-nav .nav-link.btn {
        margin: 0.25rem 0;
        text-align: center;
    }
    
    .navbar-avatar-img {
        width: 32px;
        height: 32px;
    }
}
</style>
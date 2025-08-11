<?php
session_start();

// Debug file path
error_log("Current directory in login.php: " . __DIR__);

// Include db_connect.php from the same directory
include 'db_connect.php';
if (!file_exists('db_connect.php')) {
    error_log("db_connect.php not found in " . __DIR__);
    die("Error: Database connection file not found.");
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle POST request for login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $selected_avatar = $_POST['avatar'] ?? ''; // Allow empty avatar selection

    if (empty($username) || empty($password)) {
        error_log("Missing username or password in login.php");
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // Updated query to include custom_av and avatar_memory
    $stmt = $conn->prepare("SELECT id, username, user_id, email, password, is_admin, avatar, custom_av, avatar_memory FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("Prepare failed in login.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            
            // Determine the final avatar based on priority
            $final_avatar = null;
            $should_update_avatar_memory = false;
            
            if (!empty($selected_avatar)) {
                // User selected an avatar - use it and update avatar_memory
                $final_avatar = $selected_avatar;
                $should_update_avatar_memory = true;
                error_log("User selected avatar: $selected_avatar");
            } else {
                // No avatar selected - determine fallback
                if (!empty($user['custom_av'])) {
                    // Use custom avatar if available
                    $final_avatar = $user['custom_av'];
                    error_log("Using custom avatar: {$user['custom_av']} for user: $username");
                } elseif (!empty($user['avatar_memory'])) {
                    // Use remembered avatar
                    $final_avatar = $user['avatar_memory'];
                    error_log("Using remembered avatar: {$user['avatar_memory']} for user: $username");
                } else {
                    // Default fallback
                    $final_avatar = 'default/u0.png';
                    error_log("Using default avatar for user: $username");
                }
            }
            
            // Update user's avatar and avatar_memory in database if needed
            $updates_needed = [];
            $update_params = [];
            $param_types = '';
            
            // Always update current avatar if it's different
            if ($final_avatar !== $user['avatar']) {
                $updates_needed[] = 'avatar = ?';
                $update_params[] = $final_avatar;
                $param_types .= 's';
            }
            
            // Update avatar_memory if user selected an avatar
            if ($should_update_avatar_memory && $selected_avatar !== $user['avatar_memory']) {
                $updates_needed[] = 'avatar_memory = ?';
                $update_params[] = $selected_avatar;
                $param_types .= 's';
            }
            
            // Perform database update if needed
            if (!empty($updates_needed)) {
                $update_sql = "UPDATE users SET " . implode(', ', $updates_needed) . " WHERE id = ?";
                $update_params[] = $user['id'];
                $param_types .= 'i';
                
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param($param_types, ...$update_params);
                    if ($update_stmt->execute()) {
                        error_log("Updated user data: avatar=$final_avatar" . ($should_update_avatar_memory ? ", avatar_memory=$selected_avatar" : "") . " for user: $username");
                        $user['avatar'] = $final_avatar; // Update local variable
                    } else {
                        error_log("Failed to update user data: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                }
            }
            
            // Create user session with final avatar
            $_SESSION['user'] = [
                'type' => 'user',
                'id' => $user['id'],
                'username' => $user['username'],
                'user_id' => $user['user_id'],  // This is crucial for the host system!
                'email' => $user['email'],
                'is_admin' => $user['is_admin'],
                'avatar' => $final_avatar, // Use the determined final avatar
                'ip' => $_SERVER['REMOTE_ADDR']
            ];
            
            // Debug log to ensure user_id is set
            error_log("User logged in with user_id: " . ($user['user_id'] ?? 'NULL') . ", final_avatar: " . $final_avatar);
            
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    $stmt->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: #222;
            border: 1px solid #333;
            border-radius: 15px;
            padding: 40px;
            margin: 0 auto;
            max-width: 1200px;
            width: 100%;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            padding-bottom:10px;
        }
        
        .login-header {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .login-title {
            color: #ffffff;
            font-weight: 600;
            margin: 0;
        }
        
        .login-section {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .avatar-controls {
            background: #333;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .avatar-container {
            max-height: 500px;
            overflow-y: auto;
            border: 2px solid #404040;
            border-radius: 10px;
            padding: 20px;
            background: #1a1a1a;
            margin-top: 15px;
        }
        
        .avatar-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .avatar-container::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        
        .avatar-container::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 3px;
        }
        
        .avatar-container::-webkit-scrollbar-thumb:hover {
            background: #666;
        }
        
        .avatar {
            width: 60px;
            height: 60px;
            cursor: pointer;
            margin: 2px;
            border: 1px solid #555;
            border-radius: 4px;
            transition: all 0.1s ease;
        }
        
        .avatar:hover {
            border-color: #007bff;
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.5);
        }
        
        .avatar.selected {
            border: 2px solid #007bff;
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.5);
        }

        .avatar-sel {
            width: 60px;
            height: 60px;
            margin: 2px;
            border: 1px solid #555;
            border-radius: 4px;
            transition: all 0.1s ease;
        }

        
        .avatar-group {
            margin-bottom: 25px;
            border-bottom: 1px solid #333;
            padding-bottom: 20px;
        }
        
        .avatar-group:last-child {
            border-bottom: none;
        }
        
        .avatar-group h6 {
            color: #007bff;
            font-weight: 600;
            margin-bottom: 15px;
            padding: 8px 12px;
            background: #333;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .avatar-count {
            background: #555;
            color: #e0e0e0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: auto;
        }
        
        .form-control, .form-select {
            background: #333 !important;
            border: 1px solid #555 !important;
            color: #fff !important;
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            background: #333 !important;
            border-color: #777 !important;
            color: #fff !important;
            box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.1) !important;
        }
        
        .btn-primary {
            background: #28a745;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 500;
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: #218838;
            border-color: #218838;
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-outline-secondary {
            border: 1px solid #555;
            color: #e0e0e0;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: normal;
            transition: all 0.2s ease;
        }
        
        .btn-outline-secondary:hover {
            background: #404040;
            border-color: #666;
            color: #e0e0e0;
        }
        
        .btn-outline-light {
            border: 1px solid #555;
            color: #e0e0e0;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: normal;
            transition: all 0.2s ease;
        }
        
        .btn-outline-light:hover {
            background: #404040;
            border-color: #666;
            color: #e0e0e0;
        }
        
        .form-label {
            color: #e0e0e0;
            font-weight: 500;
        }
        
        .avatar-stats {
            background: #404040;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .search-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-container .fa-search {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .search-container input {
            padding-left: 40px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .filter-btn {
            background: #404040;
            border: 1px solid #555;
            color: #e0e0e0;
            padding: 5px 12px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }
        
        .filter-btn:hover {
            background: #555;
        }
        
        .filter-btn.active {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .selected-avatar-preview {
            background: #333;
            border: 1px solid #555;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .text-muted {
            color: #666 !important;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .no-results i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .links-section {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .links-section a {
            color: #007bff;
            text-decoration: none;
        }
        
        .links-section a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            
            .login-container {
                padding: 30px 20px;
                margin: 10px;
                border-radius: 10px;
            }
            
            .avatar-container {
                max-height: 350px;
                padding: 15px;
            }
            
            .avatar {
                width: 48px;
                height: 48px;
                margin: 3px;
            }
            
            .avatar-stats {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .avatar {
                width: 42px;
                height: 42px;
                margin: 2px;
            }
            
            .login-container {
                padding: 20px 15px;
            }
            
            .filter-buttons {
                justify-content: center;
            }
        }

        /* New styles for optional avatar selection */
        .avatar-optional-notice {
            background: #2a3d4e;
            border: 1px solid #4a90a4;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #a0d0e0;
        }

        .quick-login-section {
            background: #333;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="login-container">
            <!-- Header -->
            <div class="login-header">
                <h1 class="login-title h3">
                    <i class="fas fa-user"></i> Member Login
                </h1>
                <p class="text-muted mb-0">Sign in to your account</p>
            </div>
            
            <form id="userLoginForm">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="login-section">
                            <h5 class="mb-3"><i class="fas fa-images"></i> Choose Your Avatar (Optional)</h5>
                            
                           <!-- <div class="avatar-optional-notice">
                                <i class="fas fa-info-circle"></i>
                                <strong>Avatar Selection is Optional!</strong><br>
                                If you don't select an avatar, we'll use your custom avatar, previously selected avatar, or a default one.
                            </div>-->
                            
                            <div class="avatar-controls">
                               <!-- <div class="quick-login-section">
                                    <h6 class="mb-2"><i class="fas fa-zap"></i> Quick Login</h6>
                                    
                                </div>-->
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="avatarSort" class="form-label">Filter by Color</label>
                                        <select id="avatarSort" class="form-select">
                                            <option value="all">All Colors</option>
                                            <?php
                                            $image_base_dir = __DIR__ . '/images';
                                            $excluded_folders = ['staff', 'bg', 'icon', 'time-limited', 'default'];
                                            foreach (glob($image_base_dir . '/*', GLOB_ONLYDIR) as $color_dir) {
                                                $color_name = basename($color_dir);
                                                if (in_array(strtolower($color_name), $excluded_folders)) continue;
                                                echo '<option value="' . strtolower($color_name) . '">' . ucfirst($color_name) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Avatar Actions</label>
                                        <div class="d-flex gap-2">
                                            <!--<button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                                                <i class="fas fa-times"></i> Clear
                                            </button>-->
                                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="skipAvatarSelection()">
                                        <i class="fas fa-fast-forward"></i> Use Saved/Custom
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="randomAvatar()">
                                        <i class="fas fa-random"></i> Random
                                    </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="avatar-container" id="avatarContainer">
                                <?php
                                $image_base_dir = __DIR__ . '/images';
                                $web_base_dir = 'images/';
                                $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
                                $excluded_folders = ['staff', 'bg', 'icon'];
                                $priority_folders = ['time-limited', 'default'];
                                $total_avatars = 0;

                                // Show priority folders first if they exist
                                foreach ($priority_folders as $folder) {
                                    $color_dir = $image_base_dir . '/' . $folder;
                                    if (is_dir($color_dir)) {
                                        $folder_avatars = glob($color_dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
                                        $folder_count = count($folder_avatars);
                                        $total_avatars += $folder_count;
                                        
                                        echo '<div class="avatar-group" data-group="' . strtolower($folder) . '">';
                                        echo '<h6><i class="fas fa-star"></i> ' . ucfirst($folder) . ' Avatars <span class="avatar-count">' . $folder_count . '</span></h6>';
                                        echo '<div class="d-flex flex-wrap">';
                                        
                                        foreach ($folder_avatars as $img_path) {
                                            $img_file = basename($img_path);
                                            $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                                            if (in_array($ext, $allowed_ext)) {
                                                echo '<img src="' . $web_base_dir . $folder . '/' . $img_file . '" class="avatar" data-avatar="' . $folder . '/' . $img_file . '" alt="Avatar option">';
                                            }
                                        }
                                        echo '</div></div>';
                                    }
                                }

                                // Now show all other folders except excluded and priority
                                foreach (glob($image_base_dir . '/*', GLOB_ONLYDIR) as $color_dir) {
                                    $color_name = basename($color_dir);
                                    if (in_array(strtolower($color_name), $excluded_folders)) continue;
                                    if (in_array(strtolower($color_name), $priority_folders)) continue;

                                    $folder_avatars = glob($color_dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
                                    $folder_count = count($folder_avatars);
                                    $total_avatars += $folder_count;
                                    
                                    echo '<div class="avatar-group" data-group="' . strtolower($color_name) . '">';
                                    echo '<h6><i class="fas fa-folder"></i> ' . ucfirst($color_name) . ' Avatars <span class="avatar-count">' . $folder_count . '</span></h6>';
                                    echo '<div class="d-flex flex-wrap">';
                                    
                                    foreach ($folder_avatars as $img_path) {
                                        $img_file = basename($img_path);
                                        $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                                        if (in_array($ext, $allowed_ext)) {
                                            echo '<img src="' . $web_base_dir . $color_name . '/' . $img_file . '" class="avatar" data-avatar="' . $color_name . '/' . $img_file . '" alt="Avatar option">';
                                        }
                                    }
                                    echo '</div></div>';
                                }
                                ?>
                                
                                <div id="noResults" class="no-results" style="display: none;">
                                    <i class="fas fa-search"></i>
                                    <h6>No avatars found</h6>
                                    <p class="text-muted">Try adjusting your search or filter criteria.</p>
                                </div>
                            </div>
                            
                            <input type="hidden" id="selectedAvatar" name="avatar">
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Browse through <?php echo $total_avatars; ?> avatars or skip to use your saved avatar
                            </div>
                        </div>
                    </div>
                    <!-- Login Credentials -->
                    <div class="col-lg-4">
                        <div class="login-section">
                            <h5 class="mb-3"><i class="fas fa-sign-in-alt"></i> Account Login</h5>
                            
                            <div class="mb-4">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user"></i> Username
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Enter your username"
                                       required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Enter your password"
                                       required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label"><i class="fas fa-image"></i> Selected Avatar</label>
                                <div class="selected-avatar-preview">
                                    <div id="selectedAvatarPreview" style="display: none;">
                                        <img id="selectedAvatarImg" src="" width="58" height="58" class="avatar-sel" style="border-color: #007bff !important;">
                                        <p class="mt-2 mb-0 small text-muted">Current selection</p>
                                    </div>
                                    <div id="noAvatarSelected">
                                        <div class="text-muted">
                                            <i class="fas fa-magic fa-2x mb-2"></i>
                                            <p class="mb-0 small">Using saved/custom avatar</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Avatar Selection -->
                    
                </div>
            </form>
            
            <!-- Links Section -->
            <div class="links-section">
                <p class="mb-3">Don't have an account?</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="register.php" class="btn btn-outline-light">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-user-friends"></i> Continue as Guest
                    </a>
                </div>
            </div>
            <div>
                <p class="text-center text-muted mt-4">
                    <small>By joining as a member, you agree to our <a href="terms.php" class="text-white">Terms of Service</a> and <a href="privacy.php" class="text-white">Privacy Policy</a>. ©Lenn, 2025.</small>
                </p>    
                
                </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Replace the JavaScript section in login.php with this updated version

$(document).ready(function() {
    updateAvatarStats();
    
    // Avatar selection handling
    $(document).on('click', '.avatar', function() {
        $('.avatar').removeClass('selected');
        $(this).addClass('selected');
        
        const avatarPath = $(this).data('avatar');
        $('#selectedAvatar').val(avatarPath);
        
        // Update preview
        $('#selectedAvatarImg').attr('src', $(this).attr('src'));
        $('#selectedAvatarPreview').show();
        $('#noAvatarSelected').hide();
        
        updateAvatarStats();
    });

    // Filter dropdown
    $('#avatarSort').on('change', function() {
        filterAvatars();
    });

    // Form submission - UPDATED to allow no avatar selection and use correct default
    $('#userLoginForm').submit(function(e) {
        e.preventDefault();
        
        const username = $('#username').val().trim();
        const password = $('#password').val();
        const selectedAvatar = $('#selectedAvatar').val(); // Allow empty
        
        if (!username || !password) {
            alert('Please enter both username and password');
            return;
        }
        
        // Show loading state
        const submitBtn = $('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Logging in...');
        
        $.ajax({
            url: 'api/login.php',
            method: 'POST',
            data: {
                username: username,
                password: password,
                avatar: selectedAvatar, // This can now be empty
                type: 'user'
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    window.location.href = 'lounge.php';
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('Connection error: ' + error);
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});

function filterAvatars() {
    const selectedGroup = $('#avatarSort').val();
    
    $('.avatar-group').each(function() {
        const groupName = $(this).data('group');
        
        let showGroup = true;
        
        // Filter by dropdown selection
        if (selectedGroup !== 'all' && groupName !== selectedGroup) {
            showGroup = false;
        }
        
        if (showGroup) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    
    updateAvatarStats();
    checkNoResults();
}

function updateAvatarStats() {
    const totalAvatars = $('.avatar').length;
    const visibleAvatars = $('.avatar-group:visible .avatar').length;
    const selectedCount = $('.avatar.selected').length;
    
    $('#totalAvatars').text(totalAvatars);
    $('#visibleAvatars').text(visibleAvatars);
    $('#selectedCount').text(selectedCount);
}

function checkNoResults() {
    const visibleGroups = $('.avatar-group:visible').length;
    if (visibleGroups === 0) {
        $('#noResults').show();
    } else {
        $('#noResults').hide();
    }
}

function clearSelection() {
    $('.avatar').removeClass('selected');
    $('#selectedAvatar').val('');
    $('#selectedAvatarPreview').hide();
    $('#noAvatarSelected').show();
    updateAvatarStats();
}

function skipAvatarSelection() {
    // Clear any selection and show that we're using saved avatar
    clearSelection();
    $('#noAvatarSelected .text-muted p').text('Using your saved avatar');
}

function randomAvatar() {
    const visibleAvatars = $('.avatar-group:visible .avatar');
    if (visibleAvatars.length > 0) {
        const randomIndex = Math.floor(Math.random() * visibleAvatars.length);
        $(visibleAvatars[randomIndex]).click();
    }
}
    </script>
    <script src="js/script.js"></script>
</body>
</html>
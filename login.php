<?php
session_start();
// Add to index.php - right after session_start()
if (isset($_SESSION['user'])) {
    if (isset($_SESSION['room_id'])) {
        header("Location: room.php");
    } else {
        header("Location: lounge.php");
    }
    exit;
}
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
    $selected_color = $_POST['color'] ?? ''; // Allow empty avatar selection

    if (empty($username) || empty($password)) {
        error_log("Missing username or password in login.php");
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // Updated query to include color
    $stmt = $conn->prepare("SELECT id, username, user_id, email, password, is_admin, avatar, custom_av, avatar_memory, color FROM users WHERE username = ?");
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
            
            // Update user's avatar, avatar_memory, and color in database if needed
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
            
            // Update color if selected and different from current
            if (!empty($selected_color) && $selected_color !== $user['color']) {
                $update_color_stmt = $conn->prepare("UPDATE users SET color = ? WHERE id = ?");
                $update_color_stmt->bind_param("si", $selected_color, $user['id']);
                $update_color_stmt->execute();
                $update_color_stmt->close();
                $user['color'] = $selected_color; // Update local variable for session
            }
            
            // Perform database update if needed
            if (!empty($updates_needed)) {
                $update_sql = "UPDATE users SET " . implode(', ', $updates_needed) . " WHERE id = ?";
                $update_params[] = $user['id'];
                $param_types .= 'i';
                
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param($param_types, ...$update_params);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            }
            
            // Create user session with final avatar and color
            $_SESSION['user'] = [
                'type' => 'user',
                'id' => $user['id'],
                'username' => $user['username'],
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'is_admin' => $user['is_admin'],
                'avatar' => $final_avatar,
                'color' => $user['color'],
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
    <link href="css/member_login.css" rel="stylesheet">
    <link href="css/lounge.css" rel="stylesheet">
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
                                            $excluded_folders = ['staff', 'bg', 'icon', 'time-limited', 'default', 'drrrjp', 'drrrx2'];
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
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                                                <i class="fas fa-times"></i> Clear
                                            </button>
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
                                $priority_folders = ['time-limited'];
                                $nonpriority_folders = ['default', 'drrrjp'];
                                $drrrx2 = ['drrrx2'];
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
                                    if (in_array(strtolower($color_name), $nonpriority_folders)) continue;
                                    if (in_array(strtolower($color_name), $drrrx2)) continue;

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

                                // Show priority folders first if they exist
                                foreach ($nonpriority_folders as $folder) {
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

                                // Show priority folders first if they exist
                                foreach ($drrrx2 as $folder) {
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
                                                echo '<img src="' . $web_base_dir . $folder . '/' . $img_file . '" class="avatar x2style" data-avatar="' . $folder . '/' . $img_file . '" alt="Avatar option">';
                                            }
                                        }
                                        echo '</div></div>';
                                    }
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
                            
                            <!-- Replace the existing "Selected Avatar" section with this: -->
<div class="mb-4">
    <label class="form-label"><i class="fas fa-image"></i> Selected Avatar</label>
    <div class="selected-preview-row mb-4">
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
        <div class="selected-color-preview">
            <div class="preview-circle color-black" id="selectedColorPreview"></div>
            <div>
                <strong id="selectedColorName">Black</strong> - Your message bubble color
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
                            

<!-- Replace the existing Avatar Color Customization and Color Selection sections with this: -->
<div class="customize-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <label class="form-label mb-0">
            <i class="fas fa-cog"></i> Customize Appearance
        </label>
        <button class="btn btn-outline-primary btn-md" type="button" data-bs-toggle="collapse" data-bs-target="#customizeCollapse" aria-expanded="false" aria-controls="customizeCollapse">
            <i class="fas fa-chevron-down" id="customizeChevron"></i> Options
        </button>
    </div>
    
    <div class="collapse" id="customizeCollapse">
        <div class="customize-content">
            <!-- Avatar Color Customization -->
            <div class="avatar-color-sliders">
                <label class="form-label">
                    <i class="fas fa-adjust"></i> Customize Avatar Color
                </label>
                
                <div class="color-slider-container">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="hueSlider" class="form-label mb-0">Hue Shift</label>
                        <span class="slider-value" id="hueValue">0°</span>
                    </div>
                    <input type="range" class="color-slider" id="hueSlider" name="hue_shift" 
                           min="0" max="360" value="0" oninput="updateAvatarFilter()">
                </div>
                
                <div class="color-slider-container">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="saturationSlider" class="form-label mb-0">Saturation</label>
                        <span class="slider-value" id="saturationValue">100%</span>
                    </div>
                    <input type="range" class="color-slider" id="saturationSlider" name="saturation" 
                           min="0" max="300" value="100" oninput="updateAvatarFilter()">
                </div>
                
                <div class="form-text text-muted">
                    <i class="fas fa-info-circle"></i> Adjust hue and saturation to customize your avatar's colors
                </div>
            </div>

            <hr class="my-4">


         <!-- Chat Color Selection -->
            <div class="color-selection-section">
                <label class="form-label">
                    <i class="fas fa-palette"></i> Choose Your Chat Color
                </label>
                
                <div class="color-grid">
                            <div class="color-option color-black" data-color="black" onclick="selectColor('black', this)">

                                    <div class="color-name">Black</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-blue" data-color="blue" onclick="selectColor('blue', this)">
                                    <div class="color-name">Blue</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-purple" data-color="purple" onclick="selectColor('purple', this)">
                                    <div class="color-name">Purple</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-pink" data-color="pink" onclick="selectColor('pink', this)">
                                    <div class="color-name">Pink</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-cyan" data-color="cyan" onclick="selectColor('cyan', this)">
                                    <div class="color-name">Cyan</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-mint" data-color="mint" onclick="selectColor('mint', this)">
                                    <div class="color-name">Mint</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-orange" data-color="orange" onclick="selectColor('orange', this)">
                                    <div class="color-name">Orange</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-green" data-color="green" onclick="selectColor('green', this)">
                                    <div class="color-name">Green</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-red" data-color="red" onclick="selectColor('red', this)">
                                    <div class="color-name">Red</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-yellow" data-color="yellow" onclick="selectColor('yellow', this)">
                                    <div class="color-name">Yellow</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-teal" data-color="teal" onclick="selectColor('teal', this)">
                                    <div class="color-name">Teal</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-indigo" data-color="indigo" onclick="selectColor('indigo', this)">
                                    <div class="color-name">Indigo</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                </div>
                                 <input type="hidden" id="selectedColor" name="color" value="">
                                
                            
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
        let userManuallySelectedColor = false;
        
$(document).ready(function() {

    // Add this to the document ready function in both files:
$('#customizeCollapse').on('show.bs.collapse', function () {
    $('#customizeChevron').removeClass('fa-chevron-down').addClass('fa-chevron-up');
});

$('#customizeCollapse').on('hide.bs.collapse', function () {
    $('#customizeChevron').removeClass('fa-chevron-up').addClass('fa-chevron-down');
});
    // NUCLEAR OPTION: Completely disable native form submission
    $('#userLoginForm').attr('onsubmit', 'return false;');
    $('#userLoginForm').removeAttr('action');
    $('#userLoginForm').removeAttr('method');
    
    // Remove any existing event handlers
    $('#userLoginForm').off('submit');
    $('button[type="submit"]').off('click');
    
    // Monitor ALL possible submission triggers
    $(document).on('submit', '#userLoginForm', function(e) {
        console.log('🚨 LOGIN FORM SUBMIT EVENT - BLOCKED');
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    });
    
    $(document).on('click', '#userLoginForm button[type="submit"]', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        console.log('🚨 LOGIN SUBMIT BUTTON CLICK - REDIRECTED TO CUSTOM HANDLER');
        handleUserLogin();
        return false;
    });
    
    $(document).on('keypress', '#userLoginForm input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            console.log('🚨 LOGIN ENTER KEY PRESSED - REDIRECTED TO CUSTOM HANDLER');
            handleUserLogin();
            return false;
        }
    });
    
    // Initialize sliders
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    updateAvatarFilter();
    
    // Add real-time slider tracking
    $('#hueSlider, #saturationSlider').on('input change', function() {
        updateAvatarFilter();
    });
    
    updateAvatarStats();
    
    // Avatar selection handling
    $(document).on('click', '.avatar', function() {
    $('.avatar').removeClass('selected').css('filter', '');
    $(this).addClass('selected');
    
    const avatarPath = $(this).data('avatar');
    $('#selectedAvatar').val(avatarPath);
    
    // Auto-select color based on avatar (only if user hasn't manually changed it)
    if (!userManuallySelectedColor) {
        const defaultColor = getAvatarDefaultColor(avatarPath);
        selectColor(defaultColor, document.querySelector(`[data-color="${defaultColor}"]`), true);
    }
    
    $('#selectedAvatarImg').attr('src', $(this).attr('src'));
    $('#selectedAvatarPreview').show();
    $('#noAvatarSelected').hide();
    
    updateAvatarFilter();
});

    // Filter dropdown
    $('#avatarSort').on('change', function() {
        filterAvatars();
    });
    
    // Initialize color selection
   // Change the initialization to mark it as automatic:
selectColor('black', document.querySelector('[data-color="black"]'), true);


    // Username field live preview
let usernameTimeout;
$('#username').on('input', function() {
    clearTimeout(usernameTimeout);
    const username = $(this).val().trim();
    
    if (username.length >= 3) {
        usernameTimeout = setTimeout(function() {
            fetchUserAvatar(username);
        }, 500); // Debounce for 500ms
    } else {
        // Clear preview when username is too short
        clearUserAvatarPreview();
    }
});

function fetchUserAvatar(username) {
    $.ajax({
        url: 'api/get_user_avatar.php',
        method: 'GET',
        data: { username: username },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success' && res.avatar) {
                showUserAvatarPreview(res.avatar);
            } else {
                clearUserAvatarPreview();
            }
        },
        error: function() {
            clearUserAvatarPreview();
        }
    });
}

function showUserAvatarPreview(avatarPath) {
    if ($('#selectedAvatar').val() === '') {
        $('#selectedAvatarImg').attr('src', 'images/' + avatarPath);
        $('#selectedAvatarPreview').show();
        $('#noAvatarSelected .text-muted p').text('Your saved avatar');
        
        // Only auto-select color if user hasn't manually changed it
        if (!userManuallySelectedColor) {
            const defaultColor = getAvatarDefaultColor(avatarPath);
            selectColor(defaultColor, document.querySelector(`[data-color="${defaultColor}"]`), true);
        }
    }
}

function clearUserAvatarPreview() {
    // Only clear if no avatar is manually selected
    if ($('#selectedAvatar').val() === '') {
        $('#selectedAvatarPreview').hide();
        $('#noAvatarSelected .text-muted p').text('Using saved/custom avatar');
    }
}

});

let userLoginInProgress = false;

function handleUserLogin() {
    if (userLoginInProgress) {
        console.log('🛑 User login already in progress - BLOCKED');
        return false;
    }


    
    userLoginInProgress = true;
    
    const username = $('#username').val().trim();
    const password = $('#password').val();
    const selectedAvatar = $('#selectedAvatar').val();
    const selectedColor = $('#selectedColor').val();
    const hueShift = $('#hueSlider').val() || 0;
    const saturation = $('#saturationSlider').val() || 100;

    
    
    console.log('Login form values - hue:', hueShift, 'sat:', saturation);
    
    if (!username || !password) {
        userLoginInProgress = false;
        alert('Please enter both username and password');
        return false;
    }
    
    const submitBtn = $('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Logging in...');
    $('#userLoginForm').find('input, button, select').prop('disabled', true);
    
    const submissionId = 'login_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    const formData = {
        username: username,
        password: password,
        avatar: selectedAvatar,
        color: selectedColor,
        avatar_hue: hueShift,
        avatar_saturation: saturation,
        type: 'user',
        submission_id: submissionId
    };
    
    console.log('Sending login data:', formData);
    
    $.ajax({
        url: 'api/login.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 15000,
        cache: false,
        success: function(res) {
            if (res.status === 'success') {
                setTimeout(function() {
                    window.location.href = 'lounge.php';
                }, 100);
            } else {
                resetLoginForm(submitBtn, originalText);
                alert('Error: ' + (res.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            resetLoginForm(submitBtn, originalText);
            alert('Connection error: ' + error);
        }
    });
    
    return false;
}

function resetLoginForm(submitBtn, originalText) {
    userLoginInProgress = false;
    $('#userLoginForm').find('input, button, select').prop('disabled', false);
    submitBtn.prop('disabled', false).html(originalText);
}

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
    
    // Re-trigger username preview if username exists
    const username = $('#username').val().trim();
    if (username.length >= 3) {
        fetchUserAvatar(username);
    }
    
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

function selectColor(colorName, element, isAutomatic = false) {
    // Track if this was a manual selection (not automatic from avatar)
    if (!isAutomatic) {
        userManuallySelectedColor = true;
    }
    
    // Remove selected class from all options
    document.querySelectorAll('.color-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Add selected class to clicked option
    element.classList.add('selected');
    
    // Update hidden input
    document.getElementById('selectedColor').value = colorName;
    
    // Update preview
    const preview = document.getElementById('selectedColorPreview');
    preview.className = `preview-circle color-${colorName}`;
    
    // Update color name
    document.getElementById('selectedColorName').textContent = colorName.charAt(0).toUpperCase() + colorName.slice(1);
}

function resetColorToDefault() {
    //selectColor('black', document.querySelector('[data-color="black"]'));
}

function updateAvatarFilter() {
    const hue = $('#hueSlider').val() || 0;
    const saturation = $('#saturationSlider').val() || 100;
    
    $('#hueValue').text(hue + '°');
    $('#saturationValue').text(saturation + '%');
    
    const selectedAvatar = $('.avatar.selected');
    if (selectedAvatar.length > 0) {
        const filter = `hue-rotate(${hue}deg) saturate(${saturation/100})`;
        selectedAvatar.css('filter', filter);
        selectedAvatar.addClass('avatar-customized');
    }
    
    // Also apply to preview image if it exists
    const previewImg = $('#selectedAvatarImg');
    if (previewImg.length > 0 && previewImg.is(':visible')) {
        const filter = `hue-rotate(${hue}deg) saturate(${saturation/100})`;
        previewImg.css('filter', filter);
    }
}

// Add this to track manual color clicks:
$(document).on('click', '.color-option', function() {
    const colorName = $(this).data('color');
    selectColor(colorName, this, false); // false = manual selection
});
</script>
    <script src="js/script.js"></script>
    <!-- Add this script tag before the closing </body> tag -->
<script src="js/avatar-color-mapping.js"></script>
</body>
</html>
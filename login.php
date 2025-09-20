<?php
session_start();
require_once 'security_config.php';

// Add to index.php - right after session_start()

if (!isset($_SESSION['firewall_passed'])) {
    header("Location: firewall.php");
    exit;
}

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
<?php $versions = include 'config/version.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login | Duranu</title>
    <?php include 'fav.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/member_login.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/lounge.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/bubble_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/color_previews.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/private_bubble_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/cus_modal.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/loading.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
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
    <div class="container-fluid">
        <div class="login-container">
            <!-- Header -->
            <div class="login-header">
    <div class="header-logo-section">
        <img src="images/duranu.png" alt="Duranu Logo" class="site-logo">
        <h1 class="login-title h4">
            <i class="fas fa-user"></i> Member Login
        </h1>
        <p class="text-muted mb-0">Sign in to your account</p>
    </div>
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
                                            $excluded_folders = ['staff', 'bg', 'icon', 'covers'];
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
                                $excluded_folders = ['staff', 'bg', 'icon', 'covers'];
                                $priority_folders = ['time-limited', 'community'];
                                $nonpriority_folders = ['recolored', 'default', 'mushoku', 'secret', 'drrrjp', 'drrrkari', 'drrrx2'];
                                $drrrcom = ['drrr.com'];
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
                                        echo '<div class="d-flex flex-wrap justify-content-center">';
                                        
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
                                    if (in_array(strtolower($color_name), $drrrcom)) continue;

                                    $folder_avatars = glob($color_dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
                                    $folder_count = count($folder_avatars);
                                    $total_avatars += $folder_count;
                                    
                                    echo '<div class="avatar-group" data-group="' . strtolower($color_name) . '">';
                                    echo '<h6><i class="fas fa-folder"></i> ' . ucfirst($color_name) . ' Avatars <span class="avatar-count">' . $folder_count . '</span></h6>';
                                    echo '<div class="d-flex flex-wrap justify-content-center">';
                                    
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
                                        echo '<div class="d-flex flex-wrap justify-content-center">';
                                        
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
                                foreach ($drrrcom as $folder) {
                                    $color_dir = $image_base_dir . '/' . $folder;
                                    if (is_dir($color_dir)) {
                                        $folder_avatars = glob($color_dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
                                        $folder_count = count($folder_avatars);
                                        $total_avatars += $folder_count;
                                        
                                        echo '<div class="avatar-group" data-group="' . strtolower($folder) . '">';
                                        echo '<h6><i class="fas fa-star"></i> ' . ucfirst($folder) . ' Avatars <span class="avatar-count">' . $folder_count . '</span></h6>';
                                        echo '<div class="d-flex flex-wrap justify-content-center">';
                                        
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
<!-- Replace both separate modals with this single combined modal -->

<!-- Updated preview section with single settings button -->
<div class="mb-4">
    <label class="form-label">
        <i class="fas fa-image"></i> Selected Avatar & Chat Color
    </label>
    
    <!-- Keep the original preview row structure -->
    <div class="selected-preview-row mb-3">
        <div class="selected-avatar-preview">
            <div id="selectedAvatarPreview" style="display: none;">
                <img id="selectedAvatarImg" src="" width="58" height="58" class="avatar-sel" style="border-color: #007bff !important;">
                <p class="mt-2 mb-0 small text-muted">Avatar selected</p>
            </div>
            <div id="noAvatarSelected">
                <div class="text-muted">
                    <i class="fas fa-image fa-2x mb-2"></i>
                    <p class="mb-0 small">No avatar selected</p>
                </div>
            </div>
        </div>
        
        <div class="selected-color-preview">
            <div class="preview-circle color-black" id="selectedColorPreview"></div>
            <strong id="selectedColorName" style="width:0;height:0;visibility:hidden;display:flex;"></strong>
        </div>
    </div>
    
    <!-- Single settings button -->
    <div class="settings-buttons-row mb-3">
        <button type="button" class="settings-btn" data-bs-toggle="modal" data-bs-target="#customizationModal">
            <i class="fas fa-cogs"></i> Customize Appearance
        </button>
    </div>
</div>

<div class="d-grid gap-2 mb-4">
    <button type="submit" class="btn btn-primary btn-lg">
        <i class="fas fa-sign-in-alt"></i> Enter Lounge
    </button>
</div>

<!-- Single Combined Customization Modal -->
<div class="modal fade" id="customizationModal" tabindex="-1" aria-labelledby="customizationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customizationModalLabel">
                    <i class="fas fa-cogs"></i> Customize Your Appearance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body">
                <!-- Combined Preview Section -->
                <div class="modal-preview-section mb-4">
                    <label class="form-label">
                        <i class="fas fa-eye"></i> Live Preview
                    </label>
                    <div class="modal-preview-row">
                        <div class="modal-avatar-preview">
                            <div id="modalAvatarPreview" class="modal-avatar-display">
                                <div id="modalSelectedAvatarPreview" style="display: none;">
                                    <img id="modalSelectedAvatarImg" src="" width="80" height="80" class="modal-avatar-img">
                                    <p class="mt-2 mb-0 small text-muted">Your Avatar</p>
                                </div>
                                <div id="modalNoAvatarSelected">
                                    <div class="text-muted">
                                        <i class="fas fa-image fa-3x mb-2"></i>
                                        <p class="mb-0 small">No Avatar Selected</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-color-preview">
                            <div class="modal-color-display">
                                <div class="modal-preview-circle color-black" id="modalSelectedColorPreview"></div>
                                <p class="mt-2 mb-0 small text-muted">Chat Color</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabbed Interface for Settings -->
                <ul class="nav nav-tabs mb-4" id="customizationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="avatar-tab" data-bs-toggle="tab" data-bs-target="#avatar-panel" type="button" role="tab" aria-controls="avatar-panel" aria-selected="true">
                            <i class="fas fa-user-edit"></i> Avatar Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="chat-color-tab" data-bs-toggle="tab" data-bs-target="#chat-color-panel" type="button" role="tab" aria-controls="chat-color-panel" aria-selected="false">
                            <i class="fas fa-palette"></i> Chat Color
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="customizationTabContent">
                    <!-- Avatar Settings Panel -->
                    <div class="tab-pane fade show active" id="avatar-panel" role="tabpanel" aria-labelledby="avatar-tab">
                        <div class="avatar-color-sliders">
                            <label class="form-label">
                                <i class="fas fa-adjust"></i> Avatar Color Adjustment
                            </label>
                            
                            <div class="color-slider-container">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label for="hueSlider" class="form-label mb-0">
                                        <i class="fas fa-adjust"></i> Hue Shift
                                    </label>
                                    <span class="slider-value" id="hueValue">0°</span>
                                </div>
                                <input type="range" class="color-slider" id="hueSlider" name="avatar_hue" 
                                       min="0" max="360" value="0">
                            </div>
                            
                            <div class="color-slider-container">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label for="saturationSlider" class="form-label mb-0">
                                        <i class="fas fa-tint"></i> Saturation
                                    </label>
                                    <span class="slider-value" id="saturationValue">100%</span>
                                </div>
                                <input type="range" class="color-slider" id="saturationSlider" name="avatar_saturation" 
                                       min="1" max="300" value="100">
                            </div>
                            
                            <div class="form-text text-muted mt-3">
                                <i class="fas fa-info-circle"></i> Adjust hue and saturation to customize your avatar's colors
                            </div>
                            
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetAvatarSliders()">
                                    <i class="fas fa-undo"></i> Reset Avatar Colors
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Color Panel -->
                    <div class="tab-pane fade" id="chat-color-panel" role="tabpanel" aria-labelledby="chat-color-tab">
                        <!-- Color Selection Grid -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-swatchbook"></i> Choose Your Chat Color
                            </label>
                            
                            <div class="color-grid">
                                <!-- All your existing color options -->
                                <div class="color-option color-black" data-color="black" onclick="selectColor('black', this)">
                                    <div class="color-name">Black</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-policeman2" data-color="policeman2" onclick="selectColor('policeman2', this)">
                                    <div class="color-name">Black?</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-negative" data-color="negative" onclick="selectColor('negative', this)">
                                    <div class="color-name">Negative</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-cnegative" data-color="cnegative" onclick="selectColor('cnegative', this)">
                                    <div class="color-name">Color-Negative</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-caution" data-color="caution" onclick="selectColor('caution', this)">
                                    <div class="color-name">Color-Caution</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-gray" data-color="gray" onclick="selectColor('gray', this)">
                                    <div class="color-name">Gray</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-darkgray" data-color="darkgray" onclick="selectColor('darkgray', this)">
                                    <div class="color-name">Dark Gray</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-tan" data-color="tan" onclick="selectColor('tan', this)">
                                    <div class="color-name">Tan</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-blue" data-color="blue" onclick="selectColor('blue', this)">
                                    <div class="color-name">Blue</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-cobalt" data-color="cobalt" onclick="selectColor('cobalt', this)">
                                    <div class="color-name">Cobalt</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-teal2" data-color="teal2" onclick="selectColor('teal2', this)">
                                    <div class="color-name">Teal2</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-navy" data-color="navy" onclick="selectColor('navy', this)">
                                    <div class="color-name">Navy</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-cyan" data-color="cyan" onclick="selectColor('cyan', this)">
                                    <div class="color-name">Cyan</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-purple" data-color="purple" onclick="selectColor('purple', this)">
                                    <div class="color-name">Purple</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-lavender" data-color="lavender" onclick="selectColor('lavender', this)">
                                    <div class="color-name">Lavender</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-lavender2" data-color="lavender2" onclick="selectColor('lavender2', this)">
                                    <div class="color-name">Lavender2</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-pink" data-color="pink" onclick="selectColor('pink', this)">
                                    <div class="color-name">Pink</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-orange" data-color="orange" onclick="selectColor('orange', this)">
                                    <div class="color-name">Orange</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-orange2" data-color="orange2" onclick="selectColor('orange2', this)">
                                    <div class="color-name">Blorange</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-peach" data-color="peach" onclick="selectColor('peach', this)">
                                    <div class="color-name">Peach</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-green" data-color="green" onclick="selectColor('green', this)">
                                    <div class="color-name">Green</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-urban" data-color="urban" onclick="selectColor('urban', this)">
                                    <div class="color-name">Urban</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-mudgreen" data-color="mudgreen" onclick="selectColor('mudgreen', this)">
                                    <div class="color-name">Mud Green</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-palegreen" data-color="palegreen" onclick="selectColor('palegreen', this)">
                                    <div class="color-name">Pale Green</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-red" data-color="red" onclick="selectColor('red', this)">
                                    <div class="color-name">Red</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-toyred" data-color="toyred" onclick="selectColor('toyred', this)">
                                    <div class="color-name">Toy Red</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-spooky" data-color="spooky" onclick="selectColor('spooky', this)">
                                    <div class="color-name">Spooky</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-rose" data-color="rose" onclick="selectColor('rose', this)">
                                    <div class="color-name">Rose</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                                
                                <div class="color-option color-yellow" data-color="yellow" onclick="selectColor('yellow', this)">
                                    <div class="color-name">Yellow</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                 <div class="color-option color-bbyellow" data-color="bbyellow" onclick="selectColor('bbyellow', this)">
                                    <div class="color-name">Yellow2</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-brown" data-color="brown" onclick="selectColor('brown', this)">
                                    <div class="color-name">Brown</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-deepbrown" data-color="deepbrown" onclick="selectColor('deepbrown', this)">
                                    <div class="color-name">Brown2</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-chiipink" data-color="chiipink" onclick="selectColor('chiipink', this)">
                                    <div class="color-name">Pink2</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-forest" data-color="forest" onclick="selectColor('forest', this)">
                                    <div class="color-name">Forest</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-rust" data-color="rust" onclick="selectColor('rust', this)">
                                    <div class="color-name">Rust</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-babyblue" data-color="babyblue" onclick="selectColor('babyblue', this)">
                                    <div class="color-name">Babyblue</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-sepia" data-color="sepia" onclick="selectColor('sepia', this)">
                                    <div class="color-name">Sepia</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>

                                <div class="color-option color-lenn" style="display:none;"data-color="lenn" onclick="selectColor('lenn', this)">
                                    <div class="color-name">Lenn</div>
                                    <div class="selected-indicator"><i class="fas fa-check"></i></div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Bubble Color Customization -->
                        <div class="bubble-color-sliders">
                            <label class="form-label">
                                <i class="fas fa-comment"></i> Fine-tune Bubble Color
                            </label>

                            <div class="color-slider-container">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label for="bubbleHueSlider" class="form-label mb-0">
                                        <i class="fas fa-adjust"></i> Bubble Hue
                                    </label>
                                    <span class="slider-value" id="bubbleHueValue">0°</span>
                                </div>
                                <input type="range" class="color-slider" id="bubbleHueSlider" name="bubble_hue" 
                                       min="0" max="360" value="0">
                            </div>

                            <div class="color-slider-container">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label for="bubbleSaturationSlider" class="form-label mb-0">
                                        <i class="fas fa-tint"></i> Bubble Saturation
                                    </label>
                                    <span class="slider-value" id="bubbleSaturationValue">100%</span>
                                </div>
                                <input type="range" class="color-slider" id="bubbleSaturationSlider" name="bubble_saturation" 
                                       min="1" max="300" value="100">
                            </div>
                            
                            <div class="form-text text-muted mt-3">
                                <i class="fas fa-info-circle"></i> Select a base color above, then fine-tune with hue and saturation
                            </div>
                            
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetChatColorSettings()">
                                    <i class="fas fa-undo"></i> Reset Chat Colors
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="resetAllSettings()">
                    <i class="fas fa-undo-alt"></i> Reset All
                </button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="fas fa-check"></i> Apply Changes
                </button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="selectedColor" name="color" value="">
<input type="hidden" id="selectedAvatar" name="avatar" required>
            </form>
            
            <!-- Links Section -->
            <div class="links-section">
                <p class="mb-3">Already have an account?</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-user"></i> Guest login
                    </a>
                    <a href="register.php" class="btn btn-outline-light">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                    <a href="forgot.php" class="btn btn-outline-light text-danger">
                        <i class="fas fa-lock"></i> Forgot Password
                    </a>
                </div>
            </div>
            <div>
                <p class="text-center text-muted mt-4">
                    <small>By joining as a guest, you agree to our <a href="terms.php" class="text-white">Terms of Service</a> and <a href="privacy.php" class="text-white">Privacy Policy</a>. ©Lenn, 2025.</small>
                </p>    
                
                </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/login.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/script.js?v=<?php echo $versions['version']; ?>"></script>
    <!-- Add this script tag before the closing </body> tag -->
<script src="js/avatar-color-mapping.js?v=<?php echo $versions['version']; ?>"></script>

<?php include 'terms_privacy_modals.php'; ?>
<?php include 'forgot_password.php'; ?>
<script src="js/loading.js?v=<?php echo $versions['version']; ?>"></script>
</body>
</html>
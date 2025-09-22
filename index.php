<?php

session_start();
require_once 'security_config.php';


require_once 'config/maintenance.php';

// Check if site is in maintenance mode
if (shouldShowMaintenance()) {
    header("Location: maintenance.php");
    exit;
}

// Existing firewall check
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

include 'db_connect.php';

?>
<?php $versions = include 'config/version.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Login | Duranu</title>
    <meta name="keywords" content="drrr-like-chat, duranu, drrrchat, drrr, darasu, dorasu, mushoku, drrrkari, durarara, durarara!!">
    <meta name="description" content="A free, anonymous chat service inspired by Durarara!!'s online chat. Join as a guest or register for an account to chat with others.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/style.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/guest_login.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/bubble_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/color_previews.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/private_bubble_colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/cus_modal.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/loading.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/pagination.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <?php include 'fav.php'; ?>
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
    <div class="container-fluid">
        <div class="login-container">
            <!-- Header -->
            <div class="login-header">
    <div class="header-logo-section">
        <img src="images/duranu.png" alt="Duranu Logo" class="site-logo">
        <h1 class="login-title h4">
            <i class="fas fa-user-plus"></i> Guest Login
        </h1>
        <p class="text-muted mb-0">Join the chat as a guest user</p>
    </div>
</div>
            
            <form id="guestLoginForm">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="avatar-selection-card">
                            <label class="form-label mb-3 me-2">
                                <i class="fas fa-images"></i> Choose Your Avatar, or let us pick one for you:
                            </label>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="randomAvatar()">
                                        <i class="fas fa-random"></i> Random
                                    </button>
                            <div class="avatar-container">
    <!-- Pagination Controls -->
    <div class="avatar-pagination-controls d-flex justify-content-between align-items-center mb-3">
        <button type="button" class="btn btn-outline-primary" id="prevPage" onclick="changePage(-1)">
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <div class="pagination-info">
            <span id="currentPageInfo">Page 1 of 1</span>
        </div>
        <button type="button" class="btn btn-outline-primary" id="nextPage" onclick="changePage(1)">
            Next <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <!-- Avatar Pages Container -->
    <div id="avatarPages">
        <?php
        $image_base_dir = __DIR__ . '/images';
        $web_base_dir = 'images/';
        $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        
        // Define pagination structure for index.php
        $avatar_pages = [];
        
        // Page 1: Special page with time-limited, community, and default
        $page1_folders = ['time-limited', 'community', 'default'];
        $avatar_pages[0] = $page1_folders;
        
        // Other folders for subsequent pages
        $other_folders = ['mushoku', 'secret', 'drrrjp', 'drrrkari', 'drrrx2', 'drrr.com'];
        
        // Each other folder gets its own page
        foreach ($other_folders as $folder) {
            if (is_dir($image_base_dir . '/' . $folder)) {
                $avatar_pages[] = [$folder];
            }
        }
        
        // Generate pages
        foreach ($avatar_pages as $page_index => $folders) {
            $page_num = $page_index + 1;
            echo '<div class="avatar-page" data-page="' . $page_num . '" style="' . ($page_index === 0 ? '' : 'display: none;') . '">';
            
           /* if ($page_index === 0) {
                echo '<h6 class="text-center mb-3"><i class="fas fa-star"></i> Featured Avatars</h6>';
            }*/
            
            foreach ($folders as $folder) {
                $folder_path = $image_base_dir . '/' . $folder;
                if (is_dir($folder_path)) {
                    $folder_avatars = glob($folder_path . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
                    $folder_count = count($folder_avatars);
                    
                    if ($folder_count > 0) {
                        echo '<div class="avatar-section">';
                       /* if ($page_index !== 0) {
                            echo '<h6><i class="fas fa-star"></i> ' . ucfirst($folder) . ' Avatars (' . $folder_count . ')</h6>';
                        } else {
                            echo '<div class="mb-2"><small class="text-muted">' . ucfirst($folder) . ' (' . $folder_count . ')</small></div>';
                        }*/
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
            }
            echo '</div>';
        }
        ?>
    </div>
</div>

                            <input type="hidden" id="selectedAvatar" name="avatar" required>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Scroll through the avatar gallery above to find one you like
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Color Selection -->
                         <div class="avatar-selection-card">
                            <div class="mb-4">
    <label for="guestName" class="form-label">
        <i class="fas fa-user"></i> Display Name
    </label>
    <input type="text" 
           class="form-control form-control-lg" 
           id="guestName" 
           placeholder="Enter your display name"
           required 
           maxlength="30">
    <div class="form-text text-muted">This is how others will see you in chat</div>
</div>

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
                    <a href="login.php" class="btn btn-outline-light">
                        <i class="fas fa-user"></i> Member Login
                    </a>
                    <a href="register.php" class="btn btn-outline-light">
                        <i class="fas fa-user-plus"></i> Create Account
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
    <script src="js/index.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/script.js?v=<?php echo $versions['version']; ?>"></script>
    <script src="js/avatar-color-mapping.js?v=<?php echo $versions['version']; ?>"></script>
<?php include 'terms_privacy_modals.php'; ?>
<script src="js/loading.js?v=<?php echo $versions['version']; ?>"></script>
</body>
</html>
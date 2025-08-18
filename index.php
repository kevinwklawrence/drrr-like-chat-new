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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/guest_login.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="login-container">
            <!-- Header -->
            <div class="login-header">
                <h1 class="login-title h3">
                    <i class="fas fa-user-plus"></i> Guest Login
                </h1>
                <p class="text-muted mb-0">Join the chat as a guest user</p>
            </div>
            
            <form id="guestLoginForm">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="avatar-selection-card">
                            <label class="form-label mb-3">
                                <i class="fas fa-images"></i> Choose Your Avatar
                            </label>
                            <div class="avatar-container">
                                <?php
                                $image_base_dir = __DIR__ . '/images';
                                $web_base_dir = 'images/';
                                $priority_folders = ['time-limited', 'default', 'drrrjp'];
                                $drrrx2 = ['drrrx2'];
                                $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

                                foreach ($priority_folders as $folder) {
                                    $folder_path = $image_base_dir . '/' . $folder;
                                    if (is_dir($folder_path)) {
                                        echo '<div class="avatar-section">';
                                        echo '<h6><i class="fas fa-star"></i> ' . ucfirst($folder) . ' Avatars</h6>';
                                        echo '<div class="d-flex flex-wrap">';
                                        foreach (glob($folder_path . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) as $img_path) {
                                            $img_file = basename($img_path);
                                            $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                                            if (in_array($ext, $allowed_ext)) {
                                                echo '<img src="' . $web_base_dir . $folder . '/' . $img_file . '" class="avatar" data-avatar="' . $folder . '/' . $img_file . '" alt="Avatar option">';
                                            }
                                        }
                                        echo '</div></div>';
                                    }
                                }

                                foreach ($drrrx2 as $folder) {
                                    $folder_path = $image_base_dir . '/' . $folder;
                                    if (is_dir($folder_path)) {
                                        echo '<div class="avatar-section">';
                                        echo '<h6><i class="fas fa-star"></i> ' . ucfirst($folder) . ' Avatars</h6>';
                                        echo '<div class="d-flex flex-wrap">';
                                        foreach (glob($folder_path . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) as $img_path) {
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
                            
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-image"></i> Selected Avatar
                                </label>
                                <div class="selected-preview-row mb-4">
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
        <div>
            <strong id="selectedColorName">Black</strong> - Your message bubble color
        </div>
    </div>
</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Enter Lounge
                                </button>
                            </div>
                        </div>

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
                    <input type="range" class="color-slider" id="hueSlider" name="avatar_hue" 
                           min="0" max="360" value="0" oninput="updateAvatarFilter()">
                </div>
                
                <div class="color-slider-container">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="saturationSlider" class="form-label mb-0">Saturation</label>
                        <span class="slider-value" id="saturationValue">100%</span>
                    </div>
                    <input type="range" class="color-slider" id="saturationSlider" name="avatar_saturation" 
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
                                <!-- Default: Black -->
                                <div class="color-option color-black" data-color="black" onclick="selectColor('black', this)">

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
                </div>

                
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
    <script>
        let userManuallySelectedColor = false;
$(document).ready(function() {

    $('#customizeCollapse').on('show.bs.collapse', function () {
    $('#customizeChevron').removeClass('fa-chevron-down').addClass('fa-chevron-up');
});

$('#customizeCollapse').on('hide.bs.collapse', function () {
    $('#customizeChevron').removeClass('fa-chevron-up').addClass('fa-chevron-down');
});
    
    // NUCLEAR OPTION: Completely disable native form submission
    $('#guestLoginForm').attr('onsubmit', 'return false;');
    $('#guestLoginForm').removeAttr('action');
    $('#guestLoginForm').removeAttr('method');
    
    // Remove any existing event handlers
    $('#guestLoginForm').off('submit');
    $('button[type="submit"]').off('click');
    
    // Track ALL form interactions
    let submitAttempts = 0;
    
    // Monitor ALL possible submission triggers
    $(document).on('submit', '#guestLoginForm', function(e) {
        submitAttempts++;
        console.log(`🚨 FORM SUBMIT EVENT #${submitAttempts} - BLOCKED`);
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    });
    
    $(document).on('click', 'button[type="submit"]', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        console.log('🚨 SUBMIT BUTTON CLICK - REDIRECTED TO CUSTOM HANDLER');
        handleGuestLogin();
        return false;
    });
    
    // Monitor for Enter key in form fields
    $(document).on('keypress', '#guestLoginForm input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            console.log('🚨 ENTER KEY PRESSED - REDIRECTED TO CUSTOM HANDLER');
            handleGuestLogin();
            return false;
        }
    });
    
    // Initialize sliders
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    updateAvatarFilter();
    
    // Add real-time slider tracking
    $('#hueSlider, #saturationSlider').on('input change', function() {
        console.log('Slider changed - hue:', $('#hueSlider').val(), 'sat:', $('#saturationSlider').val());
        updateAvatarFilter();
    });
    
    // Initialize color selection
    // Change the initialization to mark it as automatic:
selectColor('black', document.querySelector('[data-color="black"]'), true);
    
    // Update the avatar click handler in index.php:
$('.avatar').click(function() {
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
});

// Separate function for handling guest login
let guestLoginInProgress = false;

function handleGuestLogin() {
    console.log('=== CUSTOM GUEST LOGIN HANDLER ===');
    
    if (guestLoginInProgress) {
        console.log('🛑 Guest login already in progress - BLOCKED');
        return false;
    }
    
    guestLoginInProgress = true;
    
    const guestName = $('#guestName').val().trim();
    const selectedAvatar = $('#selectedAvatar').val();
    const selectedColor = $('#selectedColor').val();
    
    // Get slider values with extensive logging
    const hueElement = document.getElementById('hueSlider');
    const satElement = document.getElementById('saturationSlider');
    
    console.log('Hue element:', hueElement);
    console.log('Sat element:', satElement);
    console.log('Hue value:', hueElement ? hueElement.value : 'NULL');
    console.log('Sat value:', satElement ? satElement.value : 'NULL');
    
    const hueShift = hueElement ? parseInt(hueElement.value) || 0 : 0;
    const saturation = satElement ? parseInt(satElement.value) || 100 : 100;

    
    
    console.log('Final values - hue:', hueShift, 'sat:', saturation);

    
    
    if (!guestName) {
        guestLoginInProgress = false;
        alert('Please enter your display name');
        $('#guestName').focus();
        return false;
    }
    
    if (!selectedAvatar) {
        guestLoginInProgress = false;
        alert('Please select an avatar');
        return false;
    }
    
    // Show loading state
    const submitBtn = $('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Joining...');
    
    // Disable ALL form elements
    $('#guestLoginForm').find('input, button, select').prop('disabled', true);
    
    // Create unique submission ID
    const submissionId = 'guest_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    const formData = {
        guest_name: guestName,
        avatar: selectedAvatar,
        color: selectedColor,
        avatar_hue: hueShift,
        avatar_saturation: saturation,
        type: 'guest',
        submission_id: submissionId
    };
    
    console.log('Sending data with submission ID:', submissionId);
    console.log('Form data:', formData);
    
    $.ajax({
        url: 'api/join_lounge.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 15000,
        cache: false,
        success: function(res) {
            console.log('Response received:', res);
            if (res.status === 'success') {
                console.log('Success - redirecting...');
                // Add a small delay to prevent browser race conditions
                setTimeout(function() {
                    window.location.href = 'lounge.php';
                }, 100);
            } else {
                resetGuestForm(submitBtn, originalText);
                alert('Error: ' + (res.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            resetGuestForm(submitBtn, originalText);
            alert('Connection error: ' + error);
        }
    });
    
    return false;
}

function resetGuestForm(submitBtn, originalText) {
    guestLoginInProgress = false;
    $('#guestLoginForm').find('input, button, select').prop('disabled', false);
    submitBtn.prop('disabled', false).html(originalText);
}

// Color selection functions
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
    selectColor('black', document.querySelector('[data-color="black"]'));
}

function updateAvatarFilter() {
    const hue = $('#hueSlider').val() || 0;
    const saturation = $('#saturationSlider').val() || 100;
    
    console.log('UpdateAvatarFilter called - hue:', hue, 'sat:', saturation);
    
    $('#hueValue').text(hue + '°');
    $('#saturationValue').text(saturation + '%');
    
    // Apply filter to selected avatar
    const selectedAvatar = $('.avatar.selected');
    if (selectedAvatar.length > 0) {
        const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
        selectedAvatar.css('filter', filter);
        selectedAvatar.addClass('avatar-customized');
        console.log('Applied filter to avatar:', filter);
    }
    
    // Also apply to preview image if it exists
    const previewImg = $('#selectedAvatarImg');
    if (previewImg.length > 0 && previewImg.is(':visible')) {
        const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
        previewImg.css('filter', filter);
        console.log('Applied filter to preview:', filter);
    }
}

// Add this to track manual color clicks:
$(document).on('click', '.color-option', function() {
    const colorName = $(this).data('color');
    selectColor(colorName, this, false); // false = manual selection
});
</script>
    <script src="js/script.js"></script>
    <script src="js/avatar-color-mapping.js"></script>

</body>
</html>
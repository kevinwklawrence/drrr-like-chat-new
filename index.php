<?php
// This is the updated index.php with color selection integrated
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
                    <div class="col-lg-6">
                        <div class="avatar-selection-card">
                            <label class="form-label mb-3">
                                <i class="fas fa-images"></i> Choose Your Avatar
                            </label>
                            <div class="avatar-container">
                                <?php
                                $image_base_dir = __DIR__ . '/images';
                                $web_base_dir = 'images/';
                                $priority_folders = ['time-limited', 'default'];
                                $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

                                foreach ($priority_folders as $folder) {
                                    $folder_path = $image_base_dir . '/' . $folder;
                                    if (is_dir($folder_path)) {
                                        echo '<div class="avatar-section">';
                                        echo '<h6><i class="fas fa-star"></i> ' . ucfirst($folder) . ' Avatars</h6>';
                                        echo '<div class="d-flex flex-wrap ms-4">';
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
                                ?>
                            </div>
                            <input type="hidden" id="selectedAvatar" name="avatar" required>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Scroll through the avatar gallery above to find one you like
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
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
                        <div class="color-selection-section">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label">
                                    <i class="fas fa-palette"></i> Choose Your Chat Color
                                </label>
                               <!-- <button type="button" class="reset-btn" onclick="resetColorToDefault()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>-->
                            </div>
                            
                            <div class="color-grid">
                                <!-- Default: Black -->
                                <div class="color-option color-black selected" data-color="black" onclick="selectColor('black', this)">
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
                            
                           <input type="hidden" id="selectedColor" name="color" value="black">
                            
                            
                            
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
        // Updated JavaScript section for index.php (guest login)
$(document).ready(function() {
    // Initialize color selection
    selectColor('black', document.querySelector('[data-color="black"]'));
    
    // Avatar selection handling
    $('.avatar').click(function() {
        $('.avatar').removeClass('selected');
        $(this).addClass('selected');
        
        const avatarPath = $(this).data('avatar');
        $('#selectedAvatar').val(avatarPath);
        
        // Update preview
        $('#selectedAvatarImg').attr('src', $(this).attr('src'));
        $('#selectedAvatarPreview').show();
        $('#noAvatarSelected').hide();
    });

    // Form submission - UPDATED to include color
    $('#guestLoginForm').submit(function(e) {
        e.preventDefault();
        
        const guestName = $('#guestName').val().trim();
        const selectedAvatar = $('#selectedAvatar').val();
        const selectedColor = $('#selectedColor').val(); // Get selected color
        
        if (!guestName) {
            alert('Please enter your display name');
            $('#guestName').focus();
            return;
        }
        
        // Show loading state
        const submitBtn = $('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Joining...');
        
        $.ajax({
            url: 'api/join_lounge.php',
            method: 'POST',
            data: {
                guest_name: guestName,
                avatar: selectedAvatar,
                color: selectedColor, // Include color in the request
                type: 'guest'
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

// Color selection functions
function selectColor(colorName, element) {
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
    </script>
    <script src="js/script.js"></script>
</body>
</html>
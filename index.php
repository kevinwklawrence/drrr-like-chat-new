<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Login</title>
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
            max-width: 900px;
            width: 100%;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
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
        
        .avatar-selection-card {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .avatar-container {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #404040;
            border-radius: 10px;
            padding: 15px;
            background: #1a1a1a;
            margin-bottom: 20px;
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
        
        .avatar-section {
            margin-bottom: 20px;
        }
        
        .avatar-section h6 {
            color: #007bff;
            font-weight: 600;
            margin-bottom: 10px;
            border-bottom: 1px solid #404040;
            padding-bottom: 5px;
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
                max-height: 300px;
                padding: 10px;
            }
            
            .avatar {
                width: 50px;
                height: 50px;
                margin: 3px;
            }
        }
        
        @media (max-width: 480px) {
            .avatar {
                width: 45px;
                height: 45px;
                margin: 6px;
            }
            
            .login-container {
                padding: 20px 15px;
            }
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
    </style>
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
                                $priority_folders = ['default', 'time-limited'];
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
                                ?>
                            </div>
                            <input type="hidden" id="selectedAvatar" name="avatar" required>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Scroll through the avatar gallery above to find one you like
                            </div>
                        </div>
                    </div>

                    <!-- User Info -->
                    <div class="col-lg-4">
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
                                <div class="selected-avatar-preview">
                                    <div id="selectedAvatarPreview" style="display: none;">
                                        <img id="selectedAvatarImg" src="" width="58" height="58" class="avatar-sel" style="border-color: #007bff !important;">
                                        <p class="mt-2 mb-0 small text-muted">Avatar selected</p>
                                    </div>
                                    <div id="noAvatarSelected" >
                                        <div class="text-muted">
                                            <i class="fas fa-image fa-2x mb-2"></i>
                                            <p class="mb-0 small">No avatar selected</p>
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
                    </div>
                    
                    <!-- Avatar Selection -->
                    
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
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
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

            // Form submission
            $('#guestLoginForm').submit(function(e) {
                e.preventDefault();
                
                const guestName = $('#guestName').val().trim();
                const selectedAvatar = $('#selectedAvatar').val();
                
                if (!guestName) {
                    alert('Please enter your display name');
                    $('#guestName').focus();
                    return;
                }
                
                if (!selectedAvatar) {
                    alert('Please select an avatar');
                    $('.avatar-container')[0].scrollIntoView({ behavior: 'smooth' });
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
    </script>
    <script src="js/script.js"></script>
</body>
</html>
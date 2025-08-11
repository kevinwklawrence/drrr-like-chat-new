<?php
session_start();

// Include database connection
include 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle POST request for registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        error_log("Missing registration fields");
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    if ($password !== $confirm_password) {
        error_log("Password mismatch during registration");
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
        exit;
    }

    if (strlen($password) < 6) {
        error_log("Password too short during registration");
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email format during registration");
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit;
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("Prepare failed for username check in register.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        error_log("Username already exists: $username");
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        error_log("Prepare failed for email check in register.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        error_log("Email already exists: $email");
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Generate unique user_id: username + "#" + 6-digit random number
    $random_number = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $user_id_string = $username . "#" . $random_number;
    
    // Ensure user_id is unique (very unlikely collision, but safety first)
    $attempts = 0;
    while ($attempts < 10) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
        if (!$stmt) {
            error_log("Prepare failed for user_id check in register.php: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("s", $user_id_string);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            break; // user_id is unique
        }
        $stmt->close();
        
        // Generate new random number if collision
        $random_number = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $user_id_string = $username . "#" . $random_number;
        $attempts++;
    }
    
    if ($attempts >= 10) {
        error_log("Failed to generate unique user_id after 10 attempts");
        echo json_encode(['status' => 'error', 'message' => 'Registration temporarily unavailable']);
        exit;
    }

    // Replace this section in register.php (around line 85-95)

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// UPDATED: Use new default avatar path
$default_avatar = 'default/u0.png';

// Insert new user into database with avatar_memory initialization
$stmt = $conn->prepare("INSERT INTO users (username, email, password, user_id, avatar, avatar_memory, is_admin) VALUES (?, ?, ?, ?, ?, ?, 0)");
if (!$stmt) {
    error_log("Prepare failed for user insert in register.php: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ssssss", $username, $email, $hashed_password, $user_id_string, $default_avatar, $default_avatar);
if (!$stmt->execute()) {
    error_log("Execute failed for user insert in register.php: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$new_user_db_id = $conn->insert_id;
$stmt->close();

error_log("User registered with avatar and avatar_memory set to: $default_avatar");

    // Create session for newly registered user
    $_SESSION['user'] = [
        'type' => 'user',
        'id' => $new_user_db_id,
        'username' => $username,
        'user_id' => $user_id_string,  // This is the key field!
        'email' => $email,
        'is_admin' => 0,
        'avatar' => $default_avatar
    ];

    error_log("User registered successfully: username=$username, user_id=$user_id_string, db_id=$new_user_db_id");
    echo json_encode(['status' => 'success', 'message' => 'Registration successful']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/register.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="register-container">
            <!-- Header -->
            <div class="register-header">
                <h1 class="register-title h3">
                    <i class="fas fa-user-plus"></i> Create Account
                </h1>
                <p class="text-muted mb-0">Join our chat community</p>
            </div>
            
            <div class="row">
                <!-- Registration Form -->
                <div class="col-lg-12">
                    <div class="register-section">
                        <h5 class="mb-4"><i class="fas fa-clipboard-list"></i> Account Information</h5>
                        
                        <form id="registerForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user"></i> Username
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="username" 
                                               name="username" 
                                               placeholder="Choose a unique username"
                                               required 
                                               maxlength="20"
                                               pattern="^[a-zA-Z0-9_]{3,20}$">
                                        <div class="form-text">3-20 characters, letters, numbers, and underscores only</div>
                                        <div class="invalid-feedback"></div>
                                        <div class="valid-feedback">Username is available!</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               placeholder="Enter your email address"
                                               required>
                                        <div class="form-text">We'll never share your email</div>
                                        <div class="invalid-feedback"></div>
                                        <div class="valid-feedback">Email format is valid!</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock"></i> Password
                                        </label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Create a strong password"
                                               required 
                                               minlength="6">
                                        <div class="password-strength">
                                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                        </div>
                                        <div class="form-text">At least 6 characters</div>
                                        <div class="invalid-feedback"></div>
                                        <div class="valid-feedback">Password strength is good!</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label">
                                            <i class="fas fa-lock"></i> Confirm Password
                                        </label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               placeholder="Confirm your password"
                                               required>
                                        <div class="invalid-feedback"></div>
                                        <div class="valid-feedback">Passwords match!</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus"></i> Create My Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                
            </div>
            
            <!-- Links Section -->
            <div class="links-section">
                <p class="mb-3">Already have an account?</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="login.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-in-alt"></i> Login Here
                    </a>
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-user-friends"></i> Continue as Guest
                    </a>
                </div>
            </div>
            <div>
                <p class="text-center text-muted mt-4">
                    <small>By registering, you agree to our <a href="terms.php" class="text-white">Terms of Service</a> and <a href="privacy.php" class="text-white">Privacy Policy</a>. ©Lenn, 2025.</small>
                </p>    
                
                </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            let usernameCheckTimeout;
            let emailCheckTimeout;
            
            // Real-time username validation
            $('#username').on('input', function() {
                const username = $(this).val();
                const field = $(this);
                
                clearTimeout(usernameCheckTimeout);
                
                if (username.length < 3) {
                    field.removeClass('is-valid is-invalid');
                    return;
                }
                
                if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                    field.removeClass('is-valid').addClass('is-invalid');
                    field.siblings('.invalid-feedback').text('Username can only contain letters, numbers, and underscores (3-20 characters)');
                    return;
                }
                
                // Check availability after delay
                usernameCheckTimeout = setTimeout(() => {
                    checkUsernameAvailability(username, field);
                }, 500);
            });
            
            // Real-time email validation
            $('#email').on('input', function() {
                const email = $(this).val();
                const field = $(this);
                
                clearTimeout(emailCheckTimeout);
                
                if (email.length === 0) {
                    field.removeClass('is-valid is-invalid');
                    return;
                }
                
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    field.removeClass('is-valid').addClass('is-invalid');
                    field.siblings('.invalid-feedback').text('Please enter a valid email address');
                    return;
                }
                
                // Check availability after delay
                emailCheckTimeout = setTimeout(() => {
                    checkEmailAvailability(email, field);
                }, 500);
            });
            
            // Password strength checker
            $('#password').on('input', function() {
                const password = $(this).val();
                const field = $(this);
                const strengthBar = $('#passwordStrengthBar');
                
                if (password.length === 0) {
                    field.removeClass('is-valid is-invalid');
                    strengthBar.removeClass().addClass('password-strength-bar');
                    return;
                }
                
                if (password.length < 6) {
                    field.removeClass('is-valid').addClass('is-invalid');
                    field.siblings('.invalid-feedback').text('Password must be at least 6 characters');
                    strengthBar.removeClass().addClass('password-strength-bar');
                    return;
                }
                
                const strength = calculatePasswordStrength(password);
                strengthBar.removeClass().addClass('password-strength-bar strength-' + strength.level);
                
                if (strength.score >= 3) {
                    field.removeClass('is-invalid').addClass('is-valid');
                } else {
                    field.removeClass('is-valid').addClass('is-invalid');
                    field.siblings('.invalid-feedback').text('Password could be stronger');
                }
                
                // Check confirm password if it has value
                if ($('#confirm_password').val()) {
                    checkPasswordMatch();
                }
            });
            
            // Confirm password validation
            $('#confirm_password').on('input', checkPasswordMatch);
            
            // Form submission
            $('#registerForm').on('submit', function(e) {
                e.preventDefault();
                
                // Final validation
                if (!validateForm()) {
                    return;
                }
                
                const formData = {
                    username: $('#username').val().trim(),
                    email: $('#email').val().trim(),
                    password: $('#password').val(),
                    confirm_password: $('#confirm_password').val()
                };
                
                // Show loading state
                const submitBtn = $('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating Account...');
                
                $.ajax({
                    url: 'register.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert('Registration successful! Redirecting to lounge...');
                            window.location.href = 'lounge.php';
                        } else {
                            alert('Error: ' + response.message);
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
        
        function checkUsernameAvailability(username, field) {
            // In a real implementation, you'd make an AJAX call to check availability
            // For now, we'll just validate format
            field.removeClass('is-invalid').addClass('is-valid');
        }
        
        function checkEmailAvailability(email, field) {
            // In a real implementation, you'd make an AJAX call to check availability
            // For now, we'll just validate format
            field.removeClass('is-invalid').addClass('is-valid');
        }
        
        function calculatePasswordStrength(password) {
            let score = 0;
            let level = 'weak';
            
            // Length
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            
            // Character types
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            if (score <= 2) level = 'weak';
            else if (score <= 3) level = 'fair';
            else if (score <= 4) level = 'good';
            else level = 'strong';
            
            return { score, level };
        }
        
        function checkPasswordMatch() {
            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();
            const field = $('#confirm_password');
            
            if (confirmPassword.length === 0) {
                field.removeClass('is-valid is-invalid');
                return;
            }
            
            if (password === confirmPassword) {
                field.removeClass('is-invalid').addClass('is-valid');
            } else {
                field.removeClass('is-valid').addClass('is-invalid');
                field.siblings('.invalid-feedback').text('Passwords do not match');
            }
        }
        
        function validateForm() {
            let isValid = true;
            
            // Check all required fields have valid class
            $('.form-control[required]').each(function() {
                if (!$(this).hasClass('is-valid') || $(this).val().trim() === '') {
                    isValid = false;
                    if (!$(this).hasClass('is-invalid')) {
                        $(this).addClass('is-invalid');
                    }
                }
            });
            
            return isValid;
        }
    </script>
</body>
</html>
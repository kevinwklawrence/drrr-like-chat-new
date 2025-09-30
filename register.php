<?php
session_start();

require_once 'security_config.php';

// Add to index.php - right after session_start()

if (!isset($_SESSION['firewall_passed'])) {
    header("Location: /firewall");
    exit;
}

if (isset($_SESSION['user'])) {
    if (isset($_SESSION['room_id'])) {
        header("Location: /room");
    } else {
        header("Location: /lounge");
    }
    exit;
}
// Include database connection
include 'db_connect.php';

// Handle POST request for registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    if ($password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
$default_avatar = 'default/_u0.png';

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
<?php $versions = include 'config/version.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Duranu</title>
    <?php include 'fav.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/style.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/register.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="register-container">
            <!-- Header -->
            <div class="register-header">
    <div class="header-logo-section">
        <img src="images/duranu.png" alt="Duranu Logo" class="site-logo">
        <h1 class="register-title h4">
            <i class="fas fa-user-plus"></i> Create Account
        </h1>
        <p class="text-muted mb-0">Join our chat community</p>
    </div>
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
                                               maxlength="20">
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
                    <a href="/member" class="btn btn-outline-light">
                        <i class="fas fa-sign-in-alt"></i> Login Here
                    </a>
                    <a href="/guest" class="btn btn-outline-light">
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
    <script src="js/register.js?v=<?php echo $versions['version']; ?>"></script>

    <?php include 'terms_privacy_modals.php'; ?>
</body>
</html>
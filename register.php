<?php
// Add this near the top after session_start()
session_start();
include 'db_connect.php';

// Check if user has temp access or already registered
$ip_address = $_SERVER['REMOTE_ADDR'];
$invite_code_used = null;
$inviter_user_id = null;

$stmt = $conn->prepare("SELECT code, inviter_user_id, account_created, expires_at < NOW() as is_expired 
    FROM invite_usage 
    WHERE invitee_ip = ? 
    ORDER BY first_used_at DESC LIMIT 1");
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $usage = $result->fetch_assoc();
    
    if ($usage['account_created']) {
        // Already registered
        $stmt->close();
        header("Location: /login");
        exit;
    }
    
    if (!$usage['is_expired']) {
        // Valid temp access
        $invite_code_used = $usage['code'];
        $inviter_user_id = $usage['inviter_user_id'];
    } else {
        // Force registration - expired
        $invite_code_used = $usage['code'];
        $inviter_user_id = $usage['inviter_user_id'];
    }
    $stmt->close();
} else {
    // No invite usage found
    $stmt->close();
    header("Location: /firewall");
    exit;
}

// Handle registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields required']);
        exit;
    }
    
    if (strlen($username) < 3 || strlen($username) > 20) {
        echo json_encode(['status' => 'error', 'message' => 'Username must be 3-20 characters']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    // Check username exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username taken']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Check email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Generate unique user_id
    $random_number = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $user_id_string = $username . "#" . $random_number;
    
    $attempts = 0;
    while ($attempts < 10) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $user_id_string);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            break;
        }
        $stmt->close();
        $random_number = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $user_id_string = $username . "#" . $random_number;
        $attempts++;
    }
    
    try {
        $conn->begin_transaction();
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_avatar = 'default/_u0.png';
        
        // Insert user with invite code
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_id, avatar, avatar_memory, dura, invite_code_used) 
            VALUES (?, ?, ?, ?, ?, ?, 100, ?)");
        $stmt->bind_param("sssssss", $username, $email, $hashed_password, $user_id_string, $default_avatar, $default_avatar, $invite_code_used);
        $stmt->execute();
        $new_user_id = $conn->insert_id;
        $stmt->close();
        
        // Update invite_usage
        $stmt = $conn->prepare("UPDATE invite_usage SET invitee_user_id = ?, account_created = 1 WHERE invitee_ip = ?");
        $stmt->bind_param("is", $new_user_id, $ip_address);
        $stmt->execute();
        $stmt->close();
        
        // Give inviter 100 Dura
        $stmt = $conn->prepare("UPDATE users SET dura = dura + 100 WHERE id = ?");
        $stmt->bind_param("i", $inviter_user_id);
        $stmt->execute();
        $stmt->close();
        
        // Log transaction
        $stmt = $conn->prepare("INSERT INTO dura_transactions (from_user_id, to_user_id, amount, type) VALUES (?, ?, 100, 'dura')");
        $system_id = 0; // System reward
        $stmt->bind_param("ii", $system_id, $inviter_user_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO dura_transactions (from_user_id, to_user_id, amount, type) VALUES (?, ?, 100, 'dura')");
        $stmt->bind_param("ii", $system_id, $new_user_id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Account created! Both you and your inviter received 100 Dura.']);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
        exit;
    }
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
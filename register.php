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

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Default avatar for new users
    $default_avatar = 'm1.png';

    // Insert new user into database
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_id, avatar, is_admin) VALUES (?, ?, ?, ?, ?, 0)");
    if (!$stmt) {
        error_log("Prepare failed for user insert in register.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("sssss", $username, $email, $hashed_password, $user_id_string, $default_avatar);
    if (!$stmt->execute()) {
        error_log("Execute failed for user insert in register.php: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    
    $new_user_db_id = $conn->insert_id;
    $stmt->close();

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
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center">Create Account</h2>
                <form id="registerForm" class="mt-4">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <!--<div class="form-text">Your username will be part of your unique ID (e.g., Username#123456)</div>-->
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Password must be at least 6 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                    <p>Or <a href="index.php">continue as guest</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $('#registerForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Registration form submitted');
            
            // Client-side validation
            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long');
                return;
            }
            
            $.ajax({
                url: 'register.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    console.log('Response from register.php:', response);
                    try {
                        let res = JSON.parse(response);
                        if (res.status === 'success') {
                            alert('Registration successful! Redirecting to lounge...');
                            window.location.href = 'lounge.php';
                        } else {
                            alert('Error: ' + res.message);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e, response);
                        alert('Invalid response from server');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error in registration:', status, error, xhr.responseText);
                    alert('AJAX error: ' + error);
                }
            });
        });
    </script>
</body>
</html>
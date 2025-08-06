<?php
session_start();

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

    if (empty($username) || empty($password)) {
        error_log("Missing username or password in login.php");
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // Updated query to include user_id and email
    $stmt = $conn->prepare("SELECT id, username, user_id, email, password, is_admin, avatar FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("Prepare failed in login.php: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        error_log("User not found in login.php: username=$username");
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        $stmt->close();
        exit;
    }

    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password'])) {
        error_log("Incorrect password in login.php: username=$username");
        echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
        $stmt->close();
        exit;
    }

    // Updated session to include user_id and email - IMPORTANT: user_id is required for host system
    $_SESSION['user'] = [
        'type' => 'user',
        'id' => $user['id'],
        'username' => $user['username'],
        'user_id' => $user['user_id'],  // This is crucial for the host system!
        'email' => $user['email'],
        'is_admin' => $user['is_admin'],
        'avatar' => $user['avatar']
    ];

    // Debug log to ensure user_id is set
    error_log("User logged in with user_id: " . ($user['user_id'] ?? 'NULL'));
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center">Login</h2>
                <form id="userLoginForm" class="mt-4">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Choose Avatar</label>
                        <div id="avatarSelection">
                            <img src="images/m1.png" class="avatar" data-avatar="m1.png">
                            <img src="images/m2.png" class="avatar" data-avatar="m2.png">
                            <img src="images/m3.png" class="avatar" data-avatar="m3.png">
                            <img src="images/f1.png" class="avatar" data-avatar="f1.png">
                            <img src="images/f2.png" class="avatar" data-avatar="f2.png">
                            <img src="images/f3.png" class="avatar" data-avatar="f3.png">
                            <img src="images/m4.png" class="avatar" data-avatar="m4.png">
                            <img src="images/m5.png" class="avatar" data-avatar="m5.png">
                            <img src="images/m6.png" class="avatar" data-avatar="m6.png">
                            <img src="images/f4.png" class="avatar" data-avatar="f4.png">
                            <img src="images/f5.png" class="avatar" data-avatar="f5.png">
                            <img src="images/f6.png" class="avatar" data-avatar="f6.png">
                            <img src="images/m7.png" class="avatar" data-avatar="m7.png">
                            <img src="images/m8.png" class="avatar" data-avatar="m8.png">
                            <img src="images/m9.png" class="avatar" data-avatar="m9.png">
                            <img src="images/f7.png" class="avatar" data-avatar="f7.png">
                            <img src="images/rm1.png" class="avatar" data-avatar="rm1.png">
                            <img src="images/rm2.png" class="avatar" data-avatar="rm2.png">
                            <img src="images/rm3.png" class="avatar" data-avatar="rm3.png">
                            <img src="images/rf1.png" class="avatar" data-avatar="rf1.png">
                            <img src="images/rf2.png" class="avatar" data-avatar="rf2.png">
                            <img src="images/rf3.png" class="avatar" data-avatar="rf3.png">
                            <img src="images/rm4.png" class="avatar" data-avatar="rm4.png">
                            <img src="images/rm5.png" class="avatar" data-avatar="rm5.png">
                            <img src="images/rm6.png" class="avatar" data-avatar="rm6.png">
                            <img src="images/rf4.png" class="avatar" data-avatar="rf4.png">
                            <img src="images/rf5.png" class="avatar" data-avatar="rf5.png">
                            <img src="images/rf6.png" class="avatar" data-avatar="rf6.png">
                        </div>
                        <input type="hidden" id="selectedAvatar" name="avatar">
                        <div class="form-text">Select an avatar for your profile</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                
                <div class="text-center mt-3">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                    <p>Or <a href="index.php">continue as guest</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
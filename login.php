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

    $stmt = $conn->prepare("SELECT id, username, password, is_admin, avatar FROM users WHERE username = ?");
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

    $_SESSION['user'] = [
        'type' => 'user',
        'id' => $user['id'],
        'username' => $user['username'],
        'is_admin' => $user['is_admin'],
        'avatar' => $user['avatar']
    ];

    error_log("User logged in: id={$user['id']}, username={$user['username']}"); // Debug
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
        <h2>Login</h2>
        <form id="loginForm" class="mt-3">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Login form submitted:', $('#username').val());
            $.ajax({
                url: 'login.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    console.log('Response from login.php:', response);
                    try {
                        let res = JSON.parse(response);
                        if (res.status === 'success') {
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
                    console.error('AJAX error in login:', status, error, xhr.responseText);
                    alert('AJAX error: ' + error);
                }
            });
        });
    </script>
</body>
</html>
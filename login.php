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

function getAverageColor($img_path) {
    $size = 10; // Resize for speed
    $ext = strtolower(pathinfo($img_path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) return null;

    switch ($ext) {
        case 'png': $img = @imagecreatefrompng($img_path); break;
        case 'jpg':
        case 'jpeg': $img = @imagecreatefromjpeg($img_path); break;
        case 'gif': $img = @imagecreatefromgif($img_path); break;
        case 'webp': $img = @imagecreatefromwebp($img_path); break;
        default: return null;
    }
    if (!$img) return null;

    $resized = imagecreatetruecolor($size, $size);
    imagecopyresampled($resized, $img, 0, 0, 0, 0, $size, $size, imagesx($img), imagesy($img));

    $r = $g = $b = 0;
    $total = $size * $size;
    for ($x = 0; $x < $size; $x++) {
        for ($y = 0; $y < $size; $y++) {
            $rgb = imagecolorat($resized, $x, $y);
            $r += ($rgb >> 16) & 0xFF;
            $g += ($rgb >> 8) & 0xFF;
            $b += $rgb & 0xFF;
        }
    }
    imagedestroy($img);
    imagedestroy($resized);

    return [$r / $total, $g / $total, $b / $total];
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
                        <label for="avatarSort" class="form-label">Sort/Filter Avatars</label>
                        <select id="avatarSort" class="form-select">
                            <option value="all">All Colors</option>
                            <?php
                            $image_base_dir = __DIR__ . '/images';
                            $excluded_folders = ['staff', 'bg', 'icon', 'default', 'special']; // Add any folders to exclude, including priority folders if needed
                            foreach (glob($image_base_dir . '/*', GLOB_ONLYDIR) as $color_dir) {
                                $color_name = basename($color_dir);
                                if (in_array(strtolower($color_name), $excluded_folders)) continue;
                                echo '<option value="' . strtolower($color_name) . '">' . ucfirst($color_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Choose Avatar</label>
                        <div id="avatarSelection">
                            <?php
                            $image_base_dir = __DIR__ . '/images';
                            $web_base_dir = 'images/';
                            $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
                            $excluded_folders = ['staff', 'bg', 'icon']; // Add any folder names you want to exclude
                            $priority_folders = ['default', 'special'];

                            // Show priority folders first if they exist
                            foreach ($priority_folders as $folder) {
                                $color_dir = $image_base_dir . '/' . $folder;
                                if (is_dir($color_dir)) {
                                    echo '<div class="mb-2"><strong>' . ucfirst($folder) . ' Avatars</strong></div>';
                                    foreach (glob($color_dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) as $img_path) {
                                        $img_file = basename($img_path);
                                        $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                                        if (in_array($ext, $allowed_ext)) {
                                            echo '<img src="' . $web_base_dir . $folder . '/' . $img_file . '" class="avatar" data-avatar="' . $folder . '/' . $img_file . '">';
                                        }
                                    }
                                }
                            }

                            // Now show all other folders except excluded and priority
                            foreach (glob($image_base_dir . '/*', GLOB_ONLYDIR) as $color_dir) {
                                $color_name = basename($color_dir);
                                if (in_array(strtolower($color_name), $excluded_folders)) continue;
                                if (in_array(strtolower($color_name), $priority_folders)) continue;

                                echo '<div class="mb-2 avatar-group" data-group="' . strtolower($color_name) . '"><div class="mb-2"><strong>' . ucfirst($color_name) . ' Avatars</strong></div>';
                                foreach (glob($color_dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) as $img_path) {
                                    $img_file = basename($img_path);
                                    $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                                    if (in_array($ext, $allowed_ext)) {
                                        echo '<img src="' . $web_base_dir . $color_name . '/' . $img_file . '" class="avatar" data-avatar="' . $color_name . '/' . $img_file . '">';
                                    }
                                }
                                echo '</div>';
                            }
                            ?>
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
    <script>
        $(document).ready(function() {
            $('#avatarSort').on('change', function() {
                var selected = $(this).val();
                if (selected === 'all') {
                    $('.avatar-group').show();
                } else {
                    $('.avatar-group').each(function() {
                        if ($(this).data('group') === selected) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
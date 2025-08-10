<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
        <div class="col-md-6">
        <h2 class="text-center">Guest Login</h2>
        <form id="guestLoginForm" class="mt-4">
            <div class="mb-3">
                <label for="guestName" class="form-label">Name</label>
                <input type="text" class="form-control" id="guestName" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Choose Avatar</label>
                <div id="avatarSelection">
                    <?php
                    $image_base_dir = __DIR__ . '/images';
                    $web_base_dir = 'images/';
                    $priority_folders = ['default', 'time-limited'];
                    $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

                    foreach ($priority_folders as $folder) {
                        $folder_path = $image_base_dir . '/' . $folder;
                        if (is_dir($folder_path)) {
                            echo '<div class="mb-2"><strong>' . ucfirst($folder) . ' Avatars</strong></div>';
                            foreach (glob($folder_path . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) as $img_path) {
                                $img_file = basename($img_path);
                                $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                                if (in_array($ext, $allowed_ext)) {
                                    echo '<img src="' . $web_base_dir . $folder . '/' . $img_file . '" class="avatar" data-avatar="' . $folder . '/' . $img_file . '">';
                                }
                            }
                        }
                    }
                    ?>
                </div>
                <input type="hidden" id="selectedAvatar" name="avatar" required>
            </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                    <p>Or <a href="register.php">Register here</a></p>
                </div>
    </div>
</div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
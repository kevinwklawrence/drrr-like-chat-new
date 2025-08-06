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
                    <img src="images/m1.png" class="avatar" data-avatar="m1.png">
                    <img src="images/m2.png" class="avatar" data-avatar="m2.png">
                    <img src="images/m3.png" class="avatar" data-avatar="m3.png">
                    <img src="images/f1.png" class="avatar" data-avatar="f1.png">
                    <img src="images/f2.png" class="avatar" data-avatar="f2.png">
                    <img src="images/f3.png" class="avatar" data-avatar="f3.png">
                    <img src="images/m4.png" class="avatar" data-avatar="m4.png">
                    <img src="images/m5.png" class="avatar" data-avatar="m5.png">
                    <img src="images/m6.png" class="avatar" data-avatar="m2.png">
                    <img src="images/f4.png" class="avatar" data-avatar="f4.png">
                    <img src="images/f5.png" class="avatar" data-avatar="f5.png">
                    <img src="images/f6.png" class="avatar" data-avatar="f6.png">
                    <img src="images/m7.png" class="avatar" data-avatar="m7.png">
                    <img src="images/m8.png" class="avatar" data-avatar="m8.png">
                    <img src="images/m9.png" class="avatar" data-avatar="m9.png">
                    <img src="images/f7.png" class="avatar" data-avatar="f7.png">
                    <!-- Add more avatars -->
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
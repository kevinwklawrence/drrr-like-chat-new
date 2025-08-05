<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lounge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username']); ?>
            <?php if ($_SESSION['user']['type'] === 'user') { ?>
                <span class="badge bg-success">Verified</span>
                <?php if ($_SESSION['user']['is_admin']) { ?>
                    <span class="badge bg-danger">Staff</span>
                <?php } ?>
            <?php } ?>
        </h2>
        <button class="btn btn-danger" onclick="logout()">Logout</button>
        <h3>Chatrooms</h3>
        <div id="chatroomList"></div>
        <h3>Create Chatroom</h3>
        <form id="createRoomForm">
            <div class="mb-3">
                <label for="roomName" class="form-label">Room Name</label>
                <input type="text" class="form-control" id="roomName" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description"></textarea>
            </div>
            <div class="mb-3">
                <label for="background" class="form-label">Background Image</label>
                <select class="form-select" id="background">
                    <option value="">Select Background</option>
                    <option value="images/background1.jpg">Background 1</option>
                    <option value="images/background2.jpg">Background 2</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="capacity" class="form-label">Capacity</label>
                <select class="form-select" id="capacity" required>
                    <option value="">Select Capacity</option>
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="20">20</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password (optional)</label>
                <input type="password" class="form-control" id="password">
            </div>
            
            <?php if ($_SESSION['user']['type'] === 'user' && $_SESSION['user']['is_admin']) { ?>
                <div class="mb-3">
                    <label for="permanent" class="form-label">Permanent Room</label>
                    <select class="form-select" id="permanent">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                
            <?php } ?>
            <button type="submit" class="btn btn-primary">Create Room</button>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <!--<script src="js/lounge.js"></script>-->
</body>
</html>
<?php
session_start();

// Debug session data
error_log("Session data in room.php: " . print_r($_SESSION, true));

if (!isset($_SESSION['user']) || !isset($_SESSION['room_id'])) {
    error_log("Missing user or room_id in session, redirecting to index.php");
    header("Location: index.php");
    exit;
}

include 'db_connect.php';
$room_id = (int)$_SESSION['room_id'];
error_log("room_id in room.php: $room_id"); // Debug

$stmt = $conn->prepare("SELECT name, background FROM chatrooms WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed in room.php: " . $conn->error);
    header("Location: lounge.php");
    exit;
}
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("No room found for room_id: $room_id");
    header("Location: lounge.php");
    exit;
}
$room = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatroom: <?php echo htmlspecialchars($room['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body {
            background: url('<?php echo htmlspecialchars($room['background'] ?: 'images/default_background.jpg'); ?>') no-repeat center center fixed;
            background-size: cover;
        }
        #chatbox {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            background: #fff;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2><?php echo htmlspecialchars($room['name']); ?></h2>
        <button class="btn btn-danger" onclick="leaveRoom()">Leave Room</button>
        <div class="row">
            <div class="col-md-8">
                <div id="chatbox"></div>
                <form id="messageForm" class="mt-3">
                    <div class="input-group">
                        <input type="text" class="form-control" id="message" required>
                        <button type="submit" class="btn btn-primary">Send</button>
                    </div>
                </form>
            </div>
            <div class="col-md-4">
                <h3>Users</h3>
                <div id="userList"></div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ensure roomId is set
        const roomId = <?php echo json_encode($room_id); ?>;
        console.log('roomId set to:', roomId);
        const isAdmin = <?php echo isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] ? 'true' : 'false'; ?>;
        if (!roomId) {
            console.error('roomId is invalid, redirecting to lounge');
            window.location.href = 'lounge.php';
        }
    </script>
    <script src="js/room.js"></script>
</body>
</html>
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

// Check if current user is host
$user_id_string = $_SESSION['user']['user_id'] ?? '';
$is_host = false;
if (!empty($user_id_string)) {
    $stmt = $conn->prepare("SELECT is_host FROM chatroom_users WHERE room_id = ? AND user_id_string = ?");
    if ($stmt) {
        $stmt->bind_param("is", $room_id, $user_id_string);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $is_host = ($user_data['is_host'] == 1);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatroom: <?php echo htmlspecialchars($room['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body {
            background: url('<?php echo htmlspecialchars($room['background'] ?: 'images/defaultbg.png'); ?>') no-repeat center center fixed;
                      /* background-color: <?php //echo htmlspecialchars($room['background'] ?: rgb(47 47 47)); ?>*/
            background-size: cover;
        }
        
        #knocksPanel {
            background: rgba(255, 255, 255, 0.95);
        }
        .knock-item {
            background: rgba(248, 249, 250, 0.8);
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center pe-2 col-md-8">
            <h2><?php echo htmlspecialchars($room['name']); ?>
                <?php if ($is_host): ?>
                    <span class="badge rounded-pill bg-primary">Host</span>
                <?php endif; ?>
            </h2>
            <div>
                <?php if ($is_host): ?>
                    <button class="btn btn-warning me-2" onclick="showRoomSettings()">Room Settings</button>
                <?php endif; ?>
                <button class="btn btn-danger" onclick="leaveRoom()">Leave Room</button>
            </div>
        </div>
        
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
        const isAdmin = <?php echo isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] ? 'true' : 'false'; ?>;
        const isHost = <?php echo $is_host ? 'true' : 'false'; ?>;
        console.log('roomId set to:', roomId, 'isHost:', isHost);
        if (!roomId) {
            console.error('roomId is invalid, redirecting to lounge');
            window.location.href = 'lounge.php';
        }
    </script>
    <script src="js/room.js"></script>
    <?php //include 'debug_session.php' ?>
</body>
</html>
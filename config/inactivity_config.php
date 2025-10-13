<?php
// config/inactivity_config.php - Simple inactivity timer configuration

// Timeouts in seconds
define('AFK_TIMEOUT', 900);           // 15 minutes
define('DEFAULT_DISCONNECT', 3600);   // 60 minutes
define('EXTENDED_DISCONNECT', 999999999999999);  // 120 minutes (YouTube/hosts)

// Cron runs this
define('CRON_INTERVAL', 120); // 2 minutes

function getDisconnectTimeout($room_id, $is_host, $conn) {
    // Check if YouTube room
    $stmt = $conn->prepare("SELECT youtube_enabled FROM chatrooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if ($is_host || ($room && $room['youtube_enabled'])) {
        return EXTENDED_DISCONNECT;
    }
    return DEFAULT_DISCONNECT;
}
?>
<?php
// api/spawn_ghost.php - Spawn ghost hunt events
// NO session_start() - this runs from cron!

// Force all errors to display AND log
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set log file
$log_file = __DIR__ . '/../logs/ghost_spawn.log';
ini_set('error_log', $log_file);

// Echo immediately so we know script started
echo "Ghost spawn script started at " . date('Y-m-d H:i:s') . "\n";
flush();

function log_spawn($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $line; // Also echo for cron output
    flush();
}

log_spawn("Initializing ghost spawn");

// Include database connection
$db_path = __DIR__ . '/../db_connect.php';
if (!file_exists($db_path)) {
    log_spawn("FATAL: db_connect.php not found at $db_path");
    die("ERROR: db_connect.php not found\n");
}

include $db_path;

if (!isset($conn)) {
    log_spawn("FATAL: Database connection variable not set");
    die("ERROR: Database connection failed\n");
}

if ($conn->connect_error) {
    log_spawn("FATAL: Database connection error: " . $conn->connect_error);
    die("ERROR: " . $conn->connect_error . "\n");
}

log_spawn("Database connected successfully");

// Spooky phrases for ghost hunts
$ghost_phrases = [
    "BOO!",
    "TRICK OR TREAT",
    "HAUNTED",
    "SPOOKY SCARY",
    "GHOSTLY WHISPER",
    "PHANTOM PROWLER",
    "SPIRIT WALKER",
    "CREEPY CRAWLER",
    "MIDNIGHT HOWL",
    "SHADOW LURKER",
    "WITCH'S BREW",
    "FULL MOON",
    "GRAVEYARD SHIFT",
    "PUMPKIN PATCH",
    "CANDY CORN",
    "BLACK CAT",
    "VAMPIRE BITE",
    "ZOMBIE HORDE",
    "WEREWOLF PACK",
    "SKELETON KEY"
];

try {
    // Clear expired ghosts before spawning new ones
    log_spawn("Checking for expired ghosts...");
    $cleanup_stmt = $conn->prepare("
        SELECT id, room_id 
        FROM ghost_hunt_events 
        WHERE is_active = 1 
        AND spawned_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $cleanup_stmt->execute();
    $cleanup_result = $cleanup_stmt->get_result();
    
    $cleaned = 0;
    while ($expired = $cleanup_result->fetch_assoc()) {
        $expired_id = $expired['id'];
        $room_id = $expired['room_id'];
        
        $deactivate_stmt = $conn->prepare("UPDATE ghost_hunt_events SET is_active = 0 WHERE id = ?");
        $deactivate_stmt->bind_param("i", $expired_id);
        $deactivate_stmt->execute();
        $deactivate_stmt->close();
        
        $escape_message = "👻 <span style='color: #9b59b6;'>The ghost vanished into the shadows...</span> 💨";
        $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, 'GHOST_HUNT', ?, 1, NOW(), 'ghost.png', 'system')");
        $msg_stmt->bind_param("is", $room_id, $escape_message);
        $msg_stmt->execute();
        $msg_stmt->close();
        
        $cleaned++;
        log_spawn("Cleared expired ghost in room $room_id");
    }
    $cleanup_stmt->close();
    log_spawn("Expired ghosts cleared: $cleaned");
    
    // Get all active rooms
    $rooms_stmt = $conn->prepare("SELECT id FROM chatrooms");
    $rooms_stmt->execute();
    $rooms_result = $rooms_stmt->get_result();
    
    $room_count = $rooms_result->num_rows;
    log_spawn("Found $room_count active room(s)");
    
    $spawned_count = 0;
    
    while ($room = $rooms_result->fetch_assoc()) {
        $room_id = $room['id'];
        
        // Check if room has active ghost hunt
        $check_stmt = $conn->prepare("SELECT id FROM ghost_hunt_events WHERE room_id = ? AND is_active = 1");
        $check_stmt->bind_param("i", $room_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Room already has active hunt, skip
            $check_stmt->close();
            log_spawn("Room $room_id already has active ghost, skipping");
            continue;
        }
        $check_stmt->close();
        
        // Random chance to spawn (30% per check)
        // Note: This means ghosts DON'T spawn 70% of the time - this is intentional!
        // Change 30 to 100 for testing (always spawn)
        $roll = rand(1, 100);
        if ($roll <= 30) {
            log_spawn("Room $room_id: Spawn roll succeeded ($roll <= 30)");
            // Select random phrase
            $phrase = $ghost_phrases[array_rand($ghost_phrases)];
            $reward = rand(5, 15); // Random reward 8-15 currency
            
            // Create ghost hunt event
            $spawn_stmt = $conn->prepare("INSERT INTO ghost_hunt_events (room_id, ghost_phrase, reward_amount) VALUES (?, ?, ?)");
            $spawn_stmt->bind_param("isi", $room_id, $phrase, $reward);
            
            if ($spawn_stmt->execute()) {
                // Insert system message about ghost
                $ghost_message = "👻 <strong style='color: #ff6b00;'>A GHOST APPEARS!</strong> 👻<br>Type <span style='background: rgba(255,107,0,0.2); padding: 4px 8px; border-radius: 4px; font-weight: bold; color: #ff6b00;'>$phrase</span> to earn <strong>$reward 🎃 Event Currency!</strong>";
                
                $msg_stmt = $conn->prepare("INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) VALUES (?, 'GHOST_HUNT', ?, 1, NOW(), 'ghost.png', 'system')");
                $msg_stmt->bind_param("is", $room_id, $ghost_message);
                $msg_stmt->execute();
                $msg_stmt->close();
                
                $spawned_count++;
                log_spawn("SUCCESS: Ghost spawned in room $room_id - Phrase: '$phrase', Reward: $reward");
            } else {
                log_spawn("ERROR: Failed to spawn ghost in room $room_id: " . $spawn_stmt->error);
            }
            $spawn_stmt->close();
        } else {
            log_spawn("Room $room_id: Spawn roll failed ($roll > 30)");
        }
    }
    
    $rooms_stmt->close();
    
    log_spawn("=== Spawn complete ===");
    log_spawn("Total rooms checked: $room_count");
    log_spawn("Ghosts spawned: $spawned_count");
    
    echo "\nSUMMARY:\n";
    echo "- Rooms checked: $room_count\n";
    echo "- Ghosts spawned: $spawned_count\n";
    echo "- Expired cleaned: $cleaned\n";
    echo "- Success!\n";
    
} catch (Exception $e) {
    log_spawn("EXCEPTION: " . $e->getMessage());
    log_spawn("Stack trace: " . $e->getTraceAsString());
    echo "ERROR: " . $e->getMessage() . "\n";
    die(1);
}

$conn->close();
log_spawn("Script finished successfully");
echo "Script completed at " . date('Y-m-d H:i:s') . "\n";
?>
<?php
// api/pets.php - Pet Management API
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Must be logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get user's pets with calculated stats
            $stmt = $conn->prepare("
                SELECT p.*, pt.name as type_name, pt.image_url, pt.description
                FROM pets p
                JOIN pet_types pt ON p.pet_type = pt.type_id
                WHERE p.user_id = ?
                ORDER BY p.is_favorited DESC, p.created_at ASC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $pets = [];
            while ($row = $result->fetch_assoc()) {
                // Calculate time-based updates
                $pet = updatePetStats($row);
                $pets[] = $pet;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'pets' => $pets]);
            break;
            
        case 'feed':
            $pet_id = $_POST['pet_id'] ?? 0;
            feedPet($conn, $user_id, $pet_id);
            break;
            
        case 'play':
            $pet_id = $_POST['pet_id'] ?? 0;
            playWithPet($conn, $user_id, $pet_id);
            break;
            
        case 'pet':
            $pet_id = $_POST['pet_id'] ?? 0;
            carePet($conn, $user_id, $pet_id);
            break;
            
        case 'collect':
            $pet_id = $_POST['pet_id'] ?? 0;
            collectDura($conn, $user_id, $pet_id);
            break;
            
        case 'rename':
            $pet_id = $_POST['pet_id'] ?? 0;
            $new_name = trim($_POST['custom_name'] ?? '');
            renamePet($conn, $user_id, $pet_id, $new_name);
            break;
            
        case 'favorite':
            $pet_id = $_POST['pet_id'] ?? 0;
            toggleFavorite($conn, $user_id, $pet_id);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$conn->close();

// Helper Functions
function updatePetStats($pet) {
    $now = time();
    
    // Calculate hours since last update
    $last_update = strtotime($pet['last_collected'] ?? $pet['created_at']);
    $hours_passed = max(0, ($now - $last_update) / 3600);
    
    // Update hunger (decreases 1 per hour)
    $pet['hunger'] = max(0, min(100, $pet['hunger'] - floor($hours_passed)));
    
    // Update happiness based on hunger
    if ($pet['hunger'] < 30) {
        $pet['happiness'] = max(0, $pet['happiness'] - (5 * floor($hours_passed)));
    } elseif ($pet['hunger'] > 70) {
        $pet['happiness'] = min(100, $pet['happiness'] + (2 * floor($hours_passed)));
    }
    
    // Calculate Dura generation
    $base_rate = 5; // 5 Dura/hour
    $happiness_mult = $pet['happiness'] / 100;
    $bond_mult = 1.0 + ($pet['bond_level'] * 0.1);
    $dura_per_hour = $base_rate * $happiness_mult * $bond_mult;
    $accumulated_dura = floor($hours_passed * $dura_per_hour);
    
    // Add calculated fields
    $pet['accumulated_dura'] = $accumulated_dura;
    $pet['dura_per_hour'] = round($dura_per_hour, 2);
    
    // Calculate cooldowns (in seconds remaining)
    $pet['feed_cooldown'] = calculateCooldown($pet['last_fed'], 8 * 3600);
    $pet['play_cooldown'] = calculateCooldown($pet['last_played'], 4 * 3600);
    $pet['pet_cooldown'] = calculateCooldown($pet['last_played'], 3600);
    
    // Bond XP progress
    $next_level_xp = getBondXPRequired($pet['bond_level'] + 1);
    $pet['xp_to_next_level'] = max(0, $next_level_xp - $pet['bond_xp']);
    $pet['xp_progress'] = $next_level_xp > 0 ? ($pet['bond_xp'] / $next_level_xp) * 100 : 100;
    
    return $pet;
}

function calculateCooldown($last_time, $cooldown_seconds) {
    if (!$last_time) return 0;
    $elapsed = time() - strtotime($last_time);
    return max(0, $cooldown_seconds - $elapsed);
}

function getBondXPRequired($level) {
    $xp_requirements = [
        1 => 0, 2 => 100, 3 => 250, 4 => 500, 5 => 1000,
        6 => 1500, 7 => 2500, 8 => 4000, 9 => 6000, 10 => 10000
    ];
    return $xp_requirements[$level] ?? 10000;
}

function feedPet($conn, $user_id, $pet_id) {
    // Check ownership
    $stmt = $conn->prepare("SELECT * FROM pets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pet_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pet not found']);
        $stmt->close();
        return;
    }
    
    $pet = $result->fetch_assoc();
    $stmt->close();
    
    // Check cooldown (8 hours for free, or pay 50 Dura)
    $cooldown = calculateCooldown($pet['last_fed'], 8 * 3600);
    $cost = 0;
    
    if ($cooldown > 0) {
        $cost = 50;
        // Check if user has enough Dura
        $stmt = $conn->prepare("SELECT dura FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user['dura'] < $cost) {
            echo json_encode(['status' => 'error', 'message' => 'Not enough Dura. Wait for cooldown or earn more.']);
            return;
        }
        
        // Deduct cost
        $stmt = $conn->prepare("UPDATE users SET dura = dura - ? WHERE id = ?");
        $stmt->bind_param("ii", $cost, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Feed pet (restore 30-50 hunger)
    $hunger_restore = rand(30, 50);
    $stmt = $conn->prepare("UPDATE pets SET hunger = LEAST(100, hunger + ?), last_fed = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $hunger_restore, $pet_id);
    $stmt->execute();
    $stmt->close();
    
    // Update session if cost was paid
    if ($cost > 0) {
        $_SESSION['user']['dura'] -= $cost;
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Fed ' . $pet['custom_name'] . '!',
        'hunger_restored' => $hunger_restore,
        'cost' => $cost
    ]);
}

function playWithPet($conn, $user_id, $pet_id) {
    // Check ownership
    $stmt = $conn->prepare("SELECT * FROM pets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pet_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pet not found']);
        $stmt->close();
        return;
    }
    
    $pet = $result->fetch_assoc();
    $stmt->close();
    
    // Check cooldown (4 hours)
    $cooldown = calculateCooldown($pet['last_played'], 4 * 3600);
    if ($cooldown > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Cooldown active. Wait ' . formatTime($cooldown)]);
        return;
    }
    
    // Play with pet
    $happiness_gain = rand(10, 20);
    $bond_xp_gain = 10;
    
    $new_happiness = min(100, $pet['happiness'] + $happiness_gain);
    $new_bond_xp = $pet['bond_xp'] + $bond_xp_gain;
    $new_bond_level = $pet['bond_level'];
    
    // Check for level up
    $next_level_req = getBondXPRequired($new_bond_level + 1);
    if ($new_bond_xp >= $next_level_req && $new_bond_level < 10) {
        $new_bond_level++;
        
        // Check for profile slot unlocks
        if ($new_bond_level == 5 || $new_bond_level == 10) {
            $new_slots = ($new_bond_level == 5) ? 2 : 3;
            $stmt = $conn->prepare("INSERT INTO user_pet_settings (user_id, profile_pet_slots) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE profile_pet_slots = ?");
            $stmt->bind_param("iii", $user_id, $new_slots, $new_slots);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $stmt = $conn->prepare("UPDATE pets SET happiness = ?, bond_xp = ?, bond_level = ?, last_played = NOW() WHERE id = ?");
    $stmt->bind_param("iiii", $new_happiness, $new_bond_xp, $new_bond_level, $pet_id);
    $stmt->execute();
    $stmt->close();
    
    $level_up_msg = ($new_bond_level > $pet['bond_level']) ? ' 🎉 Bond Level UP to ' . $new_bond_level . '!' : '';
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Played with ' . $pet['custom_name'] . '!' . $level_up_msg,
        'happiness_gain' => $happiness_gain,
        'bond_xp_gain' => $bond_xp_gain,
        'new_bond_level' => $new_bond_level,
        'leveled_up' => $new_bond_level > $pet['bond_level']
    ]);
}

function carePet($conn, $user_id, $pet_id) {
    // Check ownership
    $stmt = $conn->prepare("SELECT * FROM pets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pet_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pet not found']);
        $stmt->close();
        return;
    }
    
    $pet = $result->fetch_assoc();
    $stmt->close();
    
    // Check cooldown (1 hour)
    $cooldown = calculateCooldown($pet['last_played'], 3600);
    if ($cooldown > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Cooldown active. Wait ' . formatTime($cooldown)]);
        return;
    }
    
    // Care for pet
    $happiness_gain = 5;
    $bond_xp_gain = 5;
    
    $new_happiness = min(100, $pet['happiness'] + $happiness_gain);
    $new_bond_xp = $pet['bond_xp'] + $bond_xp_gain;
    
    $stmt = $conn->prepare("UPDATE pets SET happiness = ?, bond_xp = ?, last_played = NOW() WHERE id = ?");
    $stmt->bind_param("iii", $new_happiness, $new_bond_xp, $pet_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Petted ' . $pet['custom_name'] . '! +' . $happiness_gain . ' Happiness',
        'happiness_gain' => $happiness_gain,
        'bond_xp_gain' => $bond_xp_gain
    ]);
}

function collectDura($conn, $user_id, $pet_id) {
    // Check ownership and get updated stats
    $stmt = $conn->prepare("SELECT * FROM pets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pet_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pet not found']);
        $stmt->close();
        return;
    }
    
    $pet = $result->fetch_assoc();
    $stmt->close();
    
    // Calculate accumulated Dura
    $pet = updatePetStats($pet);
    $dura_amount = $pet['accumulated_dura'];
    
    if ($dura_amount < 1) {
        echo json_encode(['status' => 'error', 'message' => 'No Dura to collect yet']);
        return;
    }
    
    // Add Dura to user
    $stmt = $conn->prepare("UPDATE users SET dura = dura + ?, lifetime_dura = lifetime_dura + ? WHERE id = ?");
    $stmt->bind_param("iii", $dura_amount, $dura_amount, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Update pet's last_collected and apply stat changes
    $stmt = $conn->prepare("UPDATE pets SET hunger = ?, happiness = ?, last_collected = NOW() WHERE id = ?");
    $stmt->bind_param("iii", $pet['hunger'], $pet['happiness'], $pet_id);
    $stmt->execute();
    $stmt->close();
    
    // Update session
    $_SESSION['user']['dura'] += $dura_amount;
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Collected ' . $dura_amount . ' Dura from ' . $pet['custom_name'] . '!',
        'dura_collected' => $dura_amount
    ]);
}

function renamePet($conn, $user_id, $pet_id, $new_name) {
    if (strlen($new_name) < 1 || strlen($new_name) > 20) {
        echo json_encode(['status' => 'error', 'message' => 'Name must be 1-20 characters']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE pets SET custom_name = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_name, $pet_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Pet renamed!', 'new_name' => $new_name]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to rename pet']);
    }
    $stmt->close();
}

function toggleFavorite($conn, $user_id, $pet_id) {
    // Get current favorite count and pet status
    $stmt = $conn->prepare("
        SELECT p.is_favorited, 
               (SELECT COUNT(*) FROM pets WHERE user_id = ? AND is_favorited = 1) as fav_count,
               COALESCE(ups.profile_pet_slots, 1) as max_slots
        FROM pets p
        LEFT JOIN user_pet_settings ups ON ups.user_id = p.user_id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->bind_param("iii", $user_id, $pet_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pet not found']);
        $stmt->close();
        return;
    }
    
    $data = $result->fetch_assoc();
    $stmt->close();
    
    $new_status = $data['is_favorited'] ? 0 : 1;
    
    // Check if trying to favorite but already at max
    if ($new_status == 1 && $data['fav_count'] >= $data['max_slots']) {
        echo json_encode(['status' => 'error', 'message' => 'Max profile slots reached. Increase bond level to unlock more.']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE pets SET is_favorited = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $pet_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => $new_status ? 'Added to profile!' : 'Removed from profile',
        'is_favorited' => $new_status
    ]);
}

function formatTime($seconds) {
    $hours = floor($seconds / 3600);
    $mins = floor(($seconds % 3600) / 60);
    if ($hours > 0) return $hours . 'h ' . $mins . 'm';
    return $mins . 'm';
}
?>
<?php
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
            // Get user's inventory with item details
            $stmt = $conn->prepare("
                SELECT ui.*, si.name, si.description, si.type, si.rarity, si.icon
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ?
                ORDER BY 
                    FIELD(si.rarity, 'event', 'legendary', 'strange', 'rare', 'common'),
                    si.type,
                    ui.acquired_at DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $inventory = [];
            while ($row = $result->fetch_assoc()) {
                $inventory[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'inventory' => $inventory]);
            break;
            
        case 'equip':
            $item_id = $_POST['item_id'] ?? '';
            
            if (empty($item_id)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid item']);
                exit;
            }
            
            // Get item details
            $stmt = $conn->prepare("
                SELECT si.type, si.icon, si.name
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ? AND ui.item_id = ?
            ");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Item not in inventory']);
                $stmt->close();
                exit;
            }
            
            $item = $result->fetch_assoc();
            $stmt->close();
            
            // Handle different item types
            if ($item['type'] === 'avatar') {
                // Avatars: Only one can be equipped, and it updates avatar columns
                
                // First, check if this is the first avatar being equipped
                // If so, save the current avatar to avatar_memory
                $stmt = $conn->prepare("SELECT avatar, avatar_memory FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $current_avatar = $user_data['avatar'];
                $avatar_memory = $user_data['avatar_memory'];
                $stmt->close();
                
                // Check if user has any equipped shop avatars
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_inventory ui 
                    JOIN shop_items si ON ui.item_id = si.item_id 
                    WHERE ui.user_id = ? AND si.type = 'avatar' AND ui.is_equipped = 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $count_data = $result->fetch_assoc();
                $has_equipped_avatar = $count_data['count'] > 0;
                $stmt->close();
                
                // If no shop avatar is currently equipped AND avatar_memory is empty, save current avatar
                if (!$has_equipped_avatar && (empty($avatar_memory) || $avatar_memory === $current_avatar)) {
                    $avatar_memory = $current_avatar;
                }
                
                // Unequip all other avatars
                $stmt = $conn->prepare("UPDATE user_inventory ui 
                    JOIN shop_items si ON ui.item_id = si.item_id 
                    SET ui.is_equipped = 0 
                    WHERE ui.user_id = ? AND si.type = 'avatar'");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Equip this avatar
                $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = ? AND item_id = ?");
                $stmt->bind_param("is", $user_id, $item_id);
                $stmt->execute();
                $stmt->close();
                
                // Get the avatar path from icon field
                $avatar_path = $item['icon'];
                
                // Update users table - update avatar but preserve avatar_memory
                $stmt = $conn->prepare("UPDATE users SET avatar = ?, avatar_memory = ? WHERE id = ?");
                $stmt->bind_param("ssi", $avatar_path, $avatar_memory, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Update session
                $_SESSION['user']['avatar'] = $avatar_path;
                
                // Get user_id_string for global_users and chatroom_users
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $user_id_string = $user_data['user_id'];
                $stmt->close();
                
                // Update global_users table
                $check_table = $conn->query("SHOW TABLES LIKE 'global_users'");
                if ($check_table->num_rows > 0) {
                    $stmt = $conn->prepare("UPDATE global_users SET avatar = ?, guest_avatar = ? WHERE user_id_string = ?");
                    if ($stmt) {
                        $stmt->bind_param("sss", $avatar_path, $avatar_path, $user_id_string);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                // Update chatroom_users table for any active sessions
                $check_table = $conn->query("SHOW TABLES LIKE 'chatroom_users'");
                if ($check_table->num_rows > 0) {
                    // Check if columns exist
                    $columns_check = $conn->query("SHOW COLUMNS FROM chatroom_users");
                    $has_avatar = false;
                    $has_guest_avatar = false;
                    while ($col = $columns_check->fetch_assoc()) {
                        if ($col['Field'] === 'avatar') $has_avatar = true;
                        if ($col['Field'] === 'guest_avatar') $has_guest_avatar = true;
                    }
                    
                    if ($has_avatar && $has_guest_avatar) {
                        $stmt = $conn->prepare("UPDATE chatroom_users SET avatar = ?, guest_avatar = ? WHERE user_id_string = ?");
                        if ($stmt) {
                            $stmt->bind_param("sss", $avatar_path, $avatar_path, $user_id_string);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } elseif ($has_guest_avatar) {
                        $stmt = $conn->prepare("UPDATE chatroom_users SET guest_avatar = ? WHERE user_id_string = ?");
                        if ($stmt) {
                            $stmt->bind_param("ss", $avatar_path, $user_id_string);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Avatar equipped', 'item_name' => $item['name']]);
                
            } elseif ($item['type'] === 'title') {
                // Titles: Maximum 2 can be equipped
                
                // Get current equipped title count
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_inventory ui 
                    JOIN shop_items si ON ui.item_id = si.item_id 
                    WHERE ui.user_id = ? AND si.type = 'title' AND ui.is_equipped = 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $count_data = $result->fetch_assoc();
                $equipped_count = $count_data['count'];
                $stmt->close();
                
                // Limit to 2 titles
                if ($equipped_count >= 2) {
                    echo json_encode(['status' => 'error', 'message' => 'Maximum 2 titles can be equipped at once. Please unequip a title first.']);
                    exit;
                }
                
                // Equip the title
                $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = ? AND item_id = ?");
                $stmt->bind_param("is", $user_id, $item_id);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode(['status' => 'success', 'message' => 'Title equipped', 'item_name' => $item['name']]);
                
            } elseif ($item['type'] === 'special') {
                // Special items cannot be equipped - they trigger actions on purchase
                echo json_encode(['status' => 'error', 'message' => 'Special items cannot be equipped. Their effects are applied automatically when purchased.']);
                
            } 
            elseif ($item['type'] === 'color') {
                // Colors: Only one can be equipped, works like avatars
                
                // Get current color and color_memory
                $stmt = $conn->prepare("SELECT color, color_memory FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $current_color = $user_data['color'];
                $color_memory = $user_data['color_memory'];
                $stmt->close();
                
                // Check if user has any equipped shop colors
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_inventory ui 
                    JOIN shop_items si ON ui.item_id = si.item_id 
                    WHERE ui.user_id = ? AND si.type = 'color' AND ui.is_equipped = 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $count_data = $result->fetch_assoc();
                $has_equipped_color = $count_data['count'] > 0;
                $stmt->close();
                
                // If no shop color is currently equipped AND color_memory is empty, save current color
                if (!$has_equipped_color && (empty($color_memory) || $color_memory === $current_color)) {
                    $color_memory = $current_color;
                }
                
                // Unequip all other colors
                $stmt = $conn->prepare("UPDATE user_inventory ui 
                    JOIN shop_items si ON ui.item_id = si.item_id 
                    SET ui.is_equipped = 0 
                    WHERE ui.user_id = ? AND si.type = 'color'");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Equip this color
                $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = ? AND item_id = ?");
                $stmt->bind_param("is", $user_id, $item_id);
                $stmt->execute();
                $stmt->close();
                
                // Get the color value from icon field
                $color_value = $item['icon'];
                
                // Update users table - update color but preserve color_memory
                $stmt = $conn->prepare("UPDATE users SET color = ?, color_memory = ? WHERE id = ?");
                $stmt->bind_param("ssi", $color_value, $color_memory, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Update session
                $_SESSION['user']['color'] = $color_value;
                
                // Get user_id_string for global_users and chatroom_users
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $user_id_string = $user_data['user_id'];
                $stmt->close();
                
                // Update global_users
                $stmt = $conn->prepare("UPDATE global_users SET color = ? WHERE user_id_string = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $color_value, $user_id_string);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Update chatroom_users
                $stmt = $conn->prepare("UPDATE chatroom_users SET color = ? WHERE user_id_string = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $color_value, $user_id_string);
                    $stmt->execute();
                    $stmt->close();
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Color equipped', 'item_name' => $item['name']]);
                
            } else {
                // Other item types: standard equipping
                $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = ? AND item_id = ?");
                $stmt->bind_param("is", $user_id, $item_id);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode(['status' => 'success', 'message' => 'Item equipped', 'item_name' => $item['name']]);
            }
            break;
            
        case 'unequip':
            $item_id = $_POST['item_id'] ?? '';
            
            if (empty($item_id)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid item']);
                exit;
            }
            
            // Get item type
            $stmt = $conn->prepare("
                SELECT si.type, si.name
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ? AND ui.item_id = ?
            ");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Item not found']);
                $stmt->close();
                exit;
            }
            
            $item = $result->fetch_assoc();
            $stmt->close();
            
            // Unequip the item
            $stmt = $conn->prepare("UPDATE user_inventory SET is_equipped = 0 WHERE user_id = ? AND item_id = ?");
            $stmt->bind_param("is", $user_id, $item_id);
            $stmt->execute();
            $stmt->close();
            
            // If it was an avatar, check if there's another equipped avatar or restore avatar_memory
            if ($item['type'] === 'avatar') {
                // Check if there's another equipped avatar after unequipping this one
                $stmt = $conn->prepare("
                    SELECT si.icon 
                    FROM user_inventory ui 
                    JOIN shop_items si ON ui.item_id = si.item_id 
                    WHERE ui.user_id = ? AND si.type = 'avatar' AND ui.is_equipped = 1 
                    LIMIT 1
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Use the other equipped avatar
                    $other_avatar = $result->fetch_assoc();
                    $default_avatar = $other_avatar['icon'];
                } else {
                    // No other equipped avatar, restore avatar_memory
                    $stmt2 = $conn->prepare("SELECT avatar_memory FROM users WHERE id = ?");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    $user_data = $result2->fetch_assoc();
                    $avatar_memory = $user_data['avatar_memory'];
                    $stmt2->close();
                    
                    $default_avatar = !empty($avatar_memory) ? $avatar_memory : 'default_avatar.jpg';
                }
                $stmt->close();
                
                // Update users table
                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->bind_param("si", $default_avatar, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Update session
                $_SESSION['user']['avatar'] = $default_avatar;
                
                // Get user_id_string
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $user_id_string = $user_data['user_id'];
                $stmt->close();
                
                // Update global_users
                $stmt = $conn->prepare("UPDATE global_users SET avatar = ?, guest_avatar = ? WHERE user_id_string = ?");
                if ($stmt) {
                    $stmt->bind_param("sss", $default_avatar, $default_avatar, $user_id_string);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Update chatroom_users
                $stmt = $conn->prepare("UPDATE chatroom_users SET guest_avatar = ? WHERE user_id_string = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $default_avatar, $user_id_string);
                    $stmt->execute();
                    $stmt->close();
                }
            } 
            
            if ($item['type'] === 'color') {
                // Check if there's another equipped color after unequipping this one
                $stmt = $conn->prepare("
                    SELECT si.icon 
                    FROM user_inventory ui 
                    JOIN shop_items si ON ui.item_id = si.item_id 
                    WHERE ui.user_id = ? AND si.type = 'color' AND ui.is_equipped = 1 
                    LIMIT 1
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Use the other equipped color
                    $other_color = $result->fetch_assoc();
                    $default_color = $other_color['icon'];
                } else {
                    // No other equipped color, restore color_memory
                    $stmt2 = $conn->prepare("SELECT color_memory FROM users WHERE id = ?");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    $user_data = $result2->fetch_assoc();
                    $color_memory = $user_data['color_memory'];
                    $stmt2->close();
                    
                    $default_color = !empty($color_memory) ? $color_memory : 'blue';
                }
                $stmt->close();
                
                // Update users table
                $stmt = $conn->prepare("UPDATE users SET color = ? WHERE id = ?");
                $stmt->bind_param("si", $default_color, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Update session
                $_SESSION['user']['color'] = $default_color;
                
                // Get user_id_string
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $user_id_string = $user_data['user_id'];
                $stmt->close();
                
                // Update global_users
                $stmt = $conn->prepare("UPDATE global_users SET color = ? WHERE user_id_string = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $default_color, $user_id_string);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Update chatroom_users
                $stmt = $conn->prepare("UPDATE chatroom_users SET color = ? WHERE user_id_string = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $default_color, $user_id_string);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Item unequipped', 'item_name' => $item['name']]);
            break;
            
        case 'get_equipped':
            // Get user's equipped items
            $stmt = $conn->prepare("
                SELECT ui.*, si.name, si.description, si.type, si.rarity, si.icon
                FROM user_inventory ui
                JOIN shop_items si ON ui.item_id = si.item_id
                WHERE ui.user_id = ? AND ui.is_equipped = 1
                ORDER BY si.type, si.rarity DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $equipped = [];
            while ($row = $result->fetch_assoc()) {
                $equipped[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['status' => 'success', 'equipped' => $equipped]);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
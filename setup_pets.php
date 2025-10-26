<?php
// setup_pets.php - Setup Pet Companion System (Dura-mates)
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Access denied. Admin only.");
}

echo "<h2>Dura-mates Pet System Setup</h2>\n";
echo "<pre>\n";

try {
    // Create pets table
    echo "Creating pets table...\n";
    $create_pets = "CREATE TABLE IF NOT EXISTS pets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        pet_type VARCHAR(50) NOT NULL,
        custom_name VARCHAR(20) NOT NULL,
        happiness INT DEFAULT 50 NOT NULL CHECK (happiness >= 0 AND happiness <= 100),
        hunger INT DEFAULT 100 NOT NULL CHECK (hunger >= 0 AND hunger <= 100),
        bond_level INT DEFAULT 1 NOT NULL CHECK (bond_level >= 1 AND bond_level <= 10),
        bond_xp INT DEFAULT 0 NOT NULL,
        last_fed TIMESTAMP NULL DEFAULT NULL,
        last_played TIMESTAMP NULL DEFAULT NULL,
        last_collected TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_favorited TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_favorited (is_favorited)
    )";
    $conn->query($create_pets);
    echo "✓ Created pets table\n";
    
    // Create pet_types table
    echo "\nCreating pet_types table...\n";
    $create_pet_types = "CREATE TABLE IF NOT EXISTS pet_types (
        id INT PRIMARY KEY AUTO_INCREMENT,
        type_id VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        description TEXT,
        shop_price INT NOT NULL,
        is_starter TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_starter (is_starter)
    )";
    $conn->query($create_pet_types);
    echo "✓ Created pet_types table\n";
    
    // Create user_pet_settings table
    echo "\nCreating user_pet_settings table...\n";
    $create_settings = "CREATE TABLE IF NOT EXISTS user_pet_settings (
        user_id INT PRIMARY KEY,
        profile_pet_slots INT DEFAULT 1 NOT NULL CHECK (profile_pet_slots >= 1 AND profile_pet_slots <= 3),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($create_settings);
    echo "✓ Created user_pet_settings table\n";
    
    // Insert starter and common pet types
    echo "\nInserting pet types...\n";
    $pet_types = [
        ['rocky', 'Pet Rock', 'images/pets/petrock.png', 'Mans best friend', 500, 1],

    ];
    
    $stmt = $conn->prepare("INSERT INTO pet_types (type_id, name, image_url, description, shop_price, is_starter) 
                            VALUES (?, ?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE name = VALUES(name)");
    
    foreach ($pet_types as $pet) {
        $stmt->bind_param("ssssis", $pet[0], $pet[1], $pet[2], $pet[3], $pet[4], $pet[5]);
        $stmt->execute();
    }
    $stmt->close();
    echo "✓ Inserted " . count($pet_types) . " pet types\n";
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Pet system is ready!\n";
    echo "\nSystem Details:\n";
    echo "- Pet Hub: 'The Hideout'\n";
    echo "- Pets are called: 'Pets'\n";
    echo "- Starter pet (Street Cat): 500 Dura\n";
    echo "- Common pets: 2,000 Dura\n";
    echo "- Rare pets: 5,000 Dura\n";
    echo "- Base Dura generation: 10 Dura/hour at 100% happiness\n";
    echo "- Bond levels 1-10 with XP progression\n";
    echo "- Profile slots: 1 default, unlock more at bond levels 5 and 10\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
$conn->close();
?>
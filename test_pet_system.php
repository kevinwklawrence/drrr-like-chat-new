<?php
// test_pet_system.php - Verify Pet System Setup
session_start();

// Allow admin access for testing
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Admin only. <a href='/lounge'>Back to Lounge</a>");
}

include 'db_connect.php';

$status = [
    'database' => [],
    'files' => [],
    'images' => [],
    'data' => []
];

// Check Database Tables
$tables = ['pets', 'pet_types', 'user_pet_settings'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $status['database'][$table] = $result->num_rows > 0 ? '✅' : '❌';
}

// Check pet_types has data
$result = $conn->query("SELECT COUNT(*) as count FROM pet_types");
$count = $result->fetch_assoc()['count'];
$status['data']['pet_types'] = $count > 0 ? "✅ ($count pet types)" : '❌ No pet types';

// Check Files
$files = [
    'api/pets.php',
    'api/pet_shop.php',
    'api/get_user_profile.php',
    'hideout.php',
    'css/pets.css',
    'js/hideout.js',
    'js/profile_pets_ext.js'
];

foreach ($files as $file) {
    $status['files'][$file] = file_exists($file) ? '✅' : '❌';
}

// Check Image Directory
$status['images']['directory'] = is_dir('images/pets') ? '✅' : '❌';
if (is_dir('images/pets')) {
    $images = glob('images/pets/*.{png,jpg,gif,webp}', GLOB_BRACE);
    $status['images']['count'] = count($images) > 0 ? "✅ (" . count($images) . " images)" : '❌ No images';
} else {
    $status['images']['count'] = '❌ Directory missing';
}

// Test Data - Get a sample pet if exists
$test_user_id = $_SESSION['user']['id'];
$result = $conn->query("SELECT COUNT(*) as count FROM pets WHERE user_id = $test_user_id");
$user_pets = $result->fetch_assoc()['count'];
$status['data']['user_pets'] = "User has $user_pets pets";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet System Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a1a; color: #e0e0e0; padding: 2rem; }
        .status-good { color: #43a047; }
        .status-bad { color: #ff6b6b; }
        .test-section { background: #2a2a2a; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-vial"></i> Pet System Test Page</h1>
        <p class="text-muted">Verify all components are installed correctly</p>
        
        <!-- Database Status -->
        <div class="test-section">
            <h3><i class="fas fa-database"></i> Database Tables</h3>
            <?php foreach ($status['database'] as $table => $result): ?>
                <div class="<?php echo $result === '✅' ? 'status-good' : 'status-bad'; ?>">
                    <?php echo $result; ?> <?php echo $table; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Files Status -->
        <div class="test-section">
            <h3><i class="fas fa-file-code"></i> Required Files</h3>
            <?php foreach ($status['files'] as $file => $result): ?>
                <div class="<?php echo $result === '✅' ? 'status-good' : 'status-bad'; ?>">
                    <?php echo $result; ?> <?php echo $file; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Images Status -->
        <div class="test-section">
            <h3><i class="fas fa-images"></i> Pet Images</h3>
            <?php foreach ($status['images'] as $key => $result): ?>
                <div class="<?php echo strpos($result, '✅') !== false ? 'status-good' : 'status-bad'; ?>">
                    <?php echo $result; ?> <?php echo ucfirst($key); ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Data Status -->
        <div class="test-section">
            <h3><i class="fas fa-database"></i> Data Status</h3>
            <?php foreach ($status['data'] as $key => $result): ?>
                <div class="<?php echo strpos($result, '✅') !== false ? 'status-good' : 'status-bad'; ?>">
                    <?php echo $result; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="test-section">
            <h3><i class="fas fa-tools"></i> Quick Actions</h3>
            <a href="setup_pets.php" class="btn btn-primary me-2">
                <i class="fas fa-play"></i> Run Database Setup
            </a>
            <a href="hideout.php" class="btn btn-success me-2">
                <i class="fas fa-home"></i> Go to Hideout
            </a>
            <a href="api/pets.php?action=get" class="btn btn-info me-2" target="_blank">
                <i class="fas fa-code"></i> Test API
            </a>
        </div>
        
        <!-- System Info -->
        <div class="test-section">
            <h3><i class="fas fa-info-circle"></i> System Information</h3>
            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
            <p><strong>MySQL Version:</strong> <?php echo $conn->server_info; ?></p>
            <p><strong>Current User:</strong> <?php echo $_SESSION['user']['username']; ?></p>
            <p><strong>User Dura:</strong> <?php echo $_SESSION['user']['dura'] ?? 0; ?></p>
        </div>
        
        <!-- Sample Pet Types -->
        <div class="test-section">
            <h3><i class="fas fa-paw"></i> Available Pet Types</h3>
            <?php
            $result = $conn->query("SELECT * FROM pet_types ORDER BY shop_price ASC");
            if ($result->num_rows > 0) {
                echo '<table class="table table-dark">';
                echo '<tr><th>Name</th><th>Price</th><th>Starter</th><th>Image Path</th></tr>';
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td>' . $row['shop_price'] . ' Dura</td>';
                    echo '<td>' . ($row['is_starter'] ? '✅' : '❌') . '</td>';
                    echo '<td>' . htmlspecialchars($row['image_url']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="status-bad">❌ No pet types found. Run setup_pets.php</p>';
            }
            ?>
        </div>
        
        <!-- Test Profile Modal -->
        <div class="test-section">
            <h3><i class="fas fa-user"></i> Test Profile Modal</h3>
            <button class="btn btn-primary" onclick="testProfileModal()">
                <i class="fas fa-eye"></i> Test Profile Modal
            </button>
            <div id="profileTest" class="mt-2"></div>
        </div>
        
        <div class="mt-4">
            <a href="lounge.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Lounge
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/profile_pets_ext.js"></script>
    <script>
        function testProfileModal() {
            const username = '<?php echo $_SESSION['user']['username']; ?>';
            if (typeof openUserProfile === 'function') {
                openUserProfile(username);
                document.getElementById('profileTest').innerHTML = 
                    '<div class="alert alert-success mt-2">✅ Profile modal function found and called!</div>';
            } else {
                document.getElementById('profileTest').innerHTML = 
                    '<div class="alert alert-danger mt-2">❌ openUserProfile function not found. Check if profile_pets_ext.js is loaded.</div>';
            }
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>
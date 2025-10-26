<?php
// hideout.php - The Hideout (Pet Management Hub)
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    header("Location: /guest");
    exit;
}

include 'db_connect.php';
$versions = include 'config/version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Hideout | Duranu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/style.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <link href="css/pets.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <?php include 'fav.php'; ?>
    <style>
        body {
            background: #1a1a1a;
            color: #e0e0e0;
        }
        .hideout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .pet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 768px) {
            .pet-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="hideout-header">
            <h1><i class="fas fa-home"></i> The Hideout</h1>
            <p class="mb-0">Manage your Dura-mates and collect passive Dura</p>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3>Your Dura-mates</h3>
                <small class="text-muted">Click a pet to interact</small>
            </div>
            <div>
                <a href="#" class="btn btn-primary" onclick="openPetShop(); return false;">
                    <i class="fas fa-store"></i> Pet Shop
                </a>
            </div>
        </div>
        
        <div id="petGrid" class="pet-grid">
            <div class="text-center p-5">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p class="mt-3">Loading your pets...</p>
            </div>
        </div>
    </div>
    
    <!-- Pet Interaction Modal -->
    <div class="modal fade" id="petModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444;">
                <div class="modal-header" style="border-bottom: 1px solid #555;">
                    <h5 class="modal-title" id="petModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body" id="petModalBody">
                    <!-- Dynamic content -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pet Shop Modal -->
    <div class="modal fade" id="petShopModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444;">
                <div class="modal-header" style="border-bottom: 1px solid #555;">
                    <h5 class="modal-title"><i class="fas fa-store"></i> Pet Shop</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body" id="petShopBody">
                    <!-- Dynamic content -->
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/hideout.js?v=<?php echo $versions['version']; ?>"></script>
</body>
</html>
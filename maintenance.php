<?php
session_start();

// Load maintenance configuration
require_once 'config/maintenance.php';

// Handle admin login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'admin_login') {
        $username = $_POST['admin_username'] ?? '';
        $password = $_POST['admin_password'] ?? '';
        
        header('Content-Type: application/json');
        
        if ($username === MAINTENANCE_ADMIN_USERNAME && $password === MAINTENANCE_ADMIN_PASSWORD) {
            $_SESSION['admin_bypass'] = true;
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid admin credentials']);
        }
        exit;
    }
}

// Check if admin has bypassed maintenance or maintenance is disabled
if (isset($_SESSION['admin_bypass']) || !isMaintenanceMode()) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance | Duranu</title>
    <meta name="keywords" content="drrr-like-chat, duranu, drrrchat, drrr, darasu, dorasu, mushoku, drrrkari, durarara, durarara!!">
    <meta name="description" content="Site is currently under maintenance. Please check back soon.">
    <?php include 'fav.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/colors.css?v=<?php echo $versions['version']; ?>" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: black;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Roboto Flex', sans-serif;
            overflow: hidden;
            position: relative;
        }
        
        .maintenance-container {
            text-align: center;
            z-index: 100;
            position: relative;
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .logo-container {
            position: relative;
            margin-bottom: 2rem;
            display: inline-block;
            width: 80vw;
            height: 80vw;
            max-width: 80vh;
            max-height: 80vh;
        }
        
        .site-logo {
            width: min(30vw, 30vh, 400px);
            z-index: 10;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            filter: drop-shadow(0 0 30px rgba(46, 46, 46, 0.4));
        }
        
        /* Animated Rings - Same as firewall.php */
        .ring {
            position: absolute;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .ring-1 {
            width: min(40vw, 40vh);
            height: min(40vw, 40vh);
            border-top: 4px solid rgba(255, 255, 255, 0.8);
            border-right: 4px solid rgba(255, 255, 255, 0.1);
            animation: rotate-cw 8s linear infinite;
        }
        
        .ring-2 {
            width: min(50vw, 50vh);
            height: min(50vw, 50vh);
            border-bottom: 3px solid rgba(255, 255, 255, 0.6);
            border-left: 3px solid rgba(255, 255, 255, 0.1);
            animation: rotate-ccw 12s linear infinite;
        }
        
        .ring-3 {
            width: min(60vw, 60vh);
            height: min(60vw, 60vh);
            border-top: 3px solid rgba(255, 255, 255, 0.4);
            border-right: 3px solid rgba(255, 255, 255, 0.1);
            animation: rotate-cw 15s linear infinite;
        }
        
        .ring-4 {
            width: min(70vw, 70vh);
            height: min(70vw, 70vh);
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            animation: rotate-ccw 20s linear infinite;
        }
        
        .ring-5 {
            width: min(80vw, 80vh);
            height: min(80vw, 80vh);
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            border-right: 2px solid rgba(255, 255, 255, 0.05);
            animation: rotate-cw 25s linear infinite;
        }
        
        @keyframes rotate-cw {
            from { transform: translate(-50%, -50%) rotate(0deg); }
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        @keyframes rotate-ccw {
            from { transform: translate(-50%, -50%) rotate(0deg); }
            to { transform: translate(-50%, -50%) rotate(-360deg); }
        }
        
        /* Enhanced ring effects */
        .ring::before {
            content: '';
            position: absolute;
            width: min(2vw, 2vh, 16px);
            height: min(2vw, 2vh, 16px);
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            top: min(-1vw, -1vh, -8px);
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 0 min(3vw, 25px) rgba(255, 255, 255, 0.8);
        }
        
        .ring::after {
            content: '';
            position: absolute;
            width: min(1.5vw, 1.5vh, 12px);
            height: min(1.5vw, 1.5vh, 12px);
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            bottom: min(-0.75vw, -0.75vh, -6px);
            right: 20%;
            box-shadow: 0 0 min(2vw, 20px) rgba(255, 255, 255, 0.6);
        }
        
        .maintenance-content {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            z-index: 200;
        }
        
        .maintenance-title {
            color: #ffffff;
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: clamp(1.5rem, 5vw, 2rem);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .maintenance-message {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            line-height: 1.5;
        }
        
        .admin-login-btn {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .admin-login-btn:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.2));
            color: #ffffff;
            text-decoration: none;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: rgba(0, 0, 0, 0.8);
            margin: 15% auto;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .close {
            color: rgba(255, 255, 255, 0.6);
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: #ffffff;
        }
        
        .form-control {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 1rem;
            margin-bottom: 1rem;
            width: 100%;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            background: rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.1);
            color: #ffffff;
            outline: none;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .btn-admin {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            cursor: pointer;
        }
        
        .btn-admin:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.2));
            border-color: rgba(255, 255, 255, 0.5);
            color: #ffffff;
        }
        
        .error-message {
            color: #ff6b6b;
            margin-top: 1rem;
            padding: 10px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 8px;
            border-left: 4px solid #ff6b6b;
            font-size: 0.9rem;
            display: none;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .logo-container {
                width: 95vw;
                height: 95vw;
                margin-bottom: 1rem;
            }
            
            .site-logo {
                width: min(30vw, 150px);
            }
            
            .ring-1 { width: 45vw; height: 45vw; }
            .ring-2 { width: 55vw; height: 55vw; }
            .ring-3 { width: 65vw; height: 65vw; }
            .ring-4 { width: 75vw; height: 75vw; }
            .ring-5 { width: 85vw; height: 85vw; }
            
            .maintenance-content {
                margin: 0 1rem;
                padding: 1.5rem;
            }
            
            .modal-content {
                margin: 20% auto;
                padding: 1.5rem;
            }
        }
        
        /* Floating particles */
        .particle {
            position: absolute;
            width: clamp(1px, 0.3vw, 3px);
            height: clamp(1px, 0.3vw, 3px);
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
            z-index: 1;
        }
        
        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
                opacity: 0; 
            }
            50% { 
                transform: translateY(min(-3vh, -20px)) rotate(180deg); 
                opacity: 1; 
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="logo-container">
            <img src="images/logo.png" alt="Duranu Logo" class="site-logo">
            <div class="ring ring-1"></div>
            <div class="ring ring-2"></div>
            <div class="ring ring-3"></div>
            <div class="ring ring-4"></div>
            <div class="ring ring-5"></div>
        </div>
        
        <div class="maintenance-content">
            <h1 class="maintenance-title">
                <i class="fas fa-tools"></i> Under Maintenance
            </h1>
            <p class="maintenance-message">
                <?php echo MAINTENANCE_MESSAGE; ?>
            </p>
            <p class="maintenance-message" style="font-size: 0.9rem; opacity: 0.7;">
                We apologize for any inconvenience.
            </p>
            
            <button class="admin-login-btn" onclick="openAdminModal()">
                <i class="fas fa-key"></i> Admin Access
            </button>
        </div>
    </div>

    <!-- Admin Login Modal -->
    <div id="adminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Administrator Login</h3>
                <span class="close" onclick="closeAdminModal()">&times;</span>
            </div>
            <form id="adminForm">
                <input type="hidden" name="action" value="admin_login">
                <input type="text" name="admin_username" class="form-control" placeholder="Username" required>
                <input type="password" name="admin_password" class="form-control" placeholder="Password" required>
                <button type="submit" class="btn-admin">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <div id="adminError" class="error-message"></div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Create floating particles
        function createParticles() {
            const particleCount = Math.min(50, Math.floor(window.innerWidth / 30));
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (4 + Math.random() * 4) + 's';
                document.body.appendChild(particle);
            }
        }

        // Initialize particles
        createParticles();

        // Admin modal functions
        function openAdminModal() {
            document.getElementById('adminModal').style.display = 'block';
            document.querySelector('input[name="admin_username"]').focus();
        }

        function closeAdminModal() {
            document.getElementById('adminModal').style.display = 'none';
            document.getElementById('adminForm').reset();
            document.getElementById('adminError').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('adminModal');
            if (event.target === modal) {
                closeAdminModal();
            }
        }

        // Handle admin form submission
        $('#adminForm').on('submit', function(e) {
            e.preventDefault();
            
            const button = $(this).find('button[type="submit"]');
            const errorDiv = $('#adminError');
            
            button.prop('disabled', true).text('Logging in...');
            errorDiv.hide();
            
            $.ajax({
                url: 'maintenance.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        button.text('Access Granted!');
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 1000);
                    } else {
                        errorDiv.text(response.message || 'Login failed').show();
                        button.prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Login');
                    }
                },
                error: function() {
                    errorDiv.text('Connection error. Please try again.').show();
                    button.prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Login');
                }
            });
        });

        // Recreate particles on resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                document.querySelectorAll('.particle').forEach(p => p.remove());
                createParticles();
            }, 250);
        });
    </script>
</body>
</html>
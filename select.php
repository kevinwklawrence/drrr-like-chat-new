<?php
session_start();

// Check if user passed firewall
if (!isset($_SESSION['firewall_passed'])) {
    header("Location: /firewall");
    exit;
}

// If user is already logged in, redirect appropriately
if (isset($_SESSION['user'])) {
    if (isset($_SESSION['room_id'])) {
        header("Location: /room");
    } else {
        header("Location: /lounge");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Mode | Duranu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #000;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .particle {
            position: fixed;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
            animation: float linear infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
        }
        
        .select-container {
            position: relative;
            z-index: 10;
            text-align: center;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .title {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
        }
        
        .button-grid {
            display: flex;
            flex-direction: row;
            gap: 1.5rem;
            min-width: 300px;
        }
        
        .select-btn {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 1.5rem 3rem;
            font-size: 1.3rem;
            font-weight: 600;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        
        .select-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
        }
        
        .select-btn:active {
            transform: translateY(-2px);
        }
        
        .select-btn i {
            font-size: 1.5rem;
        }
        
        .member-btn { border-color: rgba(100, 150, 255, 0.3); }
        .member-btn:hover { 
            background: rgba(100, 150, 255, 0.15);
            border-color: rgba(100, 150, 255, 0.5);
        }
        
        .guest-btn { border-color: rgba(150, 255, 150, 0.3); }
        .guest-btn:hover { 
            background: rgba(150, 255, 150, 0.15);
            border-color: rgba(150, 255, 150, 0.5);
        }
        
        .register-btn { border-color: rgba(255, 150, 100, 0.3); }
        .register-btn:hover { 
            background: rgba(255, 150, 100, 0.15);
            border-color: rgba(255, 150, 100, 0.5);
        }
        
        @media (max-width: 576px) {
            .button-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            min-width: 300px;
        }
            .title {
                font-size: 2rem;
                margin-bottom: 2rem;
            }
            
            .button-grid {
                min-width: 250px;
                gap: 1rem;
            }
            
            .select-btn {
                padding: 1.2rem 2rem;
                font-size: 1.1rem;
            }
            
            .select-btn i {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="select-container">
        <h1 class="title">Select Page</h1>
        <div class="button-grid">
            <a href="/member" class="select-btn member-btn">
                <i class="fas fa-user"></i>
                <span>Member</span>
            </a>
            <a href="/guest" class="select-btn guest-btn">
                <i class="fas fa-user-secret"></i>
                <span>Guest</span>
            </a>
            <a href="/register" class="select-btn register-btn">
                <i class="fas fa-user-plus"></i>
                <span>Register</span>
            </a>
        </div>
    </div>

    <script>
        // Create floating particles
        function createParticles() {
            const particleCount = 50;
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                const size = Math.random() * 10 + 5;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                particle.style.animationDelay = Math.random() * 5 + 's';
                document.body.appendChild(particle);
            }
        }
        
        createParticles();
        
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
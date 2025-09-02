<?php
session_start();

// If user already passed firewall, redirect to index
if (isset($_SESSION['firewall_passed'])) {
    header("Location: index.php");
    exit;
}

include 'db_connect.php';
include 'check_site_ban.php';

// Handle password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $site_password = 'baccano'; // Site password
    
    header('Content-Type: application/json');
    
    if ($password === $site_password) {
        // Check for site ban before allowing entry
        try {
            checkSiteBan($conn, true); // Return JSON format
        } catch (Exception $e) {
            // If checkSiteBan throws an exception or exits, it means user is banned
            // This won't be reached due to exit in checkSiteBan, but kept for safety
            echo json_encode(['status' => 'banned', 'message' => 'You are banned from this site']);
            exit;
        }
        
        // If we reach here, user is not banned
        $_SESSION['firewall_passed'] = true;
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duranu - Access Control</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Roboto Flex', sans-serif;
            overflow: hidden;
            position: relative;
        }
        
        .firewall-container {
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
        
        /* Animated Rings - Much Larger */
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
        
        /* Enhanced ring effects - Responsive */
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
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .logo-container {
                width: 95vw;
                height: 95vw;
                margin-bottom: 1rem;
            }
            
            .site-logo {
                width: min(30vw, 150px);
                height: min(30vw, 150px);
            }
            
            .ring-1 { width: 45vw; height: 45vw; }
            .ring-2 { width: 55vw; height: 55vw; }
            .ring-3 { width: 65vw; height: 65vw; }
            .ring-4 { width: 75vw; height: 75vw; }
            .ring-5 { width: 85vw; height: 85vw; }
            
            .password-form {
                margin: 0 1rem;
                padding: 1.5rem;
            }
        }
        
        /* Tablet optimizations */
        @media (min-width: 769px) and (max-width: 1024px) {
            .logo-container {
                width: 90vmin;
                height: 90vmin;
            }
            
            .site-logo {
                width: min(20vw, 20vh, 180px);
                height: min(20vw, 20vh, 180px);
            }
        }
        
        /* Large desktop optimizations */
        @media (min-width: 1400px) {
            .logo-container {
                width: 70vmin;
                height: 70vmin;
            }
            
            .ring::before {
                width: 20px;
                height: 20px;
                top: -10px;
                box-shadow: 0 0 30px rgba(255, 255, 255, 0.9);
            }
            
            .ring::after {
                width: 16px;
                height: 16px;
                bottom: -8px;
                box-shadow: 0 0 25px rgba(255, 255, 255, 0.7);
            }
        }
        
        .password-form {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 400px;
            margin: 0 auto;
            position: relative;
            z-index: 200;
        }
        
        .password-title {
            color: #ffffff;
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .form-control {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: clamp(1rem, 3vw, 1.1rem);
            margin-bottom: 1rem;
        }
        
        .form-control:focus {
            background: rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .btn-firewall {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            font-size: clamp(0.9rem, 3vw, 1rem);
        }
        
        .btn-firewall:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.2));
            border-color: rgba(255, 255, 255, 0.5);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.1);
        }
        
        .error-message {
            color: #ff6b6b;
            margin-top: 1rem;
            padding: 10px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 8px;
            border-left: 4px solid #ff6b6b;
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Floating particles - Responsive */
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
        
        /* Ultra-wide screen adjustments */
        @media (min-aspect-ratio: 21/9) {
            .logo-container {
                width: 60vh;
                height: 60vh;
            }
        }
        
        /* Portrait orientation adjustments */
        @media (orientation: portrait) {
            .logo-container {
                width: 80vw;
                height: 80vw;
            }
            
            .ring-1 { width: 40vw; height: 40vw; }
            .ring-2 { width: 50vw; height: 50vw; }
            .ring-3 { width: 60vw; height: 60vw; }
            .ring-4 { width: 70vw; height: 70vw; }
            .ring-5 { width: 80vw; height: 80vw; }
        }
        
        /* Landscape orientation adjustments */
        @media (orientation: landscape) and (max-height: 600px) {
            .logo-container {
                width: 70vh;
                height: 70vh;
            }
            
            .password-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="firewall-container">
        <div class="logo-container">
            <img src="images/duranu.png" alt="Duranu Logo" class="site-logo">
            <div class="ring ring-1"></div>
            <div class="ring ring-2"></div>
            <div class="ring ring-3"></div>
            <div class="ring ring-4"></div>
            <div class="ring ring-5"></div>
        </div>
        
        <div class="password-form">
            <h2 class="password-title">
                <i class="fas fa-shield-alt"></i> Access Control
            </h2>
            
            <form id="firewallForm">
                <input type="password" 
                       class="form-control" 
                       id="password" 
                       name="password" 
                       placeholder="Enter site password..." 
                       required 
                       autocomplete="off">
                
                <button type="submit" class="btn btn-firewall">
                    <div class="loading-spinner" id="loadingSpinner"></div>
                    <i class="fas fa-unlock" id="unlockIcon"></i>
                    <span id="buttonText">Authenticate</span>
                </button>
            </form>
            
            <div id="errorMessage" class="error-message" style="display: none;"></div>
        </div>
    </div>

    <!-- Terms and Privacy Modal -->
    <div class="modal fade" id="termsPrivacyModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: #1a1a1a; color: #e0e0e0; border: 1px solid #333;">
                <div class="modal-header" style="border-bottom: 1px solid #333;">
                    <h5 class="modal-title">
                        <i class="fas fa-file-contract"></i> Terms of Service & Privacy Policy
                    </h5>
                </div>
                <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                    <!-- Terms and Privacy content will be loaded here -->
                    <div id="termsPrivacyContent"></div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #333;">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="acceptTerms">
                        <label class="form-check-label" for="acceptTerms">
                            I have read and agree to the Terms of Service and Privacy Policy
                        </label>
                    </div>
                    <button type="button" class="btn btn-primary ms-3" id="continueBtn" disabled onclick="continueToSite()">
                        <i class="fas fa-arrow-right"></i> Continue to Site
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Create floating particles
        function createParticles() {
            // Determine number of particles based on screen size and performance
            const isMobile = window.innerWidth < 768;
            const isTablet = window.innerWidth >= 768 && window.innerWidth < 1200;
            const isDesktop = window.innerWidth >= 1200;
            
            let particleCount = 20; // Mobile default
            if (isTablet) particleCount = 40;
            if (isDesktop) particleCount = 60;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + 'vw';
                particle.style.top = Math.random() * 100 + 'vh';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 3 + 4) + 's';
                
                document.body.appendChild(particle);
            }
        }
        
        // Initialize particles
        createParticles();
        
        // Recreate particles on resize for better performance
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                // Remove existing particles
                document.querySelectorAll('.particle').forEach(p => p.remove());
                // Create new particles
                createParticles();
            }, 250);
        });
        
        // Handle form submission
        $('#firewallForm').on('submit', function(e) {
            e.preventDefault();
            
            const password = $('#password').val();
            const button = $(this).find('button[type="submit"]');
            const spinner = $('#loadingSpinner');
            const icon = $('#unlockIcon');
            const buttonText = $('#buttonText');
            const errorDiv = $('#errorMessage');
            
            // Show loading state
            button.prop('disabled', true);
            spinner.show();
            icon.hide();
            buttonText.text('Authenticating...');
            errorDiv.hide();
            
            $.ajax({
                url: 'firewall.php',
                method: 'POST',
                data: { password: password },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        buttonText.text('Access Granted');
                        icon.removeClass('fa-unlock').addClass('fa-check').show();
                        spinner.hide();
                        
                        // Load and show terms/privacy modal
                        loadTermsAndPrivacy();
                    } else if (response.status === 'banned') {
                        // This shouldn't happen due to checkSiteBan exit, but kept for safety
                        window.location.href = 'firewall.php';
                    } else {
                        showError(response.message || 'Authentication failed');
                    }
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 200 && xhr.responseText.includes('banned')) {
                        // User is banned - page already shown by checkSiteBan
                        return;
                    }
                    showError('Connection error. Please try again.');
                },
                complete: function() {
                    if (!$('#termsPrivacyModal').hasClass('show')) {
                        button.prop('disabled', false);
                        spinner.hide();
                        icon.show();
                        buttonText.text('Authenticate');
                    }
                }
            });
        });
        
        function showError(message) {
            const errorDiv = $('#errorMessage');
            errorDiv.text(message).show();
            
            // Reset form state
            $('#firewallForm button').prop('disabled', false);
            $('#loadingSpinner').hide();
            $('#unlockIcon').removeClass('fa-check').addClass('fa-unlock').show();
            $('#buttonText').text('Authenticate');
        }
        
        function loadTermsAndPrivacy() {
            const content = `
                <div class="terms-privacy-content">
                    <h6><i class="fas fa-gavel"></i> Terms of Service</h6>
                    <div class="mb-4" style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px;">
                        <p class="lead"><strong>By using this chat service, you agree to the following terms:</strong></p>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-users"></i> Community Guidelines</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li>Treat all users with respect and courtesy at all times</li>
                                <li>No harassment, bullying, discrimination, or personal attacks</li>
                                <li>No spam, excessive caps lock, or message flooding</li>
                                <li>No sharing of inappropriate, illegal, or explicit content</li>
                                <li>No impersonation of other users, staff members, or public figures</li>
                                <li>Keep conversations appropriate for a general audience</li>
                            </ul>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-ban"></i> Prohibited Activities</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li>Discussion or promotion of illegal activities</li>
                                <li>Sharing harmful, malicious, or dangerous content</li>
                                <li>Attempting to hack, exploit, or disrupt the service</li>
                                <li>Creating multiple accounts to evade bans or restrictions</li>
                                <li>Sharing personal information of other users without consent</li>
                                <li>Commercial advertising or promotional content without permission</li>
                            </ul>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-shield-alt"></i> Moderation & Enforcement</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li>Staff and moderators have the authority to enforce these terms</li>
                                <li>Moderation decisions are made at staff discretion and are final</li>
                                <li>Violations may result in warnings, temporary bans, or permanent suspension</li>
                                <li>We reserve the right to remove content or ban users without prior notice</li>
                                <li>Appeals may be submitted through official channels</li>
                            </ul>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-info-circle"></i> Additional Terms</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li>These terms may be updated periodically without prior notice</li>
                                <li>Use of this service constitutes acceptance of current terms</li>
                                <li>We are not responsible for user-generated content or interactions</li>
                                <li>Service availability is not guaranteed and may be interrupted</li>
                            </ul>
                        </div>
                        
                        <div class="alert" style="background: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.3); color: #f8d7da; margin-top: 1.5rem; padding: 1rem; border-radius: 8px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Important:</strong> Violation of these terms may result in immediate temporary or permanent suspension from the service. By using this chat platform, you acknowledge that you have read, understood, and agree to be bound by these terms.
                        </div>
                    </div>
                    
                    <h6><i class="fas fa-shield-alt"></i> Privacy Policy</h6>
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px;">
                        <p class="lead"><strong>Your privacy is important to us. This policy explains how we collect, use, and protect your information.</strong></p>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-database"></i> Information We Collect</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li><strong>IP Address:</strong> Automatically collected when you visit our site for security and anti-spam purposes</li>
                                <li><strong>Email Address:</strong> Required when creating a registered account, used for account recovery and important notifications</li>
                                <li><strong>Chat Messages:</strong> Temporarily stored to provide chat functionality and enable moderation</li>
                                <li><strong>User Preferences:</strong> Avatar choices, color selections, and display settings to personalize your experience</li>
                                <li><strong>Account Information:</strong> Username and basic profile data for registered users</li>
                            </ul>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-cogs"></i> How We Use Your Information</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li><strong>Service Functionality:</strong> To provide and maintain the chat service</li>
                                <li><strong>Security & Safety:</strong> To prevent abuse, spam, and ensure user safety</li>
                                <li><strong>Moderation:</strong> To enforce community guidelines and terms of service</li>
                                <li><strong>Technical Support:</strong> To provide assistance and resolve technical issues</li>
                                <li><strong>Service Improvement:</strong> To analyze usage patterns and improve our platform</li>
                            </ul>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-lock"></i> Data Protection & Security</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li><strong>No Third-Party Sales:</strong> We do not sell, rent, or share your personal information with third parties for marketing purposes</li>
                                <li><strong>Internal Use Only:</strong> Your data is used solely for website functionality, security, and user support</li>
                                <li><strong>Security Measures:</strong> We implement reasonable technical and administrative safeguards to protect your information</li>
                                <li><strong>Access Control:</strong> Only authorized personnel have access to personal information when necessary</li>
                                <li><strong>Data Retention:</strong> We retain information only as long as necessary for the purposes outlined in this policy</li>
                            </ul>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-user-cog"></i> Your Rights & Choices</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li><strong>Account Deletion:</strong> You may request deletion of your account and associated data at any time</li>
                                <li><strong>Data Access:</strong> You can view and update your account information through your profile</li>
                                <li><strong>Communication Preferences:</strong> You can opt out of non-essential communications</li>
                                <li><strong>Data Correction:</strong> You may request correction of inaccurate personal information</li>
                            </ul>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                            <h6><i class="fas fa-cookie-bite"></i> Cookies & Tracking</h6>
                            <ul style="line-height: 1.6; text-align: left;">
                                <li><strong>Session Cookies:</strong> Used to maintain your login session and preferences</li>
                                <li><strong>Security Cookies:</strong> Help protect against unauthorized access and security threats</li>
                                <li><strong>No Third-Party Tracking:</strong> We do not use third-party analytics or advertising cookies</li>
                                <li><strong>Essential Only:</strong> All cookies used are essential for basic site functionality</li>
                            </ul>
                        </div>
                        
                        <div class="alert" style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.3); color: #b6d4fe; margin-top: 1.5rem; padding: 1rem; border-radius: 8px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Contact Us:</strong> If you have questions about this privacy policy or wish to exercise your data rights, please contact the site administrators. This policy may be updated periodically, and continued use of the service constitutes acceptance of any changes.
                        </div>
                    </div>
                </div>
            `;
            
            $('#termsPrivacyContent').html(content);
            $('#termsPrivacyModal').modal('show');
        }
        
        // Enable continue button when checkbox is checked
        $('#acceptTerms').on('change', function() {
            $('#continueBtn').prop('disabled', !this.checked);
        });
        
        function continueToSite() {
            $('#continueBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
            
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        }
        
        // Auto-focus password input
        $(document).ready(function() {
            $('#password').focus();
        });
        
        // Enter key handling
        $(document).on('keypress', function(e) {
            if (e.which === 13 && !$('#termsPrivacyModal').hasClass('show')) {
                $('#firewallForm').submit();
            }
        });
    </script>
</body>
</html>
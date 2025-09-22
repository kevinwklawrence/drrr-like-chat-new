<?php
// terms_privacy_modals.php - Reusable Terms of Service and Privacy Policy modals
?>

<!-- Terms of Service Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: #2a2a2a; color: #e0e0e0; border: 1px solid #444;">
            <div class="modal-header" style="border-bottom: 1px solid #444;">
                <h5 class="modal-title" id="termsModalLabel">
                    <i class="fas fa-gavel"></i> Terms of Service
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body" style=" overflow-y: auto;">
                <div class="terms-content">
                    <p class="lead"><strong>By using this chat service, you agree to the following terms:</strong></p>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                        <h6><i class="fas fa-users"></i> Community Guidelines</h6>
                        <ul style="line-height: 1.6;">
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
                        <ul style="line-height: 1.6;">
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
                        <ul style="line-height: 1.6;">
                            <li>Staff and moderators have the authority to enforce these terms</li>
                            <li>Moderation decisions are made at staff discretion and are final</li>
                            <li>Violations may result in warnings, temporary bans, or permanent suspension</li>
                            <li>We reserve the right to remove content or ban users without prior notice</li>
                            <li>Appeals may be submitted through official channels</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px;">
                        <h6><i class="fas fa-info-circle"></i> Additional Terms</h6>
                        <ul style="line-height: 1.6;">
                            <li>These terms may be updated periodically without prior notice</li>
                            <li>Use of this service constitutes acceptance of current terms</li>
                            <li>We are not responsible for user-generated content or interactions</li>
                            <li>Service availability is not guaranteed and may be interrupted</li>
                        </ul>
                    </div>
                    
                    <div class="alert" style="background: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.3); color: #f8d7da; margin-top: 1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong> Violation of these terms may result in immediate temporary or permanent suspension from the service. By using this chat platform, you acknowledge that you have read, understood, and agree to be bound by these terms.
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #444;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: #2a2a2a; color: #e0e0e0; border: 1px solid #444;">
            <div class="modal-header" style="border-bottom: 1px solid #444;">
                <h5 class="modal-title" id="privacyModalLabel">
                    <i class="fas fa-shield-alt"></i> Privacy Policy
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body" style=" overflow-y: auto;">
                <div class="privacy-content">
                    <p class="lead"><strong>Your privacy is important to us. This policy explains how we collect, use, and protect your information.</strong></p>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                        <h6><i class="fas fa-database"></i> Information We Collect</h6>
                        <ul style="line-height: 1.6;">
                            <li><strong>IP Address:</strong> Automatically collected when you visit our site for security and anti-spam purposes</li>
                            <li><strong>Email Address:</strong> Required when creating a registered account, used for account recovery and important notifications</li>
                            <li><strong>Chat Messages:</strong> Temporarily stored to provide chat functionality and enable moderation</li>
                            <li><strong>User Preferences:</strong> Avatar choices, color selections, and display settings to personalize your experience</li>
                            <li><strong>Account Information:</strong> Username and basic profile data for registered users</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                        <h6><i class="fas fa-cogs"></i> How We Use Your Information</h6>
                        <ul style="line-height: 1.6;">
                            <li><strong>Service Functionality:</strong> To provide and maintain the chat service</li>
                            <li><strong>Security & Safety:</strong> To prevent abuse, spam, and ensure user safety</li>
                            <li><strong>Moderation:</strong> To enforce community guidelines and terms of service</li>
                            <li><strong>Technical Support:</strong> To provide assistance and resolve technical issues</li>
                            <li><strong>Service Improvement:</strong> To analyze usage patterns and improve our platform</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                        <h6><i class="fas fa-lock"></i> Data Protection & Security</h6>
                        <ul style="line-height: 1.6;">
                            <li><strong>No Third-Party Sales:</strong> We do not sell, rent, or share your personal information with third parties for marketing purposes</li>
                            <li><strong>Internal Use Only:</strong> Your data is used solely for website functionality, security, and user support</li>
                            <li><strong>Security Measures:</strong> We implement reasonable technical and administrative safeguards to protect your information</li>
                            <li><strong>Access Control:</strong> Only authorized personnel have access to personal information when necessary</li>
                            <li><strong>Data Retention:</strong> We retain information only as long as necessary for the purposes outlined in this policy</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                        <h6><i class="fas fa-user-cog"></i> Your Rights & Choices</h6>
                        <ul style="line-height: 1.6;">
                            <li><strong>Account Deletion:</strong> You may request deletion of your account and associated data at any time</li>
                            <li><strong>Data Access:</strong> You can view and update your account information through your profile</li>
                            <li><strong>Communication Preferences:</strong> You can opt out of non-essential communications</li>
                            <li><strong>Data Correction:</strong> You may request correction of inaccurate personal information</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px;">
                        <h6><i class="fas fa-cookie-bite"></i> Cookies & Tracking</h6>
                        <ul style="line-height: 1.6;">
                            <li><strong>Session Cookies:</strong> Used to maintain your login session and preferences</li>
                            <li><strong>Security Cookies:</strong> Help protect against unauthorized access and security threats</li>
                            <li><strong>No Third-Party Tracking:</strong> We do not use third-party analytics or advertising cookies</li>
                            <li><strong>Essential Only:</strong> All cookies used are essential for basic site functionality</li>
                        </ul>
                    </div>
                    
                    <div class="alert" style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.3); color: #b6d4fe; margin-top: 1.5rem;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Contact Us:</strong> If you have questions about this privacy policy or wish to exercise your data rights, please contact the site administrators. This policy may be updated periodically, and continued use of the service constitutes acceptance of any changes.
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #444;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Move modals to be right after <body>
    $('body').prepend($('#termsModal, #privacyModal'));

    // Terms of Service link handler
    $('a[href="terms.php"]').on('click', function(e) {
        e.preventDefault();
        $('#termsModal').modal('show');
    });

    // Privacy Policy link handler
    $('a[href="privacy.php"]').on('click', function(e) {
        e.preventDefault();
        $('#privacyModal').modal('show');
    });
});
</script>
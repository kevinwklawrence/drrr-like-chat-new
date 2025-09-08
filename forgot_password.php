<div class="modal fade" id="forgotModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: #2a2a2a; color: #e0e0e0; border: 1px solid #444;">
            <div class="modal-header" style="border-bottom: 1px solid #444;">
                <h5 class="modal-title" id="termsModalLabel">
                    <i class="fas fa-lock"></i> Forgot Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body" style=" overflow-y: auto;">
                <div class="terms-content">
                    <p>If you've forgotten your password, don't worry! You can reset it by following these steps:</p>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                        
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
    // Terms of Service link handler
    $('a[href="forgot.php"]').on('click', function(e) {
        e.preventDefault();
        $('#forgotModal').modal('show');
    });
});
</script>
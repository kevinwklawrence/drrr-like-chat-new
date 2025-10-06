// js/inactivity_warning.js - Warning before disconnect (SSE-integrated)
// Use var instead of let to prevent redeclaration errors if script loads multiple times
if (typeof inactivityWarned === 'undefined') {
    var inactivityWarned = false;
}

// Check inactivity status every 30 seconds ONLY if SSE is not connected
// When SSE is connected, inactivity data comes via SSE stream
setInterval(() => {
    // Only use AJAX fallback if SSE is not connected
    if (typeof sseConnected !== 'undefined' && sseConnected) {
        // SSE is handling inactivity status, skip AJAX
        return;
    }
    
    fetch('api/get_inactivity_status.php')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                handleInactivityStatusResponse(data);
            }
        })
        .catch(error => {
            console.error('Inactivity status error:', error);
        });
}, 30000);

function showInactivityWarning(seconds) {
    const minutesLeft = Math.ceil((3600 - seconds) / 60);
    
    // Check if warning already exists
    if (document.getElementById('inactivity-warning')) {
        return;
    }
    
    const warning = document.createElement('div');
    warning.id = 'inactivity-warning';
    warning.innerHTML = `
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:10000;display:flex;align-items:center;justify-content:center;">
            <div style="background:white;padding:30px;border-radius:10px;text-align:center;max-width:400px;width:90%;max-width:min(400px,90vw);">
                <h3 style="margin-top:0;">⚠️ Inactivity Warning</h3>
                <p>You will be disconnected in ${minutesLeft} minutes due to inactivity.</p>
                <button onclick="handleInactivityResponse()" style="padding:10px 20px;margin:10px;background:#4CAF50;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px;">
                    I'm Still Here!
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(warning);
}

function handleInactivityResponse() {
    // Reset the inactivity timer
    fetch('api/reset_user_activity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            // Remove the warning
            inactivityWarned = false;
            const warning = document.getElementById('inactivity-warning');
            if (warning) {
                warning.remove();
            }
        }
    })
    .catch(error => {
        console.error('Failed to reset inactivity:', error);
    });
}

function handleInactivityStatusResponse(data) {
    if (data.status !== 'success') {
        return;
    }
    
    const seconds = data.seconds || 0;
    const timeout = data.timeout || 3600;
    const warningThreshold = timeout - 300; // Warn 5 minutes before disconnect
    
    // Show warning if user is getting close to disconnect
    if (seconds >= warningThreshold && !inactivityWarned) {
        inactivityWarned = true;
        showInactivityWarning(seconds);
    }
    
    // Clear warning if user became active again
    if (seconds < warningThreshold && inactivityWarned) {
        inactivityWarned = false;
        const warning = document.getElementById('inactivity-warning');
        if (warning) {
            warning.remove();
        }
    }
}
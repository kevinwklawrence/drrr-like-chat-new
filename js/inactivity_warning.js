// js/inactivity_warning.js - Warning before disconnect
let inactivityWarned = false;

// Check inactivity status every 30 seconds
setInterval(() => {
    fetch('api/get_inactivity_status.php')
        .then(r => r.json())
        .then(data => {
            if (data.seconds >= 3300 && !inactivityWarned) { // 55 minutes
                showInactivityWarning(data.seconds);
                inactivityWarned = true;
            }
        });
}, 30000);

function showInactivityWarning(seconds) {
    const minutesLeft = Math.ceil((3600 - seconds) / 60);
    
    const warning = document.createElement('div');
    warning.id = 'inactivity-warning';
    warning.innerHTML = `
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:10000;display:flex;align-items:center;justify-content:center;">
            <div style="background:white;padding:30px;border-radius:10px;text-align:center;max-width:400px;">
                <h3>⚠️ Inactivity Warning</h3>
                <p>You will be disconnected in ${minutesLeft} minutes due to inactivity.</p>
                <button onclick="handleInactivityResponse(true)" style="padding:10px 20px;margin:10px;background:#4CAF50;color:white;border:none;border-radius:5px;cursor:pointer;">
                    I'm Still Here!
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(warning);
}

function handleInactivityResponse(stayActive) {
    if (stayActive) {
        // Reset timer
        fetch('api/reset_inactivity.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'reset_timer=1'
        }).then(() => {
            inactivityWarned = false;
            const warning = document.getElementById('inactivity-warning');
            if (warning) warning.remove();
        });
    }
}
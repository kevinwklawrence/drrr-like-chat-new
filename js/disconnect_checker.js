// js/disconnect_checker.js - Backup only (room status handled by main SSE)
(function() {
    let backupCheckInterval = null;
    let disconnectShown = false;
    
    function backupDisconnectCheck() {
        if (disconnectShown) return;
        
        fetch('api/check_room_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'not_in_room' && !disconnectShown) {
                    disconnectShown = true;
                    
                    const overlay = document.createElement('div');
                    overlay.innerHTML = `
                        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:99999;display:flex;align-items:center;justify-content:center;">
                            <div style="background:white;padding:30px;border-radius:10px;text-align:center;max-width:400px;">
                                <h2>⚠️ Disconnected</h2>
                                <p>${data.message || 'You have been removed from the room.'}</p>
                                <button onclick="window.location.href='/lounge'" style="padding:10px 30px;background:#4CAF50;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px;">
                                    Return to Lounge
                                </button>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(overlay);
                    
                    setTimeout(() => {
                        window.location.href = '/lounge';
                    }, 5000);
                    
                    if (backupCheckInterval) {
                        clearInterval(backupCheckInterval);
                    }
                }
            })
            .catch(err => console.error('Room status check failed:', err));
    }
    
    // Backup check every 30 seconds (SSE handles primary checking)
    backupCheckInterval = setInterval(backupDisconnectCheck, 30000);
    
    window.addEventListener('focus', backupDisconnectCheck);
    
    backupDisconnectCheck();
})();
// js/disconnect_checker.js - Check if user is still in room
(function() {
    let checkInterval = null;
    let disconnectShown = false;
    
    function checkRoomStatus() {
        fetch('api/check_room_status.php')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'not_in_room' && !disconnectShown) {
                    disconnectShown = true;
                    
                    // Show disconnect message
                    const modal = document.createElement('div');
                    modal.textContent = `
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
                    document.body.appendChild(modal);
                    
                    // Auto-redirect after 5 seconds
                    setTimeout(() => {
                        window.location.href = '/lounge';
                    }, 5000);
                    
                    // Stop checking
                    if (checkInterval) {
                        clearInterval(checkInterval);
                    }
                }
            })
            .catch(err => console.error('Room status check failed:', err));
    }
    
    // Check every 10 seconds
    checkInterval = setInterval(checkRoomStatus, 10000);
    
    // Also check on window focus
    window.addEventListener('focus', checkRoomStatus);
    
    // Initial check
    checkRoomStatus();
})();
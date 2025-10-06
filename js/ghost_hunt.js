// js/ghost_hunt.js - Client-side ghost hunt handling

// Show ghost caught notification
function showGhostCaughtNotification(reward, newBalance) {
    // Create animated notification
    const notification = $(`
        <div class="ghost-caught-notification" style="
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            background: linear-gradient(135deg, #ff6b00, #ffa500);
            color: white;
            padding: 30px 50px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(255, 107, 0, 0.5);
            z-index: 10000;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            animation: ghostCaughtPop 0.5s ease-out forwards;
        ">
            <div style="font-size: 60px; margin-bottom: 10px;">👻🎉</div>
            <div>GHOST CAUGHT!</div>
            <div style="font-size: 32px; margin: 15px 0;">+${reward} 🎃</div>
            <div style="font-size: 16px; opacity: 0.9;">New Balance: ${newBalance}</div>
        </div>
    `);
    
    // Add animation CSS
    if (!$('#ghostCaughtAnimation').length) {
        $('head').append(`
            <style id="ghostCaughtAnimation">
                @keyframes ghostCaughtPop {
                    0% { transform: translate(-50%, -50%) scale(0) rotate(-180deg); }
                    70% { transform: translate(-50%, -50%) scale(1.1) rotate(10deg); }
                    100% { transform: translate(-50%, -50%) scale(1) rotate(0deg); }
                }
                @keyframes ghostCaughtFade {
                    0% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
                    100% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
                }
            </style>
        `);
    }
    
    $('body').append(notification);
    
    // Play sound effect if available
    try {
        const audio = new Audio('/sounds/ghost_caught.mp3');
        audio.volume = 0.5;
        audio.play().catch(() => {});
    } catch (e) {}
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.css('animation', 'ghostCaughtFade 0.5s ease-out forwards');
        setTimeout(() => notification.remove(), 500);
    }, 3000);
    
    // Update balance display if it exists
    if (typeof currentUser !== 'undefined') {
        currentUser.event_currency = newBalance;
        if (typeof updateEventCurrencyDisplay === 'function') {
            updateEventCurrencyDisplay();
        }
        if (typeof updateShopBalances === 'function') {
            updateShopBalances();
        }
    }
}

// Enhanced message sending to handle ghost hunt responses
if (typeof originalSendMessage === 'undefined' && typeof sendMessage !== 'undefined') {
    window.originalSendMessage = sendMessage;
}

// This will be called from room.js when a message is sent
function handleGhostHuntResponse(response) {
    if (response.ghost_caught && response.ghost_reward) {
        showGhostCaughtNotification(response.ghost_reward, response.new_event_currency);
    }
}
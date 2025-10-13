// js/pumpkin_smash.js - Pumpkin Smash game system

$(document).on('click', '.pumpkin-smash-btn', function() {
    const btn = $(this);
    const pumpkinId = btn.data('pumpkin-id');
    
    if (!pumpkinId) {
        console.error('No pumpkin ID found');
        return;
    }
    
    // Disable button to prevent double clicks
    btn.prop('disabled', true);
    btn.html('💥 SMASHING...');
    
    $.ajax({
        url: 'api/claim_pumpkin.php',
        method: 'POST',
        data: { pumpkin_id: pumpkinId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Show celebration
                showPumpkinSmashNotification(response.reward, response.new_balance);
                
                // Update balance
                if (typeof currentUser !== 'undefined') {
                    currentUser.event_currency = response.new_balance;
                    if (typeof updateEventCurrencyDisplay === 'function') {
                        updateEventCurrencyDisplay();
                    }
                    if (typeof updateShopBalances === 'function') {
                        updateShopBalances();
                    }
                }
                
                // Remove button
                btn.closest('.message-content').find('.pumpkin-smash-btn').remove();
            } else {
                btn.html('❌ ' + response.message);
                setTimeout(() => btn.remove(), 2000);
            }
        },
        error: function() {
            btn.html('❌ Error');
            btn.prop('disabled', false);
        }
    });
});

function showPumpkinSmashNotification(reward, newBalance) {
    const notification = $('<div class="pumpkin-smash-notification"></div>');
    notification.html(`
        <div style="font-size: 3rem; animation: pumpkinBounce 0.6s ease;">💥</div>
        <div style="font-size: 1.5rem; font-weight: bold; color: #ff6b00;">PUMPKIN SMASHED!</div>
        <div style="font-size: 1.2rem; color: #4CAF50;">+${reward} 🎃 Event Currency</div>
        <div style="font-size: 0.9rem; color: #aaa;">New Balance: ${newBalance}</div>
    `);
    
    notification.css({
        position: 'fixed',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
        background: 'linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%)',
        padding: '30px 50px',
        borderRadius: '15px',
        border: '3px solid #ff6b00',
        boxShadow: '0 10px 40px rgba(255,107,0,0.5)',
        zIndex: 10000,
        textAlign: 'center',
        animation: 'pumpkinSmashFade 0.5s ease-in'
    });
    
    $('body').append(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.css('animation', 'pumpkinSmashFade 0.5s ease-out reverse');
        setTimeout(() => notification.remove(), 500);
    }, 3000);
}

// Add CSS animations
if (!$('#pumpkin-smash-styles').length) {
    $('head').append(`
        <style id="pumpkin-smash-styles">
            @keyframes pumpkinBounce {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.3) rotate(15deg); }
            }
            @keyframes pumpkinSmashFade {
                from { opacity: 0; transform: translate(-50%, -60%); }
                to { opacity: 1; transform: translate(-50%, -50%); }
            }
            .pumpkin-smash-btn:hover {
                transform: scale(1.05);
                box-shadow: 0 6px 20px rgba(255,107,0,0.6) !important;
            }
        </style>
    `);
}
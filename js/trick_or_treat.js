// js/trick_or_treat.js - Daily trick-or-treat roulette

function openTrickOrTreat() {
    if (currentUser.type !== 'user') {
        alert('Only registered users can spin the trick-or-treat wheel');
        return;
    }
    
    const modal = $(`
        <div class="modal fade" id="trickOrTreatModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); border: 2px solid #ff6b00; color: #fff;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title">🎃 Trick-or-Treat Roulette 🍬</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center" style="padding: 40px 20px;">
                        <div id="trickOrTreatWheel" style="font-size: 5rem; margin: 20px 0;">
                            🎃
                        </div>
                        <p style="color: #aaa; margin: 20px 0;">
                            Spin once per day for a chance at rewards!<br>
                            <strong style="color: #4CAF50;">70% chance</strong> for treats, <strong style="color: #ff6b00;">30% chance</strong> for tricks!
                        </p>
                        <button id="spinTrickOrTreat" class="btn btn-warning btn-lg" style="background: linear-gradient(135deg, #ff6b00, #ffa500); border: none; font-weight: bold; padding: 15px 40px;">
                            🎰 SPIN THE WHEEL!
                        </button>
                        <div id="trickOrTreatResult" style="margin-top: 20px; font-size: 1.2rem; min-height: 60px;"></div>
                    </div>
                </div>
            </div>
        </div>
    `);
    
    $('body').append(modal);
    const bsModal = new bootstrap.Modal(document.getElementById('trickOrTreatModal'));
    bsModal.show();
    
    $('#trickOrTreatModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
    
    $('#spinTrickOrTreat').on('click', spinTrickOrTreat);
}

function spinTrickOrTreat() {
    const btn = $('#spinTrickOrTreat');
    const wheel = $('#trickOrTreatWheel');
    const resultDiv = $('#trickOrTreatResult');
    
    btn.prop('disabled', true);
    resultDiv.html('');
    
    // Animate wheel
    const symbols = ['🎃', '🍬', '👻', '🍭', '💀', '🦇', '🧙', '🕷️'];
    let spinCount = 0;
    const spinInterval = setInterval(() => {
        wheel.html(symbols[spinCount % symbols.length]);
        spinCount++;
        
        if (spinCount >= 20) {
            clearInterval(spinInterval);
        }
    }, 100);
    
    $.ajax({
        url: 'api/trick_or_treat.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            setTimeout(() => {
                if (response.status === 'success') {
                    if (response.result === 'treat') {
                        wheel.html('🍬');
                        resultDiv.html(`
                            <div style="color: #4CAF50; font-weight: bold; font-size: 1.5rem;">
                                ${response.message}
                            </div>
                        `);
                        
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
                    } else {
                        wheel.html('👻');
                        resultDiv.html(`
                            <div style="color: #ff6b00; font-weight: bold; font-size: 1.3rem;">
                                ${response.message}
                            </div>
                            <div style="color: #aaa; font-size: 0.9rem; margin-top: 10px;">
                                Better luck tomorrow!
                            </div>
                        `);
                    }
                    
                    btn.html('Come back tomorrow! ⏰');
                } else if (response.status === 'cooldown') {
                    wheel.html('⏰');
                    resultDiv.html(`
                        <div style="color: #ffa500;">
                            ${response.message}
                        </div>
                    `);
                    btn.html('⏰ On Cooldown');
                } else {
                    wheel.html('❌');
                    resultDiv.html(`
                        <div style="color: #dc3545;">
                            ${response.message}
                        </div>
                    `);
                    btn.prop('disabled', false);
                }
            }, 2000);
        },
        error: function() {
            clearInterval(spinInterval);
            wheel.html('❌');
            resultDiv.html('<div style="color: #dc3545;">Error connecting to server</div>');
            btn.prop('disabled', false);
        }
    });
}

// Add button to shop or profile
$(document).ready(function() {
    if (typeof currentUser !== 'undefined' && currentUser.type === 'user') {
        // Add button to shop if it exists
        if ($('.shop-modal-content').length) {
            const trickOrTreatBtn = $(`
                <button class="btn btn-warning mb-3" onclick="openTrickOrTreat()" style="width: 100%; background: linear-gradient(135deg, #ff6b00, #ffa500); border: none; font-weight: bold;">
                    🎃 Daily Trick-or-Treat 🍬
                </button>
            `);
            $('.shop-modal-content').prepend(trickOrTreatBtn);
        }
    }
});
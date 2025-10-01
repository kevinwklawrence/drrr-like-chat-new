// Dura Currency System

// Get balance and auto-grant tokens
function checkAndGrantTokens() {
    if (currentUser.type !== 'user') return;
    
    $.ajax({
        url: 'api/tip.php',
        method: 'POST',
        data: { action: 'grant_tokens' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                currentUser.tokens = response.tokens;
                updateDuraDisplay();
            }
        }
    });
}

// Update balance from server
function updateDuraBalance() {
    if (currentUser.type !== 'user') return;
    
    $.ajax({
        url: 'api/tip.php',
        method: 'POST',
        data: { action: 'get_balance' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                currentUser.dura = response.dura;
                currentUser.tokens = response.tokens;
                updateDuraDisplay();
                updateShopBalances();
            }
        }
    });
}

// Update display
function updateDuraDisplay() {
    // This can be expanded to show in UI
}

// Show tip modal
function showTipModal(userId, username) {
    if (currentUser.type !== 'user') {
        alert('Only registered users can tip');
        return;
    }
    
    const modal = $(`
        <div class="modal fade" id="tipModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background: #2a2a2a; color: #fff; border: 1px solid #444;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title">Tip ${username}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <strong>Your Balance:</strong><br>
                            💎 Dura: ${currentUser.dura || 0}<br>
                            🎫 Tokens: ${currentUser.tokens || 0}
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tip Type:</label>
                            <select class="form-select" id="tipType" style="background: #333; color: #fff; border: 1px solid #555;">
                                <option value="token">Use Tokens (1 Token = 1 Dura)</option>
                                <option value="dura">Use Your Dura</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount:</label>
                            <input type="number" class="form-control" id="tipAmount" min="1" value="1" style="background: #333; color: #fff; border: 1px solid #555;">
                        </div>
                        <div id="tipError" class="text-danger mb-2" style="display: none;"></div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" onclick="sendTip(${userId}, '${username}')">Send Tip</button>
                    </div>
                </div>
            </div>
        </div>
    `);
    
    $('body').append(modal);
    const bsModal = new bootstrap.Modal(document.getElementById('tipModal'));
    bsModal.show();
    
    $('#tipModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

// Send tip
function sendTip(toUserId, username) {
    const amount = parseInt($('#tipAmount').val());
    const tipType = $('#tipType').val();
    
    if (!amount || amount <= 0) {
        $('#tipError').text('Invalid amount').show();
        return;
    }
    
    $.ajax({
        url: 'api/tip.php',
        method: 'POST',
        data: {
            action: 'tip',
            to_user_id: toUserId,
            amount: amount,
            type: tipType
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#tipModal').modal('hide');
                alert(`✓ Sent ${amount} ${tipType === 'token' ? 'Token' : 'Dura'} to ${username}!`);
                updateDuraBalance();
                updateShopBalances();
            } else {
                $('#tipError').text(response.message).show();
            }
        },
        error: function() {
            $('#tipError').text('Failed to send tip').show();
        }
    });
}

// Shop Functions
function purchaseShopItem(itemId, cost, currency) {
    if (currentUser.type !== 'user') {
        alert('Only registered users can purchase items');
        return;
    }
    
    // Check balance
    const balance = currency === 'dura' ? (currentUser.dura || 0) : (currentUser.tokens || 0);
    if (balance < cost) {
        alert(`Insufficient ${currency}! You need ${cost.toLocaleString()} but only have ${balance.toLocaleString()}.`);
        return;
    }
    
    // Confirm purchase
    if (!confirm(`Purchase ${itemId.replace('_', ' ').toUpperCase()} for ${cost.toLocaleString()} ${currency.toUpperCase()}?`)) {
        return;
    }
    
    $.ajax({
        url: 'api/shop.php',
        method: 'POST',
        data: {
            action: 'purchase',
            item_id: itemId,
            cost: cost,
            currency: currency
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('✓ Purchase successful!');
                updateDuraBalance();
                // Refresh shop display
                if ($('#shopDuraBalance').length) {
                    updateShopBalances();
                }
            } else {
                alert('Purchase failed: ' + response.message);
            }
        },
        error: function() {
            alert('Purchase failed. Please try again.');
        }
    });
}

function updateShopBalances() {
    if ($('#shopDuraBalance').length) {
        $('#shopDuraBalance').text((currentUser.dura || 0).toLocaleString());
    }
    if ($('#shopTokenBalance').length) {
        $('#shopTokenBalance').text((currentUser.tokens || 0).toLocaleString());
    }
}

// Initialize
$(document).ready(function() {
    if (currentUser && currentUser.type === 'user') {
        updateDuraBalance();
        checkAndGrantTokens();
        setInterval(checkAndGrantTokens, 3600000); // Check every hour
    }
});
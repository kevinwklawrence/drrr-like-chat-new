// Betting Pool System
let currentPool = null;
let poolUpdateInterval = null;

// Initialize betting pool system
function initBettingPool() {
    loadPoolInfo();

    // Poll for pool updates every 5 seconds
    if (poolUpdateInterval) {
        clearInterval(poolUpdateInterval);
    }
    poolUpdateInterval = setInterval(loadPoolInfo, 5000);
}

// Load current pool information
function loadPoolInfo() {
    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: { action: 'get_pool_info' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                if (response.has_pool) {
                    currentPool = response.pool;
                    currentPool.bets = response.bets;
                    currentPool.can_manage = response.can_manage;
                    currentPool.user_bet = response.user_bet;
                    updatePoolDisplay();
                } else {
                    currentPool = null;
                    hidePoolDisplay();
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading pool info:', error);
        }
    });
}

// Update pool display in UI
function updatePoolDisplay() {
    if (!currentPool) {
        hidePoolDisplay();
        return;
    }

    let poolHTML = `
        <div class="betting-pool-widget">
            <div class="pool-header">
                <h3><i class="fas fa-coins"></i> ${escapeHtml(currentPool.title)}</h3>
                ${currentPool.can_manage ? `
                    <div class="pool-actions">
                        <button class="btn btn-sm btn-success" onclick="showSelectWinnerModal()">
                            <i class="fas fa-trophy"></i> Select Winner
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="closePool()">
                            <i class="fas fa-times"></i> Close & Refund
                        </button>
                    </div>
                ` : ''}
            </div>
            ${currentPool.description ? `<p class="pool-description">${escapeHtml(currentPool.description)}</p>` : ''}
            <div class="pool-stats">
                <div class="pool-total">
                    <i class="fas fa-gem"></i> Total Pool: <strong>${currentPool.total_pool} Dura</strong>
                </div>
                <div class="pool-participants">
                    <i class="fas fa-users"></i> ${currentPool.bets.length} Participant${currentPool.bets.length !== 1 ? 's' : ''}
                </div>
            </div>
            ${currentPool.user_bet ? `
                <div class="user-bet-status">
                    <i class="fas fa-check-circle"></i> You bet ${currentPool.user_bet} Dura
                </div>
            ` : currentUser.type === 'user' ? `
                <div class="pool-bet-action">
                    <button class="btn btn-primary" onclick="showPlaceBetModal()">
                        <i class="fas fa-coins"></i> Place Bet
                    </button>
                </div>
            ` : ''}
        </div>
    `;

    // Insert or update pool widget
    let poolWidget = $('#betting-pool-widget');
    if (poolWidget.length === 0) {
        // Insert before chat messages
        $('#chatMessages').before(`<div id="betting-pool-widget">${poolHTML}</div>`);
    } else {
        poolWidget.html(poolHTML);
    }
}

// Hide pool display
function hidePoolDisplay() {
    $('#betting-pool-widget').remove();
}

// Show create pool modal
function showCreatePoolModal() {
    const modalHTML = `
        <div class="modal fade" id="createPoolModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-coins"></i> Create Betting Pool</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Pool Title *</label>
                            <input type="text" class="form-control" id="poolTitle" placeholder="e.g., Who will win the next round?" maxlength="255">
                        </div>
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea class="form-control" id="poolDescription" rows="3" placeholder="Additional details about this betting pool..."></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Users can place bets with their Dura. You can select a winner or close the pool to refund all bets.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="createPool()">Create Pool</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    $('#createPoolModal').remove();
    $('body').append(modalHTML);
    $('#createPoolModal').modal('show');
}

// Create pool
function createPool() {
    const title = $('#poolTitle').val().trim();
    const description = $('#poolDescription').val().trim();

    if (!title) {
        showNotification('error', 'Please enter a pool title');
        return;
    }

    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: {
            action: 'create_pool',
            title: title,
            description: description
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showNotification('success', response.message);
                $('#createPoolModal').modal('hide');
                loadPoolInfo();
            } else {
                showNotification('error', response.message);
            }
        },
        error: function(xhr, status, error) {
            showNotification('error', 'Error creating pool: ' + error);
        }
    });
}

// Show place bet modal
function showPlaceBetModal() {
    const modalHTML = `
        <div class="modal fade" id="placeBetModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-coins"></i> Place Bet</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Betting on: <strong>${escapeHtml(currentPool.title)}</strong>
                        </div>
                        <div class="form-group">
                            <label>Bet Amount (Dura)</label>
                            <input type="number" class="form-control" id="betAmount" min="1" placeholder="Enter amount">
                            <small class="form-text text-muted">Your balance: ${currentUser.dura || 0} Dura</small>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You can only bet once per pool. The winner will receive the entire pool.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="placeBet()">Place Bet</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    $('#placeBetModal').remove();
    $('body').append(modalHTML);
    $('#placeBetModal').modal('show');
}

// Place bet
function placeBet() {
    const amount = parseInt($('#betAmount').val());

    if (!amount || amount <= 0) {
        showNotification('error', 'Please enter a valid bet amount');
        return;
    }

    if (amount > (currentUser.dura || 0)) {
        showNotification('error', 'Not enough Dura');
        return;
    }

    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: {
            action: 'place_bet',
            amount: amount
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showNotification('success', response.message);
                $('#placeBetModal').modal('hide');

                // Update current user's balance
                if (currentUser.type === 'user') {
                    currentUser.dura = response.new_balance;
                    updateDuraDisplay();
                }

                loadPoolInfo();
                loadUsers(); // Refresh user list to show bet badge
            } else {
                showNotification('error', response.message);
            }
        },
        error: function(xhr, status, error) {
            showNotification('error', 'Error placing bet: ' + error);
        }
    });
}

// Show select winner modal
function showSelectWinnerModal() {
    if (!currentPool || !currentPool.bets || currentPool.bets.length === 0) {
        showNotification('error', 'No bets have been placed yet');
        return;
    }

    let betsListHTML = '';
    currentPool.bets.forEach(bet => {
        betsListHTML += `
            <div class="bet-item" onclick="selectWinner('${bet.user_id_string}', '${escapeHtml(bet.username)}')">
                <div class="bet-user">
                    <i class="fas fa-user"></i> ${escapeHtml(bet.username)}
                </div>
                <div class="bet-amount">
                    <i class="fas fa-coins"></i> ${bet.bet_amount} Dura
                </div>
            </div>
        `;
    });

    const modalHTML = `
        <div class="modal fade" id="selectWinnerModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trophy"></i> Select Winner</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success">
                            <i class="fas fa-gem"></i> Total Pool: <strong>${currentPool.total_pool} Dura</strong>
                        </div>
                        <p>Click on a participant to select them as the winner:</p>
                        <div class="bets-list">
                            ${betsListHTML}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    $('#selectWinnerModal').remove();
    $('body').append(modalHTML);
    $('#selectWinnerModal').modal('show');
}

// Select winner
function selectWinner(userIdString, username) {
    if (!confirm(`Select ${username} as the winner? They will receive ${currentPool.total_pool} Dura.`)) {
        return;
    }

    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: {
            action: 'select_winner',
            winner_user_id_string: userIdString
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showNotification('success', response.message);
                $('#selectWinnerModal').modal('hide');
                loadPoolInfo();
                loadUsers(); // Refresh user list to remove bet badges
            } else {
                showNotification('error', response.message);
            }
        },
        error: function(xhr, status, error) {
            showNotification('error', 'Error selecting winner: ' + error);
        }
    });
}

// Close pool and refund
function closePool() {
    if (!confirm('Close this betting pool? All bets will be refunded.')) {
        return;
    }

    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: { action: 'close_pool' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showNotification('success', response.message);
                loadPoolInfo();
                loadUsers(); // Refresh user list to remove bet badges
            } else {
                showNotification('error', response.message);
            }
        },
        error: function(xhr, status, error) {
            showNotification('error', 'Error closing pool: ' + error);
        }
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Clean up on page unload
$(window).on('beforeunload', function() {
    if (poolUpdateInterval) {
        clearInterval(poolUpdateInterval);
    }
});

// Betting Pool System - Modal Version with Floating Button
let currentPool = null;
let poolUpdateInterval = null;
let isModalOpen = false;

// Initialize betting pool system
function initBettingPool() {
    console.log('🎰 Initializing betting pool system (modal version)...');
    loadPoolInfo();

    // Poll for pool updates every 10 seconds
    if (poolUpdateInterval) {
        clearInterval(poolUpdateInterval);
    }
    poolUpdateInterval = setInterval(() => {
        if (!isModalOpen) {
            loadPoolInfo();
        }
    }, 10000);
}

// Load current pool information
function loadPoolInfo() {
    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: { action: 'get_pool_info' },
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            console.log('📊 Pool info response:', response);
            if (response.status === 'success') {
                if (response.has_pool) {
                    currentPool = response.pool;
                    currentPool.bets = response.bets;
                    currentPool.options = response.options || [];
                    currentPool.can_manage = response.can_manage;
                    currentPool.user_bet = response.user_bet;
                    console.log('✅ Active pool found:', currentPool.title);
                    showPoolButton();
                } else {
                    console.log('ℹ️ No active pool');
                    currentPool = null;
                    hidePoolButton();
                }
            } else {
                console.error('❌ Pool info error:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Pool info AJAX error:', error);
        }
    });
}

// Show floating pool button
function showPoolButton() {
    if ($('#poolFloatingBtn').length > 0) {
        // Update existing button
        updatePoolButton();
        return;
    }

    const buttonHTML = `
        <button id="poolFloatingBtn" class="pool-floating-btn" onclick="showPoolModal()" title="View Betting Pool">
            <i class="fas fa-coins"></i>
            <span class="pool-badge">${currentPool.bets.length}</span>
            ${currentPool.user_bet ? '<span class="pool-bet-indicator">✓</span>' : ''}
        </button>
    `;

    $('body').append(buttonHTML);
}

// Update floating button
function updatePoolButton() {
    const btn = $('#poolFloatingBtn');
    if (btn.length > 0) {
        btn.find('.pool-badge').text(currentPool.bets.length);
        
        if (currentPool.user_bet && btn.find('.pool-bet-indicator').length === 0) {
            btn.append('<span class="pool-bet-indicator">✓</span>');
        } else if (!currentPool.user_bet) {
            btn.find('.pool-bet-indicator').remove();
        }
    }
}

// Hide floating button
function hidePoolButton() {
    $('#poolFloatingBtn').remove();
}

// Show pool modal
function showPoolModal() {
    if (!currentPool) {
        console.error('No current pool');
        return;
    }

    console.log('📊 Opening pool modal...');

    const canBet = currentUser && currentUser.type === 'user' && !currentPool.user_bet;
    const alreadyBet = currentPool.user_bet;
    const hasOptions = currentPool.options && currentPool.options.length > 0;

    let optionsSection = '';
    if (hasOptions) {
        optionsSection = `
            <div class="pool-options-section">
                <h6><i class="fas fa-list-ul"></i> Options</h6>
                <div class="pool-options-grid">
                    ${currentPool.options.map(opt => `
                        <div class="pool-option-card">
                            <div class="option-text">${escapeHtml(opt.text)}</div>
                            <div class="option-bets">${opt.total_bets} Dura</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    const modalHTML = `
        <div class="modal fade" id="poolViewModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content pool-modal-content">
                    <div class="modal-header pool-modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-coins"></i> ${escapeHtml(currentPool.title)}
                        </h5>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        ${currentPool.description ? `
                            <div class="pool-description-box">
                                <i class="fas fa-info-circle"></i> ${escapeHtml(currentPool.description)}
                            </div>
                        ` : ''}
                        
                        <div class="pool-stats-grid">
                            <div class="pool-stat-card">
                                <i class="fas fa-gem"></i>
                                <div class="pool-stat-label">Total Pool</div>
                                <div class="pool-stat-value">${currentPool.total_pool} Dura</div>
                            </div>
                            <div class="pool-stat-card">
                                <i class="fas fa-users"></i>
                                <div class="pool-stat-label">Participants</div>
                                <div class="pool-stat-value">${currentPool.bets.length}</div>
                            </div>
                            ${alreadyBet ? `
                                <div class="pool-stat-card pool-stat-highlight">
                                    <i class="fas fa-check-circle"></i>
                                    <div class="pool-stat-label">Your Bet</div>
                                    <div class="pool-stat-value">${currentPool.user_bet} Dura</div>
                                </div>
                            ` : ''}
                        </div>

                        ${optionsSection}

                        ${currentPool.bets.length > 0 ? `
                            <div class="pool-participants-section">
                                <h6><i class="fas fa-list"></i> Participants</h6>
                                <div class="pool-participants-list">
                                    ${currentPool.bets.map(bet => `
                                        <div class="pool-participant-item">
                                            <span class="participant-name">
                                                <i class="fas fa-user"></i> ${escapeHtml(bet.username)}
                                            </span>
                                            <span class="participant-bet">
                                                <i class="fas fa-coins"></i> ${bet.bet_amount} Dura
                                            </span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : `
                            <div class="pool-empty-state">
                                <i class="fas fa-users-slash"></i>
                                <p>No bets placed yet. Be the first!</p>
                            </div>
                        `}

                        ${canBet ? `
                            <div class="pool-bet-section">
                                <button class="btn btn-primary btn-block btn-lg" onclick="showPlaceBetModal()">
                                    <i class="fas fa-coins"></i> Place Your Bet
                                </button>
                            </div>
                        ` : ''}
                    </div>
                    ${currentPool.can_manage ? `
                        <div class="modal-footer pool-modal-footer">
                            <button class="btn btn-success" onclick="showSelectWinnerModal()">
                                <i class="fas fa-trophy"></i> Select Winner
                            </button>
                            <button class="btn btn-danger" onclick="closePool()">
                                <i class="fas fa-times"></i> Close & Refund
                            </button>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;

    $('#poolViewModal').remove();
    $('body').append(modalHTML);
    isModalOpen = true;
    
    $('#poolViewModal').modal({
        backdrop: true,
        keyboard: true
    });
    
    $('#poolViewModal').on('hidden.bs.modal', function() {
        console.log('Pool modal closed');
        isModalOpen = false;
        $(this).remove();
    });
    
    $('#poolViewModal').modal('show');
}

// Show create pool modal
function showCreatePoolModal() {
    console.log('📝 Opening create pool modal...');
    
    const modalHTML = `
        <div class="modal fade" id="createPoolModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-coins"></i> Create Betting Pool</h5>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Pool Title *</label>
                            <input type="text" class="form-control" id="poolTitle" placeholder="e.g., Who will win the race?" maxlength="255">
                        </div>
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea class="form-control" id="poolDescription" rows="2" placeholder="Additional details..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Minimum Bet (Optional)</label>
                            <input type="number" class="form-control" id="poolMinBet" placeholder="0" min="0" value="0">
                            <small class="form-text text-muted">Leave 0 for no minimum</small>
                        </div>
                        <div class="form-group">
                            <label>Options (Optional)</label>
                            <small class="form-text text-muted">Leave empty for free-bet pool. Add options for choice-based betting.</small>
                            <div id="optionsList" style="margin-top: 10px;">
                                <input type="text" class="form-control mb-2 pool-option" placeholder="Option 1 (e.g., Red)" maxlength="255">
                                <input type="text" class="form-control mb-2 pool-option" placeholder="Option 2 (e.g., Blue)" maxlength="255">
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addOptionField()">
                                <i class="fas fa-plus"></i> Add Option
                            </button>
                        </div>
                        <div id="createPoolError" class="alert alert-danger" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="createPoolBtn" onclick="createPool()">Create Pool</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#createPoolModal').remove();
    $('body').append(modalHTML);
    isModalOpen = true;
    
    $('#createPoolModal').modal({
        backdrop: true,
        keyboard: true
    });
    
    $('#createPoolModal').on('hidden.bs.modal', function() {
        console.log('Create pool modal closed');
        isModalOpen = false;
        $(this).remove();
    });
    
    $('#createPoolModal').modal('show');
}

// Add option field
function addOptionField() {
    const count = $('.pool-option').length + 1;
    $('#optionsList').append(`<input type="text" class="form-control mb-2 pool-option" placeholder="Option ${count}" maxlength="255">`);
}

// Create pool
function createPool() {
    console.log('Creating pool...');
    
    const title = $('#poolTitle').val().trim();
    const description = $('#poolDescription').val().trim();
    const minBet = parseInt($('#poolMinBet').val()) || 0;
    const errorDiv = $('#createPoolError');

    if (!title) {
        errorDiv.text('Please enter a pool title').show();
        return;
    }

    // Collect options
    const options = [];
    $('.pool-option').each(function() {
        const opt = $(this).val().trim();
        if (opt) options.push(opt);
    });

    console.log('Collected options:', options);
    console.log('Min bet:', minBet);

    const btn = $('#createPoolBtn');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');
    errorDiv.hide();

    const postData = {
        action: 'create_pool',
        title: title,
        description: description,
        min_bet: minBet,
        options: JSON.stringify(options)
    };
    
    console.log('Sending data:', postData);
    console.log('Options JSON string:', JSON.stringify(options));

    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: postData,
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            console.log('Create pool response:', response);
            
            if (response.status === 'success') {
                if (typeof showNotification === 'function') {
                    showNotification('success', response.message || 'Betting pool created');
                }
                $('#createPoolModal').modal('hide');
                setTimeout(() => loadPoolInfo(), 500);
            } else {
                errorDiv.text(response.message || 'Failed to create pool').show();
                btn.prop('disabled', false).html('Create Pool');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error creating pool:', error, xhr.responseText);
            let errorMsg = 'Error creating pool';
            try {
                const resp = JSON.parse(xhr.responseText);
                if (resp.message) errorMsg = resp.message;
            } catch (e) {
                errorMsg += ': ' + error;
            }
            errorDiv.text(errorMsg).show();
            btn.prop('disabled', false).html('Create Pool');
        }
    });
}

// Show place bet modal
function showPlaceBetModal() {
    console.log('💰 Opening place bet modal...');
    console.log('Current pool:', currentPool);
    
    // Close pool view modal if open
    $('#poolViewModal').modal('hide');
    
    if (!currentPool) {
        console.error('No current pool!');
        if (typeof showNotification === 'function') {
            showNotification('error', 'No active betting pool');
        }
        return;
    }

    const hasOptions = currentPool.options && currentPool.options.length > 0;
    const minBet = currentPool.min_bet || 0;
    
    console.log('Has options:', hasOptions);
    console.log('Options:', currentPool.options);
    console.log('Min bet:', minBet);
    
    let optionsHTML = '';
    if (hasOptions) {
        optionsHTML = `
            <div class="form-group">
                <label>Select Option *</label>
                <select class="form-control" id="betOption">
                    <option value="">-- Choose an option --</option>
                    ${currentPool.options.map(opt => `
                        <option value="${opt.id}">${escapeHtml(opt.text)} (${opt.total_bets} Dura)</option>
                    `).join('')}
                </select>
            </div>
        `;
    }
    
    const modalHTML = `
        <div class="modal fade" id="placeBetModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-coins"></i> Place Bet</h5>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Betting on: <strong>${escapeHtml(currentPool.title)}</strong>
                        </div>
                        ${optionsHTML}
                        <div class="form-group">
                            <label>Bet Amount (Dura)</label>
                            <input type="number" class="form-control" id="betAmount" min="${minBet || 1}" placeholder="${minBet > 0 ? 'Min: ' + minBet : 'Enter amount'}">
                            <small class="form-text text-muted">Your balance: ${(currentUser && currentUser.dura) || 0} Dura${minBet > 0 ? ' | Min bet: ' + minBet : ''}</small>
                        </div>
                        <div id="placeBetError" class="alert alert-danger" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="placeBetBtn" onclick="placeBet()">Place Bet</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#placeBetModal').remove();
    $('body').append(modalHTML);
    isModalOpen = true;
    
    $('#placeBetModal').modal({
        backdrop: true,
        keyboard: true
    });
    
    $('#placeBetModal').on('hidden.bs.modal', function() {
        console.log('Place bet modal closed');
        isModalOpen = false;
        $(this).remove();
    });
    
    $('#placeBetModal').modal('show');
}

// Place bet
function placeBet() {
    console.log('Placing bet...');
    
    const amount = parseInt($('#betAmount').val());
    const optionId = $('#betOption').val() ? parseInt($('#betOption').val()) : null;
    const errorDiv = $('#placeBetError');
    const hasOptions = currentPool.options && currentPool.options.length > 0;

    if (!amount || amount <= 0) {
        errorDiv.text('Please enter a valid bet amount').show();
        return;
    }

    if (hasOptions && !optionId) {
        errorDiv.text('Please select an option').show();
        return;
    }

    if (currentUser && amount > (currentUser.dura || 0)) {
        errorDiv.text('Not enough Dura').show();
        return;
    }

    const btn = $('#placeBetBtn');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Placing...');
    errorDiv.hide();

    const data = {
        action: 'place_bet',
        amount: amount
    };
    if (optionId) data.option_id = optionId;

    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            console.log('Place bet response:', response);
            
            if (response.status === 'success') {
                if (typeof showNotification === 'function') {
                    showNotification('success', response.message || 'Bet placed successfully');
                }
                $('#placeBetModal').modal('hide');

                if (currentUser && currentUser.type === 'user' && response.new_balance !== undefined) {
                    currentUser.dura = response.new_balance;
                    if (typeof updateDuraDisplay === 'function') {
                        updateDuraDisplay();
                    }
                }

                setTimeout(() => {
                    loadPoolInfo();
                    if (typeof loadUsers === 'function') {
                        loadUsers();
                    }
                }, 500);
            } else {
                errorDiv.text(response.message || 'Failed to place bet').show();
                btn.prop('disabled', false).html('Place Bet');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error placing bet:', error, xhr.responseText);
            let errorMsg = 'Error placing bet';
            try {
                const resp = JSON.parse(xhr.responseText);
                if (resp.message) errorMsg = resp.message;
            } catch (e) {
                errorMsg += ': ' + error;
            }
            errorDiv.text(errorMsg).show();
            btn.prop('disabled', false).html('Place Bet');
        }
    });
}

// Show select winner modal
function showSelectWinnerModal() {
    // Close pool view modal if open
    $('#poolViewModal').modal('hide');
    
    if (!currentPool || !currentPool.bets || currentPool.bets.length === 0) {
        if (typeof showNotification === 'function') {
            showNotification('error', 'No bets have been placed yet');
        } else {
            alert('No bets have been placed yet');
        }
        return;
    }

    console.log('Opening select winner modal...');

    const hasOptions = currentPool.options && currentPool.options.length > 0;
    let contentHTML = '';
    
    if (hasOptions) {
        // OPTION-BASED POOL: Show options to select
        contentHTML = '<p>Click on an option to select it as the winner. All users who bet on this option will split the pool proportionally:</p><div class="options-list">';
        currentPool.options.forEach(opt => {
            const safeOptionId = opt.id;
            const safeOptionText = escapeHtml(opt.text);
            contentHTML += `
                <div class="option-item" data-option-id="${safeOptionId}" data-option-text="${safeOptionText}">
                    <div class="option-name">
                        <i class="fas fa-trophy"></i> ${safeOptionText}
                    </div>
                    <div class="option-details">
                        <i class="fas fa-coins"></i> ${opt.total_bets} Dura bet
                    </div>
                </div>
            `;
        });
        contentHTML += '</div>';
    } else {
        // TRADITIONAL POOL: Show users to select
        contentHTML = '<p>Click on a participant to select them as the winner:</p><div class="bets-list">';
        currentPool.bets.forEach(bet => {
            const safeUserId = escapeHtml(bet.user_id_string);
            const safeUsername = escapeHtml(bet.username);
            contentHTML += `
                <div class="bet-item" data-user-id="${safeUserId}" data-username="${safeUsername}">
                    <div class="bet-user">
                        <i class="fas fa-user"></i> ${safeUsername}
                    </div>
                    <div class="bet-amount">
                        <i class="fas fa-coins"></i> ${bet.bet_amount} Dura
                    </div>
                </div>
            `;
        });
        contentHTML += '</div>';
    }

    const modalHTML = `
        <div class="modal fade" id="selectWinnerModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trophy"></i> Select Winner</h5>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success">
                            <i class="fas fa-gem"></i> Total Pool: <strong>${currentPool.total_pool} Dura</strong>
                        </div>
                        ${contentHTML}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#selectWinnerModal').remove();
    $('body').append(modalHTML);
    isModalOpen = true;
    
    if (hasOptions) {
        $(document).on('click', '#selectWinnerModal .option-item', function() {
            const optionId = $(this).data('option-id');
            const optionText = $(this).data('option-text');
            selectWinningOption(optionId, optionText);
        });
    } else {
        $(document).on('click', '#selectWinnerModal .bet-item', function() {
            const userIdString = $(this).data('user-id');
            const username = $(this).data('username');
            selectWinner(userIdString, username);
        });
    }
    
    $('#selectWinnerModal').modal({
        backdrop: true,
        keyboard: true
    });
    
    $('#selectWinnerModal').on('hidden.bs.modal', function() {
        console.log('Select winner modal closed');
        isModalOpen = false;
        $(document).off('click', '#selectWinnerModal .bet-item');
        $(document).off('click', '#selectWinnerModal .option-item');
        $(this).remove();
    });
    
    $('#selectWinnerModal').modal('show');
}

// Select winner
function selectWinner(userIdString, username) {
    console.log('Selecting winner:', username);
    
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
        timeout: 10000,
        success: function(response) {
            console.log('Select winner response:', response);
            
            if (response.status === 'success') {
                if (typeof showNotification === 'function') {
                    showNotification('success', response.message || 'Winner selected');
                }
                $('#selectWinnerModal').modal('hide');
                setTimeout(() => {
                    loadPoolInfo();
                    if (typeof loadUsers === 'function') {
                        loadUsers();
                    }
                }, 500);
            } else {
                if (typeof showNotification === 'function') {
                    showNotification('error', response.message || 'Failed to select winner');
                } else {
                    alert(response.message || 'Failed to select winner');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error selecting winner:', error, xhr.responseText);
            if (typeof showNotification === 'function') {
                showNotification('error', 'Error selecting winner: ' + error);
            } else {
                alert('Error selecting winner: ' + error);
            }
        }
    });
}

// Select winning option (for option-based pools)
function selectWinningOption(optionId, optionText) {
    console.log('Selecting winning option:', optionText);
    
    if (!confirm(`Select "${optionText}" as the winning option? All users who bet on this option will split the ${currentPool.total_pool} Dura pool proportionally.`)) {
        return;
    }

    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: {
            action: 'select_winner',
            winner_option_id: optionId
        },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            console.log('Select winning option response:', response);
            
            if (response.status === 'success') {
                if (typeof showNotification === 'function') {
                    showNotification('success', response.message || 'Winners awarded');
                }
                $('#selectWinnerModal').modal('hide');
                setTimeout(() => {
                    loadPoolInfo();
                    if (typeof loadUsers === 'function') {
                        loadUsers();
                    }
                }, 500);
            } else {
                if (typeof showNotification === 'function') {
                    showNotification('error', response.message || 'Failed to select winner');
                } else {
                    alert(response.message || 'Failed to select winner');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error selecting winning option:', error, xhr.responseText);
            if (typeof showNotification === 'function') {
                showNotification('error', 'Error selecting winner: ' + error);
            } else {
                alert('Error selecting winner: ' + error);
            }
        }
    });
}

// Close pool and refund
function closePool() {
    // Close pool view modal if open
    $('#poolViewModal').modal('hide');
    
    console.log('Closing pool...');
    
    if (!confirm('Close this betting pool? All bets will be refunded.')) {
        return;
    }

    $.ajax({
        url: 'api/betting_pool.php',
        method: 'POST',
        data: { action: 'close_pool' },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            console.log('Close pool response:', response);
            
            if (response.status === 'success') {
                if (typeof showNotification === 'function') {
                    showNotification('success', response.message || 'Pool closed and refunded');
                }
                setTimeout(() => {
                    loadPoolInfo();
                    if (typeof loadUsers === 'function') {
                        loadUsers();
                    }
                }, 500);
            } else {
                if (typeof showNotification === 'function') {
                    showNotification('error', response.message || 'Failed to close pool');
                } else {
                    alert(response.message || 'Failed to close pool');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error closing pool:', error, xhr.responseText);
            if (typeof showNotification === 'function') {
                showNotification('error', 'Error closing pool: ' + error);
            } else {
                alert('Error closing pool: ' + error);
            }
        }
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Clean up on page unload
$(window).on('beforeunload', function() {
    if (poolUpdateInterval) {
        clearInterval(poolUpdateInterval);
    }
});

// Close any modals on escape key
$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && isModalOpen) {
        $('.modal').modal('hide');
    }
});
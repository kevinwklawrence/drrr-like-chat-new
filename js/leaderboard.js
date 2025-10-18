// Leaderboard Modal System

function showLeaderboardModal() {
    // Remove existing modal
    $('#leaderboardModal').remove();
    
    const modalHtml = `
        <div class="modal fade" id="leaderboardModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="background: #333; border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-trophy"></i> Leaderboards
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-3" id="leaderboardTabs" role="tablist" style="border-bottom: 1px solid #444;">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="dura-tab" data-bs-toggle="tab" 
                                        data-bs-target="#duraLeaderboard" type="button" role="tab">
                                    <i class="fas fa-gem"></i> Top Dura
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="event-tab" data-bs-toggle="tab" 
                                        data-bs-target="#eventLeaderboard" type="button" role="tab">
                                    <i class="fas fa-calendar-star"></i> Top Event Currency
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="leaderboardTabContent">
                            <div class="tab-pane fade show active" id="duraLeaderboard" role="tabpanel">
                                <div id="duraLeaderboardContent" class="text-center p-3">
                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                </div>
                            </div>
                            <div class="tab-pane fade" id="eventLeaderboard" role="tabpanel">
                                <div id="eventLeaderboardContent" class="text-center p-3">
                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add CSS
    if ($('#leaderboardCSS').length === 0) {
        const css = `
            <style id="leaderboardCSS">
            #leaderboardModal .nav-link {
                color: #fff;
                background: transparent;
                border: none;
                border-bottom: 2px solid transparent;
            }
            
            #leaderboardModal .nav-link.active {
                background: transparent !important;
                border-bottom-color: #ffc107;
                color: #ffc107 !important;
            }
            
            #leaderboardModal .nav-link:hover {
                background: rgba(255, 193, 7, 0.1);
                border-bottom-color: #ffc107;
            }
            
            .leaderboard-item {
                background: #1a1a1a;
                padding: 12px;
                margin-bottom: 8px;
                border-radius: 6px;
                border-left: 3px solid #444;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .leaderboard-item.rank-1 {
                border-left-color: #ffd700;
                background: linear-gradient(90deg, rgba(255,215,0,0.1) 0%, rgba(26,26,26,1) 100%);
            }
            
            .leaderboard-item.rank-2 {
                border-left-color: #c0c0c0;
                background: linear-gradient(90deg, rgba(192,192,192,0.1) 0%, rgba(26,26,26,1) 100%);
            }
            
            .leaderboard-item.rank-3 {
                border-left-color: #cd7f32;
                background: linear-gradient(90deg, rgba(205,127,50,0.1) 0%, rgba(26,26,26,1) 100%);
            }
            
            .leaderboard-rank {
                font-weight: bold;
                font-size: 1.2rem;
                min-width: 40px;
            }
            
            .rank-1 .leaderboard-rank { color: #ffd700; }
            .rank-2 .leaderboard-rank { color: #c0c0c0; }
            .rank-3 .leaderboard-rank { color: #cd7f32; }
            
            .leaderboard-user {
                flex: 1;
                margin: 0 15px;
            }
            
            .leaderboard-username {
                font-weight: 600;
                color: #fff;
                margin-bottom: 2px;
            }
            
            .leaderboard-amount {
                font-size: 1.1rem;
                font-weight: bold;
                color: #4caf50;
            }
            </style>
        `;
        $('head').append(css);
    }

    $('body').append(modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('leaderboardModal'));
    modal.show();
    
    // Load leaderboards
    loadDuraLeaderboard();
    
    // Load event leaderboard when tab is clicked
    $('#event-tab').on('click', function() {
        if ($('#eventLeaderboardContent').html().includes('fa-spinner')) {
            loadEventLeaderboard();
        }
    });
}

function loadDuraLeaderboard() {
    $.ajax({
        url: 'api/leaderboard.php',
        method: 'POST',
        data: { type: 'dura' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayLeaderboard(response.data, 'dura', '#duraLeaderboardContent');
            } else {
                $('#duraLeaderboardContent').html(`
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Failed to load leaderboard
                    </div>
                `);
            }
        },
        error: function() {
            $('#duraLeaderboardContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle"></i> Error loading leaderboard
                </div>
            `);
        }
    });
}

function loadEventLeaderboard() {
    $.ajax({
        url: 'api/leaderboard.php',
        method: 'POST',
        data: { type: 'event_currency' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayLeaderboard(response.data, 'event_currency', '#eventLeaderboardContent');
            } else {
                $('#eventLeaderboardContent').html(`
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Failed to load leaderboard
                    </div>
                `);
            }
        },
        error: function() {
            $('#eventLeaderboardContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle"></i> Error loading leaderboard
                </div>
            `);
        }
    });
}

function displayLeaderboard(data, type, containerSelector) {
    if (data.length === 0) {
        $(containerSelector).html(`
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No data available yet
            </div>
        `);
        return;
    }

    const icon = type === 'dura' ? '💎' : '🎃';
    
    let html = '';
    data.forEach((user, index) => {
        const rank = index + 1;
        const rankClass = rank <= 3 ? `rank-${rank}` : '';
        const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : '';
        
        html += `
            <div class="leaderboard-item ${rankClass}">
                <div class="leaderboard-rank">${medal || rank}</div>
                <div class="leaderboard-user">
                    <div class="leaderboard-username">${escapeHtml(user.username)}</div>
                </div>
                <div class="leaderboard-amount">${icon} ${user.amount.toLocaleString()}</div>
            </div>
        `;
    });
    
    $(containerSelector).html(html);
}

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
// ===== DEBUG CONFIGURATION =====
const DEBUG_MODE = false;
const SHOW_SENSITIVE_DATA = false;

function debugLog(message, data = null) {
    if (DEBUG_MODE) {
        if (data !== null) {
            console.log('[ROOM]', message, data);
        } else {
            console.log('[ROOM]', message);
        }
    }
}

function debugError(message, error = null) {
    if (DEBUG_MODE) {
        if (error !== null) {
            console.error('[ROOM]', message, error);
        } else {
            console.error('[ROOM]', message);
        }
    }
}

function criticalError(message, error = null) {
    if (error !== null) {
        console.error('[CRITICAL]', message, error);
    } else {
        console.error('[CRITICAL]', message);
    }
}

// ===== GLOBAL VARIABLES =====

// Kick Detection System
let kickDetectionInterval;
let userKickedModalShown = false;
let kickDetectionEnabled = true;
let lastStatusCheck = 0;
let consecutiveErrors = 0;

// Activity Tracking System
let activityInterval = null;
let disconnectCheckInterval = null;
let lastActivityUpdate = 0;
let userIsActive = true;
let activityTrackingEnabled = false;

// Message System
let lastScrollTop = 0;
let lastMessageCount = 0;
let userIsScrolling = false;

// YouTube Player System
let youtubePlayer = null;
let youtubePlayerReady = false;
let youtubeEnabled = false;
let isYoutubeHost = false;
let playerHidden = false;
let lastSyncToken = null;
let playerSyncInterval = null;
let queueUpdateInterval = null;
let currentVideoData = null;
let playerQueue = [];
let playerSuggestions = [];
let youtubeAPIReady = false;

// ===== MESSAGE FUNCTIONS =====
function sendMessage() {
    const messageInput = $('#message');
    const message = messageInput.val().trim();
    
    if (!message) {
        messageInput.focus();
        return false;
    }
    
    debugLog('💬 Sending message:', message);
    
    const sendBtn = $('.btn-send-message');
    const originalText = sendBtn.html();
    sendBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
    
    updateUserActivity('message_send');
    
    $.ajax({
        url: 'api/send_message.php',
        method: 'POST',
        data: {
            room_id: roomId,
            message: message
        },
        success: function(response) {
            try {
                let res = JSON.parse(response);
                if (res.status === 'success') {
                    messageInput.val('');
                    loadMessages();
                    
                    setTimeout(() => {
                        checkUserStatus();
                    }, 200);
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (e) {
                console.error('JSON parse error:', e, response);
                alert('Invalid response from server');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in sendMessage:', status, error, xhr.responseText);
            checkUserStatus();
            alert('AJAX error: ' + error);
        },
        complete: function() {
            sendBtn.prop('disabled', false).html(originalText);
            messageInput.focus();
        }
    });
    
    return false;
}

function loadMessages() {
    debugLog('Loading messages for roomId:', roomId);
    $.ajax({
        url: 'api/get_messages.php',
        method: 'GET',
        data: { room_id: roomId },
        success: function(response) {
            debugLog('Response from api/get_messages.php:', response);
            try {
                let messages = JSON.parse(response);
                let html = '';
                
                if (!Array.isArray(messages)) {
                    console.error('Expected array from get_messages, got:', messages);
                    html = '<div class="empty-chat"><i class="fas fa-exclamation-triangle"></i><h5>Error loading messages</h5><p>Please try refreshing the page</p></div>';
                } else if (messages.length === 0) {
                    html = '<div class="empty-chat"><i class="fas fa-comments"></i><h5>No messages yet</h5><p>Start the conversation!</p></div>';
                } else {
                    messages.forEach(msg => {
                        html += renderMessage(msg);
                    });
                }
                
                const chatbox = $('#chatbox');
                const isAtBottom = chatbox.scrollTop() + chatbox.innerHeight() >= chatbox[0].scrollHeight - 20;
                const newMessageCount = messages.length;
                
                chatbox.html(html);
                
                if (isAtBottom || (newMessageCount > lastMessageCount && !userIsScrolling)) {
                    chatbox.scrollTop(chatbox[0].scrollHeight);
                }
                
                lastMessageCount = newMessageCount;
            } catch (e) {
                console.error('JSON parse error:', e, response);
                $('#chatbox').html('<div class="empty-chat"><i class="fas fa-exclamation-triangle"></i><h5>Error loading messages</h5><p>Failed to parse server response</p></div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in loadMessages:', status, error, xhr.responseText);
            $('#chatbox').html('<div class="empty-chat"><i class="fas fa-wifi"></i><h5>Connection Error</h5><p>Failed to load messages. Check your connection.</p></div>');
        }
    });
}

function getUserColor(msg) {
    if (msg && msg.color) {
        return `user-color-${msg.color}`;
    }
    
    if (msg && msg.user_color) {
        return `user-color-${msg.user_color}`;
    }
    
    return 'user-color-blue';
}

function renderMessage(msg) {
    const avatar = msg.avatar || msg.guest_avatar || 'default_avatar.jpg';
    const name = msg.username || msg.guest_name || 'Unknown';
    const userIdString = msg.user_id_string || msg.user_id || 'unknown';
    
    if (msg.type === 'system' || msg.is_system) {
        return `
            <div class="system-message">
                <img src="images/${avatar}" alt="System">
                <span>${msg.message}</span>
            </div>
        `;
    }
    
    const userColorClass = getUserColor(msg);
    
    const timestamp = new Date(msg.timestamp).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    let badges = '';
    if (msg.is_admin) {
        badges += '<span class="user-badge badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
    }
    if (msg.is_host) {
        badges += '<span class="user-badge badge-host"><i class="fas fa-crown"></i> Host</span>';
    }
    if (msg.user_id && !msg.is_admin) {
        badges += '<span class="user-badge badge-verified"><i class="fas fa-check-circle"></i> Verified</span>';
    } else if (!msg.user_id) {
        badges += '<span class="user-badge badge-guest"><i class="fas fa-user"></i> Guest</span>';
    }
    
    let adminInfo = '';
    if (isAdmin && msg.ip_address) {
        adminInfo = `<div class="admin-info"><small class="text-muted">IP: ${msg.ip_address}</small></div>`;
    }
    
    return `
        <div class="chat-message">
            <img src="images/${avatar}" class="message-avatar" alt="${name}'s avatar">
            <div class="message-bubble ${userColorClass}">
                <div class="message-header">
                    <div class="message-header-left">
                        <div class="message-author">${name}</div>
                        ${badges ? `<div class="message-badges">${badges}</div>` : ''}
                    </div>
                    <div class="message-time">${timestamp}</div>
                </div>
                <div class="message-content">${msg.message}</div>
                ${adminInfo}
            </div>
        </div>
    `;
}

// ===== USER MANAGEMENT FUNCTIONS =====
function loadUsers() {
    debugLog('Loading users for roomId:', roomId);
    $.ajax({
        url: 'api/get_room_users.php',
        method: 'GET',
        data: { room_id: roomId },
        success: function(response) {
            debugLog('Response from api/get_room_users.php:', response);
            try {
                let users = JSON.parse(response);
                let html = '';
                
                if (!Array.isArray(users)) {
                    console.error('Expected array from get_room_users, got:', users);
                    html = '<div class="empty-users"><i class="fas fa-exclamation-triangle"></i><p>Error loading users</p></div>';
                } else if (users.length === 0) {
                    html = '<div class="empty-users"><i class="fas fa-users"></i><p>No users in room</p></div>';
                } else {
                    users.sort((a, b) => {
                        if (a.is_host && !b.is_host) return -1;
                        if (!a.is_host && b.is_host) return 1;
                        const nameA = a.display_name || a.username || a.guest_name || 'Unknown';
                        const nameB = b.display_name || b.username || b.guest_name || 'Unknown';
                        return nameA.localeCompare(nameB);
                    });
                    
                    users.forEach(user => {
                        html += renderUser(user);
                    });
                }
                
                $('#userList').html(html);
            } catch (e) {
                console.error('JSON parse error:', e, response);
                $('#userList').html('<div class="empty-users"><i class="fas fa-exclamation-triangle"></i><p>Error loading users</p></div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in loadUsers:', status, error, xhr.responseText);
            $('#userList').html('<div class="empty-users"><i class="fas fa-wifi"></i><p>Connection error</p></div>');
        }
    });
}

function renderUser(user) {
    const avatar = user.avatar || user.guest_avatar || 'default_avatar.jpg';
    const name = user.display_name || user.username || user.guest_name || 'Unknown';
    const userIdString = user.user_id_string || 'unknown';
    
    let badges = '';
    if (user.is_admin) {
        badges += '<span class="user-badge badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
    }
    if (user.is_host) {
        badges += '<span class="user-badge badge-host"><i class="fas fa-crown"></i> Host</span>';
    }
    if (user.user_id && !user.is_admin) {
        badges += '<span class="user-badge badge-verified"><i class="fas fa-check-circle"></i> Verified</span>';
    } else if (!user.user_id) {
        badges += '<span class="user-badge badge-guest"><i class="fas fa-user"></i> Guest</span>';
    }
    
    let actions = '';
    if ((isHost || isAdmin) && !user.is_host && !user.is_admin) {
        actions = `
            <div class="user-actions">
                <button class="btn btn-ban-user" onclick="showBanModal('${userIdString}', '${name.replace(/'/g, "\\'")}')">
                    <i class="fas fa-ban"></i> Ban
                </button>
            </div>
        `;
    }
    
    return `
        <div class="user-item">
            <div class="user-info-row">
                <img src="images/${avatar}" class="user-avatar" alt="${name}'s avatar">
                <div class="user-details">
                    <div class="user-name">${name}</div>
                    <div class="user-badges-row">${badges}</div>
                </div>
            </div>
            ${actions}
        </div>
    `;
}

// ===== YOUTUBE PLAYER SYSTEM =====

function loadYouTubeAPI() {
    if (window.YT && window.YT.Player) {
        youtubeAPIReady = true;
        initializeYouTubePlayer();
        return;
    }
    
    debugLog('🎬 Loading YouTube IFrame API...');
    
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    const firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
}

function initializeYouTubePlayer() {
    if (!youtubeAPIReady || !youtubeEnabled) {
        debugLog('🎬 Cannot initialize player: API ready =', youtubeAPIReady, ', enabled =', youtubeEnabled);
        return;
    }
    
    debugLog('🎬 Initializing YouTube player...');
    
    youtubePlayer = new YT.Player('youtube-player', {
        height: '280',
        width: '100%',
        playerVars: {
            'playsinline': 1,
            'controls': isHost ? 1 : 0,
            'disablekb': isHost ? 0 : 1,
            'fs': 1,
            'rel': 0,
            'showinfo': 0,
            'modestbranding': 1
        },
        events: {
            'onReady': onYouTubePlayerReady,
            'onStateChange': onYouTubePlayerStateChange
        }
    });
}

function onYouTubePlayerReady(event) {
    debugLog('🎬 YouTube player ready');
    youtubePlayerReady = true;
    
    startPlayerSync();
    startQueueUpdates();
    syncPlayerState();
}

function onYouTubePlayerStateChange(event) {
    debugLog('🎬 Player state changed:', event.data);
    
    if (isHost && youtubePlayerReady) {
        const currentTime = youtubePlayer.getCurrentTime();
        const videoId = getCurrentVideoId();
        
        switch (event.data) {
            case YT.PlayerState.PLAYING:
                updatePlayerSync(videoId, currentTime, true);
                break;
            case YT.PlayerState.PAUSED:
                updatePlayerSync(videoId, currentTime, false);
                break;
            case YT.PlayerState.ENDED:
                if (isHost) {
                    skipToNextVideo();
                }
                break;
        }
    }
}

function startPlayerSync() {
    if (playerSyncInterval) {
        clearInterval(playerSyncInterval);
    }
    
    playerSyncInterval = setInterval(syncPlayerState, 2000);
    debugLog('🔄 Started player sync');
}

function syncPlayerState() {
    if (!youtubeEnabled || !youtubePlayerReady) {
        return;
    }
    
    $.ajax({
        url: 'api/youtube_sync.php',
        method: 'GET',
        data: { action: 'get_sync' },
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            if (response.status === 'success') {
                const sync = response.sync_data;
                
                if (!sync.enabled) {
                    return;
                }
                
                if (sync.sync_token !== lastSyncToken) {
                    debugLog('🔄 Syncing player state:', sync);
                    lastSyncToken = sync.sync_token;
                    
                    if (sync.video_id) {
                        const currentVideoId = getCurrentVideoId();
                        
                        if (currentVideoId !== sync.video_id) {
                            youtubePlayer.loadVideoById({
                                videoId: sync.video_id,
                                startSeconds: sync.current_time
                            });
                        } else {
                            const currentTime = youtubePlayer.getCurrentTime();
                            const timeDiff = Math.abs(currentTime - sync.current_time);
                            
                            if (timeDiff > 3) {
                                youtubePlayer.seekTo(sync.current_time, true);
                            }
                        }
                        
                        if (sync.is_playing && youtubePlayer.getPlayerState() !== YT.PlayerState.PLAYING) {
                            youtubePlayer.playVideo();
                        } else if (!sync.is_playing && youtubePlayer.getPlayerState() === YT.PlayerState.PLAYING) {
                            youtubePlayer.pauseVideo();
                        }
                    } else {
                        if (youtubePlayer.getPlayerState() !== YT.PlayerState.CUED) {
                            youtubePlayer.stopVideo();
                        }
                    }
                }
            }
        },
        error: function(xhr, status, error) {
            debugLog('⚠️ Sync error:', error);
        }
    });
}

function updatePlayerSync(videoId, currentTime, isPlaying) {
    if (!isHost || !youtubeEnabled) {
        return;
    }
    
    $.ajax({
        url: 'api/youtube_sync.php',
        method: 'POST',
        data: {
            action: 'update_time',
            video_id: videoId,
            current_time: currentTime,
            is_playing: isPlaying ? 1 : 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                lastSyncToken = response.sync_token;
                debugLog('🔄 Updated player sync');
            }
        },
        error: function(xhr, status, error) {
            debugLog('⚠️ Sync update error:', error);
        }
    });
}

function startQueueUpdates() {
    if (queueUpdateInterval) {
        clearInterval(queueUpdateInterval);
    }
    
    queueUpdateInterval = setInterval(updateQueue, 3000);
    updateQueue();
    debugLog('📋 Started queue updates');
}

function updateQueue() {
    if (!youtubeEnabled) {
        return;
    }
    
    $.ajax({
        url: 'api/youtube_queue.php',
        method: 'GET',
        data: { action: 'get' },
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            if (response.status === 'success') {
                playerQueue = response.data.queue || [];
                playerSuggestions = response.data.suggestions || [];
                currentVideoData = response.data.current_playing;
                
                renderQueue();
                renderSuggestions();
                updateVideoInfo();
            }
        },
        error: function(xhr, status, error) {
            debugLog('⚠️ Queue update error:', error);
        }
    });
}

function renderQueue() {
    const container = $('#youtube-queue-list');
    let html = '';
    
    if (playerQueue.length === 0) {
        html = `
            <div class="youtube-empty-state">
                <i class="fas fa-list"></i>
                <h6>Queue is empty</h6>
                <p>Videos will appear here when added to the queue</p>
            </div>
        `;
    } else {
        playerQueue.forEach((video, index) => {
            const isPlaying = currentVideoData && currentVideoData.id === video.id;
            const actions = isHost ? `
                <div class="youtube-queue-item-actions">
                    <button class="btn btn-queue-remove" onclick="removeFromQueue(${video.id})" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            ` : '';
            
            html += `
                <div class="youtube-queue-item ${isPlaying ? 'playing' : ''}">
                    <div class="youtube-queue-item-content">
                        <img src="${video.video_thumbnail}" class="youtube-queue-item-thumb" alt="Thumbnail" onerror="this.src='https://img.youtube.com/vi/${video.video_id}/default.jpg'">
                        <div class="youtube-queue-item-details">
                            <div class="youtube-queue-item-title">${video.video_title}</div>
                            <div class="youtube-queue-item-meta">
                                Added by ${video.suggested_by_name} • #${index + 1} in queue
                            </div>
                            ${actions}
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    container.html(html);
}

function renderSuggestions() {
    const container = $('#youtube-suggestions-list');
    let html = '';
    
    if (playerSuggestions.length === 0) {
        html = `
            <div class="youtube-empty-state">
                <i class="fas fa-lightbulb"></i>
                <h6>No suggestions</h6>
                <p>Video suggestions from users will appear here</p>
            </div>
        `;
    } else {
        playerSuggestions.forEach(video => {
            const actions = isHost ? `
                <div class="youtube-queue-item-actions">
                    <button class="btn btn-queue-approve" onclick="approveVideo(${video.id})" title="Add to Queue">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-queue-deny" onclick="denyVideo(${video.id})" title="Deny">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            ` : '';
            
            html += `
                <div class="youtube-queue-item suggestion">
                    <div class="youtube-queue-item-content">
                        <img src="${video.video_thumbnail}" class="youtube-queue-item-thumb" alt="Thumbnail" onerror="this.src='https://img.youtube.com/vi/${video.video_id}/default.jpg'">
                        <div class="youtube-queue-item-details">
                            <div class="youtube-queue-item-title">${video.video_title}</div>
                            <div class="youtube-queue-item-meta">
                                Suggested by ${video.suggested_by_name}
                            </div>
                            ${actions}
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    container.html(html);
}

function updateVideoInfo() {
    const infoContainer = $('#youtube-video-info');
    
    if (currentVideoData) {
        infoContainer.html(`
            <div class="youtube-video-title">${currentVideoData.video_title}</div>
            <div class="youtube-video-meta">
                <span>Added by ${currentVideoData.suggested_by_name}</span>
                <span>•</span>
                <span>Now Playing</span>
            </div>
        `);
    } else {
        infoContainer.html(`
            <div class="youtube-video-title">No video playing</div>
            <div class="youtube-video-meta">
                <span>Select a video or add one to the queue</span>
            </div>
        `);
    }
}

function suggestVideo() {
    const input = $('#youtube-suggest-input');
    const url = input.val().trim();
    
    if (!url) {
        alert('Please enter a YouTube URL or video ID');
        return;
    }
    
    const button = $('#youtube-suggest-btn');
    const originalText = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Suggesting...');
    
    $.ajax({
        url: 'api/youtube_queue.php',
        method: 'POST',
        data: {
            action: 'suggest',
            video_url: url
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                input.val('');
                updateQueue();
                showToast('Video suggested successfully!', 'success');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Suggest video error:', error);
            alert('Error suggesting video: ' + error);
        },
        complete: function() {
            button.prop('disabled', false).html(originalText);
        }
    });
}

function approveVideo(suggestionId) {
    $.ajax({
        url: 'api/youtube_queue.php',
        method: 'POST',
        data: {
            action: 'approve',
            suggestion_id: suggestionId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateQueue();
                showToast('Video approved and added to queue!', 'success');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Approve video error:', error);
            alert('Error approving video: ' + error);
        }
    });
}

function denyVideo(suggestionId) {
    $.ajax({
        url: 'api/youtube_queue.php',
        method: 'POST',
        data: {
            action: 'deny',
            suggestion_id: suggestionId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateQueue();
                showToast('Video suggestion denied', 'info');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Deny video error:', error);
            alert('Error denying video: ' + error);
        }
    });
}

function removeFromQueue(queueId) {
    if (!confirm('Remove this video from the queue?')) {
        return;
    }
    
    $.ajax({
        url: 'api/youtube_queue.php',
        method: 'POST',
        data: {
            action: 'remove',
            queue_id: queueId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateQueue();
                showToast('Video removed from queue', 'info');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Remove video error:', error);
            alert('Error removing video: ' + error);
        }
    });
}

function playVideo() {
    if (!isHost || !youtubePlayerReady) return;
    
    const currentTime = youtubePlayer.getCurrentTime();
    
    $.ajax({
        url: 'api/youtube_player.php',
        method: 'POST',
        data: {
            action: 'resume',
            current_time: currentTime
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                debugLog('🎬 Video resumed');
            }
        }
    });
}

function pauseVideo() {
    if (!isHost || !youtubePlayerReady) return;
    
    const currentTime = youtubePlayer.getCurrentTime();
    
    $.ajax({
        url: 'api/youtube_player.php',
        method: 'POST',
        data: {
            action: 'pause',
            current_time: currentTime
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                debugLog('🎬 Video paused');
            }
        }
    });
}

function skipToNextVideo() {
    if (!isHost) return;
    
    $.ajax({
        url: 'api/youtube_player.php',
        method: 'POST',
        data: { action: 'skip' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                debugLog('🎬 Skipped to next video');
                updateQueue();
            } else {
                showToast(response.message || 'No more videos in queue', 'info');
            }
        }
    });
}

function stopVideo() {
    if (!isHost) return;
    
    $.ajax({
        url: 'api/youtube_player.php',
        method: 'POST',
        data: { action: 'stop' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                debugLog('🎬 Video stopped');
            }
        }
    });
}

function togglePlayerVisibility() {
    const container = $('.youtube-player-container');
    const toggle = $('.youtube-player-toggle');
    
    if (container.hasClass('user-hidden')) {
        container.removeClass('user-hidden');
        toggle.removeClass('hidden-player').html('<i class="fas fa-video-slash"></i>').attr('title', 'Hide Player');
        playerHidden = false;
        
        if (youtubePlayerReady) {
            syncPlayerState();
        }
    } else {
        container.addClass('user-hidden');
        toggle.addClass('hidden-player').html('<i class="fas fa-video"></i>').attr('title', 'Show Player');
        playerHidden = true;
    }
    
    localStorage.setItem(`youtube_hidden_${roomId}`, playerHidden);
}

function getCurrentVideoId() {
    if (!youtubePlayer || !youtubePlayerReady) {
        return null;
    }
    
    try {
        const url = youtubePlayer.getVideoUrl();
        const match = url.match(/[?&]v=([^&]+)/);
        return match ? match[1] : null;
    } catch (e) {
        return null;
    }
}

function showToast(message, type = 'info') {
    const toast = $(`
        <div class="alert alert-${type} alert-dismissible fade show" 
             style="position: fixed; top: 70px; right: 20px; z-index: 1060; min-width: 300px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(toast);
    
    setTimeout(() => {
        toast.alert('close');
    }, 4000);
}

function stopYouTubePlayer() {
    debugLog('🛑 Stopping YouTube player system');
    
    if (playerSyncInterval) {
        clearInterval(playerSyncInterval);
        playerSyncInterval = null;
    }
    
    if (queueUpdateInterval) {
        clearInterval(queueUpdateInterval);
        queueUpdateInterval = null;
    }
    
    if (youtubePlayer && youtubePlayerReady) {
        try {
            youtubePlayer.stopVideo();
        } catch (e) {
            debugLog('Error stopping YouTube player:', e);
        }
    }
    
    youtubeEnabled = false;
    youtubePlayerReady = false;
}

// ===== KICK DETECTION FUNCTIONS =====
function checkUserStatus() {
    if (userKickedModalShown || !kickDetectionEnabled) {
        return;
    }
    
    const now = Date.now();
    if (now - lastStatusCheck < 1000) {
        return;
    }
    lastStatusCheck = now;
    
    debugLog('🔍 Checking user status...');
    
    $.ajax({
        url: 'api/check_user_status.php',
        method: 'GET',
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            debugLog('📡 Status check result:', response);
            consecutiveErrors = 0;
            
            switch(response.status) {
                case 'banned':
                    handleUserBanned(response);
                    break;
                case 'removed':
                    handleUserKicked(response);
                    break;
                case 'room_deleted':
                    handleRoomDeleted(response);
                    break;
                case 'not_in_room':
                    debugLog('👤 User not in room, redirecting to lounge');
                    stopKickDetection();
                    window.location.href = 'lounge.php';
                    break;
                case 'active':
                    debugLog('✅ User status: Active in', response.room_name);
                    break;
                case 'error':
                    console.error('❌ Server error:', response.message);
                    handleStatusCheckError();
                    break;
                default:
                    console.warn('⚠️ Unknown status:', response.status);
                    break;
            }
        },
        error: function(xhr, status, error) {
            debugLog('🔌 Status check failed:', { status, error, responseText: xhr.responseText });
            handleStatusCheckError();
        }
    });
}

function handleUserBanned(response) {
    debugLog('🚫 User has been BANNED:', response);
    stopKickDetection();
    
    let banMessage = response.message || 'You have been banned from this room';
    let banDetails = '';
    
    if (response.ban_info) {
        if (response.ban_info.permanent) {
            banDetails += '<div class="alert alert-danger"><strong>This is a PERMANENT ban.</strong></div>';
        } else if (response.ban_info.expires_in_minutes) {
            banDetails += `<div class="alert alert-warning"><strong>Ban expires in ${response.ban_info.expires_in_minutes} minute${response.ban_info.expires_in_minutes !== 1 ? 's' : ''}.</strong></div>`;
        }
        
        if (response.ban_info.reason) {
            banDetails += `<p><strong>Reason:</strong> ${response.ban_info.reason}</p>`;
        }
    }
    
    showKickModal('🚫 You Have Been Banned', banMessage, banDetails, 'danger');
}

function handleUserKicked(response) {
    debugLog('👢 User has been KICKED:', response);
    stopKickDetection();
    
    const message = response.message || 'You have been removed from this room';
    const details = '<div class="alert alert-info">You can try to rejoin the room if it\'s still available.</div>';
    
    showKickModal('👢 Removed from Room', message, details, 'warning');
}

function handleRoomDeleted(response) {
    debugLog('🏗️ Room has been DELETED:', response);
    stopKickDetection();
    
    const message = response.message || 'This room has been deleted';
    const details = '<div class="alert alert-info">The room no longer exists. You will be redirected to the lounge.</div>';
    
    showKickModal('🏗️ Room Deleted', message, details, 'info');
}

function handleStatusCheckError() {
    consecutiveErrors++;
    
    if (consecutiveErrors >= 3) {
        console.warn('⚠️ Multiple consecutive errors, may have connection issues');
        
        if (consecutiveErrors >= 5) {
            console.error('🔥 Too many errors, redirecting to lounge');
            stopKickDetection();
            alert('Connection lost. Redirecting to lounge.');
            window.location.href = 'lounge.php';
        }
    }
}

function showKickModal(title, message, details, type) {
    userKickedModalShown = true;
    
    const typeColors = {
        'danger': { bg: 'bg-danger', icon: 'fas fa-ban' },
        'warning': { bg: 'bg-warning', icon: 'fas fa-exclamation-triangle' },
        'info': { bg: 'bg-info', icon: 'fas fa-info-circle' }
    };
    
    const typeConfig = typeColors[type] || typeColors['info'];
    
    const modalHtml = `
        <div class="modal fade" id="kickNotificationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-${type}" style="background: #2a2a2a; color: #e0e0e0;">
                    <div class="modal-header ${typeConfig.bg} text-white">
                        <h5 class="modal-title">
                            <i class="${typeConfig.icon}"></i> ${title}
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <i class="${typeConfig.icon} fa-4x text-${type}"></i>
                        </div>
                        <h6 class="text-${type} mb-3">${message}</h6>
                        ${details}
                        <div class="alert alert-light mt-3" style="background: #333; border-color: #555; color: #e0e0e0;">
                            <i class="fas fa-home"></i>
                            <strong>You will be redirected to the lounge in <span id="redirectCountdown">8</span> seconds</strong>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-primary" onclick="handleKickModalClose()">
                            <i class="fas fa-home"></i> Go to Lounge Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#kickNotificationModal').remove();
    $('body').append(modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('kickNotificationModal'));
    modal.show();
    
    let countdown = 8;
    const countdownInterval = setInterval(() => {
        countdown--;
        $('#redirectCountdown').text(countdown);
        
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            handleKickModalClose();
        }
    }, 1000);
}

function handleKickModalClose() {
    debugLog('🏠 Redirecting to lounge...');
    stopKickDetection();
    
    $.ajax({
        url: 'api/leave_room.php',
        method: 'POST',
        data: { room_id: roomId, action: 'kicked_user_cleanup' },
        complete: function() {
            window.location.href = 'lounge.php';
        }
    });
}

function stopKickDetection() {
    debugLog('🛑 Stopping kick detection system');
    kickDetectionEnabled = false;
    
    if (kickDetectionInterval) {
        clearInterval(kickDetectionInterval);
        kickDetectionInterval = null;
    }
}

// ===== ACTIVITY TRACKING FUNCTIONS =====
function initializeActivityTracking() {
    if (activityTrackingEnabled) {
        debugLog('🔄 Activity tracking already initialized');
        return;
    }
    
    debugLog('🔄 Initializing activity tracking system...');
    activityTrackingEnabled = true;
    
    if (activityInterval) {
        clearInterval(activityInterval);
    }
    if (disconnectCheckInterval) {
        clearInterval(disconnectCheckInterval);
    }
    
    activityInterval = setInterval(() => {
        if (userIsActive && activityTrackingEnabled) {
            updateUserActivity('heartbeat');
            userIsActive = false;
        }
    }, 30 * 1000);
    
    disconnectCheckInterval = setInterval(() => {
        if (activityTrackingEnabled) {
            triggerDisconnectCheck();
        }
    }, 2 * 60 * 1000);
    
    setupActivityListeners();
    updateUserActivity('system_start');
    
    debugLog('✅ Activity tracking system initialized successfully');
}

function setupActivityListeners() {
    debugLog('🎯 Setting up activity listeners...');
    
    $(document).off('mousemove.activity keypress.activity scroll.activity click.activity');
    $(window).off('focus.activity');
    
    let activityTimeout;
    function markUserActive() {
        userIsActive = true;
        
        clearTimeout(activityTimeout);
        activityTimeout = setTimeout(() => {
            if (activityTrackingEnabled) {
                updateUserActivity('interaction');
            }
        }, 5000);
    }
    
    $(document).on('mousemove.activity keypress.activity scroll.activity click.activity', markUserActive);
    
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && activityTrackingEnabled) {
            updateUserActivity('page_focus');
        }
    });
    
    $(window).on('focus.activity', function() {
        if (activityTrackingEnabled) {
            updateUserActivity('window_focus');
        }
    });
    
    debugLog('✅ Activity listeners set up successfully');
}

function updateUserActivity(activityType = 'general') {
    if (!activityTrackingEnabled) {
        return;
    }
    
    const now = Date.now();
    const minInterval = activityType === 'heartbeat' ? 25000 : 3000;
    
    if (now - lastActivityUpdate < minInterval) {
        return;
    }
    
    lastActivityUpdate = now;
    
    debugLog(`📍 Updating activity: ${activityType}`);
    
    $.ajax({
        url: 'api/update_activity.php',
        method: 'POST',
        data: { activity_type: activityType },
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            if (response.status === 'success') {
                debugLog(`✅ Activity updated: ${activityType}`);
            } else if (response.status === 'not_in_room') {
                debugLog('❌ Not in room - stopping activity tracking');
                stopActivityTracking();
                
                if (typeof forceStatusCheck === 'function') {
                    forceStatusCheck();
                }
            }
        },
        error: function(xhr, status, error) {
            debugLog(`⚠️ Activity update failed: ${status} - ${error}`);
        }
    });
}

function triggerDisconnectCheck() {
    if (!activityTrackingEnabled) {
        return;
    }
    
    debugLog('🔍 Triggering disconnect check...');
    
    $.ajax({
        url: 'api/check_disconnects.php',
        method: 'GET',
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.status === 'success') {
                debugLog('📊 Disconnect check completed:', response.summary);
                
                const summary = response.summary;
                if (summary.users_disconnected > 0 || summary.hosts_transferred > 0 || summary.rooms_deleted > 0) {
                    debugLog(`👥 Changes detected, refreshing UI`);
                    
                    setTimeout(() => {
                        loadUsers();
                        loadMessages();
                    }, 1000);
                }
            }
        },
        error: function(xhr, status, error) {
            debugLog('⚠️ Disconnect check error:', error);
        }
    });
}

function stopActivityTracking() {
    debugLog('🛑 Stopping activity tracking system');
    activityTrackingEnabled = false;
    
    if (activityInterval) {
        clearInterval(activityInterval);
        activityInterval = null;
    }
    
    if (disconnectCheckInterval) {
        clearInterval(disconnectCheckInterval);
        disconnectCheckInterval = null;
    }
    
    $(document).off('mousemove.activity keypress.activity scroll.activity click.activity');
    $(window).off('focus.activity');
}

// ===== ROOM MANAGEMENT FUNCTIONS =====
function showRoomSettings() {
    debugLog('Loading room settings for roomId:', roomId);
    
    $.ajax({
        url: 'api/get_room_settings.php',
        method: 'GET',
        data: { room_id: roomId },
        success: function(response) {
            try {
                let res = JSON.parse(response);
                if (res.status === 'success') {
                    displayRoomSettingsModal(res.settings);
                } else {
                    alert('Error loading room settings: ' + res.message);
                }
            } catch (e) {
                console.error('JSON parse error:', e, response);
                alert('Invalid response from server');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in showRoomSettings:', status, error, xhr.responseText);
            alert('AJAX error: ' + error);
        }
    });
}

function displayRoomSettingsModal(settings) {
    console.log('Displaying room settings modal with:', settings); // Debug log
    
    const modalHtml = `
        <div class="modal fade" id="roomSettingsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="background: #333; border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-cog"></i> Room Settings
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist" style="border-bottom: 1px solid #444;">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" style="color: #fff; background: transparent; border: none;">General</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" style="color: #fff; background: transparent; border: none;">Security</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="youtube-tab" data-bs-toggle="tab" data-bs-target="#youtube" type="button" role="tab" style="color: #fff; background: transparent; border: none;">
                                    <i class="fab fa-youtube text-danger"></i> YouTube Player
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="banlist-tab" data-bs-toggle="tab" data-bs-target="#banlist" type="button" role="tab" style="color: #fff; background: transparent; border: none;">
                                    <i class="fas fa-ban"></i> Banlist
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="settingsTabsContent">
                            <!-- General Settings -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <form id="roomSettingsForm" class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="settingsRoomName" class="form-label">Room Name</label>
                                                <input type="text" class="form-control" id="settingsRoomName" value="${settings.name}" required style="background: #333; border: 1px solid #555; color: #fff;">
                                            </div>
                                            <div class="mb-3">
                                                <label for="settingsCapacity" class="form-label">Capacity</label>
                                                <select class="form-select" id="settingsCapacity" required style="background: #333; border: 1px solid #555; color: #fff;">
                                                    <option value="5"${settings.capacity == 5 ? ' selected' : ''}>5</option>
                                                    <option value="10"${settings.capacity == 10 ? ' selected' : ''}>10</option>
                                                    <option value="20"${settings.capacity == 20 ? ' selected' : ''}>20</option>
                                                    <option value="50"${settings.capacity == 50 ? ' selected' : ''}>50</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="settingsDescription" class="form-label">Description</label>
                                                <textarea class="form-control" id="settingsDescription" rows="3" style="background: #333; border: 1px solid #555; color: #fff;">${settings.description || ''}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Security Settings -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="settingsHasPassword"${settings.has_password ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsHasPassword">
                                                        <i class="fas fa-lock"></i> Password Protected
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mb-3" id="passwordFieldSettings" style="display: ${settings.has_password ? 'block' : 'none'};">
                                                <label for="settingsPassword" class="form-label">Room Password</label>
                                                <input type="password" class="form-control" id="settingsPassword" placeholder="Leave empty to keep current password" style="background: #333; border: 1px solid #555; color: #fff;">
                                                <div class="form-text text-muted">Leave empty to keep current password, or enter new password to change it.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3" id="knockingFieldSettings" style="display: ${settings.has_password ? 'block' : 'none'};">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="settingsAllowKnocking"${settings.allow_knocking ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsAllowKnocking">
                                                        <i class="fas fa-hand-paper"></i> Allow Knocking
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">Let users request access when they don't know the password</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- YouTube Player Settings -->
                            <div class="tab-pane fade" id="youtube" role="tabpanel">
                                <div class="mt-3">
                                    <div class="mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="settingsYouTubeEnabled"${settings.youtube_enabled ? ' checked' : ''}>
                                            <label class="form-check-label" for="settingsYouTubeEnabled">
                                                <i class="fab fa-youtube text-danger"></i> <strong>Enable YouTube Player</strong>
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">Allow synchronized video playback for all users in the room</small>
                                    </div>
                                    
                                    <div id="youtubePlayerInfo" style="display: ${settings.youtube_enabled ? 'block' : 'none'};">
                                        <div class="alert" style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.3); color: #b3d4fc; border-radius: 8px;">
                                            <h6><i class="fas fa-info-circle"></i> YouTube Player Features:</h6>
                                            <ul class="mb-0" style="padding-left: 1.2rem;">
                                                <li><strong>Host Controls:</strong> Only hosts can control playback (play, pause, skip, stop)</li>
                                                <li><strong>Video Suggestions:</strong> Users can suggest videos for host approval</li>
                                                <li><strong>Queue System:</strong> Approved videos are queued for continuous playback</li>
                                                <li><strong>Individual Toggle:</strong> Users can hide the player locally if they want</li>
                                                <li><strong>Real-time Sync:</strong> All users see the same video at the same time</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Banlist -->
                            <div class="tab-pane fade" id="banlist" role="tabpanel">
                                <div class="mt-3">
                                    <h6><i class="fas fa-ban"></i> Banned Users</h6>
                                    <div id="bannedUsersList">
                                        <p class="text-muted">Loading banned users...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveRoomSettings()"><i class="fas fa-save"></i> Save Settings</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove any existing modal
    $('#roomSettingsModal').remove();
    $('body').append(modalHtml);
    
    // Set up event handlers
    setupRoomSettingsHandlers();
    
    // Set up banlist tab click handler
    $('#banlist-tab').on('click', function() {
        loadBannedUsers();
    });
    
    // Show the modal
    $('#roomSettingsModal').modal('show');
}

function setupRoomSettingsHandlers() {
    $('#settingsHasPassword').on('change', function() {
        if (this.checked) {
            $('#passwordFieldSettings').show();
            $('#knockingFieldSettings').show();
        } else {
            $('#passwordFieldSettings').hide();
            $('#knockingFieldSettings').hide();
            $('#settingsPassword').val('');
            $('#settingsAllowKnocking').prop('checked', true);
        }
    });
    
    $('#settingsYouTubeEnabled').on('change', function() {
        if (this.checked) {
            $('#youtubePlayerInfo').show();
        } else {
            $('#youtubePlayerInfo').hide();
        }
    });
}

function loadBannedUsers() {
    debugLog('Loading banned users for room:', roomId);
    
    $.ajax({
        url: 'api/get_banned_users_simple.php',
        method: 'GET',
        dataType: 'json',
        data: { room_id: roomId },
        success: function(response) {
            debugLog('Banned users response:', response);
            
            let html = '';
            
            if (!Array.isArray(response)) {
                html = '<p class="text-danger">Error loading banned users.</p>';
            } else {
                if (response.length === 0) {
                    html = '<p class="text-muted">No banned users.</p>';
                } else {
                    response.forEach((ban) => {
                        const name = ban.username || ban.guest_name || 'Unknown User';
                        const banType = ban.is_permanent ? 'Permanent' : 'Temporary';
                        const expiry = ban.ban_until ? new Date(ban.ban_until).toLocaleString() : 'Never';
                        const reason = ban.reason || 'No reason provided';
                        
                        html += `
                            <div class="card mb-2" style="background: #333; border: 1px solid #555;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong style="color: #fff;">${name}</strong> 
                                            <span class="badge ${banType === 'Permanent' ? 'bg-danger' : 'bg-warning'}">${banType}</span>
                                            <br>
                                            <small class="text-muted">
                                                Expires: ${expiry}<br>
                                                Reason: ${reason}
                                            </small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success" onclick="unbanUser('${ban.user_id_string}', '${name.replace(/'/g, "\\'")}')">
                                            <i class="fas fa-unlock"></i> Unban
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
            }
            
            $('#bannedUsersList').html(html);
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in loadBannedUsers:', status, error);
            $('#bannedUsersList').html('<p class="text-danger">Error loading banned users.</p>');
        }
    });
}

function unbanUser(userIdString, userName) {
    if (!confirm('Are you sure you want to unban ' + userName + '?')) {
        return;
    }
    
    $.ajax({
        url: 'api/unban_user_simple.php',
        method: 'POST',
        dataType: 'json',
        data: {
            room_id: roomId,
            user_id_string: userIdString
        },
        success: function(response) {
            if (response.status === 'success') {
                alert(userName + ' has been unbanned successfully!');
                loadBannedUsers(); // Reload the list
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in unbanUser:', status, error);
            alert('AJAX error: ' + error);
        }
    });
}

function saveRoomSettings() {
    const formData = {
        room_id: roomId,
        name: $('#settingsRoomName').val().trim(),
        description: $('#settingsDescription').val().trim(),
        capacity: $('#settingsCapacity').val(),
        has_password: $('#settingsHasPassword').is(':checked') ? 1 : 0,
        password: $('#settingsPassword').val(),
        allow_knocking: $('#settingsAllowKnocking').is(':checked') ? 1 : 0,
        youtube_enabled: $('#settingsYouTubeEnabled').is(':checked') ? 1 : 0
    };
    
    if (!formData.name) {
        alert('Room name is required');
        $('#settingsRoomName').focus();
        return;
    }
    
    const saveButton = $('#roomSettingsModal .btn-primary');
    const originalText = saveButton.html();
    saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
    
    $.ajax({
        url: 'api/update_room.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            try {
                let res = JSON.parse(response);
                if (res.status === 'success') {
                    alert('Room settings updated successfully!');
                    $('#roomSettingsModal').modal('hide');
                    
                    const newYouTubeState = formData.youtube_enabled === 1;
                    if (newYouTubeState !== youtubeEnabled) {
                        showToast('YouTube player setting changed. Refreshing room...', 'info');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                alert('Invalid response from server');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in saveRoomSettings:', status, error);
            alert('AJAX error: ' + error);
        },
        complete: function() {
            saveButton.prop('disabled', false).html(originalText);
        }
    });
}

function leaveRoom() {
    debugLog('Leave room clicked for roomId:', roomId);
    
    $.ajax({
        url: 'api/leave_room.php',
        method: 'POST',
        data: { 
            room_id: roomId,
            action: 'check_options'
        },
        success: function(response) {
            debugLog('Response from api/leave_room.php (check):', response);
            try {
                let res = JSON.parse(response);
                
                if (res.status === 'host_leaving') {
                    showHostLeavingModal(
                        res.other_users || [], 
                        res.show_transfer !== false, 
                        res.last_user === true
                    );
                } else if (res.status === 'success') {
                    window.location.href = 'lounge.php';
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (e) {
                console.error('JSON parse error:', e, 'Raw response:', response);
                if (response.includes('success')) {
                    window.location.href = 'lounge.php';
                } else {
                    alert('Invalid response from server: ' + response);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in leaveRoom:', status, error);
            alert('AJAX error: ' + error);
        }
    });
}

function showHostLeavingModal(otherUsers, showTransfer, isLastUser) {
    let userOptions = '';
    let transferSection = '';
    
    if (showTransfer && otherUsers.length > 0) {
        otherUsers.forEach(user => {
            let displayName = user.username || user.guest_name;
            userOptions += '<option value="' + user.user_id_string + '">' + displayName + '</option>';
        });
        
        transferSection = `
            <div class="mb-3">
                <label for="newHostSelect" class="form-label">Or transfer host privileges to:</label>
                <select class="form-select mb-2" id="newHostSelect" style="background: #333; border: 1px solid #555; color: #fff;">
                    <option value="">Select new host...</option>
                    ${userOptions}
                </select>
                <button type="button" class="btn btn-primary w-100" onclick="transferHost()">Transfer Host & Leave</button>
            </div>
        `;
    }

    let modalHtml = `
        <div class="modal fade" id="hostLeavingModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title">${isLastUser ? 'Last User in Room' : 'You are the Host'}</h5>
                    </div>
                    <div class="modal-body">
                        <p>${isLastUser ? 
                            'You are the last user in this room. When you leave, the room will be deleted.' : 
                            'You are the host of this room. What would you like to do?'}</p>
                        <div class="mb-3">
                            <button type="button" class="btn btn-danger w-100 mb-2" onclick="deleteRoom()">
                                ${isLastUser ? 'Leave & Delete Room' : 'Delete Room'}
                            </button>
                            ${transferSection}
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#hostLeavingModal').remove();
    $('body').append(modalHtml);
    $('#hostLeavingModal').modal('show');
}

function deleteRoom() {
    if (confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
        $.ajax({
            url: 'api/leave_room.php',
            method: 'POST',
            data: { 
                room_id: roomId,
                action: 'delete_room'
            },
            success: function(response) {
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
                        stopKickDetection();
                        alert('Room deleted successfully');
                        window.location.href = 'lounge.php';
                    } else {
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in deleteRoom:', status, error);
                alert('AJAX error: ' + error);
            }
        });
    }
}

function transferHost() {
    let newHostId = $('#newHostSelect').val();
    if (!newHostId) {
        alert('Please select a user to transfer host privileges to');
        return;
    }
    
    if (confirm('Are you sure you want to transfer host privileges and leave the room?')) {
        $.ajax({
            url: 'api/leave_room.php',
            method: 'POST',
            data: { 
                room_id: roomId,
                action: 'transfer_host',
                new_host_user_id: newHostId
            },
            success: function(response) {
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
                        stopKickDetection();
                        alert('Host privileges transferred successfully');
                        window.location.href = 'lounge.php';
                    } else {
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in transferHost:', status, error);
                alert('AJAX error: ' + error);
            }
        });
    }
}

// ===== BAN SYSTEM FUNCTIONS =====
function showBanModal(userIdString, userName) {
    const modalHtml = `
        <div class="modal fade" id="banUserModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title"><i class="fas fa-ban"></i> Ban User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to ban <strong>${userName}</strong> from this room.</p>
                        <div class="mb-3">
                            <label for="banDuration" class="form-label">Ban Duration</label>
                            <select class="form-select" id="banDuration" required style="background: #333; border: 1px solid #555; color: #fff;">
                                <option value="300">5 minutes</option>
                                <option value="1800">30 minutes</option>
                                <option value="permanent">Permanent</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="banReason" class="form-label">Reason (optional)</label>
                            <input type="text" class="form-control" id="banReason" placeholder="Enter reason for ban" style="background: #333; border: 1px solid #555; color: #fff;">
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmBanUser('${userIdString}', '${userName.replace(/'/g, "\\'")}')">
                            <i class="fas fa-ban"></i> Ban User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#banUserModal').remove();
    $('body').append(modalHtml);
    $('#banUserModal').modal('show');
}

function confirmBanUser(userIdString, userName) {
    const duration = $('#banDuration').val();
    const reason = $('#banReason').val().trim();
    
    const durationText = duration === 'permanent' ? 'permanently' : 
                       duration == 300 ? 'for 5 minutes' :
                       duration == 1800 ? 'for 30 minutes' : 'for ' + duration + ' seconds';
    
    if (!confirm('Are you sure you want to ban ' + userName + ' ' + durationText + '?')) {
        return;
    }
    
    const banButton = $('#banUserModal .btn-danger');
    const originalText = banButton.html();
    banButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Banning...');
    
    $.ajax({
        url: 'api/ban_user_simple.php',
        method: 'POST',
        dataType: 'json',
        data: {
            room_id: roomId,
            user_id_string: userIdString,
            duration: duration,
            reason: reason
        },
        success: function(response) {
            if (response.status === 'success') {
                alert('User banned successfully ' + durationText + '!');
                $('#banUserModal').modal('hide');
                
                loadUsers();
                loadMessages();
                
                setTimeout(() => {
                    checkUserStatus();
                }, 500);
                
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in confirmBanUser:', status, error);
            alert('AJAX error: ' + error);
        },
        complete: function() {
            banButton.prop('disabled', false).html(originalText);
        }
    });
}

// ===== KNOCK SYSTEM FUNCTIONS =====
function checkForKnocks() {
    if (!isHost) {
        return;
    }
    
    $.ajax({
        url: 'api/check_knocks.php',
        method: 'GET',
        dataType: 'json',
        success: function(knocks) {
            if (Array.isArray(knocks) && knocks.length > 0) {
                displayKnockNotifications(knocks);
            }
        },
        error: function(xhr, status, error) {
            // Silently fail for knock checks
        }
    });
}

function displayKnockNotifications(knocks) {
    knocks.forEach((knock, index) => {
        if ($(`#knock-${knock.id}`).length > 0) {
            return; // Already displayed
        }
        
        const userName = knock.username || knock.guest_name || 'Unknown User';
        const avatar = knock.avatar || 'default_avatar.jpg';
        const topPosition = 20 + (index * 140);
        
        const notificationHtml = `
            <div class="alert alert-info knock-notification" 
                 id="knock-${knock.id}" 
                 role="alert" 
                 style="position: fixed; top: ${topPosition}px; right: 20px; z-index: 1070; max-width: 400px; background: #2a2a2a; border: 1px solid #404040; color: #e0e0e0;">
                <div class="d-flex align-items-center">
                    <img src="images/${avatar}" width="40" height="40" class="rounded-circle me-3" alt="${userName}" style="border: 2px solid #007bff;">
                    <div class="flex-grow-1">
                        <h6 class="mb-1" style="color: #e0e0e0;">
                            <i class="fas fa-hand-paper text-primary"></i> Knock Request
                        </h6>
                        <p class="mb-2" style="color: #ccc;"><strong>${userName}</strong> wants to join this room</p>
                        <div>
                            <button class="btn btn-success btn-sm me-2" onclick="respondToKnock(${knock.id}, 'accepted')">
                                <i class="fas fa-check"></i> Accept
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="respondToKnock(${knock.id}, 'denied')">
                                <i class="fas fa-times"></i> Deny
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-close" onclick="dismissKnock(${knock.id})" style="filter: invert(1);"></button>
                </div>
            </div>
        `;
        
        $('body').append(notificationHtml);
        $(`#knock-${knock.id}`).hide().fadeIn(300);
        
        // Auto-dismiss after 45 seconds
        setTimeout(() => {
            dismissKnock(knock.id);
        }, 45000);
    });
}

function respondToKnock(knockId, response) {
    $.ajax({
        url: 'api/respond_knocks.php',
        method: 'POST',
        data: {
            knock_id: knockId,
            response: response
        },
        dataType: 'json',
        success: function(result) {
            if (result.status === 'success') {
                dismissKnock(knockId);
                loadMessages();
                
                const message = response === 'accepted' ? 
                    'Knock accepted! The user can now join the room.' : 
                    'Knock request denied.';
                alert(message);
            } else {
                alert('Error: ' + result.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error responding to knock:', error);
            alert('Error responding to knock: ' + error);
        }
    });
}

function dismissKnock(knockId) {
    $(`#knock-${knockId}`).fadeOut(300, function() {
        $(this).remove();
    });
}

// ===== UTILITY FUNCTIONS =====
function createTestUser() {
    $.ajax({
        url: 'api/create_test_user.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('Test user created: ' + response.user.name);
                loadUsers();
                loadMessages();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in createTestUser:', status, error);
            alert('AJAX error: ' + error);
        }
    });
}

// ===== INITIALIZATION =====
$(document).ready(function() {
    debugLog('🏠 Room loaded, roomId:', roomId);
    
    if (!roomId) {
        console.error('❌ Invalid room ID, redirecting to lounge');
        window.location.href = 'lounge.php';
        return;
    }

    // Set up event handlers
    $(document).on('submit', '#messageForm', function(e) {
        e.preventDefault();
        sendMessage();
        return false;
    });

    $(document).on('keypress', '#message', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            sendMessage();
            return false;
        }
    });

    $(document).on('submit', '#youtube-suggest-form', function(e) {
        e.preventDefault();
        suggestVideo();
        return false;
    });

    $(document).on('keypress', '#youtube-suggest-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            suggestVideo();
            return false;
        }
    });

    $(document).on('scroll', '#chatbox', function() {
        userIsScrolling = true;
        setTimeout(function() {
            userIsScrolling = false;
        }, 1000);
    });

    // Page visibility and focus tracking
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateUserActivity('page_focus');
            setTimeout(checkUserStatus, 100);
        }
    });

    $(window).on('focus', function() {
        setTimeout(checkUserStatus, 100);
    });

    // Initialize YouTube player if enabled
    if (typeof youtubeEnabledGlobal !== 'undefined' && youtubeEnabledGlobal) {
        debugLog('🎬 YouTube enabled for this room');
        youtubeEnabled = true;
        isYoutubeHost = isHost;
        
        const savedHidden = localStorage.getItem(`youtube_hidden_${roomId}`);
        if (savedHidden === 'true') {
            $('.youtube-player-container').addClass('user-hidden');
            $('.youtube-player-toggle').addClass('hidden-player').html('<i class="fas fa-video"></i>').attr('title', 'Show Player');
            playerHidden = true;
        }
        
        // Set up YouTube API ready callback
        window.onYouTubeIframeAPIReady = function() {
            youtubeAPIReady = true;
            initializeYouTubePlayer();
        };
        
        loadYouTubeAPI();
        $('.youtube-player-container').addClass('enabled');
        $('.youtube-player-toggle').show();
    } else {
        debugLog('🎬 YouTube not enabled for this room');
        youtubeEnabled = false;
    }

    // Initialize other systems
    setTimeout(checkUserStatus, 500);
    kickDetectionInterval = setInterval(checkUserStatus, 2000);
    kickDetectionEnabled = true;

    initializeActivityTracking();

    // Start knock checking if user is host
    if (isHost) {
        debugLog('🚪 User is host, starting knock checking...');
        setInterval(checkForKnocks, 3000);
        setTimeout(checkForKnocks, 1000);
    }

    loadMessages();
    loadUsers();
    
    setInterval(loadMessages, 500);
    setInterval(loadUsers, 500);
    
    $('#message').focus();
    
    debugLog('✅ Room initialization complete');
});

$(window).on('beforeunload', function() {
    stopYouTubePlayer();
    stopActivityTracking();
    stopKickDetection();
});
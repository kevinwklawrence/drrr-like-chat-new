const DEBUG_MODE = false;
const SHOW_SENSITIVE_DATA = false;

function debugLog(message, data = null) {
    if (DEBUG_MODE) {
        if (data !== null) {
            debugLog('[ROOM]', message, data);
        } else {
            debugLog('[ROOM]', message);
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


let youtubeUpdateInterval = null;
let isYoutubeUpdating = false;

let messageOffset = 0;
let messageLimit = 50;
let totalMessageCount = 0;
let isLoadingMessages = false;
let hasMoreOlderMessages = false;
let isInitialLoad = true;

let lastSeenMessageId = null;
let initializedMessages = false;

let friendshipCache = new Map();
let friendshipCacheTimeout = new Map();

let kickDetectionInterval;
let userKickedModalShown = false;
let kickDetectionEnabled = true;
let lastStatusCheck = 0;
let consecutiveErrors = 0;

const ACTIVITY_CONFIG = {
    HEARTBEAT_INTERVAL: 30000,        // 30 seconds
    ACTIVITY_UPDATE_INTERVAL: 5000,   // 5 seconds for interaction tracking
    DISCONNECT_CHECK_INTERVAL: 60000, // 60 seconds
    STATUS_CHECK_INTERVAL: 5000,      // 5 seconds
    MIN_ACTIVITY_INTERVAL: 3000,      // Minimum 3 seconds between activity updates
    
    AFK_TIMEOUT_MINUTES: 20,          // 20 minutes to AFK
    DISCONNECT_TIMEOUT_MINUTES: 80,   // 80 minutes total to disconnect
    SESSION_TIMEOUT_MINUTES: 60       // 60 minutes for session timeout
};

let activityInterval = null;
let disconnectCheckInterval = null;
let lastActivityUpdate = 0;
let userIsActive = true;
let activityTrackingEnabled = false;

let lastScrollTop = 0;
let lastMessageCount = 0;
let userIsScrolling = false;
let lastPlayedMessageCount = 0;

function playMessageNotification() {
    const audio = new Audio('/sounds/message_notification.mp3');
   // audio.play();
}

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


let mentionNotifications = [];
let currentReplyTo = null;
let mentionCheckInterval = null;
let mentionPanelOpen = false;

let currentUserAFK = false;
let manualAFK = false;



let activityTracker = {
    enabled: false,
    intervals: {
        heartbeat: null,
        activityUpdate: null,
        disconnectCheck: null,
        statusCheck: null
    },
    lastActivityUpdate: 0,
    lastInteraction: 0,
    userIsActive: false,
    activityQueue: [],
    
    init() {
        if (this.enabled) {
            debugLog('🔄 Activity tracking already initialized');
            return;
        }
        
        debugLog('🔄 Initializing activity tracking system...');
        this.enabled = true;
        
        this.cleanup();
        
        this.intervals.heartbeat = setInterval(() => {
            this.sendHeartbeat();
        }, ACTIVITY_CONFIG.HEARTBEAT_INTERVAL);
        
        this.intervals.activityUpdate = setInterval(() => {
            this.processActivityQueue();
        }, ACTIVITY_CONFIG.ACTIVITY_UPDATE_INTERVAL);
        
        this.intervals.disconnectCheck = setInterval(() => {
            this.triggerDisconnectCheck();
        }, ACTIVITY_CONFIG.DISCONNECT_CHECK_INTERVAL);
        
        this.intervals.statusCheck = setInterval(() => {
            this.checkUserStatus();
        }, ACTIVITY_CONFIG.STATUS_CHECK_INTERVAL);
        
        this.setupActivityListeners();
        
        this.recordActivity('system_start');
        
        debugLog('✅ Activity tracking system initialized');
    },
    
    cleanup() {
        debugLog('🛑 Cleaning up activity tracking intervals');
        Object.keys(this.intervals).forEach(key => {
            if (this.intervals[key]) {
                clearInterval(this.intervals[key]);
                this.intervals[key] = null;
            }
        });
        this.enabled = false;
    },
    
    setupActivityListeners() {
        debugLog('🎯 Setting up activity listeners...');
        
        $(document).off('mousemove.activity keypress.activity scroll.activity click.activity');
        $(window).off('focus.activity blur.activity');
        
        let activityTimeout;
        const markUserActive = () => {
            const now = Date.now();
            
            if (now - this.lastInteraction < 1000) return;
            
            this.lastInteraction = now;
            this.userIsActive = true;
            
            clearTimeout(activityTimeout);
            activityTimeout = setTimeout(() => {
                this.recordActivity('interaction');
            }, 2000);
        };
        
        $(document).on('mousemove.activity keypress.activity scroll.activity click.activity', markUserActive);
        
        $(window).on('focus.activity', () => {
            this.recordActivity('window_focus');
        });
        
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.recordActivity('page_focus');
            }
        });
        
        debugLog('✅ Activity listeners set up');
    },
    
    recordActivity(activityType) {
        if (!this.enabled) return;
        
        const now = Date.now();
        
        if (now - this.lastActivityUpdate >= ACTIVITY_CONFIG.MIN_ACTIVITY_INTERVAL) {
            this.activityQueue.push({
                type: activityType,
                timestamp: now
            });
            
            debugLog(`📝 Recorded activity: ${activityType}`);
        }
    },
    
    processActivityQueue() {
        if (!this.enabled || this.activityQueue.length === 0) return;
        
        const latestActivity = this.activityQueue[this.activityQueue.length - 1];
        this.activityQueue = []; // Clear queue
        
        const now = Date.now();
        
        if (now - this.lastActivityUpdate >= ACTIVITY_CONFIG.MIN_ACTIVITY_INTERVAL) {
            this.sendActivityUpdate(latestActivity.type);
            this.lastActivityUpdate = now;
        }
    },
    
    sendActivityUpdate(activityType) {
        if (!this.enabled) return;
        
        debugLog(`📡 Sending activity update: ${activityType}`);
        
        $.ajax({
            url: 'api/update_activity.php',
            method: 'POST',
            data: { activity_type: activityType },
            dataType: 'json',
            timeout: 5000,
            success: (response) => {
                if (response.status === 'success') {
                    debugLog(`✅ Activity updated: ${activityType}`);
                    
                    if (response.afk_status_changed && response.returned_from_afk) {
                        debugLog('🔄 User returned from AFK automatically');
                        this.handleAFKStatusChange(false);
                        
                        if (typeof showToast === 'function') {
                            showToast('You are no longer AFK', 'info');
                        }
                        
                        setTimeout(() => {
                            if (typeof loadUsers === 'function') loadUsers();
                            if (typeof loadMessages === 'function') loadMessages();
                        }, 500);
                    }
                } else if (response.status === 'not_in_room') {
                    debugLog('❌ Not in room - stopping activity tracking');
                    this.cleanup();
                    
                    if (typeof checkUserStatus === 'function') {
                        checkUserStatus();
                    }
                }
            },
            error: (xhr, status, error) => {
                debugError(`⚠️ Activity update failed: ${status} - ${error}`);
                
                if (xhr.status === 403 || xhr.status === 401) {
                    this.cleanup();
                }
            }
        });
    },
    
    sendHeartbeat() {
        if (!this.enabled) return;
        
        debugLog('💓 Sending heartbeat');
        
        $.ajax({
            url: 'api/heartbeat.php',
            method: 'POST',
            dataType: 'json',
            timeout: 10000,
            success: (response) => {
                if (response.status === 'success') {
                    debugLog('💓 Heartbeat successful');
                } else {
                    debugError('💔 Heartbeat failed:', response.message);
                }
            },
            error: (xhr, status, error) => {
                debugError(`💔 Heartbeat error: ${status} - ${error}`);
                
                if (xhr.status === 403 || xhr.status === 401) {
                    debugError('💔 Session appears to be invalid');
                    this.cleanup();
                }
            }
        });
    },
    
    triggerDisconnectCheck() {
        if (!this.enabled) return;
        
        debugLog('🔍 Triggering disconnect check...');
        
        $.ajax({
            url: 'api/check_disconnects.php',
            method: 'GET',
            dataType: 'json',
            timeout: 15000,
            success: (response) => {
                if (response.status === 'success') {
                    const summary = response.summary;
                    debugLog('📊 Disconnect check completed:', summary);
                    
                    const totalChanges = summary.users_marked_afk + summary.users_disconnected + 
                                       summary.hosts_transferred + summary.rooms_deleted;
                    
                    if (totalChanges > 0) {
                        debugLog(`👥 ${totalChanges} changes detected, refreshing UI`);
                        
                        setTimeout(() => {
                            if (typeof loadUsers === 'function') loadUsers();
                            if (typeof loadMessages === 'function') loadMessages();
                        }, 1000);
                    }
                } else {
                    debugError('❌ Disconnect check failed:', response.message);
                }
            },
            error: (xhr, status, error) => {
                debugError('⚠️ Disconnect check error:', error);
            }
        });
    },
    
    checkUserStatus() {
        if (!kickDetectionEnabled) return;
        
        const now = Date.now();
        if (now - lastStatusCheck < 1000) return;
        lastStatusCheck = now;
        
        $.ajax({
            url: 'api/check_user_status.php',
            method: 'GET',
            dataType: 'json',
            timeout: 5000,
            success: (response) => {
                consecutiveErrors = 0;
                
                switch(response.status) {
                    case 'banned':
                        this.cleanup();
                        if (typeof handleUserBanned === 'function') {
                            handleUserBanned(response);
                        }
                        break;
                    case 'removed':
                        this.cleanup();
                        if (typeof handleUserKicked === 'function') {
                            handleUserKicked(response);
                        }
                        break;
                    case 'room_deleted':
                        this.cleanup();
                        if (typeof handleRoomDeleted === 'function') {
                            handleRoomDeleted(response);
                        }
                        break;
                    case 'not_in_room':
                        debugLog('👤 User not in room, redirecting to lounge');
                        this.cleanup();
                        window.location.href = 'lounge.php';
                        break;
                    case 'active':
                        debugLog('✅ User status: Active');
                        break;
                    case 'error':
                        debugError('❌ Server error:', response.message);
                        consecutiveErrors++;
                        break;
                }
            },
            error: (xhr, status, error) => {
                debugError('🔌 Status check failed:', { status, error });
                consecutiveErrors++;
                
                if (consecutiveErrors >= 5) {
                    debugError('🔥 Too many consecutive errors, redirecting');
                    this.cleanup();
                    alert('Connection lost. Redirecting to lounge.');
                    window.location.href = 'lounge.php';
                }
            }
        });
    },
    
    handleAFKStatusChange(isAFK) {
        currentUserAFK = isAFK;
        
        if (typeof updateAFKButton === 'function') {
            updateAFKButton();
        }
    }
};

if (typeof debugLog === 'undefined') {
    window.debugLog = function(...args) {
        console.log('[DEBUG]', ...args);
    };
}


// Add this to the top of room.js, after the global variables

// Request Management System
class RequestManager {
    constructor() {
        this.activeRequests = 0;
        this.maxConcurrentRequests = 2;
        this.requestQueue = [];
        this.isProcessingQueue = false;
        this.requestStats = new Map();
    }
    
    async makeRequest(options) {
        return new Promise((resolve, reject) => {
            this.requestQueue.push({ options, resolve, reject });
            this.processQueue();
        });
    }
    
    async processQueue() {
        if (this.isProcessingQueue || this.requestQueue.length === 0) {
            return;
        }
        
        this.isProcessingQueue = true;
        
        while (this.requestQueue.length > 0 && this.activeRequests < this.maxConcurrentRequests) {
            const { options, resolve, reject } = this.requestQueue.shift();
            this.executeRequest(options, resolve, reject);
        }
        
        this.isProcessingQueue = false;
        
        // Continue processing if queue still has items
        if (this.requestQueue.length > 0) {
            setTimeout(() => this.processQueue(), 100);
        }
    }
    
    executeRequest(options, resolve, reject) {
        this.activeRequests++;
        const startTime = Date.now();
        const url = options.url;
        
        // Track request stats
        if (!this.requestStats.has(url)) {
            this.requestStats.set(url, { count: 0, totalTime: 0, avgTime: 0 });
        }
        
        const originalSuccess = options.success || (() => {});
        const originalError = options.error || (() => {});
        
        options.success = (data) => {
            this.activeRequests--;
            const duration = Date.now() - startTime;
            
            // Update stats
            const stats = this.requestStats.get(url);
            stats.count++;
            stats.totalTime += duration;
            stats.avgTime = stats.totalTime / stats.count;
            
            if (DEBUG_MODE) {
                console.log(`✅ ${url}: ${duration}ms (avg: ${Math.round(stats.avgTime)}ms)`);
            }
            
            originalSuccess(data);
            resolve(data);
            this.processQueue();
        };
        
        options.error = (xhr, status, error) => {
            this.activeRequests--;
            if (DEBUG_MODE) {
                console.error(`❌ ${url}: ${error}`);
            }
            originalError(xhr, status, error);
            reject(error);
            this.processQueue();
        };
        
        $.ajax(options);
    }
    
    getStats() {
        const stats = {};
        this.requestStats.forEach((value, key) => {
            stats[key] = {
                count: value.count,
                avgTime: Math.round(value.avgTime)
            };
        });
        return stats;
    }
}

// Initialize request manager
const requestManager = new RequestManager();

// Wrapper function for managed requests
function managedAjax(options) {
    return requestManager.makeRequest(options);
}

// Add missing loadYouTubeAPI function
function loadYouTubeAPI() {
    if (window.YT && window.YT.Player) {
        youtubeAPIReady = true;
        initializeYouTubePlayer();
        return;
    }
    
    if (document.querySelector('script[src*="youtube"]')) {
        // Already loading
        return;
    }
    
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    const firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    
    debugLog('🎬 Loading YouTube API...');
}

// Combined data fetcher for all room data
function fetchAllRoomData() {
    const promises = [];
    
    // 1. Messages
    promises.push(
        managedAjax({
            url: 'api/get_messages.php',
            method: 'GET',
            data: { 
                room_id: roomId,
                limit: messageLimit,
                offset: 0
            },
            dataType: 'json'
        }).then(response => {
            handleMessagesResponse(response);
        }).catch(error => {
            console.error('Messages error:', error);
        })
    );
    
    // 2. Users
    promises.push(
        managedAjax({
            url: 'api/get_room_users.php',
            method: 'GET',
            data: { room_id: roomId },
            dataType: 'json'
        }).then(response => {
            handleUsersResponse(response);
        }).catch(error => {
            console.error('Users error:', error);
        })
    );
    
    // 3. Mentions
    promises.push(
        managedAjax({
            url: 'api/get_mentions.php',
            method: 'GET',
            dataType: 'json'
        }).then(response => {
            handleMentionsResponse(response);
        }).catch(error => {
            console.error('Mentions error:', error);
        })
    );
    
    // 4. Whispers
    promises.push(
        managedAjax({
            url: 'api/room_whispers.php',
            method: 'GET',
            data: { action: 'get_conversations' },
            dataType: 'json'
        }).then(response => {
            handleWhispersResponse(response);
        }).catch(error => {
            console.error('Whispers error:', error);
        })
    );
    
    // 5. YouTube data (if enabled)
    if (youtubeEnabled) {
        promises.push(
            managedAjax({
                url: 'api/youtube_combined.php',
                method: 'GET',
                dataType: 'json'
            }).then(response => {
                handleYouTubeResponse(response);
            }).catch(error => {
                console.error('YouTube error:', error);
            })
        );
    }
    
    return Promise.allSettled(promises);
}

// Response handlers
function handleMessagesResponse(data) {
    if (data.status === 'success') {
        const messages = data.messages || [];
        let html = '';
        
        if (messages.length === 0) {
            html = '<div class="empty-chat"><i class="fas fa-comments"></i><h5>No messages yet</h5><p>Start the conversation!</p></div>';
        } else {
            messages.forEach(msg => {
                html += renderMessage(msg);
            });
        }
        
        const chatbox = $('#chatbox');
        const wasAtBottom = isInitialLoad || (chatbox.scrollTop() + chatbox.innerHeight() >= chatbox[0].scrollHeight - 20);
        
        chatbox.html(html);
        
        if (wasAtBottom || isInitialLoad) {
            setTimeout(() => {
                chatbox.scrollTop(chatbox[0].scrollHeight);
            }, 50);
            isInitialLoad = false;
        }
        
        if (typeof applyAllAvatarFilters === 'function') {
            setTimeout(applyAllAvatarFilters, 100);
        }
    }
}

function handleUsersResponse(users) {
    if (Array.isArray(users)) {
        let html = '';
        
        if (users.length === 0) {
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
    }
}

function handleMentionsResponse(response) {
    if (response.status === 'success') {
        mentionNotifications = response.mentions;
        updateMentionCounter(response.unread_count);
        
        if (response.unread_count > 0 && !mentionPanelOpen) {
            showNewMentionNotification(response.unread_count);
        }
    }
}

function handleWhispersResponse(response) {
    if (response.status === 'success') {
        response.conversations.forEach(conv => {
            const userIdString = conv.other_user_id_string;
            
            if (conv.unread_count > 0 && !openWhispers.has(userIdString)) {
                const displayName = conv.username || conv.guest_name || 'Unknown';
                openWhisper(userIdString, displayName);
            }
            
            if (openWhispers.has(userIdString)) {
                const data = openWhispers.get(userIdString);
                data.unreadCount = conv.unread_count;
                openWhispers.set(userIdString, data);
                
                const unreadElement = $(`#whisper-unread-${data.safeId}`);
                if (conv.unread_count > 0) {
                    unreadElement.text(conv.unread_count).show();
                } else {
                    unreadElement.hide();
                }
            }
        });
    }
}

function handleYouTubeResponse(response) {
    if (response.status === 'success') {
        // Update sync data
        const sync = response.sync_data;
        if (sync.enabled && sync.sync_token !== lastSyncToken) {
            debugLog('🔄 Syncing player state:', sync);
            lastSyncToken = sync.sync_token;
            applySyncState(sync);
        }
        
        // Update queue data  
        const queueData = response.queue_data;
        playerQueue = queueData.queue || [];
        playerSuggestions = queueData.suggestions || [];
        currentVideoData = queueData.current_playing;
        
        renderQueue();
        renderSuggestions();
        updateVideoInfo();
    }
}

function applySyncState(sync) {
    if (!youtubePlayerReady) return;
    
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

// Replace all the individual intervals with one managed update cycle
let roomUpdateInterval = null;
let isUpdatingRoom = false;

function startRoomUpdates() {
    if (roomUpdateInterval) {
        clearInterval(roomUpdateInterval);
    }
    
    // Single update cycle every 3 seconds instead of multiple 1-second intervals
    roomUpdateInterval = setInterval(updateAllRoomData, 3000);
    updateAllRoomData(); // Initial load
    
    debugLog('🔄 Started managed room updates (every 3s)');
}

function updateAllRoomData() {
    if (isUpdatingRoom) {
        debugLog('⏸️ Skipping update - already in progress');
        return;
    }
    
    isUpdatingRoom = true;
    
    fetchAllRoomData().finally(() => {
        isUpdatingRoom = false;
    });
}

function stopRoomUpdates() {
    if (roomUpdateInterval) {
        clearInterval(roomUpdateInterval);
        roomUpdateInterval = null;
    }
    isUpdatingRoom = false;
    debugLog('🛑 Stopped room updates');
}

// Debug function to show request stats
window.showRequestStats = function() {
    console.table(requestManager.getStats());
    console.log('Active requests:', requestManager.activeRequests);
    console.log('Queued requests:', requestManager.requestQueue.length);
};



function checkIfFriend(userId, callback) {
    if (!userId || currentUser.type !== 'user') {
        callback(false);
        return;
    }
    
    if (friendshipCache.has(userId)) {
        callback(friendshipCache.get(userId));
        return;
    }
    
    $.ajax({
        url: 'api/friends.php',
        method: 'GET',
        data: { action: 'get' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const isFriend = response.friends.some(friend => 
                    friend.friend_user_id == userId && friend.status === 'accepted'
                );
                
                friendshipCache.set(userId, isFriend);
                
                if (friendshipCacheTimeout.has(userId)) {
                    clearTimeout(friendshipCacheTimeout.get(userId));
                }
                friendshipCacheTimeout.set(userId, setTimeout(() => {
                    friendshipCache.delete(userId);
                    friendshipCacheTimeout.delete(userId);
                }, 30000));
                
                callback(isFriend);
            } else {
                callback(false);
            }
        },
        error: function() {
            callback(false);
        }
    });
}

function clearFriendshipCache(userId = null) {
    if (userId) {
        friendshipCache.delete(userId);
        if (friendshipCacheTimeout.has(userId)) {
            clearTimeout(friendshipCacheTimeout.get(userId));
            friendshipCacheTimeout.delete(userId);
        }
    } else {
        friendshipCache.clear();
        friendshipCacheTimeout.forEach(timeout => clearTimeout(timeout));
        friendshipCacheTimeout.clear();
    }
}

function sendMessage() {
    const messageInput = $('#message');
    const message = messageInput.val().trim();
    
    if (!message) {
        messageInput.focus();
        return false;
    }
    
    debugLog('💬 Preparing to send message:', message);
    
    validateImagesInMessage(message).then(validation => {
        if (!validation.valid) {
            alert(`Cannot send message: ${validation.error}`);
            messageInput.focus();
            return;
        }
        
        sendValidatedMessage(message);
    }).catch(error => {
        console.error('Image validation error:', error);
        alert('Cannot send message: Failed to validate images');
        messageInput.focus();
    });
    
    return false;
}

async function validateImagesInMessage(message) {
    const imageRegex = /!\[([^\]]*)\]\(([^)]+)\)/g;
    const images = [];
    let match;
    
    while ((match = imageRegex.exec(message)) !== null) {
        images.push({
            alt: match[1],
            url: match[2].trim()
        });
    }
    
    if (images.length === 0) {
        return { valid: true };
    }
    
    console.log('Found images to validate:', images);
    
    for (let i = 0; i < images.length; i++) {
        const image = images[i];
        
        if (!isValidImageUrl(image.url)) {
            return {
                valid: false,
                error: `Invalid image URL: ${image.url}`
            };
        }
        
        const isAccessible = await testImageAccessibility(image.url);
        if (!isAccessible) {
            return {
                valid: false,
                error: `Image not accessible: ${image.url}`
            };
        }
    }
    
    return { valid: true };
}

function isValidImageUrl(url) {
    try {
        const urlObj = new URL(url);
        
        if (!['http:', 'https:'].includes(urlObj.protocol)) {
            return false;
        }
        
        const pathname = urlObj.pathname.toLowerCase();
        const hasValidExtension = /\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(url);
        
        return hasValidExtension;
    } catch (e) {
        return false;
    }
}

function testImageAccessibility(url) {
    return new Promise((resolve) => {
        const img = new Image();
        const timeout = setTimeout(() => {
            img.onload = img.onerror = null;
            resolve(false);
        }, 5000); // 5 second timeout
        
        img.onload = () => {
            clearTimeout(timeout);
            resolve(true);
        };
        
        img.onerror = () => {
            clearTimeout(timeout);
            resolve(false);
        };
        
        const currentDomain = window.location.hostname;
        let imageUrl = url;
        
        try {
            const urlHost = new URL(url).hostname;
            if (urlHost !== currentDomain) {
                imageUrl = `api/image_proxy.php?url=${encodeURIComponent(url)}`;
            }
        } catch (e) {
        }
        
        img.src = imageUrl;
    });
}

function sendValidatedMessage(message) {
    const messageInput = $('#message');
    const sendBtn = $('.btn-send-message');
    const originalText = sendBtn.html();
    
    sendBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
    
    if (typeof activityTracker !== 'undefined') {
        activityTracker.recordActivity('message_send');
    }
    
    const sendData = {
        room_id: roomId,
        message: message
    };
    
    if (typeof currentReplyTo !== 'undefined' && currentReplyTo) {
        sendData.reply_to = currentReplyTo;
    }
    
    $.ajax({
        url: 'api/send_message.php',
        method: 'POST',
        data: sendData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                messageInput.val('');
                if (typeof clearReplyInterface === 'function') {
                    clearReplyInterface();
                }
                
                if (response.afk_cleared) {
                    debugLog('🔄 AFK status was cleared due to sending message');
                    if (typeof activityTracker !== 'undefined') {
                        activityTracker.handleAFKStatusChange(false);
                    }
                    
                    if (typeof showToast === 'function') {
                        showToast('You are no longer AFK', 'info');
                    }
                    
                    setTimeout(() => {
                        if (typeof loadUsers === 'function') loadUsers();
                    }, 500);
                }
                
                if (typeof loadMessages === 'function') {
                    loadMessages();
                }
                
                setTimeout(() => {
                    if (typeof checkUserStatus === 'function') {
                        checkUserStatus();
                    }
                }, 200);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in sendMessage:', status, error);
            alert('AJAX error: ' + error);
        },
        complete: function() {
            sendBtn.prop('disabled', false).html(originalText);
            messageInput.focus();
        }
    });
}

function loadMessages(loadOlder = false) {
    if (isLoadingMessages) return;
    
    debugLog('Loading messages for roomId:', roomId, 'loadOlder:', loadOlder, 'offset:', messageOffset);
    
    isLoadingMessages = true;
    
    if (loadOlder) {
        $('.load-more-messages').html('<i class="fas fa-spinner fa-spin"></i> Loading...').prop('onclick', null);
    }
    
    managedAjax({
        url: 'api/get_messages.php',
        method: 'GET',
        data: { 
            room_id: roomId,
            limit: messageLimit,
            offset: loadOlder ? messageOffset : 0,
            load_older: loadOlder
        },
        dataType: 'json'
    }).then(response => {
        try {
            let data = typeof response === 'string' ? JSON.parse(response) : response;
            
            if (data.status === 'error') {
                throw new Error(data.message);
            }
            
            let messages = data.messages || [];
            let pagination = data.pagination || {};
            
            totalMessageCount = pagination.total_count || 0;
            hasMoreOlderMessages = pagination.has_more_older || false;
            
            let html = '';
            
            if (!Array.isArray(messages)) {
                console.error('Expected array from get_messages, got:', messages);
                html = '<div class="empty-chat"><i class="fas fa-exclamation-triangle"></i><h5>Error loading messages</h5><p>Please try refreshing the page</p></div>';
            } else if (messages.length === 0 && !loadOlder) {
                html = '<div class="empty-chat"><i class="fas fa-comments"></i><h5>No messages yet</h5><p>Start the conversation!</p></div>';
            } else {
                messages.forEach(msg => {
                    html += renderMessage(msg);
                });
            }
            
            const chatbox = $('#chatbox');
            
            if (loadOlder && messages.length > 0) {
                const currentScrollTop = chatbox.scrollTop();
                const currentScrollHeight = chatbox[0].scrollHeight;
                
                $('.load-more-messages').after(html);
                
                requestAnimationFrame(() => {
                    const newScrollHeight = chatbox[0].scrollHeight;
                    const heightDiff = newScrollHeight - currentScrollHeight;
                    chatbox.scrollTop(currentScrollTop + heightDiff);
                });
                
                messageOffset += messages.length;
                
            } else if (loadOlder && messages.length === 0) {
                $('.load-more-messages').remove();
                hasMoreOlderMessages = false;
                
            } else if (!loadOlder) {
                const wasAtBottom = isInitialLoad || (chatbox.scrollTop() + chatbox.innerHeight() >= chatbox[0].scrollHeight - 20);
                
                chatbox.html(html);
                messageOffset = messages.length;
                
                if (wasAtBottom || isInitialLoad) {
                    setTimeout(() => {
                        chatbox.scrollTop(chatbox[0].scrollHeight);
                    }, 50);
                    isInitialLoad = false;
                }
                
                if (!isInitialLoad && messages.length > lastMessageCount) {
                    playMessageNotification();
                    lastPlayedMessageCount = messages.length;
                }
                lastMessageCount = messages.length;
            }
            
            if (typeof applyAllAvatarFilters === 'function') {
                setTimeout(applyAllAvatarFilters, 100);
            }
            
        } catch (e) {
            console.error('JSON parse error:', e, response);
            $('#chatbox').html('<div class="empty-chat"><i class="fas fa-exclamation-triangle"></i><h5>Error loading messages</h5><p>Failed to parse server response</p></div>');
        }
    }).catch(error => {
        console.error('AJAX error in loadMessages:', error);
        if (loadOlder) {
            $('.load-more-messages').html('<i class="fas fa-exclamation-triangle"></i> Error - Click to retry').attr('onclick', 'loadOlderMessages()');
        } else {
            $('#chatbox').html('<div class="empty-chat"><i class="fas fa-wifi"></i><h5>Connection Error</h5><p>Failed to load messages. Check your connection.</p></div>');
        }
    }).finally(() => {
        isLoadingMessages = false;
    });
}

function loadOlderMessages() {
    debugLog('loadOlderMessages called. hasMoreOlderMessages:', hasMoreOlderMessages, 'isLoadingMessages:', isLoadingMessages);
    
    if (!isLoadingMessages && hasMoreOlderMessages) {
        loadMessages(true);
    } else if (isLoadingMessages) {
        debugLog('Already loading messages, skipping...');
    } else if (!hasMoreOlderMessages) {
        debugLog('No more older messages available');
    }
}

$(document).on('click', '.load-more-messages', function(e) {
    e.preventDefault();
    debugLog('Load more button clicked via event handler');
    loadOlderMessages();
});

function resetMessagePagination() {
    messageOffset = 0;
    totalMessageCount = 0;
    isLoadingMessages = false;
    hasMoreOlderMessages = false;
    isInitialLoad = true;
    $('.load-more-messages').remove();
}

function pollForNewMessages() {
    if (isLoadingMessages) return;
    
    const chatbox = $('#chatbox');
    const isAtBottom = chatbox.scrollTop() + chatbox.innerHeight() >= chatbox[0].scrollHeight - 100;
    
    if (isAtBottom) {
        $.ajax({
            url: 'api/get_messages.php',
            method: 'GET',
            data: { 
                room_id: roomId,
                limit: 5, // Just check last 5 messages
                offset: 0
            },
            success: function(response) {
                try {
                    let data = JSON.parse(response);
                    if (data.status === 'success' && data.messages && data.messages.length > 0) {
                        const latestMessage = data.messages[data.messages.length - 1];
                        const currentLatest = $('.chat-message').last().data('message-id');
                        
                        if (latestMessage.id != currentLatest) {
                            const wasAtBottom = chatbox.scrollTop() + chatbox.innerHeight() >= chatbox[0].scrollHeight - 50;
                            if (wasAtBottom) {
                                loadMessages(); // This will maintain bottom position
                            }
                        }
                    }
                } catch (e) {
                    console.debug('Poll error:', e);
                }
            },
            error: function() {
                console.debug('Poll request failed');
            }
        });
    }
}

function optimizeForMobile() {
    const isMobile = window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (isMobile) {
        messageLimit = 20; // Smaller batches for mobile
    }
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
    const hue = msg.user_avatar_hue !== undefined ? msg.user_avatar_hue : (msg.avatar_hue || 0);
    const saturation = msg.user_avatar_saturation !== undefined ? msg.user_avatar_saturation : (msg.avatar_saturation || 100);
    const bubbleHue = msg.bubble_hue || 0;
    const bubbleSat = msg.bubble_saturation || 100;
    
    if (msg.type === 'announcement') {
        return `
            <div class="system-message announcement-message">
                <div class="announcement-header">
                    <i class="fas fa-bullhorn"></i>
                    <span class="announcement-label">SITE ANNOUNCEMENT</span>
                </div>
                <div class="announcement-content">
                    ${msg.message}
                </div>
            </div>
        `;
    }
    
    if (msg.type === 'system' || msg.is_system) {
        const systemHue = msg.avatar_hue || msg.user_avatar_hue || 0;
        const systemSat = msg.avatar_saturation || msg.user_avatar_saturation || 100;
        
        return `
            <div class="system-message">
                <img src="images/${avatar}" 
                     style="filter: hue-rotate(${systemHue}deg) saturate(${systemSat}%);"
                     alt="System">
                <span>${msg.message}</span>
            </div>
        `;
    }
    
    const userColorClass = getUserColor(msg);
    const timestamp = new Date(msg.timestamp).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });

    const isRegisteredUser = msg.user_id && msg.user_id > 0;
    const isCurrentUser = msg.user_id_string === currentUserIdString;

    let avatarClickHandler = '';
    if (isRegisteredUser) {
        avatarClickHandler = `onclick="handleAvatarClick(event, ${msg.user_id}, '${(msg.username || '').replace(/'/g, "\\'")}')" style="cursor: pointer;"`;
    } else if (isCurrentUser) {
        avatarClickHandler = `onclick="showProfileEditor()" style="cursor: pointer;"`;
    }
    
    let badges = '';
    if (msg.is_admin) {
        badges += '<span class="user-badge badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
    }
    if (msg.is_moderator) {
        badges += '<span class="user-badge badge-moderator"><i class="fas fa-gavel"></i> Moderator</span>';
    }
    if (msg.is_host) {
        badges += '<span class="user-badge badge-host"><i class="fas fa-crown"></i> Host</span>';
    }
    if (msg.user_id && !msg.is_admin && !msg.is_moderator) {
        badges += '<span class="user-badge badge-verified"><i class="fas fa-check-circle"></i> Verified</span>';
    } else if (!msg.user_id) {
        badges += '<span class="user-badge badge-guest"><i class="fas fa-user"></i> Guest</span>';
    }
    
    let adminInfo = '';
    let moderatorActions = '';
    
    if ((isAdmin || isModerator) && msg.user_id_string !== currentUserIdString) {
        moderatorActions = `
            <div class="moderator-actions">
                <button class="btn btn-sm btn-outline-danger" onclick="showQuickBanModal('${msg.user_id_string}', '${name.replace(/'/g, "\\'")}', '${msg.ip_address || ''}')">
                    <i class="fas fa-ban"></i> Site Ban
                </button>
                <small class="text-danger">IP: ${msg.ip_address}</small>
            </div>
        `;
    }
    
    let replyContent = '';
    if (msg.reply_data) {
        const replyData = msg.reply_data;
        replyContent = `
            <div class="message-reply user-color-${replyData.color}" style="filter: hue-rotate(${replyData.bubble_hue}deg) saturate(${replyData.bubble_saturation}%);">
                <div class="reply-header" style="filter: hue-rotate(${-replyData.bubble_hue}deg) saturate(${replyData.bubble_saturation > 0 ? (10000/replyData.bubble_saturation) : 100}%);">
                    <img src="images/${replyData.avatar}" 
                         class="reply-author-avatar"
                         style="filter: hue-rotate(${replyData.avatar_hue}deg) saturate(${replyData.avatar_saturation}%);"
                         alt="${replyData.author}">
                    <span class="reply-author-name">${replyData.author}</span>
                    <i class="fas fa-external-link-alt reply-jump-icon" 
                       onclick="jumpToMessage(${replyData.id})" 
                       title="Jump to original message"></i>
                </div>
                <div class="reply-content"  >${replyData.message}</div>
            </div>
        `;
    }
    
    let messageActions = '';
    if (!msg.is_system && msg.type !== 'system' && msg.type !== 'announcement') {
        messageActions = `
            <div class="message-actions">
                <button class="message-action-btn" onclick="showReplyInterface(${msg.id}, '${name.replace(/'/g, "\\'")}', '${msg.message.replace(/<[^>]*>/g, '').replace(/'/g, "\\'").substring(0, 50)}...')" title="Reply">
                    <i class="fas fa-reply"></i>
                </button>
            </div>
        `;
    }
    
    let processedMessage = processMentionsInContent(msg.message, msg.user_id_string);
    
    return `
        <div class="chat-message ${userColorClass} ${msg.reply_data ? 'has-reply' : ''}" 
             data-message-id="${msg.id}" 
             data-type="${msg.type || 'chat'}"
             style="position: relative;">
            ${messageActions}
            <img src="images/${avatar}" 
                 class="message-avatar" 
                 style="filter: hue-rotate(${hue}deg) saturate(${saturation}%); ${avatarClickHandler ? 'cursor: pointer;' : ''}"
                 ${avatarClickHandler}
                 alt="${name}'s avatar">
            
            <!-- Message header moved outside the bubble -->
            <div class="message-header-external">
                <div class="message-header-left">
                    <div class="message-author">${name}</div>
                    ${badges ? `<div class="message-badges">${badges}</div>` : ''}
                </div>
                <div class="message-time">${timestamp}</div>
            </div>
            
            <!-- Message bubble with filters, but content isolated from filters -->
            <div class="message-bubble " style="filter: hue-rotate(${bubbleHue}deg) saturate(${bubbleSat}%);">
                ${replyContent}
                <!-- Message content wrapper that resets filters -->
                <div class="message-content-wrapper" style="filter: hue-rotate(${-bubbleHue}deg) saturate(${bubbleSat > 0 ? (10000/bubbleSat) : 100}%);">
                    <div class="message-content">${processedMessage}</div>
                    ${adminInfo}
                    ${moderatorActions}
                </div>
            </div>
        </div>
    `;
}

function processMentionsInContent(content, senderUserId) {
    if (content.includes(`data-user="${currentUserIdString}"`)) {
        content = content.replace(
            new RegExp(`<span class="mention" data-user="${currentUserIdString}"`, 'g'),
            '<span class="mention mention-self" data-user="' + currentUserIdString + '"'
        );
    }
    
    return content;
}

function loadUsers() {
    debugLog('Loading users for roomId:', roomId);
    
    managedAjax({
        url: 'api/get_room_users.php',
        method: 'GET',
        data: { room_id: roomId },
        dataType: 'json'
    }).then(response => {
        try {
            let users = typeof response === 'string' ? JSON.parse(response) : response;
            handleUsersResponse(users);
        } catch (e) {
            console.error('JSON parse error:', e, response);
            $('#userList').html('<div class="empty-users"><i class="fas fa-exclamation-triangle"></i><p>Error loading users</p></div>');
        }
    }).catch(error => {
        console.error('AJAX error in loadUsers:', error);
        $('#userList').html('<div class="empty-users"><i class="fas fa-wifi"></i><p>Connection error</p></div>');
    });
}

if (DEBUG_MODE) {
    setInterval(() => {
        console.log('📊 Request Stats:');
        window.showRequestStats();
    }, 30000);
}

function renderUser(user) {
    const avatar = user.avatar || user.guest_avatar || 'default_avatar.jpg';
    const name = user.display_name || user.username || user.guest_name || 'Unknown';
    const userIdString = user.user_id_string || 'unknown';
    const hue = user.avatar_hue || 0;
    const saturation = user.avatar_saturation || 100;

    const isRegisteredUser = user.user_type === 'registered';
    const isCurrentUser = user.user_id_string === currentUserIdString;

    let avatarClickHandler = '';
    if (isRegisteredUser) {
        avatarClickHandler = `onclick="handleAvatarClick(event, ${user.user_id}, '${(user.username || '').replace(/'/g, "\\'")}')" style="cursor: pointer;"`;
    } else if (isCurrentUser) {
        avatarClickHandler = `onclick="showProfileEditor()" style="cursor: pointer;"`;
    }
    
    let badges = '';

    if (isCurrentUser) {
        badges += '<span class="user-badge badge-you"><i class="fas fa-user-circle"></i> You</span>';
        currentUserAFK = user.is_afk;
        manualAFK = user.manual_afk;
    }

    if (user.is_afk) {
        const afkType = user.manual_afk ? 'Manual' : 'Auto';
        const afkDuration = user.afk_duration_minutes > 0 ? ` (${formatAFKDuration(user.afk_duration_minutes)})` : '';
        badges += `<span class="user-badge badge-afk" title="${afkType} AFK${afkDuration}"><i class="fas fa-bed"></i> AFK</span>`;
    }

    if (user.is_admin) {
        badges += '<span class="user-badge badge-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
    }

    if (user.is_moderator && !user.is_admin) {
        badges += '<span class="user-badge badge-moderator"><i class="fas fa-gavel"></i> Moderator</span>';
    }

    if (user.is_host) {
        badges += '<span class="user-badge badge-host"><i class="fas fa-crown"></i> Host</span>';
    }

    if (isRegisteredUser && !user.is_admin && !user.is_moderator) {
        badges += '<span class="user-badge badge-verified"><i class="fas fa-check-circle"></i> Verified</span>';
    } else if (!isRegisteredUser) {
        badges += '<span class="user-badge badge-guest"><i class="fas fa-user"></i> Guest</span>';
    }
    
    let actions = '';
    if (user.user_id_string !== currentUserIdString) {
        actions = `<div class="user-actions">`;
        
        const displayName = user.display_name || user.username || user.guest_name || 'Unknown';
        const whisperText = user.is_afk ? '' : '';
        actions += `
            <button class="btn whisper-btn ${user.is_afk ? 'afk-user' : ''}" onclick="openWhisper('${user.user_id_string}', '${displayName.replace(/'/g, "\\'")}')">
                <i class="fas fa-comment"></i> ${whisperText}
            </button>
        `;
        
        if (user.user_id && currentUser.type === 'user') {
            if (friendshipCache.has(user.user_id)) {
                const isFriend = friendshipCache.get(user.user_id);
                if (isFriend) {
                    const pmText = user.is_afk ? 'PM (AFK)' : 'PM';
                    actions += `
                        <button class="btn btn-primary ${user.is_afk ? 'afk-user' : ''}" onclick="openPrivateMessage(${user.user_id}, '${(user.username || '').replace(/'/g, "\\'")}')">
                            <i class="fas fa-envelope"></i> ${pmText}
                        </button>
                    `;
                } else {
                    actions += `
                        <button class="btn friend-btn" onclick="sendFriendRequest(${user.user_id}, '${(user.username || '').replace(/'/g, "\\'")}')">
                            <i class="fas fa-user-plus"></i>
                        </button>
                    `;
                }
            } else {
                actions += `<div id="friend-action-${user.user_id}" class="d-inline">
                    <button class="btn btn-secondary btn-sm" disabled>
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </button>
                </div>`;
                
                setTimeout(() => {
                    checkIfFriend(user.user_id, function(isFriend) {
                        const container = $(`#friend-action-${user.user_id}`);
                        if (container.length > 0) {
                            if (isFriend) {
                                const pmText = user.is_afk ? 'PM (AFK)' : 'PM';
                                container.html(`
                                    <button class="btn btn-primary ${user.is_afk ? 'afk-user' : ''}" onclick="openPrivateMessage(${user.user_id}, '${(user.username || '').replace(/'/g, "\\'")}')">
                                        <i class="fas fa-envelope"></i> ${pmText}
                                    </button>
                                `);
                            } else {
                                container.html(`
                                    <button class="btn friend-btn" onclick="sendFriendRequest(${user.user_id}, '${(user.username || '').replace(/'/g, "\\'")}')">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                `);
                            }
                        }
                    });
                }, 50);
            }
        }
        
        if ((isHost || isAdmin || isModerator) && !user.is_host && !user.is_admin && !user.is_moderator) {
            actions += `
                <button class="btn btn-ban-user" onclick="showBanModal('${user.user_id_string}', '${displayName.replace(/'/g, "\\'")}')">
                    <i class="fas fa-ban"></i> Ban
                </button>
            `;
        }

        if ((isAdmin || isModerator) && !user.is_admin && !(user.is_moderator && !isAdmin)) {
            actions += `
                <button class="btn btn-site-ban-user" onclick="showQuickBanModal('${user.user_id_string}', '${displayName.replace(/'/g, "\\'")}', '')">
                    <i class="fas fa-ban"></i> Site Ban
                </button>
            `;
        }
        
        actions += `</div>`;
    } else if (isCurrentUser) {
        // AFK toggle for current user
       /* actions = `
            <div class="user-actions">
                <button class="btn btn-toggle-afk ${currentUserAFK ? 'btn-warning' : 'btn-outline-warning'}" onclick="toggleAFK()">
                    ${currentUserAFK ? '<i class="fas fa-eye"></i> Back from AFK' : '<i class="fas fa-bed"></i> Go AFK'}
                </button>
            </div>
        `;*/
    }
    
    const userItemClass = user.is_afk ? 'user-item afk-user' : 'user-item';
    
    return `
        <div class="${userItemClass}">
            <div class="user-info-row">
                <img src="images/${avatar}" 
                     class="user-avatar ${user.is_afk ? 'afk-avatar' : ''}" 
                     style="filter: hue-rotate(${hue}deg) saturate(${saturation}%); ${avatarClickHandler ? 'cursor: pointer;' : ''}"
                     ${avatarClickHandler}
                     alt="${name}'s avatar">
                <div class="user-details">
                    <div class="user-name ${user.is_afk ? 'afk-name' : ''}">${name}</div>
                    <div class="user-badges-row">${badges}</div>
                </div>
            </div>
            ${actions}
        </div>
    `;
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
    
    //startPlayerSync();
    //startQueueUpdates();
    syncPlayerState();
    startYouTubeUpdates();
}

function onYouTubePlayerStateChange(event) {
    debugLog('🎬 Player state changed:', event.data);
    
    if (youtubePlayerReady) {
        const currentTime = youtubePlayer.getCurrentTime();
        const videoId = getCurrentVideoId();
        
        if (isHost) {
            switch (event.data) {
                case YT.PlayerState.PLAYING:
                    updatePlayerSync(videoId, currentTime, true);
                    break;
                case YT.PlayerState.PAUSED:
                    updatePlayerSync(videoId, currentTime, false);
                    break;
                case YT.PlayerState.ENDED:
                    setTimeout(() => skipToNextVideo(), 1000);
                    break;
            }
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
    updateYouTubeData();
   /* $.ajax({
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
    });*/
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
        container.removeClass('user-hidden').show();
        toggle.removeClass('hidden-player').html('<i class="fas fa-video-slash"></i>').attr('title', 'Hide Player');
        playerHidden = false;
        
        if (youtubePlayerReady) {
            setTimeout(() => syncPlayerState(), 500);
        }
    } else {
        container.addClass('user-hidden').hide();
        toggle.addClass('hidden-player').html('<i class="fas fa-video"></i>').attr('title', 'Show Player');
        playerHidden = true;
    }
    
    localStorage.setItem(`youtube_hidden_${roomId}`, playerHidden.toString());
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
    
    if (youtubeUpdateInterval) {
        clearInterval(youtubeUpdateInterval);
        youtubeUpdateInterval = null;
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
    isYoutubeUpdating = false;
}

function checkUserStatus() {
    activityTracker.checkUserStatus();
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

function initializeActivityTracking() {
    debugLog('🚀 Initializing room activity tracking...');
    
    activityTracker.init();
    
    
    if (roomId) {
        activityTracker.recordActivity('room_join');
    }
    
    $(document).on('submit', '.private-message-form', function() {
        activityTracker.recordActivity('private_message');
    });
    
    $(document).on('submit', '.whisper-form', function() {
        activityTracker.recordActivity('whisper');
    });
    
    $(document).on('click', '.btn-toggle-afk', function() {
        activityTracker.recordActivity('manual_activity');
    });
    
    debugLog('✅ Activity tracking initialization complete');
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
    activityTracker.recordActivity(activityType);
}

function triggerDisconnectCheck() {
    activityTracker.triggerDisconnectCheck();
}

function stopActivityTracking() {
    debugLog('🛑 Stopping activity tracking system');
    activityTracker.cleanup();
}

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
    debugLog('Displaying room settings modal with:', settings); // Debug log
    
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
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" style="color: #fff; background: transparent; border: none;">Access Control</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab" style="color: #fff; background: transparent; border: none;">Features</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="banlist-tab" data-bs-toggle="tab" data-bs-target="#banlist" type="button" role="tab" style="color: #fff; background: transparent; border: none;">
                                    <i class="fas fa-ban"></i> Banlist
                                </button>
                            </li>
                            ${(isAdmin || isModerator) ? `
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="admin-settings-tab" data-bs-toggle="tab" data-bs-target="#admin-settings" type="button" role="tab" style="color: #fff; background: transparent; border: none;">
                                    <i class="fas fa-shield-alt"></i> Admin
                                </button>
                            </li>
                            ` : ''}
                        </ul>
                        
                        <div class="tab-content" id="settingsTabsContent">
                            <!-- General Settings -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <form id="roomSettingsForm" class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="settingsRoomName" class="form-label">Room Name</label>
                                                <input type="text" class="form-control" id="settingsRoomName" value="${settings.name}" required maxlength="50" style="background: #333; border: 1px solid #555; color: #fff;">
                                            </div>
                                            <div class="mb-3">
                                                <label for="settingsCapacity" class="form-label">Capacity</label>
                                                <select class="form-select" id="settingsCapacity" required style="background: #333; border: 1px solid #555; color: #fff;">
                                                    <option value="5"${settings.capacity == 5 ? ' selected' : ''}>5 users</option>
                                                    <option value="10"${settings.capacity == 10 ? ' selected' : ''}>10 users</option>
                                                    <option value="20"${settings.capacity == 20 ? ' selected' : ''}>20 users</option>
                                                    <option value="50"${settings.capacity == 50 ? ' selected' : ''}>50 users</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="settingsTheme" class="form-label">Theme</label>
                                                <select class="form-select" id="settingsTheme" style="background: #333; border: 1px solid #555; color: #fff;">
                                                    <option value="default"${settings.theme === 'default' ? ' selected' : ''}>Default</option>
                                                    <option value="cyberpunk"${settings.theme === 'cyberpunk' ? ' selected' : ''}>Cyberpunk</option>
                                                    <option value="forest"${settings.theme === 'forest' ? ' selected' : ''}>Forest</option>
                                                    <option value="ocean"${settings.theme === 'ocean' ? ' selected' : ''}>Ocean</option>
                                                    <option value="sunset"${settings.theme === 'sunset' ? ' selected' : ''}>Sunset</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="settingsDescription" class="form-label">Description</label>
                                                <textarea class="form-control" id="settingsDescription" rows="4" maxlength="200" style="background: #333; border: 1px solid #555; color: #fff;">${settings.description || ''}</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="settingsIsRP"${settings.is_rp ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsIsRP">
                                                        <i class="fas fa-theater-masks"></i> Roleplay Room
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">Mark this room as suitable for roleplay</small>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Access Control Settings -->
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
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="settingsMembersOnly"${settings.members_only ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsMembersOnly">
                                                        <i class="fas fa-user-check"></i> Members Only
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">Only registered users can join</small>
                                            </div>
                                            
                                            ${currentUser.type === 'user' ? `
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="settingsFriendsOnly"${settings.friends_only ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsFriendsOnly">
                                                        <i class="fas fa-user-friends"></i> Friends Only
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">Only your friends can join</small>
                                            </div>
                                            ` : ''}
                                            
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="settingsInviteOnly"${settings.invite_only ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsInviteOnly">
                                                        <i class="fas fa-link"></i> Invite Only
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">Generate a special invite link</small>
                                                ${settings.invite_only && settings.invite_code ? `
                                                <div class="mt-2 p-2" style="background: #333; border-radius: 4px;">
                                                    <small class="text-success">Current invite link:</small><br>
                                                    <small style="word-break: break-all;">${window.location.origin}/lounge.php?invite=${settings.invite_code}</small>
                                                    <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="copyInviteLink('${settings.invite_code}')">
                                                        <i class="fas fa-copy"></i> Copy
                                                    </button>
                                                </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Features Tab -->
                            <div class="tab-pane fade" id="features" role="tabpanel">
                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="settingsYouTubeEnabled"${settings.youtube_enabled ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsYouTubeEnabled">
                                                        <i class="fab fa-youtube text-danger"></i> <strong>Enable YouTube Player</strong> <span class="betatext" /> <span class="betatext2" />
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">Allow synchronized video playback for all users in the room</small>
                                            </div>
                                            
                                            <div id="youtubePlayerInfo" style="display: ${settings.youtube_enabled ? 'block' : 'none'};">
                                                <div class="alert" style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.3); color: #b3d4fc; border-radius: 8px;">
                                                    <h6><i class="fas fa-info-circle"></i> YouTube Player Features:</h6>
                                                    <ul class="mb-0" style="padding-left: 1.2rem;">
                                                        <li><strong>Host Controls:</strong> Only hosts can control playback</li>
                                                        <li><strong>Video Suggestions:</strong> Users can suggest videos for approval</li>
                                                        <li><strong>Queue System:</strong> Approved videos are queued for playback</li>
                                                        <li><strong>Real-time Sync:</strong> All users see the same video</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="settingsDisappearingMessages"${settings.disappearing_messages ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsDisappearingMessages">
                                                        <i class="fas fa-clock"></i> <strong>Disappearing Messages</strong>
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">Messages automatically delete after a set time</small>
                                            </div>
                                            
                                            <div class="mb-3" id="messageLifetimeFieldSettings" style="display: ${settings.disappearing_messages ? 'block' : 'none'};">
                                                <label for="settingsMessageLifetime" class="form-label">Message Lifetime</label>
                                                <select class="form-select" id="settingsMessageLifetime" style="background: #333; border: 1px solid #555; color: #fff;">
                                                    <option value="5"${settings.message_lifetime_minutes == 5 ? ' selected' : ''}>5 minutes</option>
                                                    <option value="15"${settings.message_lifetime_minutes == 15 ? ' selected' : ''}>15 minutes</option>
                                                    <option value="30"${settings.message_lifetime_minutes == 30 ? ' selected' : ''}>30 minutes</option>
                                                    <option value="60"${settings.message_lifetime_minutes == 60 ? ' selected' : ''}>1 hour</option>
                                                    <option value="120"${settings.message_lifetime_minutes == 120 ? ' selected' : ''}>2 hours</option>
                                                    <option value="360"${settings.message_lifetime_minutes == 360 ? ' selected' : ''}>6 hours</option>
                                                    <option value="720"${settings.message_lifetime_minutes == 720 ? ' selected' : ''}>12 hours</option>
                                                    <option value="1440"${settings.message_lifetime_minutes == 1440 ? ' selected' : ''}>24 hours</option>
                                                </select>
                                            </div>
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

                            <!-- Admin Settings Tab (only for moderators/admins) -->
                            ${(isAdmin || isModerator) ? `
                            <div class="tab-pane fade" id="admin-settings" role="tabpanel">
                                <div class="mt-3">
                                    <div class="alert alert-warning" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); color: #ffc107;">
                                        <i class="fas fa-shield-alt"></i> <strong>Administrator Settings</strong><br>
                                        These options are only available to moderators and administrators.
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="settingsPermanentRoom"${settings.permanent ? ' checked' : ''}>
                                                    <label class="form-check-label" for="settingsPermanentRoom">
                                                        <i class="fas fa-star text-warning"></i> <strong>Permanent Room</strong>
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">
                                                    This room will never be automatically deleted, even when empty. 
                                                    It will be displayed at the top of the room list with a special indicator.
                                                </small>
                                                <div class="mt-2">
                                                    <small class="text-info">
                                                        <i class="fas fa-info-circle"></i> 
                                                        When the host of a permanent room leaves, they retain host privileges even while offline.
                                                    </small>
                                                </div>
                                                ${settings.permanent ? `
                                                <div class="mt-2">
                                                    <div class="alert alert-info" style="background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.3); color: #b3d4fc;">
                                                        <i class="fas fa-star"></i> This room is currently marked as permanent.
                                                    </div>
                                                </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
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
    
    $('#roomSettingsModal').remove();
    $('body').append(modalHtml);
    
    setupRoomSettingsHandlers();
    
    $('#banlist-tab').on('click', function() {
        loadBannedUsers();
    });
    
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
    
    $('#settingsDisappearingMessages').on('change', function() {
        if (this.checked) {
            $('#messageLifetimeFieldSettings').show();
        } else {
            $('#messageLifetimeFieldSettings').hide();
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
        theme: $('#settingsTheme').val(),
        has_password: $('#settingsHasPassword').is(':checked') ? 1 : 0,
        password: $('#settingsPassword').val(),
        allow_knocking: $('#settingsAllowKnocking').is(':checked') ? 1 : 0,
        youtube_enabled: $('#settingsYouTubeEnabled').is(':checked') ? 1 : 0,
        is_rp: $('#settingsIsRP').is(':checked') ? 1 : 0,
        friends_only: $('#settingsFriendsOnly').is(':checked') ? 1 : 0,
        invite_only: $('#settingsInviteOnly').is(':checked') ? 1 : 0,
        members_only: $('#settingsMembersOnly').is(':checked') ? 1 : 0,
        disappearing_messages: $('#settingsDisappearingMessages').is(':checked') ? 1 : 0,
        message_lifetime_minutes: $('#settingsDisappearingMessages').is(':checked') ? $('#settingsMessageLifetime').val() : 0,
        permanent: $('#settingsPermanentRoom').is(':checked') ? 1 : 0  
    };
    
    debugLog('Saving room settings:', formData);
    
    if (!formData.name) {
        alert('Room name is required');
        $('#settingsRoomName').focus();
        return;
    }
    
    if (formData.has_password && !formData.password) {
        if (!confirm('Password protection is enabled but no password was entered. Do you want to keep the existing password?')) {
            $('#settingsPassword').focus();
            return;
        }
    }
    
    const saveButton = $('#roomSettingsModal .btn-primary');
    const originalText = saveButton.html();
    saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
    
    $.ajax({
        url: 'api/update_room.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            debugLog('Update room response:', response);
            if (response.status === 'success') {
                let message = 'Room settings updated successfully!';
                
                if (formData.permanent) {
                    message += ' This room is now permanent.';
                }
                
                if (response.invite_code) {
                    const inviteLink = window.location.origin + '/' + response.invite_link;
                    message += '\\n\\nInvite link: ' + inviteLink;
                    
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(inviteLink).then(() => {
                            message += '\\n\\n(Invite link copied to clipboard!)';
                            alert(message);
                        }).catch(() => {
                            alert(message);
                        });
                    } else {
                        alert(message);
                    }
                } else {
                    alert(message);
                }
                
                $('#roomSettingsModal').modal('hide');
                
                const needsReload = 
                    formData.youtube_enabled !== youtubeEnabled ||
                    formData.theme !== (roomTheme || 'default') ||
                    formData.disappearing_messages !== (typeof disappearingMessages !== 'undefined' ? disappearingMessages : false);
                
                if (needsReload) {
                    showToast('Settings changed. Refreshing room...', 'info');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    loadMessages();
                    loadUsers();
                }
            } else {
                alert('Error: ' + response.message);
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
                
                if (res.status === 'permanent_room_leave') {
                    if (confirm(res.message + ' Are you sure you want to leave?')) {
                        $.ajax({
                            url: 'api/leave_room.php',
                            method: 'POST',
                            data: { 
                                room_id: roomId,
                                action: 'permanent_room_leave'
                            },
                            success: function(leaveResponse) {
                                try {
                                    let leaveRes = JSON.parse(leaveResponse);
                                    if (leaveRes.status === 'success') {
                                        alert(leaveRes.message || 'Left room successfully');
                                        window.location.href = 'lounge.php';
                                    } else {
                                        alert('Error: ' + leaveRes.message);
                                    }
                                } catch (e) {
                                    console.error('JSON parse error:', e, leaveResponse);
                                    alert('Error leaving room');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX error leaving permanent room:', error);
                                alert('Error leaving room: ' + error);
                            }
                        });
                    }
                } else if (res.status === 'host_leaving') {
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







let openWhispers = new Map();
let whisperTabs = [];

function escapeSelector(str) {
    return str.replace(/([ #;&,.+*~':"!^$[\]()=>|\/])/g, '\\$1');
}

function createSafeId(str) {
    return str.replace(/[^a-zA-Z0-9_-]/g, '_');
}

function openWhisper(userIdString, username) {
    debugLog('Opening whisper for user:', userIdString, username);
    
    if (openWhispers.has(userIdString)) {
        showWhisperTab(userIdString);
        return;
    }
    
    const safeId = createSafeId(userIdString);
    const tabId = `whisper-tab-${safeId}`;
    const windowId = `whisper-${safeId}`;
    
    const tabHtml = `
        <div class="whisper-tab" id="${tabId}" onclick="toggleWhisperTab('${userIdString.replace(/'/g, "\\'")}')">
            <span class="whisper-tab-title">💬 ${username}</span>
            <span class="whisper-tab-unread" id="whisper-unread-${safeId}" style="display: none;">0</span>
            <button class="whisper-tab-close" onclick="event.stopPropagation(); closeWhisper('${userIdString.replace(/'/g, "\\'")}');" title="Close">&times;</button>
        </div>
    `;
    
    const windowHtml = `
        <div class="whisper-window" id="${windowId}">
            <div class="whisper-body" id="whisper-body-${safeId}">
                Loading messages...
            </div>
            <div class="whisper-input">
                <form class="whisper-form" onsubmit="sendWhisper('${userIdString.replace(/'/g, "\\'")}'); return false;">
                    <input type="text" id="whisper-input-${safeId}" placeholder="Type a whisper..." required>
                    <button type="submit">Send</button>
                </form>
            </div>
        </div>
    `;
    
    if ($('#whisper-tabs').length === 0) {
        $('body').append('<div id="whisper-tabs"></div>');
    }
    $('#whisper-tabs').append(tabHtml);
    $('body').append(windowHtml);
    
    openWhispers.set(userIdString, { username: username, unreadCount: 0, safeId: safeId });
    whisperTabs.push(userIdString);
    
    loadWhisperMessages(userIdString);
    showWhisperTab(userIdString);
}

function toggleWhisperTab(userIdString) {
    debugLog('Toggling whisper tab for:', userIdString);
    const data = openWhispers.get(userIdString);
    if (!data) return;
    
    const safeId = data.safeId;
    const window = $(`#whisper-${safeId}`);
    const tab = $(`#whisper-tab-${safeId}`);
    const isCollapsed = window.hasClass('collapsed');
    
    $('.whisper-window').addClass('collapsed');
    $('.whisper-tab').removeClass('active');
    
    if (isCollapsed) {
        window.removeClass('collapsed');
        tab.addClass('active');
        markWhisperAsRead(userIdString);
        setTimeout(() => {
            $(`#whisper-input-${safeId}`).focus();
        }, 300);
    } else {
        window.addClass('collapsed');
        tab.removeClass('active');
    }
}

function showWhisperTab(userIdString) {
    debugLog('Showing whisper tab for:', userIdString);
    const data = openWhispers.get(userIdString);
    if (!data) return;
    
    const safeId = data.safeId;
    $('.whisper-window').addClass('collapsed');
    $('.whisper-tab').removeClass('active');
    
    const window = $(`#whisper-${safeId}`);
    const tab = $(`#whisper-tab-${safeId}`);
    
    window.removeClass('collapsed');
    tab.addClass('active');
    markWhisperAsRead(userIdString);
    
    setTimeout(() => {
        $(`#whisper-input-${safeId}`).focus();
    }, 300);
}

function closeWhisper(userIdString) {
    const data = openWhispers.get(userIdString);
    if (!data) return;
    
    const safeId = data.safeId;
    $(`#whisper-tab-${safeId}`).remove();
    $(`#whisper-${safeId}`).remove();
    openWhispers.delete(userIdString);
    whisperTabs = whisperTabs.filter(id => id !== userIdString);
    
    if (whisperTabs.length === 0) {
        $('#whisper-tabs').remove();
    }
}

function sendWhisper(recipientUserIdString) {
    const data = openWhispers.get(recipientUserIdString);
    if (!data) return false;
    
    const safeId = data.safeId;
    const input = $(`#whisper-input-${safeId}`);
    const message = input.val().trim();
    
    if (!message) return false;
    
    $.ajax({
        url: 'api/room_whispers.php',
        method: 'POST',
        data: {
            action: 'send',
            recipient_user_id_string: recipientUserIdString,
            message: message
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                input.val('');
                loadWhisperMessages(recipientUserIdString);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Send whisper error:', error);
            alert('Error sending whisper: ' + error);
        }
    });
    
    return false;
}

function loadWhisperMessages(otherUserIdString) {
    debugLog('Loading whisper messages for:', otherUserIdString);
    
    managedAjax({
        url: 'api/room_whispers.php',
        method: 'GET',
        data: {
            action: 'get',
            other_user_id_string: otherUserIdString
        },
        dataType: 'json'
    }).then(response => {
        debugLog('Whisper messages response:', response);
        if (response.status === 'success') {
            displayWhisperMessages(otherUserIdString, response.messages);
        } else {
            console.error('API error:', response.message);
            const data = openWhispers.get(otherUserIdString);
            if (data) {
                $(`#whisper-body-${data.safeId}`).html('<div style="color: #f44336; padding: 10px;">Error: ' + response.message + '</div>');
            }
        }
    }).catch(error => {
        console.error('AJAX error details:', error);
        const data = openWhispers.get(otherUserIdString);
        if (data) {
            $(`#whisper-body-${data.safeId}`).html('<div style="color: #f44336; padding: 10px;">Failed to load messages. Check console for details.</div>');
        }
    });
}

function displayWhisperMessages(otherUserIdString, messages) {
    const data = openWhispers.get(otherUserIdString);
    if (!data) {
        console.error('No whisper data found for user:', otherUserIdString);
        return;
    }
    
    const safeId = data.safeId;
    const container = $(`#whisper-body-${safeId}`);
    
    if (container.length === 0) {
        console.error('Whisper container not found:', `#whisper-body-${safeId}`);
        return;
    }
    
    const wasAtBottom = container[0].scrollHeight > 0 ? 
        (container.scrollTop() + container.innerHeight() >= container[0].scrollHeight - 20) : true;
    
    let html = '';
    
    if (messages.length === 0) {
        html = '<div style="text-align: center; color: #999; padding: 20px;">No whispers yet</div>';
    } else {
        messages.forEach(msg => {
    const isOwn = msg.sender_user_id_string === currentUserIdString;
    const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    
    const author = isOwn ? 
        (currentUser.name || currentUser.username || 'You') : 
        (msg.sender_username || msg.sender_guest_name || 'Unknown');
    const avatar = isOwn ? 
        (currentUser.avatar || 'default_avatar.jpg') : 
        (msg.sender_avatar || 'default_avatar.jpg');
    const userColor = isOwn ? 
        (currentUser.color || 'blue') : 
        (msg.sender_color || 'blue');
    
    const avatarHue = isOwn ? (currentUser.avatar_hue || 0) : (msg.sender_avatar_hue || 0);
    const avatarSat = isOwn ? (currentUser.avatar_saturation || 100) : (msg.sender_avatar_saturation || 100);
    const bubbleHue = isOwn ? (currentUser.bubble_hue || 0) : (msg.bubble_hue || 0);
const bubbleSat = isOwn ? (currentUser.bubble_saturation || 100) : (msg.bubble_saturation || 100);
    
    html += `
        <div class="private-chat-message ${isOwn ? 'sent' : 'received'}">
            <img src="images/${avatar}" 
                 class="private-message-avatar" 
                 style="filter: hue-rotate(${avatarHue}deg) saturate(${avatarSat}%);"
                 alt="${author}'s avatar">
            <div class="private-message-bubble ${isOwn ? 'sent' : 'received'} user-color-${userColor}" style="filter: hue-rotate(${bubbleHue}deg) saturate(${bubbleSat}%);">
                <div class="private-message-header-info">
                    <div class="private-message-author">${author}</div>
                    <div class="private-message-time">${time}</div>
                </div>
                
                <div class="private-message-content">${msg.message}</div>
            </div>
        </div>
    `;
});
    }
    
    container.html(html);
    
    if (wasAtBottom && container[0].scrollHeight > 0) {
        container.scrollTop(container[0].scrollHeight);
    }
}

function markWhisperAsRead(userIdString) {
    const data = openWhispers.get(userIdString);
    if (data && data.unreadCount > 0) {
        data.unreadCount = 0;
        openWhispers.set(userIdString, data);
        $(`#whisper-unread-${data.safeId}`).hide().text('0');
    }
}

function checkForNewWhispers() {
    $.ajax({
        url: 'api/room_whispers.php',
        method: 'GET',
        data: { action: 'get_conversations' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                response.conversations.forEach(conv => {
                    const userIdString = conv.other_user_id_string;
                    
                    if (conv.unread_count > 0 && !openWhispers.has(userIdString)) {
                        const displayName = conv.username || conv.guest_name || 'Unknown';
                        openWhisper(userIdString, displayName);
                    }
                    
                    if (openWhispers.has(userIdString)) {
                        const data = openWhispers.get(userIdString);
                        data.unreadCount = conv.unread_count;
                        openWhispers.set(userIdString, data);
                        
                        const unreadElement = $(`#whisper-unread-${data.safeId}`);
                        if (conv.unread_count > 0) {
                            unreadElement.text(conv.unread_count).show();
                        } else {
                            unreadElement.hide();
                        }
                    }
                });
            }
        },
        error: function() {
            // Silently fail
        }
    });
    
    openWhispers.forEach((data, userIdString) => {
    const safeId = data.safeId;
    const input = $(`#whisper-input-${safeId}`);
    
    // Only update if user is not actively typing in this specific whisper
    if (!input.is(':focus') || input.val().length === 0) {
        // This will be handled by fetchAllRoomData, no need for individual calls
    }
});
if ($('#friendsPanel').is(':visible')) {
    // This will be handled by fetchAllRoomData
}
}








function sendFriendRequest(userId, username) {
    if (!userId || !username) {
        alert('Invalid user data');
        return;
    }
    
    if (confirm('Send friend request to ' + username + '?')) {
        $.ajax({
            url: 'api/friends.php',
            method: 'POST',
            data: {
                action: 'add',
                friend_username: username
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Friend request sent to ' + username + '!');
                    clearFriendshipCache(userId);
                    loadUsers(); // Refresh user list
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Send friend request error:', error);
                alert('Error sending friend request: ' + error);
            }
        });
    }
}



window.debugPagination = function() {
    console.log('=== PAGINATION DEBUG ===');
    console.log('messageOffset:', messageOffset);
    console.log('messageLimit:', messageLimit);
    console.log('hasMoreOlderMessages:', hasMoreOlderMessages);
    console.log('isLoadingMessages:', isLoadingMessages);
    console.log('totalMessageCount:', totalMessageCount);
    console.log('Load more button exists:', $('.load-more-messages').length > 0);
    console.log('Current messages in DOM:', $('.chat-message').length);
};


$(document).ready(function() {
    debugLog('🏠 Room loaded, roomId:', roomId);

    if (!roomId) {
        console.error('❌ Invalid room ID, redirecting to lounge');
        window.location.href = 'lounge.php';
        return;
    }

    // Form handlers
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

    // Initialize features
    addAFKStyles();
    setTimeout(updateAFKButton, 1000);

    // Scroll handler
    $(document).on('scroll', '#chatbox', function() {
        userIsScrolling = true;
        setTimeout(function() {
            userIsScrolling = false;
        }, 1000);
    });

    // Visibility handlers
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateUserActivity('page_focus');
            setTimeout(checkUserStatus, 100);
        }
    });

    $(window).on('focus', function() {
        setTimeout(checkUserStatus, 100);
    });

    // YouTube setup
    if (typeof youtubeEnabledGlobal !== 'undefined' && youtubeEnabledGlobal) {
        debugLog('🎬 YouTube enabled for this room');
        youtubeEnabled = true;
        isYoutubeHost = isHost;
        
        // Restore hidden state
        const savedHidden = localStorage.getItem(`youtube_hidden_${roomId}`);
        if (savedHidden === 'true') {
            $('.youtube-player-container').addClass('user-hidden').hide();
            $('.youtube-player-toggle').addClass('hidden-player').html('<i class="fas fa-video"></i>').attr('title', 'Show Player');
            playerHidden = true;
        }
        
        // YouTube API callback
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

    // Initialize activity tracking
    initializeActivityTracking();

    // Host-specific features
    if (isHost) {
        debugLog('🚪 User is host, starting knock checking...');
        setInterval(checkForKnocks, 5000); // Reduced frequency
        setTimeout(checkForKnocks, 1000);
    }

    // Initialize mentions and replies
    setTimeout(() => {
        initializeMentionsAndReplies();
        addMentionHighlightCSS();
    }, 1000);

    // Initialize private messaging for registered users
    if (currentUser.type === 'user') {
        setTimeout(initializePrivateMessaging, 1000);
    }

    // CRITICAL: Replace all the individual intervals with managed updates
    
    // Remove these old intervals - they're causing the request storm:
    // setInterval(loadMessages, 1000);
    // setInterval(loadUsers, 1000);
    // setInterval(checkForNewWhispers, 1000);
    // setInterval(checkForMentions, 1000);
    
    // Use the new managed update system instead:
    startRoomUpdates();

    // Keep only essential intervals at lower frequencies
    setTimeout(checkUserStatus, 1000);
    kickDetectionInterval = setInterval(checkUserStatus, 10000); // Reduced from 5s to 10s
    
    // Focus message input
    $('#message').focus();
    
    debugLog('✅ Room initialization complete with managed updates');
});

$(window).on('beforeunload', function() {
    stopRoomUpdates(); // Stop managed updates
    
    if (mentionCheckInterval) {
        clearInterval(mentionCheckInterval);
        mentionCheckInterval = null;
    }
    
    stopYouTubePlayer();
    stopActivityTracking();
    stopKickDetection();
});

function toggleMobileUsers() {
    const userList = $('#userList');
    const toggleBtn = $('.mobile-users-toggle');
    
    if (userList.hasClass('expanded')) {
        userList.removeClass('expanded');
        toggleBtn.removeClass('expanded');
    } else {
        userList.addClass('expanded');
        toggleBtn.addClass('expanded');
    }
}

function toggleMobileQueue(section) {
    const tabContent = $('#youtube-queue-content');
    const queueBtn = $('.mobile-queue-btn').eq(0);
    const suggestionsBtn = $('.mobile-queue-btn').eq(1);
    
    $('.mobile-queue-btn').removeClass('active expanded');
    
    if (section === 'queue') {
        queueBtn.addClass('active');
        $('#queue-tab').tab('show');
    } else {
        suggestionsBtn.addClass('active');
        $('#suggestions-tab').tab('show');
    }
    
    if (tabContent.hasClass('expanded')) {
        tabContent.removeClass('expanded');
    } else {
        tabContent.addClass('expanded');
        if (section === 'queue') {
            queueBtn.addClass('expanded');
        } else {
            suggestionsBtn.addClass('expanded');
        }
    }
}

let openPrivateChats = new Map();
let friends = [];

function initializePrivateMessaging() {
    if (currentUser.type !== 'user') return;
    
    debugLog('💬 Initializing private messaging...');
    loadFriends();
    
    // Remove the old interval - whispers are now handled by fetchAllRoomData
    // setInterval(checkForNewPrivateMessages, 3000);
    
    debugLog('✅ Private messaging initialized (using managed updates)');
}

function showFriendsPanel() {
    $('#friendsPanel').show();
    loadFriends();
    loadConversations();
}

function closeFriendsPanel() {
    $('#friendsPanel').hide();
}

function loadFriends() {
    debugLog('Loading friends...');
    managedAjax({
        url: 'api/friends.php',
        method: 'GET',
        data: { action: 'get' },
        dataType: 'json'
    }).then(response => {
        debugLog('Friends response:', response);
        if (response.status === 'success') {
            friends = response.friends;
            updateFriendsPanel();
        } else {
            $('#friendsList').html('<p class="text-danger">Error: ' + response.message + '</p>');
        }
    }).catch(error => {
        console.error('Friends API error:', error);
        $('#friendsList').html('<p class="text-danger">Failed to load friends. Check console for details.</p>');
    });
}

function updateFriendsPanel() {
    debugLog('Updating friends panel with:', friends);
    
    let html = `
        <div class="mb-3">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="addFriendInput" placeholder="Username to add" style="background: #333; border: 1px solid #555; color: #fff;">
                <button class="btn btn-primary" onclick="addFriend()">
                    <i class="fas fa-user-plus"></i> Add
                </button>
            </div>
        </div>
        <div class="mb-3">
            <h6 style="color: #e0e0e0;">Recent Conversations</h6>
            <div id="conversationsList">Loading conversations...</div>
        </div>
        <div>
            <h6 style="color: #e0e0e0;">Friends</h6>
            <div id="friendsListContent">
    `;
    
    if (!friends || friends.length === 0) {
        html += '<p class="text-muted small">No friends yet. Add someone using the form above!</p>';
    } else {
        friends.forEach(friend => {
            if (friend.status === 'accepted') {
                html += `
                    <div class="d-flex align-items-center mb-2 p-2" style="background: #333; border-radius: 4px;">
                        <img src="images/${friend.avatar || 'default_avatar.jpg'}" width="24" height="24" class="me-2" style="border-radius: 2px; filter: hue-rotate(${friend.avatar_hue || 0}deg) saturate(${friend.avatar_saturation || 100}%);">
                        <div class="flex-grow-1">
                            <small style="color: #e0e0e0;">${friend.username}</small>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="openPrivateMessage(${friend.friend_user_id}, '${friend.username}')">
                            <i class="fas fa-comment"></i>
                        </button>
                    </div>
                `;
            } else if (friend.status === 'pending' && friend.request_type === 'received') {
                html += `
                    <div class="d-flex align-items-center mb-2 p-2" style="background: #4a4a2a; border-radius: 4px;">
                        <img src="images/${friend.avatar || 'default_avatar.jpg'}" width="24" height="24" class="me-2" style="border-radius: 2px;">
                        <div class="flex-grow-1">
                            <small style="color: #e0e0e0;">${friend.username}</small>
                            <br><small class="text-warning">Pending request</small>
                        </div>
                        <button class="btn btn-sm btn-success" onclick="acceptFriend(${friend.id})">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                `;
            }
        });
    }
    
    html += '</div></div>';
    $('#friendsList').html(html);
    
    loadConversations();
}

function addFriend() {
    const username = $('#addFriendInput').val().trim();
    if (!username) return;
    
    $.ajax({
        url: 'api/friends.php',
        method: 'POST',
        data: {
            action: 'add',
            friend_username: username
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#addFriendInput').val('');
                alert('Friend request sent!');
                loadFriends();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function acceptFriend(friendId) {
    $.ajax({
        url: 'api/friends.php',
        method: 'POST',
        data: {
            action: 'accept',
            friend_id: friendId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('Friend request accepted!');
                loadFriends();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function loadConversations() {
    debugLog('Loading conversations...');
    managedAjax({
        url: 'api/private_messages.php',
        method: 'GET',
        data: { action: 'get_conversations' },
        dataType: 'json'
    }).then(response => {
        debugLog('Conversations response:', response);
        if (response.status === 'success') {
            displayConversations(response.conversations);
        } else {
            $('#conversationsList').html('<p class="text-danger small">Error loading conversations</p>');
        }
    }).catch(error => {
        console.error('Conversations API error:', error);
        $('#conversationsList').html('<p class="text-danger small">Failed to load conversations</p>');
    });
}

function displayConversations(conversations) {
    let html = '';
    
    if (conversations.length === 0) {
        html = '<p class="text-muted small">No conversations yet</p>';
    } else {
        conversations.forEach(conv => {
            const unreadBadge = conv.unread_count > 0 ? `<span class="badge bg-danger">${conv.unread_count}</span>` : '';
            html += `
                <div class="d-flex align-items-center mb-2 p-2" style="background: #333; border-radius: 4px; cursor: pointer;" onclick="openPrivateMessage(${conv.other_user_id}, '${conv.username}')">
                    <img src="images/${conv.avatar}" width="24" height="24" class="me-2" style="border-radius: 2px; filter: hue-rotate(${conv.avatar_hue || 0}deg) saturate(${conv.avatar_saturation || 100}%);">
                    <div class="flex-grow-1">
                        <small style="color: #e0e0e0;">${conv.username}</small>
                        <br><small class="text-muted">${conv.last_message ? conv.last_message.substring(0, 30) + '...' : 'No messages'}</small>
                    </div>
                    ${unreadBadge}
                </div>
            `;
        });
    }
    
    $('#conversationsList').html(html);
}

function openPrivateMessage(userId, username) {
    debugLog('=== DEBUG openPrivateMessage ===');
    debugLog('Received userId:', userId, 'Type:', typeof userId);
    debugLog('Received username:', username, 'Type:', typeof username);
    debugLog('Current user:', currentUser);
    
    if (openPrivateChats.has(userId)) {
        $(`#pm-${userId}`).show();
        return;
    }
    
    const windowHtml = `
        <div class="private-message-window" id="pm-${userId}">
            <div class="private-message-header">
                <h6 class="private-message-title">Chat with ${username}</h6>
                <button class="private-message-close" onclick="closePrivateMessage(${userId})">&times;</button>
            </div>
            <div class="private-message-body" id="pm-body-${userId}">
                Loading messages...
            </div>
            <div class="private-message-input">
                <form class="private-message-form" onsubmit="sendPrivateMessage(${userId}); return false;">
                    <input type="text" id="pm-input-${userId}" placeholder="Type a message..." required>
                    <button type="submit">Send</button>
                </form>
            </div>
        </div>
    `;
    
    $('body').append(windowHtml);
    openPrivateChats.set(userId, { username: username, color: 'blue' }); // Default until we fetch
    
    debugLog('Fetching user info for userId:', userId);
    $.ajax({
        url: 'api/get_user_info.php',
        method: 'GET',
        data: { user_id: userId },
        dataType: 'json',
        success: function(response) {
            debugLog('User info response:', response);
            if (response.status === 'success') {
                const chatData = openPrivateChats.get(userId);
                chatData.color = response.user.color || 'blue';
                chatData.avatar = response.user.avatar || 'default_avatar.jpg';
                openPrivateChats.set(userId, chatData);
                debugLog('Fetched user color:', response.user.color);
                loadPrivateMessages(userId);
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to fetch user info:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                userId: userId
            });
            debugLog('Failed to fetch user info, using default color');
            loadPrivateMessages(userId);
        }
    });
}

function closePrivateMessage(userId) {
    $(`#pm-${userId}`).remove();
    openPrivateChats.delete(userId);
}

function sendPrivateMessage(recipientId) {
    debugLog('=== DEBUG sendPrivateMessage ===');
    debugLog('Sending message to recipientId:', recipientId, 'Type:', typeof recipientId);
    
    const input = $(`#pm-input-${recipientId}`);
    const message = input.val().trim();
    
    debugLog('Message content:', message);
    
    if (!message) return false;
    
    const requestData = {
        action: 'send',
        recipient_id: recipientId,
        message: message
    };
    
    debugLog('Request data being sent:', requestData);
    
    $.ajax({
        url: 'api/private_messages.php',
        method: 'POST',
        data: requestData,
        dataType: 'json',
        success: function(response) {
            debugLog('Send message response:', response);
            if (response.status === 'success') {
                input.val('');
                loadPrivateMessages(recipientId);
            } else {
                console.error('API Error:', response.message);
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Send message AJAX error:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                recipientId: recipientId,
                requestData: requestData
            });
            alert('Error sending message: ' + error);
        }
    });
    
    return false;
}

function loadPrivateMessages(otherUserId) {
    debugLog('Loading private messages with user:', otherUserId);
    
    managedAjax({
        url: 'api/private_messages.php',
        method: 'GET',
        data: {
            action: 'get',
            other_user_id: otherUserId
        },
        dataType: 'json'
    }).then(response => {
        debugLog('Load messages response:', response);
        if (response.status === 'success') {
            displayPrivateMessages(otherUserId, response.messages);
        } else {
            $(`#pm-body-${otherUserId}`).html('<div style="color: #f44336; padding: 10px;">Error: ' + response.message + '</div>');
        }
    }).catch(error => {
        console.error('Load messages error:', error);
        $(`#pm-body-${otherUserId}`).html('<div style="color: #f44336; padding: 10px;">Failed to load messages</div>');
    });
}

function displayPrivateMessages(otherUserId, messages) {
    const container = $(`#pm-body-${otherUserId}`);
    
    
    const wasAtBottom = container[0] ? 
        (container.scrollTop() + container.innerHeight() >= container[0].scrollHeight - 20) : true;
    
    let html = '';
    
    if (messages.length === 0) {
        html = '<div style="text-align: center; color: #999; padding: 20px;">No messages yet</div>';
    } else {
        messages.forEach(msg => {
    const isOwn = msg.sender_id == currentUser.id;
    const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    
    const author = isOwn ? (currentUser.username || currentUser.name) : msg.sender_username;
    const avatar = isOwn ? (currentUser.avatar || 'default_avatar.jpg') : (msg.sender_avatar || 'default_avatar.jpg');
    const userColor = isOwn ? (currentUser.color || 'blue') : (msg.sender_color || 'blue');
    
    const avatarHue = isOwn ? (currentUser.avatar_hue || 0) : (msg.sender_avatar_hue || 0);
    const avatarSat = isOwn ? (currentUser.avatar_saturation || 100) : (msg.sender_avatar_saturation || 100);
    
    debugLog('Avatar customization debug:', {
        isOwn: isOwn,
        avatarHue: avatarHue,
        avatarSat: avatarSat,
        msg_sender_avatar_hue: msg.sender_avatar_hue,
        currentUser_avatar_hue: currentUser.avatar_hue
    });
    
    html += `
        <div class="private-chat-message ${isOwn ? 'sent' : 'received'}">
            <img src="images/${avatar}" 
                 class="private-message-avatar" 
                 style="filter: hue-rotate(${avatarHue}deg) saturate(${avatarSat}%);"
                 alt="${author}'s avatar">
            <div class="private-message-bubble ${isOwn ? 'sent' : 'received'} user-color-${userColor}">
                <div class="private-message-header-info">
                    <div class="private-message-author">${author}</div>
                    <div class="private-message-time">${time}</div>
                </div>
                <div class="private-message-content">${msg.message}</div>
            </div>
        </div>
    `;
});
    }
    
    container.html(html);
    
    if (wasAtBottom) {
        container.scrollTop(container[0].scrollHeight);
    }
}

/*function checkForNewPrivateMessages() {
    if (currentUser.type !== 'user') return;
    
    openPrivateChats.forEach((data, userId) => {
        const input = $(`#pm-input-${userId}`);
        const isTyping = input.is(':focus') && input.val().length > 0;
        
        if (!isTyping) {
            loadPrivateMessages(userId);
        }
    });
    
    if ($('#friendsPanel').is(':visible')) {
        loadConversations();
    }
}*/

function syncAvatarCustomization() {
    $.ajax({
        url: 'api/update_room_avatar_customization.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                debugLog('Avatar customization synced:', response);
                setTimeout(() => {
                    loadUsers();
                    loadMessages();
                }, 200);
            } else {
                debugLog('Avatar sync failed:', response.message);
            }
        },
        error: function(xhr, status, error) {
            debugLog('Avatar sync error (non-critical):', error);
        }
    });
}

function applyAvatarFilter(imgElement, hue, saturation) {
    if (hue !== undefined && saturation !== undefined) {
        const hueValue = parseInt(hue) || 0;
        const satValue = parseInt(saturation) || 100;
        const filterValue = `hue-rotate(${hueValue}deg) saturate(${satValue}%)`;
        const filterKey = `${hueValue}-${satValue}`;
        
        if (imgElement.data('filter-applied') !== filterKey) {
            imgElement.css('filter', filterValue);
            imgElement.data('filter-applied', filterKey);
            imgElement.addClass('avatar-filtered');
        }
    }
}

function applyAllAvatarFilters() {
    $('.avatar-filtered, .message-avatar, .user-avatar, .private-message-avatar').each(function() {
        const $img = $(this);
        const hue = $img.data('hue');
        const sat = $img.data('saturation');
        
        if (hue === undefined || sat === undefined) return;
        
        const filterKey = `${hue}-${sat}`;
        const appliedKey = $img.data('filter-applied');
        
        if (appliedKey !== filterKey) {
            const filterValue = `hue-rotate(${hue}deg) saturate(${sat}%)`;
            $img.css('filter', filterValue);
            $img.data('filter-applied', filterKey);
        }
    });
}

function handleAvatarClick(event, userId, username) {
    event.preventDefault();
    event.stopPropagation();
    
    debugLog('Avatar clicked - userId:', userId, 'username:', username); // Debug log
    
    if (userId && userId !== 'null' && userId !== null && userId > 0) {
        if (userId == currentUser.id) {
            showUserProfile(userId, event.target);
        } else {
            showUserProfile(userId, event.target);
        }
    }
}

if (typeof disappearingMessages !== 'undefined' && disappearingMessages && messageLifetimeMinutes > 0) {
    $('.room-title').append(`
        <span class="badge bg-warning ms-2" title="Messages disappear after ${messageLifetimeMinutes} minutes">
            <i class="fas fa-clock"></i> ${messageLifetimeMinutes}min
        </span>
    `);
    
    setTimeout(() => {
        showToast(`This room has disappearing messages enabled. Messages will be deleted after ${messageLifetimeMinutes} minutes.`, 'warning');
    }, 2000);
}

function copyInviteLink(inviteCode) {
    const inviteLink = `${window.location.origin}/lounge.php?invite=${inviteCode}`;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(inviteLink).then(() => {
            showToast('Invite link copied to clipboard!', 'success');
        }).catch(() => {
            fallbackCopyTextToClipboard(inviteLink);
        });
    } else {
        fallbackCopyTextToClipboard(inviteLink);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showToast('Invite link copied to clipboard!', 'success');
    } catch (err) {
        console.error('Fallback: Oops, unable to copy', err);
        showToast('Unable to copy link automatically. Please copy manually.', 'warning');
    }
    
    document.body.removeChild(textArea);
}

function showAnnouncementModal() {
    const modalHtml = `
        <div class="modal fade" id="announcementModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-bullhorn"></i> Send Site Announcement
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="announcementMessage" class="form-label">Announcement Message</label>
                            <textarea class="form-control" id="announcementMessage" rows="4" maxlength="500" placeholder="Enter your announcement message..." style="background: #333; border: 1px solid #555; color: #fff;"></textarea>
                            <div class="form-text text-muted">Maximum 500 characters. This will be sent to all active rooms.</div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" onclick="sendAnnouncement()">
                            <i class="fas fa-bullhorn"></i> Send Announcement
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#announcementModal').remove();
    $('body').append(modalHtml);
    $('#announcementModal').modal('show');
}

function sendAnnouncement() {
    const message = $('#announcementMessage').val().trim();
    
    if (!message) {
        alert('Please enter an announcement message');
        return;
    }
    
    const button = $('#announcementModal .btn-warning');
    const originalText = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
    
    $.ajax({
        url: 'api/send_announcement.php',
        method: 'POST',
        data: { message: message },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('Announcement sent successfully to all rooms!');
                $('#announcementModal').modal('hide');
                if (typeof loadMessages === 'function') {
                    setTimeout(loadMessages, 1000);
                }
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Failed to send announcement: ' + error);
        },
        complete: function() {
            button.prop('disabled', false).html(originalText);
        }
    });
}

function showQuickBanModal(userIdString, username, ipAddress) {
    const modalHtml = `
        <div class="modal fade" id="quickBanModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-ban"></i> Site Ban User
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This will ban the user from the entire site, not just this room.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">User to Ban</label>
                            <input type="text" class="form-control" value="${username}" readonly style="background: #333; border: 1px solid #555; color: #fff;">
                        </div>
                        <div class="mb-3">
                            <label for="quickBanDuration" class="form-label">Ban Duration</label>
                            <select class="form-select" id="quickBanDuration" style="background: #333; border: 1px solid #555; color: #fff;">
                                <option value="3600">1 Hour</option>
                                <option value="21600">6 Hours</option>
                                <option value="86400">24 Hours</option>
                                <option value="604800">7 Days</option>
                                <option value="permanent">Permanent</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="quickBanReason" class="form-label">Reason</label>
                            <textarea class="form-control" id="quickBanReason" rows="3" placeholder="Enter reason for ban..." style="background: #333; border: 1px solid #555; color: #fff;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="executeQuickBan('${userIdString}', '${username.replace(/'/g, "\\'")}', '${ipAddress}')">
                            <i class="fas fa-ban"></i> Site Ban
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#quickBanModal').remove();
    $('body').append(modalHtml);
    $('#quickBanModal').modal('show');
}

function executeQuickBan(userIdString, username, ipAddress) {
    const duration = $('#quickBanDuration').val();
    const reason = $('#quickBanReason').val().trim();
    
    const button = $('#quickBanModal .btn-danger');
    const originalText = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Banning...');
    
    const banData = {
        user_id_string: userIdString,
        duration: duration,
        reason: reason
    };
    
    if (username) banData.username = username;
    if (ipAddress) banData.ip_address = ipAddress;
    
    $.ajax({
        url: 'api/site_ban_user.php',
        method: 'POST',
        data: banData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert(response.message);
                $('#quickBanModal').modal('hide');
                
                setTimeout(() => {
                    loadUsers();
                    loadMessages();
                }, 1000);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Failed to ban user: ' + error);
        },
        complete: function() {
            button.prop('disabled', false).html(originalText);
        }
    });
}


function initializeMentionsAndReplies() {
    debugLog('🏷️ Initializing mentions and replies system...');
    
    // Remove the old interval - mentions are now handled by fetchAllRoomData
    // mentionCheckInterval = setInterval(checkForMentions, 1000);
    
    setupMentionsEventHandlers();
    
    debugLog('✅ Mentions and replies system initialized (using managed updates)');
}


function setupMentionsEventHandlers() {
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.mentions-panel, .mentions-counter').length) {
            closeMentionsPanel();
        }
    });
    
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            clearReplyInterface();
        }
    });
}


function checkForMentions() {
    if (!mentionCheckInterval) return;
    
    $.ajax({
        url: 'api/get_mentions.php',
        method: 'GET',
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            if (response.status === 'success') {
                mentionNotifications = response.mentions;
                updateMentionCounter(response.unread_count);
                
                if (response.unread_count > 0 && !mentionPanelOpen) {
                    showNewMentionNotification(response.unread_count);
                }
            }
        },
        error: function() {
            // Silently fail
        }
    });
}

function updateMentionCounter(count) {
    const counter = $('.mentions-counter');
    
    if (count > 0) {
        if (counter.length === 0) {
            const counterHtml = `
                <div class="mentions-counter" onclick="toggleMentionsPanel()">
                    <i class="fas fa-at"></i> <span class="mention-count">${count}</span> mention${count !== 1 ? 's' : ''}
                </div>
            `;
            $('body').append(counterHtml);
            setTimeout(() => $('.mentions-counter').addClass('show'), 100);
        } else {
            counter.find('.mention-count').text(count);
            counter.addClass('show');
        }
    } else {
        counter.removeClass('show');
        setTimeout(() => counter.remove(), 200);
    }
}

function showNewMentionNotification(count) {
    const notification = $(`
        <div class="mention-notification-toast" style="
            position: fixed;
            top: 20px;
            right: 20px;
            background: #faa61a;
            color: #000;
            padding: 12px 16px;
            border-radius: 8px;
            font-weight: 600;
            z-index: 1060;
            animation: slideInFromRight 0.3s ease-out;
        ">
            <i class="fas fa-at"></i> ${count} new mention${count !== 1 ? 's' : ''}!
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(() => {
        notification.fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);
}

function toggleMentionsPanel() {
    if (mentionPanelOpen) {
        closeMentionsPanel();
    } else {
        openMentionsPanel();
    }
}

function openMentionsPanel() {
    if ($('.mentions-panel').length > 0) {
        $('.mentions-panel').addClass('show');
        mentionPanelOpen = true;
        return;
    }
    
    const panelHtml = `
        <div class="mentions-panel">
            <div class="mentions-panel-header">
                <h6 class="mentions-panel-title">
                    <i class="fas fa-at"></i> Mentions & Replies
                </h6>
                <button class="mentions-panel-close" onclick="closeMentionsPanel()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mentions-panel-content" id="mentionsContent">
                Loading mentions...
            </div>
        </div>
    `;
    
    $('body').append(panelHtml);
    
    setTimeout(() => {
        $('.mentions-panel').addClass('show');
        mentionPanelOpen = true;
        displayMentions();
    }, 50);
}

function closeMentionsPanel() {
    const panel = $('.mentions-panel');
    if (panel.length > 0) {
        panel.removeClass('show');
        mentionPanelOpen = false;
        
        setTimeout(() => panel.remove(), 300);
    }
}

function displayMentions() {
    const container = $('#mentionsContent');
    
    if (mentionNotifications.length === 0) {
        container.html(`
            <div class="mentions-empty">
                <i class="fas fa-bell"></i>
                <p>No mentions yet</p>
                <small>You'll see @mentions and replies here</small>
            </div>
        `);
        return;
    }
    
    let html = '';
    mentionNotifications.forEach(mention => {
        const timeAgo = getTimeAgo(new Date(mention.created_at));
        const typeIcon = mention.type === 'reply' ? 'fa-reply' : 'fa-at';
        const typeName = mention.type === 'reply' ? 'Reply' : 'Mention';
        
        html += `
            <div class="mention-notification unread ${mention.type}" 
                 onclick="jumpToMessage(${mention.message_id}, ${mention.id})">
                <div class="mention-notification-header">
                    <img src="images/${mention.sender_avatar}" class="mention-notification-avatar" alt="Avatar">
                    <span class="mention-notification-author">${mention.sender_name}</span>
                    <span class="mention-notification-type ${mention.type}">
                        <i class="fas ${typeIcon}"></i> ${typeName}
                    </span>
                </div>
                <div class="mention-notification-content">
                    ${mention.message}
                </div>
                <div class="mention-notification-time">${timeAgo}</div>
            </div>
        `;
    });
    
    container.html(html);
}

function jumpToMessage(messageId, mentionId) {
    markMentionAsRead(mentionId);
    
    const messageElement = $(`.chat-message[data-message-id="${messageId}"]`);
    if (messageElement.length > 0) {
        const chatbox = $('#chatbox');
        const messageTop = messageElement.position().top + chatbox.scrollTop();
        chatbox.animate({ scrollTop: messageTop - 100 }, 300);
        
        messageElement.addClass('mentioned-highlight');
        setTimeout(() => {
            messageElement.removeClass('mentioned-highlight');
        }, 3000);
    }
    
    closeMentionsPanel();
}

function markMentionAsRead(mentionId) {
    $.ajax({
        url: 'api/mark_mentions_read.php',
        method: 'POST',
        data: { mention_id: mentionId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                mentionNotifications = mentionNotifications.filter(m => m.id !== mentionId);
                updateMentionCounter(mentionNotifications.length);
            }
        }
    });
}

function markAllMentionsAsRead() {
    $.ajax({
        url: 'api/mark_mentions_read.php',
        method: 'POST',
        data: { mark_all: true },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                mentionNotifications = [];
                updateMentionCounter(0);
                displayMentions();
            }
        }
    });
}


function showReplyInterface(messageId, author, content) {
    clearReplyInterface();
    
    const replyHtml = `
        <div class="reply-interface" id="replyInterface">
            <div class="reply-interface-header">
                <div class="reply-interface-label">
                    <i class="fas fa-reply"></i>
                    Replying to ${author}
                </div>
                <button class="reply-interface-close" onclick="clearReplyInterface()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="reply-interface-preview">
                <div class="reply-preview-author">${author}</div>
                <div class="reply-preview-content">${content}</div>
            </div>
        </div>
    `;
    
    $('.chat-input-container').before(replyHtml);
    currentReplyTo = messageId;
    
    $('#message').focus();
}

function clearReplyInterface() {
    $('.reply-interface').remove();
    currentReplyTo = null;
}

function getTimeAgo(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
    
    return date.toLocaleDateString();
}

function addMentionHighlightCSS() {
    if ($('#mentionHighlightCSS').length > 0) return;
    
    const css = `
        <style id="mentionHighlightCSS">
        @keyframes mentionHighlight {
            0% { background-color: rgba(250, 166, 26, 0.4); }
            100% { background-color: transparent; }
        }

        @keyframes mentionHighlightBorder {
            0% {--user-border-color: #faa61a !important;
            --user-tail-color: #faa61a !important;}
        100% {--user-border-color: transparent !important;
            --user-tail-color: transparent !important;}
        }

            @keyframes mentionHighlightBorder {
        0% {--user-border-color: #faa61a !important;
            --user-tail-color: #faa61a !important;}
        100% {--user-border-color: transparent !important;
            --user-tail-color: transparent !important;}
        }
        
        .mentioned-highlight {
            animation: mentionHighlight 5s ease-out;
        }
        
        .mentioned-highlight .message-bubble {
            --user-border-color: #faa61a !important;
            --user-tail-color: #faa61a !important; 
        }
        

        </style>
    `;
    
    $('head').append(css);
}

    function toggleAFK() {
    const button = $('.btn-toggle-afk');
    const originalText = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
        url: 'api/toggle_afk.php',
        method: 'POST',
        data: { action: 'toggle' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                currentUserAFK = response.is_afk;
                manualAFK = response.manual_afk;
                
                updateAFKButton();
                
                setTimeout(() => {
                    loadUsers();
                    loadMessages();
                }, 500);
                
                showToast(response.message, 'success');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AFK toggle error:', error);
            alert('Failed to toggle AFK status: ' + error);
        },
        complete: function() {
            button.prop('disabled', false);
            updateAFKButton(); // Restore button text
        }
    });
}

function updateAFKButton() {
    const button = $('.btn-toggle-afk');
    
    if (currentUserAFK) {
       // button.removeClass('btn-outline-warning')
             // button.addClass('btn-warning')
              button.html('<i class="fas fa-plane-arrival"></i>');
    } else {
      //  button.removeClass('btn-warning')
             // button.addClass('btn-outline-warning')
              button.html('<i class="fas fa-plane-departure"></i>');
    }
}

function formatAFKDuration(minutes) {
    if (minutes < 60) {
        return `${minutes}m`;
    } else {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
    }
}

function addAFKStyles() {
    if ($('#afkStylesCSS').length > 0) return;
    
    const css = `
        <style id="afkStylesCSS">
        .user-item.afk-user {
            opacity: 0.7;
        }
        
        .user-item.afk-user .user-avatar {
            opacity: 0.6;
            filter: grayscale(30%) !important;
        }
        
        .user-item.afk-user .user-name {
            color: #888 !important;
        }
        
        .badge-afk {
            background-color: #6c757d !important;
            color: white !important;
        }
        
        .btn.afk-user {
            opacity: 0.8;
            border-color: #6c757d !important;
            color: #6c757d !important;
        }
        
        .btn.afk-user:hover {
            background-color: rgba(108, 117, 125, 0.1) !important;
        }
        
        .system-message img[src*="afk.png"],
        .system-message img[src*="active.png"] {
            width: 16px;
            height: 16px;
        }
        </style>
    `;
    
    $('head').append(css);
}

function showActivityStatus() {
    //if (!DEBUG_MODE) return;
    
    const statusHtml = `
        <div class="activity-status-debug" style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 9999;">
            <div><strong>Activity Status</strong></div>
            <div>Tracker Enabled: ${activityTracker.enabled}</div>
            <div>AFK Timeout: ${ACTIVITY_CONFIG.AFK_TIMEOUT_MINUTES}min</div>
            <div>Disconnect Timeout: ${ACTIVITY_CONFIG.DISCONNECT_TIMEOUT_MINUTES}min</div>
            <div>Last Activity: ${new Date(activityTracker.lastActivityUpdate).toLocaleTimeString()}</div>
            <div>Current AFK: ${currentUserAFK}</div>
        </div>
    `;
    
    $('.activity-status-debug').remove();
    $('body').append(statusHtml);
    
    setTimeout(() => $('.activity-status-debug').remove(), 10000);
}

if (DEBUG_MODE) {
    setInterval(showActivityStatus, 30000);
}

function startYouTubeUpdates() {
    if (youtubeUpdateInterval) {
        clearInterval(youtubeUpdateInterval);
    }
    
    // Reduced frequency: every 5 seconds instead of 2-3 seconds
    youtubeUpdateInterval = setInterval(updateYouTubeData, 5000);
    updateYouTubeData(); // Initial load
    debugLog('🔄 Started combined YouTube updates (every 5s)');
}

function updateYouTubeData() {
    if (!youtubeEnabled || isYoutubeUpdating) {
        return;
    }
    
    isYoutubeUpdating = true;
    
    $.ajax({
        url: 'api/youtube_combined.php',
        method: 'GET',
        dataType: 'json',
        timeout: 8000,
        success: function(response) {
            if (response.status === 'success') {
                // Update sync data
                const sync = response.sync_data;
                if (sync.enabled && sync.sync_token !== lastSyncToken) {
                    debugLog('🔄 Syncing player state:', sync);
                    lastSyncToken = sync.sync_token;
                    applySyncState(sync);
                }
                
                // Update queue data  
                const queueData = response.queue_data;
                playerQueue = queueData.queue || [];
                playerSuggestions = queueData.suggestions || [];
                currentVideoData = queueData.current_playing;
                
                renderQueue();
                renderSuggestions();
                updateVideoInfo();
            }
        },
        error: function(xhr, status, error) {
            debugLog('⚠️ YouTube update error:', error);
        },
        complete: function() {
            isYoutubeUpdating = false;
        }
    });
}

function applySyncState(sync) {
    if (!youtubePlayerReady) return;
    
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
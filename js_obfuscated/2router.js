// SSE DATA ROUTER
// Intercepts AJAX polling requests and serves data from SSE cache
// This maintains all existing code while using SSE as the single data source

(function() {
    'use strict';
    
    // ============================================
    // PART 1: SSE DATA CACHE
    // ============================================
    
    window.sseDataCache = {
        messages: [],
        users: [],
        mentions: { mentions: [], unread_count: 0 },
        whispers: { conversations: [] },
        friends: { friends: [] },
        privateMessages: { conversations: [] },
        roomSettings: {},
        youtube: { queue: [], now_playing: null },
        friendNotifications: { count: 0, notifications: [] },
        generalNotifications: { count: 0, notifications: [] },
        roomStatus: { status: 'active' },
        knocks: [],
        lastUpdate: {}
    };
    
    // ============================================
    // PART 2: AJAX INTERCEPTOR
    // ============================================
    
    $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
        const url = options.url || '';
        
        // Check if this request should be intercepted
        const shouldIntercept = 
            url.includes('get_messages.php') ||
            url.includes('get_room_users.php') ||
            url.includes('get_mentions.php') ||
            url.includes('room_whispers.php') ||
            url.includes('friends.php') ||
            url.includes('private_messages.php') ||
            url.includes('youtube_combined.php') ||
            url.includes('get_notifications.php') ||
            url.includes('check_room_status.php') ||
            url.includes('check_knocks.php');
        
        if (!shouldIntercept) {
            return; // Let the request through
        }
        
        console.log('🔍 Intercepting:', url);
        
        // Get cached data
        const cachedData = getCachedResponse(url, options.data);
        
        if (cachedData) {
            console.log('📦 Serving from SSE cache:', url);
            
            // Abort the request
            options.beforeSend = function() { return false; };
            
            // Return cached data asynchronously
            setTimeout(function() {
                if (options.success) {
                    options.success(cachedData);
                }
                if (options.complete) {
                    options.complete({ status: 200 }, 'success');
                }
            }, 0);
        }
    });
    
    // ============================================
    // PART 3: CACHE RESPONSE MAPPER
    // ============================================
    
    function getCachedResponse(url, data) {
        // Messages
        if (url.includes('get_messages.php')) {
            return {
                status: 'success',
                messages: sseDataCache.messages,
                pagination: {
                    total_count: sseDataCache.messages.length,
                    has_more_older: false
                }
            };
        }
        
        // Users
        if (url.includes('get_room_users.php')) {
            return sseDataCache.users;
        }
        
        // Mentions
        if (url.includes('get_mentions.php')) {
            return {
                status: 'success',
                mentions: sseDataCache.mentions.mentions || [],
                unread_count: sseDataCache.mentions.unread_count || 0
            };
        }
        
        // Whispers
        if (url.includes('room_whispers.php')) {
            if (data && data.action === 'get_conversations') {
                return {
                    status: 'success',
                    conversations: sseDataCache.whispers.conversations || []
                };
            }
            if (data && data.action === 'get') {
                // Return specific conversation
                const conv = sseDataCache.whispers.conversations?.find(
                    c => c.other_user_id_string === data.other_user_id_string
                );
                return {
                    status: 'success',
                    messages: conv?.messages || []
                };
            }
        }
        
        // Friends
        if (url.includes('friends.php')) {
            if (data && data.action === 'get') {
                return {
                    status: 'success',
                    friends: sseDataCache.friends.friends || []
                };
            }
            if (data && data.action === 'get_notifications') {
                return sseDataCache.friendNotifications;
            }
        }
        
        // Private Messages
        if (url.includes('private_messages.php')) {
            if (data && data.action === 'get_conversations') {
                return {
                    status: 'success',
                    conversations: sseDataCache.privateMessages.conversations || []
                };
            }
            if (data && data.action === 'get') {
                const conv = sseDataCache.privateMessages.conversations?.find(
                    c => c.other_user_id == data.other_user_id
                );
                return {
                    status: 'success',
                    messages: conv?.messages || []
                };
            }
        }
        
        // YouTube
        if (url.includes('youtube_combined.php')) {
            return {
                status: 'success',
                queue: sseDataCache.youtube.queue || [],
                now_playing: sseDataCache.youtube.now_playing
            };
        }
        
        // Notifications
        if (url.includes('get_notifications.php')) {
            return sseDataCache.generalNotifications;
        }
        
        // Room Status
        if (url.includes('check_room_status.php')) {
            return sseDataCache.roomStatus;
        }
        
        // Knocks
        if (url.includes('check_knocks.php')) {
            return sseDataCache.knocks || [];
        }
        
        return null; // Not cached
    }
    
    // ============================================
    // PART 4: SSE EVENT HANDLER
    // ============================================
    
    window.updateSSECache = function(data) {
        if (!data || !data.updates) return;
        
        const updates = data.updates;
        const now = Date.now();
        
        // Update cache with latest data
        if (updates.messages !== undefined) {
            sseDataCache.messages = updates.messages;
            sseDataCache.lastUpdate.messages = now;
        }
        
        if (updates.users !== undefined) {
            sseDataCache.users = updates.users;
            sseDataCache.lastUpdate.users = now;
        }
        
        if (updates.mentions !== undefined) {
            sseDataCache.mentions = updates.mentions;
            sseDataCache.lastUpdate.mentions = now;
        }
        
        if (updates.whispers !== undefined) {
            sseDataCache.whispers = updates.whispers;
            sseDataCache.lastUpdate.whispers = now;
        }
        
        if (updates.friends !== undefined) {
            sseDataCache.friends = updates.friends;
            sseDataCache.lastUpdate.friends = now;
        }
        
        if (updates.private_messages !== undefined) {
            sseDataCache.privateMessages = updates.private_messages;
            sseDataCache.lastUpdate.privateMessages = now;
        }
        
        if (updates.youtube !== undefined) {
            sseDataCache.youtube = updates.youtube;
            sseDataCache.lastUpdate.youtube = now;
        }
        
        if (updates.friend_notifications !== undefined) {
            sseDataCache.friendNotifications = updates.friend_notifications;
            sseDataCache.lastUpdate.friendNotifications = now;
        }
        
        if (updates.general_notifications !== undefined) {
            sseDataCache.generalNotifications = updates.general_notifications;
            sseDataCache.lastUpdate.generalNotifications = now;
        }
        
        if (updates.room_status !== undefined) {
            sseDataCache.roomStatus = updates.room_status;
            sseDataCache.lastUpdate.roomStatus = now;
        }
        
        if (updates.knocks !== undefined) {
            sseDataCache.knocks = updates.knocks;
            sseDataCache.lastUpdate.knocks = now;
        }
        
        console.log('📦 SSE cache updated:', Object.keys(updates));
    };
    
    // ============================================
    // PART 5: DEBUG FUNCTIONS
    // ============================================
    
    window.debugSSERouter = function() {
        console.log('=== SSE ROUTER STATUS ===');
        console.table({
            'Messages': sseDataCache.messages.length,
            'Users': sseDataCache.users.length,
            'Mentions': sseDataCache.mentions.mentions?.length || 0,
            'Whispers': sseDataCache.whispers.conversations?.length || 0,
            'Friends': sseDataCache.friends.friends?.length || 0,
            'Knocks': sseDataCache.knocks?.length || 0
        });
        
        console.log('Last Updates:');
        Object.entries(sseDataCache.lastUpdate).forEach(([key, timestamp]) => {
            const age = Math.floor((Date.now() - timestamp) / 1000);
            console.log(`  ${key}: ${age}s ago`);
        });
        
        return sseDataCache;
    };
    
    console.log('✅ SSE Router loaded - AJAX requests will be served from SSE cache');
    
})();
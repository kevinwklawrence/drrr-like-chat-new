// socket-client.js - SIMPLIFIED VERSION (Manual Authentication)
class SocketChatClient {
    constructor() {
        this.socket = null;
        this.connected = false;
        this.authenticated = false;
        this.roomId = null;
        this.useSocketsEnabled = true;
        this.retryCount = 0;
        this.maxRetries = 5;
        this.retryDelay = 2000;
        
        // Callbacks for integration with existing code
        this.onNewMessage = null;
        this.onUserJoined = null;
        this.onUserLeft = null;
        this.onRoomJoined = null;
        this.onUserTyping = null;
        this.onError = null;
        this.onAuthSuccess = null;
        
        // Typing indicator state
        this.typingTimeout = null;
        this.isTyping = false;
    }
    
    // Initialize socket connection
    init(roomId, authData = null) {
        if (!this.useSocketsEnabled) {
            debugLog('Sockets disabled, falling back to polling');
            return false;
        }
        
        this.roomId = roomId;
        
        try {
            // Connect to socket server
            this.socket = io('http://localhost:3001', {
                transports: ['websocket', 'polling'],
                timeout: 20000,
                forceNew: true
            });
            
            this.setupEventHandlers();
            
            // Store auth data for when connection establishes
            this.pendingAuthData = authData || this.extractAuthDataFromPage();
            
            return true;
        } catch (error) {
            debugError('Failed to initialize socket:', error);
            this.useSocketsEnabled = false;
            return false;
        }
    }
    
    // Extract authentication data from the current page/session
    extractAuthDataFromPage() {
        // Try to get user data from global variables or PHP-generated data
        const authData = {
            user_id_string: null,
            room_id: this.roomId
        };
        
        // Method 1: Check for PHP-generated user data
        if (typeof window.currentUser !== 'undefined') {
            authData.user_id_string = window.currentUser.user_id || window.currentUser.user_id_string;
        }
        
        // Method 2: Check for user ID in a hidden input or data attribute
        const userIdInput = document.querySelector('input[name="user_id_string"]');
        if (userIdInput) {
            authData.user_id_string = userIdInput.value;
        }
        
        // Method 3: Check for data attributes on body or main elements
        const bodyUserId = document.body.dataset.userId || document.body.dataset.userIdString;
        if (bodyUserId) {
            authData.user_id_string = bodyUserId;
        }
        
        // Method 4: Try to extract from existing session info in DOM
        const sessionScript = document.querySelector('script[data-session]');
        if (sessionScript) {
            try {
                const sessionData = JSON.parse(sessionScript.dataset.session);
                authData.user_id_string = sessionData.user_id || sessionData.user_id_string;
            } catch (e) {
                // Silent fail
            }
        }
        
        debugLog('Extracted auth data:', authData);
        return authData;
    }
    
    setupEventHandlers() {
        // Connection events
        this.socket.on('connect', () => {
            debugLog('✅ Socket connected');
            this.connected = true;
            this.retryCount = 0;
            
            // Send authentication immediately
            if (this.pendingAuthData && this.pendingAuthData.user_id_string && this.pendingAuthData.room_id) {
                debugLog('🔐 Sending authentication data...');
                this.socket.emit('authenticate', this.pendingAuthData);
            } else {
                debugError('❌ No authentication data available');
                this.socket.emit('get_auth_data_needed');
            }
        });
        
        this.socket.on('disconnect', (reason) => {
            debugLog('❌ Socket disconnected:', reason);
            this.connected = false;
            this.authenticated = false;
            
            // Auto-reconnect for certain reasons
            if (reason === 'io server disconnect') {
                this.reconnect();
            }
        });
        
        this.socket.on('connect_error', (error) => {
            debugError('🔌 Socket connection error:', error);
            this.connected = false;
            this.authenticated = false;
            
            // Fall back to polling if socket fails
            if (this.retryCount >= this.maxRetries) {
                debugLog('Max retries reached, falling back to polling');
                this.useSocketsEnabled = false;
                this.fallbackToPolling();
            } else {
                this.reconnect();
            }
        });
        
        // Authentication events
        this.socket.on('auth_success', (data) => {
            debugLog('🔐 Authentication successful:', data.user);
            this.authenticated = true;
            
            if (this.onAuthSuccess) {
                this.onAuthSuccess(data);
            }
        });
        
        this.socket.on('auth_error', (data) => {
            debugError('🔐 Authentication failed:', data.message);
            this.authenticated = false;
            
            if (this.onError) {
                this.onError('Authentication failed: ' + data.message);
            }
            
            // Try to get auth data from user
            this.promptForAuthData();
        });
        
        // Room events
        this.socket.on('room_joined', (data) => {
            debugLog('🏠 Joined room:', data.room_id);
            if (this.onRoomJoined) {
                this.onRoomJoined(data);
            }
        });
        
        this.socket.on('user_joined', (data) => {
            debugLog('👤 User joined:', data.user);
            if (this.onUserJoined) {
                this.onUserJoined(data);
            }
        });
        
        this.socket.on('user_left', (data) => {
            debugLog('👋 User left:', data.user_id_string);
            if (this.onUserLeft) {
                this.onUserLeft(data);
            }
        });
        
        // Message events
        this.socket.on('new_message', (message) => {
            debugLog('💬 New message received:', message);
            if (this.onNewMessage) {
                this.onNewMessage(message);
            }
        });
        
        // Typing indicators
        this.socket.on('user_typing', (data) => {
            if (this.onUserTyping) {
                this.onUserTyping(data);
            }
        });
        
        // Error handling
        this.socket.on('error', (data) => {
            debugError('Socket error:', data);
            if (this.onError) {
                this.onError(data.message || 'Socket error');
            }
        });
    }
    
    // Prompt user for authentication data if automatic extraction fails
    promptForAuthData() {
        debugLog('🔐 Prompting for authentication data...');
        
        // Try to extract again
        const authData = this.extractAuthDataFromPage();
        
        if (!authData.user_id_string) {
            // Show user-friendly message
            if (typeof showToast === 'function') {
                showToast('Socket authentication failed. Using polling mode.', 'warning');
            }
            
            // Fall back to polling
            this.useSocketsEnabled = false;
            this.fallbackToPolling();
            return;
        }
        
        // Retry authentication
        if (this.socket && this.connected) {
            this.socket.emit('authenticate', authData);
        }
    }
    
    // Send a message
    sendMessage(message, replyTo = null) {
        if (!this.isConnected() || !this.authenticated) {
            debugLog('Socket not connected or not authenticated, using fallback');
            return false;
        }
        
        this.socket.emit('send_message', {
            message: message,
            reply_to: replyTo
        });
        
        debugLog('Message sent via socket:', message);
        return true;
    }
    
    // Send typing indicator
    setTyping(isTyping) {
        if (!this.isConnected() || !this.authenticated) return;
        
        if (isTyping && !this.isTyping) {
            this.socket.emit('activity', { type: 'typing', typing: true });
            this.isTyping = true;
            
            // Auto-clear typing after 3 seconds
            this.typingTimeout = setTimeout(() => {
                this.setTyping(false);
            }, 3000);
            
        } else if (!isTyping && this.isTyping) {
            if (this.typingTimeout) {
                clearTimeout(this.typingTimeout);
                this.typingTimeout = null;
            }
            this.socket.emit('activity', { type: 'typing', typing: false });
            this.isTyping = false;
        }
    }
    
    // Send activity update
    sendActivity(activityType = 'interaction') {
        if (!this.isConnected() || !this.authenticated) return;
        
        this.socket.emit('activity', { type: activityType });
    }
    
    // Leave current room
    leaveRoom() {
        if (!this.isConnected() || !this.authenticated) return;
        
        this.socket.emit('leave_room');
        debugLog('Left room via socket');
    }
    
    // Check if connected and authenticated
    isConnected() {
        return this.socket && this.connected && this.authenticated;
    }
    
    // Reconnect logic
    reconnect() {
        if (this.retryCount >= this.maxRetries) {
            debugLog('Max retries reached, giving up');
            return;
        }
        
        this.retryCount++;
        const delay = this.retryDelay * this.retryCount;
        
        debugLog(`Reconnecting in ${delay}ms (attempt ${this.retryCount}/${this.maxRetries})`);
        
        setTimeout(() => {
            if (this.socket) {
                this.socket.connect();
            }
        }, delay);
    }
    
    // Fallback to existing polling system
    fallbackToPolling() {
        debugLog('🔄 Falling back to polling system');
        this.useSocketsEnabled = false;
        
        // Re-enable existing polling intervals if they were disabled
        if (typeof activityTracker !== 'undefined' && activityTracker.init) {
            activityTracker.init();
        }
        
        // Start message polling
        if (typeof loadMessages === 'function') {
            const messageInterval = setInterval(loadMessages, 3000);
            
            // Store interval for cleanup
            if (typeof window.pollingIntervals === 'undefined') {
                window.pollingIntervals = [];
            }
            window.pollingIntervals.push(messageInterval);
        }
        
        // Start user list polling
        if (typeof loadUsers === 'function') {
            const userInterval = setInterval(loadUsers, 5000);
            if (typeof window.pollingIntervals === 'undefined') {
                window.pollingIntervals = [];
            }
            window.pollingIntervals.push(userInterval);
        }
    }
    
    // Manual authentication with provided data
    authenticate(authData) {
        if (!this.socket || !this.connected) {
            debugLog('Cannot authenticate: socket not connected');
            return false;
        }
        
        debugLog('🔐 Sending manual authentication:', authData);
        this.socket.emit('authenticate', authData);
        return true;
    }
    
    // Cleanup
    disconnect() {
        if (this.socket) {
            this.socket.disconnect();
            this.socket = null;
        }
        this.connected = false;
        this.authenticated = false;
        this.roomId = null;
        
        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
            this.typingTimeout = null;
        }
    }
    
    // Feature flag control
    enableSockets() {
        this.useSocketsEnabled = true;
    }
    
    disableSockets() {
        this.useSocketsEnabled = false;
        this.disconnect();
        this.fallbackToPolling();
    }
}

// Global socket client instance
let socketClient = null;

// Initialize socket integration with manual authentication
function initializeSocketIntegration(roomId, authData = null) {
    if (!roomId) {
        debugError('Room ID required for socket initialization');
        return false;
    }
    
    debugLog('🔌 Initializing socket integration for room:', roomId);
    
    socketClient = new SocketChatClient();
    
    // Set up callbacks to integrate with existing functions
    socketClient.onNewMessage = (message) => {
        const formattedMessage = formatSocketMessage(message);
        
        // Add to messages display using existing function
        if (typeof displayMessage === 'function') {
            displayMessage(formattedMessage);
        } else {
            appendMessageToChat(formattedMessage);
        }
    };
    
    socketClient.onUserJoined = (data) => {
        if (typeof loadUsers === 'function') {
            loadUsers();
        }
        
        if (typeof showToast === 'function') {
            const userName = data.user.username || data.user.guest_name || 'Unknown';
            showToast(`${userName} joined the room`, 'info');
        }
    };
    
    socketClient.onUserLeft = (data) => {
        if (typeof loadUsers === 'function') {
            loadUsers();
        }
    };
    
    socketClient.onRoomJoined = (data) => {
        debugLog('✅ Successfully joined room via socket');
        
        if (data.users && typeof updateUsersList === 'function') {
            updateUsersList(data.users);
        }
    };
    
    socketClient.onUserTyping = (data) => {
        showTypingIndicator(data.user_id_string, data.typing, data.user_name);
    };
    
    socketClient.onError = (message) => {
        debugError('Socket error:', message);
        if (typeof showToast === 'function') {
            showToast('Connection error: ' + message, 'error');
        }
    };
    
    socketClient.onAuthSuccess = (data) => {
        debugLog('✅ Socket authentication successful');
        if (typeof showToast === 'function') {
            showToast('Real-time connection established', 'success');
        }
    };
    
    // Initialize the socket connection
    const success = socketClient.init(roomId, authData);
    
    if (success) {
        debugLog('✅ Socket client initialized successfully');
        
        // Disable existing polling if sockets work
        if (typeof activityTracker !== 'undefined' && activityTracker.cleanup) {
            debugLog('🔄 Disabling polling in favor of sockets');
            activityTracker.cleanup();
        }
        
        // Clear existing polling intervals
        if (typeof window.pollingIntervals !== 'undefined') {
            window.pollingIntervals.forEach(clearInterval);
            window.pollingIntervals = [];
        }
        
        return true;
    } else {
        debugLog('❌ Socket initialization failed, keeping polling');
        return false;
    }
}

// Enhanced send message function that uses sockets first
function sendMessageViaSocket(message, replyTo = null) {
    if (socketClient && socketClient.isConnected()) {
        return socketClient.sendMessage(message, replyTo);
    }
    
    return false;
}

// Format socket message for existing display code
function formatSocketMessage(socketMessage) {
    return {
        id: socketMessage.id,
        message: socketMessage.message,
        timestamp: socketMessage.timestamp,
        type: socketMessage.type || 'chat',
        user_id_string: socketMessage.user_id_string,
        display_name: socketMessage.display_name,
        avatar: socketMessage.avatar,
        color: socketMessage.color,
        avatar_hue: socketMessage.avatar_hue,
        avatar_saturation: socketMessage.avatar_saturation,
        bubble_hue: socketMessage.bubble_hue,
        bubble_saturation: socketMessage.bubble_saturation,
        is_admin: socketMessage.is_admin,
        is_moderator: socketMessage.is_moderator,
        is_host: socketMessage.is_host,
        reply_to_message_id: socketMessage.reply_to_message_id
    };
}

// Typing indicator display
function showTypingIndicator(userId, isTyping, userName = null) {
    const indicator = document.getElementById('typing-indicators');
    if (!indicator) return;
    
    const indicatorId = `typing-${userId}`;
    let userIndicator = document.getElementById(indicatorId);
    
    if (isTyping) {
        if (!userIndicator) {
            userIndicator = document.createElement('div');
            userIndicator.id = indicatorId;
            userIndicator.className = 'typing-indicator';
            userIndicator.innerHTML = `<small>${userName || userId} is typing...</small>`;
            indicator.appendChild(userIndicator);
        }
    } else {
        if (userIndicator) {
            userIndicator.remove();
        }
    }
}

// Override existing sendMessage to try socket first
if (typeof window.originalSendMessage === 'undefined' && typeof sendMessage === 'function') {
    window.originalSendMessage = sendMessage;
}

function sendMessage(replyTo = null) {
    const messageInput = $('#messageInput');
    const message = messageInput.val().trim();
    
    if (!message) return false;
    
    // Try socket first
    if (socketClient && socketClient.sendMessage(message, replyTo)) {
        messageInput.val('');
        socketClient.setTyping(false);
        return false;
    }
    
    // Fallback to original PHP method
    if (typeof window.originalSendMessage === 'function') {
        return window.originalSendMessage(replyTo);
    }
    
    return false;
}

// Add typing detection to message input
$(document).ready(function() {
    let typingTimer;
    
    $('#messageInput').on('input', function() {
        if (socketClient && socketClient.isConnected()) {
            socketClient.setTyping(true);
            
            clearTimeout(typingTimer);
            
            typingTimer = setTimeout(() => {
                socketClient.setTyping(false);
            }, 1000);
        }
    });
    
    $('#messageInput').on('blur', function() {
        if (socketClient) {
            socketClient.setTyping(false);
        }
    });
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (socketClient) {
        socketClient.disconnect();
    }
});
// friends_messaging_safe.js - Namespaced version to avoid conflicts
// All functions wrapped in FriendsMessaging namespace

const FriendsMessaging = {
    // Internal state
    friends: [],
    privateMessageConversations: [],
    whisperConversations: [],
    openPrivateChats: new Map(),
    friendshipCache: new Map(),
    friendshipCacheTimeout: new Map(),

    // ========================================
    // INITIALIZATION
    // ========================================
    init() {
        if (typeof currentUser === 'undefined' || currentUser.type !== 'user') {
            console.log('[Friends] User is not registered, skipping initialization');
            return;
        }

        console.log('[Friends] Initializing friends & messaging system...');
        
        // Load initial data
        this.loadFriends();
        this.loadConversations();
        this.loadFriendRequests();
        
        // Load whispers if in a room
        if (typeof roomId !== 'undefined' && roomId) {
            this.loadWhisperConversations();
        }
        
        // Set up periodic updates (every 10 seconds)
        setInterval(() => {
            this.loadFriends();
            this.loadConversations();
            this.loadFriendRequests();
            if (typeof roomId !== 'undefined' && roomId) {
                this.loadWhisperConversations();
            }
        }, 10000);
        
        // Set up event handlers
        this.setupEventHandlers();
        
        console.log('[Friends] System initialized successfully');
    },

    setupEventHandlers() {
        // Handle mobile modal sync
        $('#friendsMobileModal').on('show.bs.modal', () => {
            const sidebarContent = document.getElementById('friendsSidebarContent');
            const mobileContent = document.getElementById('friendsMobileContent');
            if (sidebarContent && mobileContent) {
                mobileContent.innerHTML = sidebarContent.innerHTML;
            }
        });
        
        // Handle window resize
        $(window).on('resize', () => {
            if (window.innerWidth > 768) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('friendsMobileModal'));
                if (modal) modal.hide();
            }
        });
    },

    // ========================================
    // FRIENDS MANAGEMENT
    // ========================================
    loadFriends() {
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/friends.php',
            method: 'GET',
            data: { action: 'get' },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                this.friends = response.friends || [];
                this.renderFriends();
            }
        }).catch(error => {
            console.error('[Friends] Error loading friends:', error);
        });
    },

    renderFriends() {
        const friendsList = document.getElementById('friendsList');
        if (!friendsList) return;

        const acceptedFriends = this.friends.filter(f => f.status === 'accepted');
        const friendsCount = document.getElementById('friendsCount');
        if (friendsCount) {
            friendsCount.textContent = acceptedFriends.length;
        }

        if (acceptedFriends.length === 0) {
            friendsList.innerHTML = `
                <div class="sidebar-empty-state">
                    <i class="fas fa-user-friends"></i>
                    <p>No friends yet</p>
                </div>
            `;
            return;
        }

        let html = '';
        acceptedFriends.forEach(friend => {
            const isOnline = friend.is_online || false;
            const statusClass = isOnline ? 'online' : 'offline';
            const statusText = isOnline ? 'Online' : 'Offline';
            const friendUserId = friend.friend_user_id;

            html += `
                <div class="friend-item" data-user-id="${friendUserId}">
                    <div class="friend-item-avatar-container">
                        <img src="images/${friend.avatar || 'default_avatar.jpg'}"
                             class="friend-item-avatar"
                             alt="${friend.username}"
                             style="filter: hue-rotate(${friend.avatar_hue || 0}deg) saturate(${friend.avatar_saturation || 100}%);">
                        <div class="friend-status-indicator ${statusClass}"></div>
                    </div>
                    <div class="friend-item-info">
                        <div class="friend-item-name">${friend.username}</div>
                        <div class="friend-item-status">${statusText}</div>
                    </div>
                    <div class="friend-item-actions">
                        <button class="friend-action-btn" onclick="FriendsMessaging.openPrivateMessage(${friendUserId}, '${friend.username}')" title="Send message">
                            <i class="fas fa-envelope"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        friendsList.innerHTML = html;
    },

    loadFriendRequests() {
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/friends.php',
            method: 'GET',
            data: { action: 'get_notifications' },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                const pendingRequests = response.notifications.filter(n => n.type === 'friend_request' && !n.is_read);
                this.renderFriendRequests(pendingRequests);
            }
        }).catch(error => {
            console.error('[Friends] Error loading friend requests:', error);
        });
    },

    renderFriendRequests(requests) {
        const requestsList = document.getElementById('friendRequestsList');
        const requestsSection = document.getElementById('friendRequestsSection');
        const requestsCount = document.getElementById('friendRequestsCount');

        if (!requestsList || !requestsSection) return;

        if (requests.length === 0) {
            requestsSection.style.display = 'none';
            return;
        }

        requestsSection.style.display = 'block';
        if (requestsCount) {
            requestsCount.textContent = requests.length;
        }

        let html = '';
        requests.forEach(request => {
            const timeAgo = this.getTimeAgo(request.created_at);
            html += `
                <div class="friend-request-item" data-notification-id="${request.id}">
                    <img src="images/${request.from_avatar || 'default_avatar.jpg'}"
                         class="friend-request-avatar"
                         alt="${request.from_username}">
                    <div class="friend-request-info">
                        <div class="friend-request-name">${request.from_username}</div>
                        <div class="friend-request-time">${timeAgo}</div>
                    </div>
                    <div class="friend-request-actions">
                        <button class="friend-request-btn accept" onclick="FriendsMessaging.acceptFriendRequest(${request.from_user_id}, ${request.id})">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="friend-request-btn decline" onclick="FriendsMessaging.declineFriendRequest(${request.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        requestsList.innerHTML = html;
    },

    addFriend() {
        const input = $('#addFriendInput');
        const username = input.val().trim();
        if (!username) return;
        
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/friends.php',
            method: 'POST',
            data: {
                action: 'add',
                friend_username: username
            },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                input.val('');
                this.showNotification('Friend request sent!', 'success');
                this.loadFriends();
            } else {
                this.showNotification('Error: ' + response.message, 'error');
            }
        }).catch(error => {
            console.error('[Friends] Error adding friend:', error);
            this.showNotification('Failed to send friend request', 'error');
        });
    },

    acceptFriendRequest(friendId, notificationId) {
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/friends.php',
            method: 'POST',
            data: {
                action: 'accept',
                friend_id: friendId
            },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                $(`[data-notification-id="${notificationId}"]`).fadeOut(300, function() {
                    $(this).remove();
                });
                
                this.loadFriends();
                this.loadFriendRequests();
                
                // Clear friendship cache
                if (typeof clearFriendshipCache === 'function') {
                    clearFriendshipCache();
                }
                
                this.showNotification('Friend request accepted!', 'success');
            } else {
                this.showNotification('Error: ' + response.message, 'error');
            }
        }).catch(error => {
            console.error('[Friends] Error accepting friend:', error);
            this.showNotification('Failed to accept friend request', 'error');
        });
    },

    declineFriendRequest(notificationId) {
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/friends.php',
            method: 'POST',
            data: {
                action: 'mark_read',
                notification_id: notificationId
            },
            dataType: 'json'
        }).then(response => {
            $(`[data-notification-id="${notificationId}"]`).fadeOut(300, function() {
                $(this).remove();
            });
            this.loadFriendRequests();
        }).catch(error => {
            console.error('[Friends] Error declining friend:', error);
        });
    },

    // ========================================
    // CONVERSATIONS MANAGEMENT
    // ========================================
    loadConversations() {
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/private_messages.php',
            method: 'GET',
            data: { action: 'get_conversations' },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                this.privateMessageConversations = response.conversations || [];
                this.renderConversations();
            }
        }).catch(error => {
            console.error('[Friends] Error loading conversations:', error);
        });
    },

    loadWhisperConversations() {
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/room_whispers.php',
            method: 'GET',
            data: { action: 'get_conversations' },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                this.whisperConversations = (response.conversations || []).map(conv => ({
                    ...conv,
                    type: 'whisper'
                }));
                this.renderConversations();
            }
        }).catch(error => {
            console.error('[Friends] Error loading whispers:', error);
        });
    },

    renderConversations() {
        const conversationsList = document.getElementById('conversationsList');
        if (!conversationsList) return;

        const allConversations = [...this.privateMessageConversations, ...this.whisperConversations];

        if (allConversations.length === 0) {
            conversationsList.innerHTML = `
                <div class="sidebar-empty-state">
                    <i class="fas fa-comments"></i>
                    <p>No conversations yet</p>
                </div>
            `;
            return;
        }

        allConversations.sort((a, b) => {
            const timeA = new Date(a.last_message_time || 0);
            const timeB = new Date(b.last_message_time || 0);
            return timeB - timeA;
        });

        let html = '';
        allConversations.forEach(conv => {
            const unreadClass = conv.unread_count > 0 ? 'unread' : '';
            const preview = conv.last_message ? this.stripHTML(conv.last_message) : 'No messages yet';
            const timeAgo = this.getTimeAgo(conv.last_message_time);
            const isWhisper = conv.type === 'whisper';
            const icon = isWhisper ? '<i class="fas fa-comment-dots" style="font-size: 0.8rem; margin-right: 4px;"></i>' : '';

            const recipientId = isWhisper ? conv.other_user_id_string : conv.other_user_id;
            const idParam = isWhisper ? `'${recipientId}'` : recipientId;
            const funcName = isWhisper ? 'openWhisperConversation' : 'openPrivateMessage';

            html += `
                <div class="conversation-item ${unreadClass}" onclick="FriendsMessaging.${funcName}(${idParam}, '${conv.username}')">
                    <img src="images/${conv.avatar || 'default_avatar.jpg'}"
                         class="conversation-avatar"
                         alt="${conv.username}"
                         style="filter: hue-rotate(${conv.avatar_hue || 0}deg) saturate(${conv.avatar_saturation || 100}%);">
                    <div class="conversation-info">
                        <div class="conversation-name">${icon}${conv.username}</div>
                        <div class="conversation-preview">${preview}</div>
                    </div>
                    <div class="conversation-meta">
                        <div class="conversation-time">${timeAgo}</div>
                        ${conv.unread_count > 0 ? `<div class="conversation-unread-badge">${conv.unread_count}</div>` : ''}
                    </div>
                </div>
            `;
        });

        conversationsList.innerHTML = html;
    },

    // ========================================
    // PRIVATE MESSAGES
    // ========================================
    openPrivateMessage(userId, username) {
        console.log('[Friends] Opening private message with:', userId, username);
        
        const dmModal = document.getElementById('dmModal');
        const dmTitle = dmModal?.querySelector('.dm-modal-title');
        
        if (dmModal) {
            dmModal.classList.remove('hidden', 'minimized');
            
            if (dmTitle) {
                dmTitle.innerHTML = `<i class="fas fa-envelope"></i> Chat with ${username}`;
            }
            
            dmModal.setAttribute('data-recipient-id', userId);
            dmModal.setAttribute('data-recipient-name', username);
            
            this.switchDMTab('private-messages');
            this.loadPrivateMessages(userId);
        }
        
        if (window.innerWidth <= 768) {
            const mobileModal = bootstrap.Modal.getInstance(document.getElementById('friendsMobileModal'));
            if (mobileModal) mobileModal.hide();
        }
    },

    loadPrivateMessages(userId) {
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/private_messages.php',
            method: 'GET',
            data: {
                action: 'get',
                other_user_id: userId
            },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                this.renderPrivateMessages(response.messages || []);
            }
        }).catch(error => {
            console.error('[Friends] Error loading private messages:', error);
        });
    },

    renderPrivateMessages(messages) {
        const messagesTab = document.getElementById('privateMessagesTab');
        if (!messagesTab) return;

        if (messages.length === 0) {
            messagesTab.innerHTML = `
                <div class="dm-empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <p class="dm-empty-state-title">No messages yet</p>
                    <p class="dm-empty-state-text">Start the conversation!</p>
                </div>
            `;
            return;
        }

        let html = '';
        messages.forEach(msg => {
            const isSent = msg.sender_id == currentUser.id;
            const messageClass = isSent ? 'sent' : 'received';
            const timeFormatted = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            const username = msg.sender_username;
            const avatar = msg.sender_avatar;
            const userColor = msg.color || 'blue';
            const bubbleClass = `user-color-${userColor}`;

            const avatarHue = msg.avatar_hue || 0;
            const avatarSat = msg.avatar_saturation || 100;
            let bubbleStyle = '';
            if (msg.bubble_hue !== null && msg.bubble_saturation !== null) {
                bubbleStyle = `filter: hue-rotate(${msg.bubble_hue}deg) saturate(${msg.bubble_saturation}%);`;
            }

            html += `
                <div class="private-chat-message ${messageClass}">
                    <img src="images/${avatar || 'default_avatar.jpg'}"
                         class="private-message-avatar"
                         style="filter: hue-rotate(${avatarHue}deg) saturate(${avatarSat}%);"
                         alt="${username}">
                    <div class="private-message-bubble ${bubbleClass} ${messageClass}" style="${bubbleStyle}">
                        <div class="private-message-header-info">
                            <div class="private-message-author">${username}</div>
                            <div class="private-message-time">${timeFormatted}</div>
                        </div>
                        <div class="private-message-content">${msg.message}</div>
                    </div>
                </div>
            `;
        });

        messagesTab.innerHTML = html;

        setTimeout(() => {
            const dmModalBody = messagesTab.closest('.dm-modal-body');
            if (dmModalBody) {
                dmModalBody.scrollTop = dmModalBody.scrollHeight;
            }
        }, 100);
    },

    sendPrivateMessage() {
        const dmModal = document.getElementById('dmModal');
        const recipientId = dmModal?.getAttribute('data-recipient-id');
        const messageInput = document.getElementById('dmMessageInput');
        const message = messageInput?.value.trim();
        
        if (!message || !recipientId) return;
        
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/private_messages.php',
            method: 'POST',
            data: {
                action: 'send',
                recipient_id: recipientId,
                message: message
            },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                messageInput.value = '';
                this.loadPrivateMessages(recipientId);
                this.loadConversations();
            } else {
                this.showNotification('Error: ' + response.message, 'error');
            }
        }).catch(error => {
            console.error('[Friends] Error sending message:', error);
            this.showNotification('Failed to send message', 'error');
        });
    },

    // ========================================
    // WHISPER MESSAGES
    // ========================================
    openWhisperConversation(userIdString, username) {
        console.log('[Friends] Opening whisper with:', userIdString, username);
        
        const dmModal = document.getElementById('dmModal');
        const dmTitle = dmModal?.querySelector('.dm-modal-title');
        
        if (dmModal) {
            dmModal.classList.remove('hidden', 'minimized');
            
            if (dmTitle) {
                dmTitle.innerHTML = `<i class="fas fa-comment-dots"></i> Whisper with ${username}`;
            }
            
            dmModal.setAttribute('data-whisper-recipient-id', userIdString);
            dmModal.setAttribute('data-recipient-name', username);
            
            this.switchDMTab('whispers');
            this.loadWhisperMessages(userIdString);
        }
        
        if (window.innerWidth <= 768) {
            const mobileModal = bootstrap.Modal.getInstance(document.getElementById('friendsMobileModal'));
            if (mobileModal) mobileModal.hide();
        }
    },

    loadWhisperMessages(userIdString) {
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/room_whispers.php',
            method: 'GET',
            data: {
                action: 'get',
                other_user_id_string: userIdString
            },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                this.renderWhisperMessages(response.messages || []);
            }
        }).catch(error => {
            console.error('[Friends] Error loading whispers:', error);
        });
    },

    renderWhisperMessages(messages) {
        const whispersTab = document.getElementById('whispersTab');
        if (!whispersTab) return;

        if (messages.length === 0) {
            whispersTab.innerHTML = `
                <div class="dm-empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <p class="dm-empty-state-title">No whispers yet</p>
                    <p class="dm-empty-state-text">Start a whisper conversation!</p>
                </div>
            `;
            return;
        }

        let html = '';
        messages.forEach(msg => {
            const isSent = msg.sender_id == currentUser.id;
            const messageClass = isSent ? 'sent' : 'received';
            const timeFormatted = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            const username = msg.sender_username;
            const avatar = msg.sender_avatar;
            const userColor = msg.color || 'blue';
            const bubbleClass = `user-color-${userColor}`;

            const avatarHue = msg.avatar_hue || 0;
            const avatarSat = msg.avatar_saturation || 100;
            let bubbleStyle = '';
            if (msg.bubble_hue !== null && msg.bubble_saturation !== null) {
                bubbleStyle = `filter: hue-rotate(${msg.bubble_hue}deg) saturate(${msg.bubble_saturation}%);`;
            }

            html += `
                <div class="private-chat-message ${messageClass}">
                    <img src="images/${avatar || 'default_avatar.jpg'}"
                         class="private-message-avatar"
                         style="filter: hue-rotate(${avatarHue}deg) saturate(${avatarSat}%);"
                         alt="${username}">
                    <div class="private-message-bubble ${bubbleClass} ${messageClass}" style="${bubbleStyle}">
                        <div class="private-message-header-info">
                            <div class="private-message-author">${username}</div>
                            <div class="private-message-time">${timeFormatted}</div>
                        </div>
                        <div class="private-message-content">${msg.message}</div>
                    </div>
                </div>
            `;
        });

        whispersTab.innerHTML = html;

        setTimeout(() => {
            const dmModalBody = whispersTab.closest('.dm-modal-body');
            if (dmModalBody) {
                dmModalBody.scrollTop = dmModalBody.scrollHeight;
            }
        }, 100);
    },

    sendWhisperMessage() {
        const dmModal = document.getElementById('dmModal');
        const recipientId = dmModal?.getAttribute('data-whisper-recipient-id');
        const messageInput = document.getElementById('dmMessageInput');
        const message = messageInput?.value.trim();
        
        if (!message || !recipientId) return;
        
        const ajaxFunction = typeof managedAjax !== 'undefined' ? managedAjax : $.ajax;
        
        ajaxFunction({
            url: 'api/room_whispers.php',
            method: 'POST',
            data: {
                action: 'send',
                recipient_user_id_string: recipientId,
                message: message
            },
            dataType: 'json'
        }).then(response => {
            if (response.status === 'success') {
                messageInput.value = '';
                this.loadWhisperMessages(recipientId);
                this.loadWhisperConversations();
            } else {
                this.showNotification('Error: ' + response.message, 'error');
            }
        }).catch(error => {
            console.error('[Friends] Error sending whisper:', error);
            this.showNotification('Failed to send whisper', 'error');
        });
    },

    // ========================================
    // DM MODAL CONTROLS
    // ========================================
    switchDMTab(tabName) {
        document.querySelectorAll('.dm-modal-tab').forEach(tab => {
            if (tab.getAttribute('data-tab') === tabName) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        document.querySelectorAll('.dm-tab-content').forEach(content => {
            content.classList.remove('active');
        });

        if (tabName === 'private-messages') {
            document.getElementById('privateMessagesTab')?.classList.add('active');
        } else if (tabName === 'whispers') {
            document.getElementById('whispersTab')?.classList.add('active');
        }
    },

    sendDMMessage(event) {
        // Prevent form submission
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        const dmModal = document.getElementById('dmModal');
        const activeTab = dmModal?.querySelector('.dm-modal-tab.active')?.getAttribute('data-tab');
        
        if (activeTab === 'private-messages') {
            this.sendPrivateMessage();
        } else if (activeTab === 'whispers') {
            this.sendWhisperMessage();
        }
        
        return false;
    },

    closeDMModal() {
        const dmModal = document.getElementById('dmModal');
        if (dmModal) {
            dmModal.classList.add('hidden');
        }
    },

    minimizeDMModal() {
        const dmModal = document.getElementById('dmModal');
        if (dmModal) {
            dmModal.classList.toggle('minimized');
        }
    },

    showFriendsPanel() {
        if (window.innerWidth <= 768) {
            const modal = new bootstrap.Modal(document.getElementById('friendsMobileModal'));
            modal.show();
        } else {
            const sidebar = document.getElementById('friendsSidebar');
            if (sidebar) {
                sidebar.querySelector('.friends-sidebar-content')?.scrollTo(0, 0);
            }
        }
    },

    // ========================================
    // UTILITY FUNCTIONS
    // ========================================
    getTimeAgo(timestamp) {
        if (!timestamp) return '';
        
        const now = new Date();
        const past = new Date(timestamp);
        const seconds = Math.floor((now - past) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
        return past.toLocaleDateString();
    },

    stripHTML(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.innerHTML = text;
        return div.textContent || div.innerText || '';
    },

    showNotification(message, type) {
        if (typeof showNotification === 'function') {
            showNotification(message, type);
        } else {
            alert(message);
        }
    }
};

// Global wrapper functions for onclick attributes
function openPrivateMessage(userId, username) {
    FriendsMessaging.openPrivateMessage(userId, username);
}

function openWhisperConversation(userIdString, username) {
    FriendsMessaging.openWhisperConversation(userIdString, username);
}

function addFriend() {
    FriendsMessaging.addFriend();
}

function acceptFriendRequest(friendId, notificationId) {
    FriendsMessaging.acceptFriendRequest(friendId, notificationId);
}

function declineFriendRequest(notificationId) {
    FriendsMessaging.declineFriendRequest(notificationId);
}

function switchDMTab(tabName) {
    FriendsMessaging.switchDMTab(tabName);
}

function sendDMMessage(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    FriendsMessaging.sendDMMessage(event);
    return false;
}

function closeDMModal() {
    FriendsMessaging.closeDMModal();
}

function minimizeDMModal() {
    FriendsMessaging.minimizeDMModal();
}

function showFriendsPanel() {
    FriendsMessaging.showFriendsPanel();
}

// Auto-initialize
$(document).ready(function() {
    if (typeof currentUser !== 'undefined' && currentUser.type === 'user') {
        FriendsMessaging.init();
    }
});
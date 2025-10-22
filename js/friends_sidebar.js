// friends_sidebar.js - New Friends Sidebar, DM Modal, and Notifications System
// This module handles the redesigned friends/DM/whisper interface

class FriendsSidebarManager {
    constructor() {
        this.friends = [];
        this.conversations = [];
        this.whisperConversations = [];
        this.friendRequests = [];
        this.dmModalOpen = false;
        this.currentDMRecipient = null;
        this.currentWhisperRecipient = null;
        this.currentTab = 'private-messages';
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };
        this.updateInterval = null;
        this.notificationQueue = [];

        this.initialize();
    }

    initialize() {
        console.log('Initializing Friends Sidebar Manager...');

        // Only initialize for registered users
        if (typeof currentUser === 'undefined' || currentUser.type !== 'user') {
            console.log('User is not a registered member, skipping sidebar initialization');
            return;
        }

        // Set up event listeners
        this.setupEventListeners();

        // Initialize draggable functionality for DM modal
        this.initializeDraggable();

        // Load initial data
        this.loadFriends();
        this.loadConversations();
        this.loadFriendRequests();

        // Start periodic updates (every 10 seconds)
        this.updateInterval = setInterval(() => {
            this.loadFriends();
            this.loadConversations();
            this.loadFriendRequests();
        }, 10000);

        console.log('Friends Sidebar Manager initialized');
    }

    setupEventListeners() {
        // Handle mobile/desktop switch for Friends button
        const originalShowFriendsPanel = window.showFriendsPanel;
        window.showFriendsPanel = () => {
            if (window.innerWidth <= 768) {
                // Mobile: Show modal
                const modal = new bootstrap.Modal(document.getElementById('friendsMobileModal'));
                modal.show();
            } else {
                // Desktop: Sidebar is always visible, just scroll to top
                const sidebar = document.getElementById('friendsSidebar');
                if (sidebar) {
                    sidebar.querySelector('.friends-sidebar-content').scrollTop = 0;
                }
            }
        };

        // Sync mobile modal content with sidebar content
        $('#friendsMobileModal').on('show.bs.modal', () => {
            const sidebarContent = document.getElementById('friendsSidebarContent');
            const mobileContent = document.getElementById('friendsMobileContent');
            if (sidebarContent && mobileContent) {
                mobileContent.innerHTML = sidebarContent.innerHTML;
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            this.handleResize();
        });
    }

    handleResize() {
        // Close mobile modal if switching to desktop
        if (window.innerWidth > 768) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('friendsMobileModal'));
            if (modal) {
                modal.hide();
            }
        }
    }

    // ========================================
    // FRIENDS MANAGEMENT
    // ========================================

    loadFriends() {
        $.ajax({
            url: 'api/friends.php',
            method: 'GET',
            data: { action: 'get' },
            dataType: 'json',
            success: (response) => {
                if (response.status === 'success') {
                    this.friends = response.friends || [];
                    this.renderFriends();
                }
            },
            error: (xhr, status, error) => {
                console.error('Error loading friends:', error);
            }
        });
    }

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

            html += `
                <div class="friend-item" data-user-id="${friend.friend_id}">
                    <div class="friend-item-avatar-container" style="position: relative;">
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
                        <button class="friend-action-btn" onclick="friendsSidebarManager.openPrivateMessage('${friend.friend_id}', '${friend.username}')" title="Send message">
                            <i class="fas fa-envelope"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        friendsList.innerHTML = html;
    }

    loadFriendRequests() {
        $.ajax({
            url: 'api/friends.php',
            method: 'GET',
            data: { action: 'get_notifications' },
            dataType: 'json',
            success: (response) => {
                if (response.status === 'success') {
                    const pendingRequests = response.notifications.filter(n => n.type === 'friend_request' && !n.is_read);
                    this.friendRequests = pendingRequests;
                    this.renderFriendRequests();
                }
            },
            error: (xhr, status, error) => {
                console.error('Error loading friend requests:', error);
            }
        });
    }

    renderFriendRequests() {
        const requestsList = document.getElementById('friendRequestsList');
        const requestsSection = document.getElementById('friendRequestsSection');
        const requestsCount = document.getElementById('friendRequestsCount');

        if (!requestsList || !requestsSection) return;

        if (this.friendRequests.length === 0) {
            requestsSection.style.display = 'none';
            return;
        }

        requestsSection.style.display = 'block';
        if (requestsCount) {
            requestsCount.textContent = this.friendRequests.length;
        }

        let html = '';
        this.friendRequests.forEach(request => {
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
                        <button class="friend-request-btn accept" onclick="friendsSidebarManager.acceptFriendRequest(${request.from_user_id}, ${request.id})">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="friend-request-btn decline" onclick="friendsSidebarManager.declineFriendRequest(${request.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        requestsList.innerHTML = html;
    }

    acceptFriendRequest(friendId, notificationId) {
        $.ajax({
            url: 'api/friends.php',
            method: 'POST',
            data: {
                action: 'accept',
                friend_id: friendId
            },
            dataType: 'json',
            success: (response) => {
                if (response.status === 'success') {
                    // Remove the notification
                    $(`[data-notification-id="${notificationId}"]`).fadeOut(300, function() {
                        $(this).remove();
                    });

                    // Reload friends and requests
                    this.loadFriends();
                    this.loadFriendRequests();

                    this.showToastNotification('friend-request', 'Friend Request Accepted', `You are now friends with this user!`);
                } else {
                    alert('Error accepting friend request: ' + response.message);
                }
            },
            error: (xhr, status, error) => {
                console.error('Error accepting friend request:', error);
                alert('Failed to accept friend request');
            }
        });
    }

    declineFriendRequest(notificationId) {
        // Mark notification as read to remove it
        $.ajax({
            url: 'api/friends.php',
            method: 'POST',
            data: {
                action: 'mark_read',
                notification_id: notificationId
            },
            dataType: 'json',
            success: (response) => {
                $(`[data-notification-id="${notificationId}"]`).fadeOut(300, function() {
                    $(this).remove();
                });
                this.loadFriendRequests();
            }
        });
    }

    // ========================================
    // CONVERSATIONS MANAGEMENT
    // ========================================

    loadConversations() {
        $.ajax({
            url: 'api/private_messages.php',
            method: 'GET',
            data: { action: 'get_conversations' },
            dataType: 'json',
            success: (response) => {
                if (response.status === 'success') {
                    this.conversations = response.conversations || [];
                    this.renderConversations();
                }
            },
            error: (xhr, status, error) => {
                console.error('Error loading conversations:', error);
            }
        });
    }

    renderConversations() {
        const conversationsList = document.getElementById('conversationsList');
        if (!conversationsList) return;

        if (this.conversations.length === 0) {
            conversationsList.innerHTML = `
                <div class="sidebar-empty-state">
                    <i class="fas fa-comments"></i>
                    <p>No conversations yet</p>
                </div>
            `;
            return;
        }

        let html = '';
        this.conversations.forEach(conv => {
            const unreadClass = conv.unread_count > 0 ? 'unread' : '';
            const preview = conv.last_message ? this.stripHTML(conv.last_message) : 'No messages yet';
            const timeAgo = this.getTimeAgo(conv.last_message_time);

            html += `
                <div class="conversation-item ${unreadClass}" onclick="friendsSidebarManager.openPrivateMessage('${conv.other_user_id}', '${conv.other_username}')">
                    <img src="images/${conv.other_avatar || 'default_avatar.jpg'}"
                         class="conversation-avatar"
                         alt="${conv.other_username}"
                         style="filter: hue-rotate(${conv.other_avatar_hue || 0}deg) saturate(${conv.other_avatar_saturation || 100}%);">
                    <div class="conversation-info">
                        <div class="conversation-name">${conv.other_username}</div>
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
    }

    // ========================================
    // DM MODAL MANAGEMENT
    // ========================================

    openPrivateMessage(userId, username) {
        this.currentDMRecipient = { userId, username };
        this.dmModalOpen = true;
        this.currentTab = 'private-messages';

        const dmModal = document.getElementById('dmModal');
        const dmRecipientInfo = document.getElementById('dmRecipientInfo');

        if (dmModal) {
            dmModal.classList.remove('hidden');
            dmModal.classList.remove('minimized');

            if (dmRecipientInfo) {
                dmRecipientInfo.textContent = `- ${username}`;
            }

            // Switch to Private Messages tab and show conversation
            this.switchDMTab('private-messages');
            this.showDMConversation(userId, username);
        }
    }

    showDMConversation(userId, username) {
        // Hide conversations list, show messages container
        const conversationsList = document.getElementById('dmConversationsList');
        const messagesContainer = document.getElementById('dmMessagesContainer');
        const dmCurrentRecipient = document.getElementById('dmCurrentRecipient');

        if (conversationsList) conversationsList.style.display = 'none';
        if (messagesContainer) {
            messagesContainer.classList.add('active');
            messagesContainer.style.display = 'flex';
        }
        if (dmCurrentRecipient) dmCurrentRecipient.textContent = username;

        // Load messages for this user
        this.loadPrivateMessages(userId);
    }

    closeDMConversation() {
        const conversationsList = document.getElementById('dmConversationsList');
        const messagesContainer = document.getElementById('dmMessagesContainer');

        if (conversationsList) conversationsList.style.display = 'flex';
        if (messagesContainer) {
            messagesContainer.classList.remove('active');
            messagesContainer.style.display = 'none';
        }

        this.currentDMRecipient = null;
    }

    loadPrivateMessages(userId) {
        $.ajax({
            url: 'api/private_messages.php',
            method: 'GET',
            data: {
                action: 'get',
                other_user_id: userId
            },
            dataType: 'json',
            success: (response) => {
                if (response.status === 'success') {
                    this.renderPrivateMessages(response.messages || []);
                }
            },
            error: (xhr, status, error) => {
                console.error('Error loading private messages:', error);
            }
        });
    }

    renderPrivateMessages(messages) {
        const messagesList = document.getElementById('dmMessagesList');
        if (!messagesList) return;

        if (messages.length === 0) {
            messagesList.innerHTML = `
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
            const timeAgo = this.getTimeAgo(msg.created_at);

            html += `
                <div class="dm-message ${messageClass}">
                    <img src="images/${msg.avatar || 'default_avatar.jpg'}"
                         class="dm-message-avatar"
                         style="filter: hue-rotate(${msg.avatar_hue || 0}deg) saturate(${msg.avatar_saturation || 100}%);"
                         alt="${msg.username}">
                    <div class="dm-message-content">
                        <div class="dm-message-header">
                            <span class="dm-message-author">${msg.username}</span>
                            <span class="dm-message-time">${timeAgo}</span>
                        </div>
                        <div class="dm-message-bubble">${msg.message}</div>
                    </div>
                </div>
            `;
        });

        messagesList.innerHTML = html;

        // Scroll to bottom
        messagesList.scrollTop = messagesList.scrollHeight;
    }

    switchDMTab(tabName) {
        this.currentTab = tabName;

        // Update tab buttons
        document.querySelectorAll('.dm-modal-tab').forEach(tab => {
            if (tab.getAttribute('data-tab') === tabName) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        // Update tab content
        document.querySelectorAll('.dm-tab-content').forEach(content => {
            content.classList.remove('active');
        });

        if (tabName === 'private-messages') {
            document.getElementById('privateMessagesTab').classList.add('active');
        } else if (tabName === 'whispers') {
            document.getElementById('whispersTab').classList.add('active');
            this.loadWhisperConversations();
        }
    }

    loadWhisperConversations() {
        // TODO: Implement whisper conversations loading
        // This will integrate with the existing whisper system
        console.log('Loading whisper conversations...');
    }

    toggleDMModal() {
        const dmModal = document.getElementById('dmModal');
        if (dmModal) {
            dmModal.classList.toggle('minimized');
        }
    }

    closeDMModal() {
        const dmModal = document.getElementById('dmModal');
        if (dmModal) {
            dmModal.classList.add('hidden');
        }
        this.dmModalOpen = false;
        this.currentDMRecipient = null;
    }

    sendDMMessage(event) {
        event.preventDefault();

        if (!this.currentDMRecipient) {
            alert('No recipient selected');
            return;
        }

        const input = document.getElementById('dmMessageInput');
        const message = input.value.trim();

        if (!message) return;

        $.ajax({
            url: 'api/private_messages.php',
            method: 'POST',
            data: {
                action: 'send',
                recipient_id: this.currentDMRecipient.userId,
                message: message
            },
            dataType: 'json',
            success: (response) => {
                if (response.status === 'success') {
                    input.value = '';
                    // Reload messages
                    this.loadPrivateMessages(this.currentDMRecipient.userId);
                    // Reload conversations to update preview
                    this.loadConversations();
                } else {
                    alert('Error sending message: ' + response.message);
                }
            },
            error: (xhr, status, error) => {
                console.error('Error sending message:', error);
                alert('Failed to send message');
            }
        });
    }

    // ========================================
    // DRAGGABLE FUNCTIONALITY
    // ========================================

    initializeDraggable() {
        const dmModal = document.getElementById('dmModal');
        const dragHandle = dmModal?.querySelector('.dm-modal-drag-handle');

        if (!dmModal || !dragHandle) return;

        dragHandle.addEventListener('mousedown', (e) => {
            this.isDragging = true;
            const rect = dmModal.getBoundingClientRect();
            this.dragOffset = {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
            dmModal.classList.add('dragging');
        });

        document.addEventListener('mousemove', (e) => {
            if (!this.isDragging) return;

            const dmModal = document.getElementById('dmModal');
            if (!dmModal) return;

            const x = e.clientX - this.dragOffset.x;
            const y = e.clientY - this.dragOffset.y;

            // Constrain to viewport
            const maxX = window.innerWidth - dmModal.offsetWidth;
            const maxY = window.innerHeight - dmModal.offsetHeight;

            const constrainedX = Math.max(0, Math.min(x, maxX));
            const constrainedY = Math.max(0, Math.min(y, maxY));

            dmModal.style.left = `${constrainedX}px`;
            dmModal.style.top = `${constrainedY}px`;
            dmModal.style.right = 'auto';
            dmModal.style.bottom = 'auto';
        });

        document.addEventListener('mouseup', () => {
            if (this.isDragging) {
                this.isDragging = false;
                const dmModal = document.getElementById('dmModal');
                if (dmModal) {
                    dmModal.classList.remove('dragging');
                }
            }
        });
    }

    // ========================================
    // TOAST NOTIFICATIONS
    // ========================================

    showToastNotification(type, title, message) {
        const container = document.getElementById('notificationToastContainer');
        if (!container) return;

        const iconClass = type === 'friend-request' ? 'fa-user-plus' : 'fa-envelope';
        const toastClass = type === 'friend-request' ? 'friend-request' : 'dm';

        const toastHtml = `
            <div class="notification-toast">
                <div class="notification-toast-icon ${toastClass}">
                    <i class="fas ${iconClass}"></i>
                </div>
                <div class="notification-toast-content">
                    <div class="notification-toast-title">${title}</div>
                    <div class="notification-toast-message">${message}</div>
                </div>
                <button class="notification-toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        const toast = $(toastHtml);
        container.appendChild(toast[0]);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.fadeOut(300, function() {
                this.remove();
            });
        }, 5000);
    }

    // ========================================
    // UTILITY FUNCTIONS
    // ========================================

    getTimeAgo(dateString) {
        if (!dateString) return '';

        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
        return date.toLocaleDateString();
    }

    stripHTML(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }

    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
    }
}

// Initialize the Friends Sidebar Manager
let friendsSidebarManager;
$(document).ready(() => {
    if (typeof currentUser !== 'undefined' && currentUser.type === 'user') {
        friendsSidebarManager = new FriendsSidebarManager();
    }
});

// Global functions for onclick handlers
function toggleDMModal() {
    if (friendsSidebarManager) {
        friendsSidebarManager.toggleDMModal();
    }
}

function closeDMModal() {
    if (friendsSidebarManager) {
        friendsSidebarManager.closeDMModal();
    }
}

function switchDMTab(tabName) {
    if (friendsSidebarManager) {
        friendsSidebarManager.switchDMTab(tabName);
    }
}

function closeDMConversation() {
    if (friendsSidebarManager) {
        friendsSidebarManager.closeDMConversation();
    }
}

function closeWhisperConversation() {
    if (friendsSidebarManager) {
        // TODO: Implement whisper conversation closing
        console.log('Closing whisper conversation');
    }
}

function sendDMMessage(event) {
    if (friendsSidebarManager) {
        friendsSidebarManager.sendDMMessage(event);
    }
}

function addFriend(event) {
    event.preventDefault();

    const input = document.getElementById('addFriendInput');
    const username = input.value.trim();

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
                input.value = '';
                if (friendsSidebarManager) {
                    friendsSidebarManager.showToastNotification('friend-request', 'Friend Request Sent', `Request sent to ${username}`);
                    friendsSidebarManager.loadFriends();
                }
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error adding friend:', error);
            alert('Failed to send friend request');
        }
    });
}

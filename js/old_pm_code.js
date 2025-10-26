
// Minimal initialization for private messaging (without panel functions)
function initializePrivateMessaging() {
    if (currentUser.type !== 'user') return;

    debugLog('💬 Initializing private messaging...');
    // loadFriends(); // REMOVED - handled by new friends_sidebar.js
    // checkForNewPrivateMessages is now handled by polling system

    debugLog('✅ Private messaging initialized (using managed updates)');
}

// OLD PANEL FUNCTIONS DISABLED - Using new friends_sidebar.js instead
/*
function showFriendsPanel() {
    $('#friendsPanel').show();
    loadFriends();
    loadConversations();
}

function closeFriendsPanel() {
    $('#friendsPanel').hide();
}
*/

// OLD SYSTEM DISABLED - Using new friends_sidebar.js instead
/*
function loadFriends() {
    debugLog('Loading friends...');

    // REMOVE the polling check - always make AJAX call for now
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
        $('#friendsList').html('<p class="text-danger">Failed to load friends</p>');
    });
}
*/

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
                        <img src="images/${friend.avatar || 'default_avatar.jpg'}" loading="eager" class="me-2" style="border-radius: 2px; filter: hue-rotate(${friend.avatar_hue || 0}deg) saturate(${friend.avatar_saturation || 100}%);">
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
    
    managedAjax({
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
                // loadFriends(); // REMOVED - handled by friends_sidebar.js
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function acceptFriend(friendId) {
    // Disable button to prevent double-clicks
    const acceptBtn = $(`button[onclick="acceptFriend(${friendId})"]`);
    const originalHtml = acceptBtn.html();
    acceptBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    managedAjax({
        url: 'api/friends.php',
        method: 'POST',
        data: {
            action: 'accept',
            friend_id: friendId
        },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.status === 'success') {
                showNotification('Friend request accepted!', 'success');
                
                if (typeof clearFriendshipCache === 'function') {
                    clearFriendshipCache();
                }
                if (typeof loadUsers === 'function') {
                    loadUsers();
                }
                
                // Update friends list - DISABLED, handled by friends_sidebar.js
                // if (friends && Array.isArray(friends)) {
                //     updateFriendsPanel();
                // }
            } else {
                showNotification('Error: ' + (response.message || 'Unknown error'), 'error');
                acceptBtn.prop('disabled', false).html(originalHtml);
            }
        },
        error: function(xhr, status, error) {
            console.error('Accept friend error:', {xhr, status, error});
            let errorMsg = 'Network error occurred';
            
            if (status === 'timeout') {
                errorMsg = 'Request timed out. Please try again.';
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            
            showNotification('Error: ' + errorMsg, 'error');
            acceptBtn.prop('disabled', false).html(originalHtml);
        },
        complete: function() {
            // Always re-enable button after request completes
            setTimeout(() => {
                acceptBtn.prop('disabled', false).html(originalHtml);
            }, 100);
        }
    });
}

function loadConversations() {
    debugLog('Loading conversations...');
    
    // REMOVE the polling check - always make AJAX call for now
    managedAjax({
        url: 'api/private_messages.php',
        method: 'GET',
        data: { action: 'get_conversations' },
        dataType: 'json'
    }).then(response => {
        debugLog('Conversations response:', response);
        if (response.status === 'success') {
            privateMessageConversations = response.conversations || [];
            displayConversations(privateMessageConversations);
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
                    <img src="images/${conv.avatar}" loading="eager" class="me-2" style="border-radius: 2px; filter: hue-rotate(${conv.avatar_hue || 0}deg) saturate(${conv.avatar_saturation || 100}%);">
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
    managedAjax({
        url: 'api/get_user_info.php',
        method: 'GET',
        data: { user_id: userId },
        dataType: 'json'
    }).then(response => {
        debugLog('User info response:', response);
        if (response.status === 'success') {
            const chatData = openPrivateChats.get(userId);
            chatData.color = response.user.color || 'blue';
            chatData.avatar = response.user.avatar || 'default_avatar.jpg';
            openPrivateChats.set(userId, chatData);
            debugLog('Fetched user color:', response.user.color);
            loadPrivateMessages(userId);
        }
    }).catch(error => {
        console.error('Failed to fetch user info:', error);
        debugLog('Failed to fetch user info, using default color');
        loadPrivateMessages(userId);
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
    
    managedAjax({
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
                 loading="eager"
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

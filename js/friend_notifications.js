// js/friend_notifications.js - Uses main SSE connection (NO polling, NO separate SSE)
let notificationCount = 0;
let lastNotificationCheck = Date.now();

function initFriendNotifications() {
    if (typeof currentUser === 'undefined' || currentUser.type !== 'user') return;
    
    addNotificationBadge();
}

// Called from room.js when SSE data arrives
window.updateFriendNotifications = function(data) {
    if (!data) return;
    
    const newCount = data.count;
    
    if (newCount > notificationCount && notificationCount > 0) {
        showNotificationPopup(data.notifications[0]);
    }
    
    notificationCount = newCount;
    updateNotificationBadge(newCount);
    
    if ($('#friendsPanel').is(':visible')) {
        updateNotificationList(data.notifications);
    }
};

// Called from room.js when SSE detects room status change
window.handleRoomStatusUpdate = function(status) {
    if (status === 'not_in_room') {
        handleRoomDisconnect();
    }
};

function handleRoomDisconnect() {
    const overlay = document.createElement('div');
    overlay.innerHTML = `
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:99999;display:flex;align-items:center;justify-content:center;">
            <div style="background:white;padding:30px;border-radius:10px;text-align:center;max-width:400px;">
                <h2>⚠️ Disconnected</h2>
                <p>You have been removed from the room.</p>
                <button onclick="window.location.href='/lounge'" style="padding:10px 30px;background:#4CAF50;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px;">
                    Return to Lounge
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    
    setTimeout(() => {
        window.location.href = '/lounge';
    }, 5000);
}

function addNotificationBadge() {
    $('.friends-btn').each(function() {
        if (!$(this).find('.notification-badge').length) {
            $(this).css('position', 'relative');
            $(this).append('<span class="notification-badge" style="display:none;">0</span>');
        }
    });
}

function updateNotificationBadge(count) {
    $('.notification-badge').each(function() {
        if (count > 0) {
            $(this).text(count).show();
            $(this).parent().addClass('has-notifications');
        } else {
            $(this).hide();
            $(this).parent().removeClass('has-notifications');
        }
    });
}

function updateNotificationList(notifications) {
    const container = $('#friendsList .notifications-section');
    
    if (notifications.length === 0) {
        if (container.length) {
            container.remove();
        }
        return;
    }
    
    let html = '<div class="notifications-section mb-3">';
    html += '<h6 style="color: #e0e0e0;"><i class="fas fa-bell"></i> Notifications</h6>';
    
    notifications.forEach(notif => {
        html += createNotificationHTML(notif);
    });
    
    html += '<button class="btn btn-sm btn-secondary w-100 mt-2" onclick="markAllNotificationsRead()">Mark all as read</button>';
    html += '</div>';
    
    if (container.length) {
        container.replaceWith(html);
    } else {
        $('#friendsList').prepend(html);
    }
}

function createNotificationHTML(notif) {
    const timeAgo = getTimeAgo(new Date(notif.created_at));
    const title = getNotificationTitle(notif.type);
    
    return `
        <div class="notification-item unread" data-id="${notif.id}" data-type="${notif.type}">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <img src="images/${notif.from_avatar || 'default.png'}" 
                     style="width:32px;height:32px;border-radius:6px;object-fit:cover;" 
                     alt="Avatar">
                <div style="flex:1;">
                    <div style="color:#fff;font-weight:600;font-size:0.85rem;">
                        ${notif.from_user}
                    </div>
                    <div style="color:#b9bbbe;font-size:0.75rem;">${title}</div>
                </div>
            </div>
            <div style="color:#dcddde;font-size:0.8rem;line-height:1.4;">
                ${notif.message}
            </div>
            <div class="notification-time" style="color:#72767d;font-size:0.7rem;margin-top:4px;">
                ${timeAgo}
            </div>
        </div>
    `;
}

function getNotificationTitle(type) {
    switch(type) {
        case 'friend_request': return 'New Friend Request';
        case 'friend_accepted': return 'Friend Request Accepted';
        case 'message': return 'New Message';
        default: return 'Notification';
    }
}

function showNotificationPopup(notif) {
    const html = `
        <div class="friend-notification-popup">
            <div class="notification-popup-header">
                <img src="images/${notif.from_avatar || 'default.png'}" class="notification-popup-avatar">
                <div class="notification-popup-content">
                    <div class="notification-popup-title">${getNotificationTitle(notif.type)}</div>
                    <div class="notification-popup-message">${notif.message}</div>
                </div>
                <button class="notification-popup-close" onclick="$(this).closest('.friend-notification-popup').fadeOut()">×</button>
            </div>
        </div>
    `;
    
    $('body').append(html);
    $('.friend-notification-popup').fadeIn();
    
    setTimeout(() => {
        $('.friend-notification-popup').fadeOut(function() {
            $(this).remove();
        });
    }, 5000);
    
    playFriendNotificationSound();
}

function markAllNotificationsRead() {
    $.ajax({
        url: 'api/friends.php',
        method: 'POST',
        data: { action: 'mark_read' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                notificationCount = 0;
                updateNotificationBadge(0);
                $('.notification-item').removeClass('has-notifications');
            }
        }
    });
}

function getTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    return Math.floor(seconds / 86400) + ' days ago';
}

function playFriendNotificationSound() {
    if (!$('#notificationSound').length) {
        $('body').append('<audio id="notificationSound" src="sounds/notification.mp3" preload="auto"></audio>');
    }
    
    const audio = document.getElementById('notificationSound');
    if (audio) {
        audio.volume = 0.3;
        audio.play().catch(e => console.log('Could not play notification sound:', e));
    }
}

const originalShowFriendsPanel = window.showFriendsPanel;
window.showFriendsPanel = function() {
    if (typeof originalShowFriendsPanel === 'function') {
        originalShowFriendsPanel();
    }
    
    // Fetch fresh data when panel opens
    if (notificationCount > 0) {
        $.ajax({
            url: 'api/friends.php',
            method: 'GET',
            data: { action: 'get_notifications' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    updateNotificationList(response.notifications);
                }
            }
        });
    }
    
    setTimeout(() => {
        if (notificationCount > 0) {
            markAllNotificationsRead();
        }
    }, 1000);
};

$(document).ready(function() {
    setTimeout(initFriendNotifications, 500);
});
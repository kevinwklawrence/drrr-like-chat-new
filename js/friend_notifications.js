// js/friend_notifications.js - Friend notification system

let notificationCount = 0;
let lastNotificationCheck = Date.now();

// Initialize notification system
function initFriendNotifications() {
    if (typeof currentUser === 'undefined' || currentUser.type !== 'user') return;
    
    // Check for notifications immediately
    checkFriendNotifications();
    
    // Check for new notifications every 5 seconds
    setInterval(checkFriendNotifications, 5000);
    
    // Add notification badge to friends button
    addNotificationBadge();
}

// Add notification badge to friends button
function addNotificationBadge() {
    // Add badge container to friends button if not exists
    $('.friends-btn').each(function() {
        if (!$(this).find('.notification-badge').length) {
            $(this).css('position', 'relative');
            $(this).append('<span class="notification-badge" style="display:none;">0</span>');
        }
    });
}

// Check for new friend notifications
function checkFriendNotifications() {
    $.ajax({
        url: 'api/friends.php',
        method: 'GET',
        data: { action: 'get_notifications' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const newCount = response.count;
                
                // If there are new notifications, show them
                if (newCount > notificationCount && notificationCount > 0) {
                    showNotificationPopup(response.notifications[0]);
                }
                
                notificationCount = newCount;
                updateNotificationBadge(newCount);
                
                // Update notification list if panel is open
                if ($('#friendsPanel').is(':visible')) {
                    updateNotificationList(response.notifications);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to check notifications:', error);
        }
    });
}

// Update notification badge
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

// Show popup notification
function showNotificationPopup(notification) {
    const popup = $(`
        <div class="friend-notification-popup">
            <div class="notification-popup-header">
                <img src="images/${notification.from_avatar || 'default_avatar.jpg'}" class="notification-popup-avatar">
                <div class="notification-popup-content">
                    <div class="notification-popup-title">${getNotificationTitle(notification.type)}</div>
                    <div class="notification-popup-message">${notification.message}</div>
                </div>
                <button class="notification-popup-close" onclick="$(this).closest('.friend-notification-popup').fadeOut()">×</button>
            </div>
        </div>
    `);
    
    $('body').append(popup);
    popup.fadeIn();
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        popup.fadeOut(function() {
            $(this).remove();
        });
    }, 5000);
    
    // Play notification sound for friend notifications
    playFriendNotificationSound();
}

// Play sound for friend notification
function playFriendNotificationSound() {
    const audio = new Audio('/sounds/private_message_notification.mp3');
   // audio.play();
}

// Get notification title based on type
function getNotificationTitle(type) {
    switch(type) {
        case 'friend_request':
            return 'New Friend Request';
        case 'friend_accepted':
            return 'Friend Request Accepted';
        case 'private_message':
            return 'New Message';
        default:
            return 'Notification';
    }
}

// Update notification list in friends panel
function updateNotificationList(notifications) {
    let html = '<div class="notifications-section mb-3">';
    html += '<h6 style="color: #e0e0e0;"><i class="fas fa-bell"></i> Notifications</h6>';
    
    if (notifications.length === 0) {
        html += '<p class="text-muted small">No new notifications</p>';
    } else {
        notifications.forEach(notif => {
            const timeAgo = getTimeAgo(new Date(notif.created_at));
            const unreadClass = notif.is_read == 0 ? 'unread' : '';
            
            html += `
                <div class="notification-item ${unreadClass}" data-id="${notif.id}">
                    <img src="images/${notif.from_avatar || 'default_avatar.jpg'}" class="notification-avatar">
                    <div class="notification-content">
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">${timeAgo}</div>
                    </div>
                </div>
            `;
        });
        
        html += '<button class="btn btn-sm btn-secondary w-100 mt-2" onclick="markAllNotificationsRead()">Mark all as read</button>';
    }
    
    html += '</div>';
    
    // Prepend to friends panel content
    if ($('#friendsList .notifications-section').length) {
        $('#friendsList .notifications-section').replaceWith(html);
    } else {
        $('#friendsList').prepend(html);
    }
}

// Mark all notifications as read
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
                $('.notification-item').removeClass('unread');
            }
        }
    });
}

// Helper function to get time ago string
function getTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    return Math.floor(seconds / 86400) + ' days ago';
}

// Play notification sound
function playNotificationSound() {
    // Create audio element if it doesn't exist
    if (!$('#notificationSound').length) {
        $('body').append('<audio id="notificationSound" src="sounds/notification.mp3" preload="auto"></audio>');
    }
    
    const audio = document.getElementById('notificationSound');
    if (audio) {
        audio.volume = 0.3;
        audio.play().catch(e => console.log('Could not play notification sound:', e));
    }
}

// Override the existing showFriendsPanel to mark notifications as read
const originalShowFriendsPanel = window.showFriendsPanel;
window.showFriendsPanel = function() {
    if (typeof originalShowFriendsPanel === 'function') {
        originalShowFriendsPanel();
    }
    
    // Check for new notifications when panel opens
    checkFriendNotifications();
    
    // Mark as read after a short delay
    setTimeout(() => {
        if (notificationCount > 0) {
            markAllNotificationsRead();
        }
    }, 1000);
};

// Initialize when document is ready
$(document).ready(function() {
    // Initialize notifications after a short delay to ensure everything is loaded
    setTimeout(initFriendNotifications, 500);
});
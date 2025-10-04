// js/notifications.js - Uses main SSE connection (NO polling, NO separate SSE)
let playedNotificationIds = new Set();
let currentNotifications = [];

function initializeNotifications() {
    $(document).on('click', '#notificationBell', toggleNotificationsPanel);
    $(document).on('click', '.notification-panel-close', closeNotificationsPanel);
    $(document).on('click', '.notification-mark-all', markAllNotificationsRead);
    $(document).on('click', '.notification-item', handleNotificationClick);
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.notifications-panel, #notificationBell').length) {
            closeNotificationsPanel();
        }
    });
}

// Called from room.js when SSE data arrives
window.updateGeneralNotifications = function(data) {
    if (!data) return;
    
    currentNotifications = data.notifications || [];
    updateNotificationBell();
    updateNotificationsPanel();
};

function updateNotificationBell() {
    const bell = $('#notificationBell');
    const badge = bell.find('.notification-badge');
    const count = currentNotifications.length;
    
    if (count > 0) {
        if (!badge.length) {
            bell.addClass('has-notifications');
            bell.append('<span class="notification-badge"></span>');
        }
        bell.find('.notification-badge').text(count > 99 ? '99+' : count);
    } else {
        bell.removeClass('has-notifications');
        badge.remove();
    }
}

function showNotificationsPanel() {
    let panel = $('.notifications-panel');
    
    if (panel.length === 0) {
        createNotificationsPanel();
        panel = $('.notifications-panel');
    }
    
    updateNotificationsPanel();
    panel.addClass('show');
}

function closeNotificationsPanel() {
    $('.notifications-panel').removeClass('show');
}

function createNotificationsPanel() {
    const panelHTML = `
        <div class="notifications-panel">
            <div class="notifications-panel-header">
                <h6 class="notifications-panel-title">
                    <i class="fas fa-bell"></i> Notifications
                </h6>
                <div class="notifications-panel-actions">
                    <button class="btn btn-sm btn-outline-light notification-mark-all" title="Mark all as read">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-light notification-panel-close" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="notifications-panel-content">
                <div class="notifications-loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
        </div>
    `;
    
    $('body').append(panelHTML);
}

function updateNotificationsPanel() {
    const content = $('.notifications-panel-content');
    
    if (currentNotifications.length === 0) {
        content.html('<div class="notifications-empty"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>');
        return;
    }
    
    let html = '';
    
    currentNotifications.forEach(notification => {
        html += createNotificationHTML(notification);
        
        if ((notification.type === 'mention' || notification.type === 'reply') && 
            !playedNotificationIds.has(notification.id)) {
            playReplyOrMentionSound();
            playedNotificationIds.add(notification.id);
        }
    });
    
    content.html(html);
}

function createNotificationHTML(notification) {
    const timeAgo = getTimeAgo(notification.timestamp);
    
    let iconClass = 'fa-bell';
    if (notification.type === 'mention') iconClass = 'fa-at';
    else if (notification.type === 'reply') iconClass = 'fa-reply';
    
    return `
        <div class="notification-item ${notification.type}" 
             data-id="${notification.id}"
             data-type="${notification.type}"
             data-message-id="${notification.message_id || ''}">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <img src="images/${notification.sender_avatar || ''}" 
                     style="width:32px;height:32px;border-radius:6px;object-fit:cover;" 
                     alt="Avatar">
                <div style="flex:1;">
                    <div style="color:#fff;font-weight:600;font-size:0.85rem;display:flex;align-items:center;gap:6px;">
                        <i class="fas ${iconClass}"></i> ${notification.sender_name}
                    </div>
                    <div style="color:#b9bbbe;font-size:0.75rem;">${notification.title}</div>
                </div>
                <div style="color:#72767d;font-size:0.7rem;">${timeAgo}</div>
            </div>
            <div style="color:#dcddde;font-size:0.8rem;line-height:1.4;overflow-wrap:break-word;">
                ${notification.message}
            </div>
        </div>
    `;
}

function handleNotificationClick(e) {
    const item = $(e.currentTarget);
    const id = item.data('id');
    const type = item.data('type');
    const messageId = item.data('message-id');
    
    markNotificationRead(id, type);
    
    if (type === 'mention' && messageId) {
        jumpToMessage(messageId);
    }
    
    closeNotificationsPanel();
}

function markNotificationRead(id, type) {
    $.ajax({
        url: 'api/handle_notification_action.php',
        method: 'POST',
        data: {
            action: 'mark_read',
            notification_type: type,
            notification_id: id
        },
        dataType: 'json',
        success: function(response) {
            currentNotifications = currentNotifications.filter(n => n.id !== id);
            updateNotificationBell();
        }
    });
}

function markAllNotificationsRead() {
    $.ajax({
        url: 'api/handle_notification_action.php',
        method: 'POST',
        data: {
            action: 'mark_all_read'
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                currentNotifications = [];
                updateNotificationBell();
                updateNotificationsPanel();
            }
        }
    });
}

function toggleNotificationsPanel() {
    const panel = $('.notifications-panel');
    
    if (panel.hasClass('show')) {
        closeNotificationsPanel();
    } else {
        showNotificationsPanel();
    }
}

function jumpToMessage(messageId) {
    const message = $(`[data-message-id="${messageId}"]`);
    
    if (message.length > 0) {
        const chatbox = $('#chatbox');
        const targetPos = message.position().top + chatbox.scrollTop();
        
        chatbox.animate({
            scrollTop: targetPos - 100
        }, 300);
        
        message.addClass('mentioned-highlight');
        setTimeout(() => {
            message.removeClass('mentioned-highlight');
        }, 3000);
    }
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const then = new Date(timestamp);
    const seconds = Math.floor((now - then) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
    return then.toLocaleDateString();
}

function playReplyOrMentionSound() {
    const audio = new Audio('/sounds/reply_or_mention_notification.mp3');
    audio.play();
}

$(document).ready(function() {
    initializeNotifications();
});
// Working Notification System JavaScript (RESTORED)
let currentNotifications = [];
let notificationCheckInterval;

function initializeNotifications() {
    notificationCheckInterval = setInterval(checkForNotifications, 5000);
    checkForNotifications();
    $(document).on("click", "#notificationBell", toggleNotificationsPanel);
    $(document).on("click", ".notification-panel-close", closeNotificationsPanel);
    $(document).on("click", ".notification-mark-all", markAllNotificationsRead);
    $(document).on("click", ".notification-item", handleNotificationClick);
    $(document).on("click", function(e) {
        if (!$(e.target).closest(".notifications-panel, #notificationBell").length) {
            closeNotificationsPanel();
        }
    });
}

function checkForNotifications() {
    if (!$("#notificationBell").length) return;
    
    $.ajax({
        url: "api/get_notifications.php",
        method: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                currentNotifications = response.notifications || [];
                updateNotificationBell();
                updateNotificationsPanel();
            }
        },
        error: function(xhr, status, error) {
            console.error("Notification check failed:", error);
        }
    });
}

function updateNotificationBell() {
    const bellElement = $("#notificationBell");
    const badge = bellElement.find(".notification-badge");
    const count = currentNotifications.length;
    
    if (count > 0) {
        bellElement.addClass("has-notifications");
        if (badge.length === 0) {
            bellElement.append("<span class=\"notification-badge\"></span>");
        }
        bellElement.find(".notification-badge").text(count > 99 ? "99+" : count);
    } else {
        bellElement.removeClass("has-notifications");
        badge.remove();
    }
}

function toggleNotificationsPanel() {
    const panel = $(".notifications-panel");
    if (panel.hasClass("show")) {
        closeNotificationsPanel();
    } else {
        showNotificationsPanel();
    }
}

function showNotificationsPanel() {
    let panel = $(".notifications-panel");
    if (panel.length === 0) {
        createNotificationsPanel();
        panel = $(".notifications-panel");
    }
    updateNotificationsPanel();
    panel.addClass("show");
}

function closeNotificationsPanel() {
    $(".notifications-panel").removeClass("show");
}

function createNotificationsPanel() {
    const panelHtml = `
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
    $("body").append(panelHtml);
}

function updateNotificationsPanel() {
    const container = $(".notifications-panel-content");
    if (currentNotifications.length === 0) {
        container.html(`<div class="notifications-empty"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>`);
        return;
    }
    
    let html = "";
    currentNotifications.forEach(notification => {
        html += createNotificationHTML(notification);
    });
    container.html(html);
}

function createNotificationHTML(notification) {
    const timeAgo = getTimeAgo(notification.timestamp);
    let iconClass = "fa-bell";
    if (notification.type === "mention") iconClass = "fa-at";
    else if (notification.type === "reply") iconClass = "fa-reply";
    
    return `
        <div class="notification-item ${notification.type}" 
             data-id="${notification.id}" 
             data-type="${notification.notification_type}"
             data-message-id="${notification.message_id || ""}">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <img src="images/${notification.sender_avatar}" 
                     style="width:32px;height:32px;border-radius:6px;object-fit:cover;" 
                     alt="Avatar">
                <div style="flex:1;">
                    <div style="color:#fff;font-weight:600;font-size:0.85rem;display:flex;align-items:center;gap:6px;">
                        <i class="fas ${iconClass}"></i> ${notification.title}
                    </div>
                    <div style="color:#b9bbbe;font-size:0.75rem;">${notification.sender_name}</div>
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
    const notification = $(e.currentTarget);
    const notificationId = notification.data("id");
    const notificationType = notification.data("type");
    const messageId = notification.data("message-id");
    
    markNotificationRead(notificationId, notificationType);
    
    if (notificationType === "mention" && messageId) {
        jumpToMessage(messageId);
    }
    
    closeNotificationsPanel();
}

function markNotificationRead(notificationId, notificationType) {
    $.ajax({
        url: "api/handle_notification_action.php",
        method: "POST",
        data: {
            action: "mark_read",
            notification_type: notificationType,
            notification_id: notificationId
        },
        dataType: "json",
        success: function(response) {
            currentNotifications = currentNotifications.filter(n => n.id !== notificationId);
            updateNotificationBell();
        }
    });
}

function markAllNotificationsRead() {
    $.ajax({
        url: "api/handle_notification_action.php",
        method: "POST",
        data: { action: "mark_all_read" },
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                currentNotifications = [];
                updateNotificationBell();
                updateNotificationsPanel();
            }
        }
    });
}

function jumpToMessage(messageId) {
    const messageElement = $(`.chat-message[data-message-id="${messageId}"]`);
    if (messageElement.length > 0) {
        const chatbox = $("#chatbox");
        const messageTop = messageElement.position().top + chatbox.scrollTop();
        chatbox.animate({ scrollTop: messageTop - 100 }, 300);
        messageElement.addClass("mentioned-highlight");
        setTimeout(() => {
            messageElement.removeClass("mentioned-highlight");
        }, 3000);
    }
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diffInSeconds = Math.floor((now - time) / 1000);
    
    if (diffInSeconds < 60) return "Just now";
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + "m ago";
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + "h ago";
    if (diffInSeconds < 604800) return Math.floor(diffInSeconds / 86400) + "d ago";
    
    return time.toLocaleDateString();
}

$(document).ready(function() {
    initializeNotifications();
});
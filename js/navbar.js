// js/navbar.js - Global navbar functionality

// Initialize navbar functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeNavbar();
});

function initializeNavbar() {
    // Update active navigation based on current page
    updateActiveNavigation();
}

function updateActiveNavigation() {
    const currentPage = window.location.pathname.split('/').pop().replace('.php', '');
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
        }
    });
}

// Navigation action functions
function showCreateRoomModal() {
    if (typeof bootstrap !== 'undefined' && document.getElementById('createRoomModal')) {
        const modal = new bootstrap.Modal(document.getElementById('createRoomModal'));
        modal.show();
    } else {
        console.warn('Create room modal not found or Bootstrap not loaded');
    }
}

function showFriendsPanel() {
    const friendsPanel = document.getElementById('friendsPanel');
    if (friendsPanel) {
        friendsPanel.style.display = 'block';
        
        // Load friends list if function exists
        if (typeof loadFriendsList === 'function') {
            loadFriendsList();
        }
    } else {
        console.warn('Friends panel not found');
    }
}

function closeFriendsPanel() {
    const friendsPanel = document.getElementById('friendsPanel');
    if (friendsPanel) {
        friendsPanel.style.display = 'none';
    }
}

function showProfileEditor() {
    if (typeof showProfileEditorModal === 'function') {
        showProfileEditorModal();
    } else if (document.getElementById('profileEditorModal')) {
        const modal = new bootstrap.Modal(document.getElementById('profileEditorModal'));
        modal.show();
    } else {
        console.warn('Profile editor not available');
    }
}

function showUserSettings() {
    if (typeof showUserSettingsModal === 'function') {
        showUserSettingsModal();
    } else if (document.getElementById('userSettingsModal')) {
        const modal = new bootstrap.Modal(document.getElementById('userSettingsModal'));
        modal.show();
    } else {
        console.warn('User settings not available');
    }
}

function showRoomSettings() {
    if (typeof showRoomSettingsModal === 'function') {
        showRoomSettingsModal();
    } else if (document.getElementById('roomSettingsModal')) {
        const modal = new bootstrap.Modal(document.getElementById('roomSettingsModal'));
        modal.show();
    } else {
        console.warn('Room settings not available');
    }
}

function toggleAFK() {
    const btn = document.querySelector('.btn-toggle-afk');
    if (btn) {
        btn.classList.add('loading');
    }
    
    if (typeof window.toggleAFK === 'function') {
        window.toggleAFK();
    } else {
        // Fallback AJAX call
        fetch('api/toggle_afk.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                updateAFKButton(data.is_afk);
                showToast(data.is_afk ? 'You are now AFK' : 'Welcome back!', 'info');
            } else {
                showToast('Failed to toggle AFK status', 'error');
            }
        })
        .catch(error => {
            console.error('AFK toggle error:', error);
            showToast('Error toggling AFK status', 'error');
        })
        .finally(() => {
            if (btn) {
                btn.classList.remove('loading');
            }
        });
    }
}

function updateAFKButton(isAfk) {
    const btn = document.querySelector('.btn-toggle-afk');
    if (btn) {
        if (isAfk) {
            btn.classList.remove('btn-outline-warning');
            btn.classList.add('btn-warning');
            btn.textContent = '<i class="fas fa-plane-arrival"></i>';
            btn.title = 'Return from AFK';
        } else {
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-outline-warning');
            btn.textContent = '<i class="fas fa-plane-departure"></i>';
            btn.title = 'Go AFK';
        }
    }
}

function toggleGhostMode() {
    if (typeof window.toggleGhostMode === 'function') {
        window.toggleGhostMode();
    } else {
        // Fallback AJAX call
        fetch('api/toggle_ghost_mode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                updateGhostModeButton(data.ghost_mode);
                showToast(data.ghost_mode ? 'Ghost mode enabled' : 'Ghost mode disabled', 'info');
            } else {
                showToast('Failed to toggle ghost mode', 'error');
            }
        })
        .catch(error => {
            console.error('Ghost mode toggle error:', error);
            showToast('Error toggling ghost mode', 'error');
        });
    }
}

function updateGhostModeButton(ghostMode) {
    const btn = document.querySelector('[onclick="toggleGhostMode()"]');
    if (btn) {
        if (ghostMode) {
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-secondary');
        } else {
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-outline-secondary');
        }
    }
}

function toggleNotifications() {
    // Check if browser supports notifications
    if (!('Notification' in window)) {
        showToast('This browser does not support notifications', 'warning');
        return;
    }
    
    // Check current permission
    if (Notification.permission === 'granted') {
        // Notifications are enabled, toggle off
        localStorage.setItem('notificationsEnabled', 'false');
        showToast('Notifications disabled', 'info');
        updateNotificationButton(false);
    } else if (Notification.permission === 'denied') {
        showToast('Notifications are blocked. Please enable them in your browser settings.', 'warning');
    } else {
        // Request permission
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                localStorage.setItem('notificationsEnabled', 'true');
                showToast('Notifications enabled', 'success');
                updateNotificationButton(true);
                
                // Send test notification
                new Notification('Duranu Chat', {
                    body: 'Notifications are now enabled!',
                    icon: '/images/duranu.png'
                });
            } else {
                showToast('Notifications permission denied', 'warning');
            }
        });
    }
}

function leaveRoom() {
    if (typeof window.leaveRoom === 'function') {
        window.leaveRoom();
    } else {
        // Fallback AJAX call
        fetch('api/leave_room.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                window.location.href = '/lounge';
            } else {
                showToast('Failed to leave room: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Leave room error:', error);
            showToast('Error leaving room', 'error');
        });
    }
}

function updateNotificationButton(enabled) {
    const btn = document.querySelector('[onclick="toggleNotifications()"]');
    if (btn) {
        if (enabled) {
            btn.classList.remove('btn-outline-light');
            btn.classList.add('btn-warning');
            btn.textContent = '<i class="fas fa-bell-slash"></i><span class="d-none d-xl-inline ms-1">Disable Notifications</span>';
        } else {
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-outline-light');
            btn.textContent = '<i class="fas fa-bell"></i><span class="d-none d-xl-inline ms-1">Notifications</span>';
        }
    }
}

// Utility function for showing toast messages
function showToast(message, type = 'info') {
    // Check if there's an existing toast function
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
        return;
    }
    
    // Fallback: create simple toast
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type} position-fixed`;
    toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 250px; opacity: 0.95;';
    toast.textContent = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 3000);
}

// Initialize notification button state on page load
document.addEventListener('DOMContentLoaded', function() {
    const notificationsEnabled = localStorage.getItem('notificationsEnabled') === 'true' && 
                                  Notification.permission === 'granted';
    updateNotificationButton(notificationsEnabled);
});

// Export functions for global use
window.navbarFunctions = {
    showCreateRoomModal,
    showFriendsPanel,
    closeFriendsPanel,
    showProfileEditor,
    showUserSettings,
    showRoomSettings,
    toggleAFK,
    toggleGhostMode,
    toggleNotifications,
    leaveRoom,
    showToast
};
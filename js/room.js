// Global variables for kick detection
let kickDetectionInterval;
let userKickedModalShown = false;
let kickDetectionEnabled = true;
let lastStatusCheck = 0;
let consecutiveErrors = 0;
// Activity tracking variables - IMPORTANT: Declare at global scope
let activityInterval = null;
let disconnectCheckInterval = null;
let lastActivityUpdate = 0;
let userIsActive = true;
let activityTrackingEnabled = false;
let activityDebugMode = true; // Set to false in production


$(document).ready(function() {
    console.log('🛡️ Starting enhanced kick detection system...');
    
    // Start checking immediately
    setTimeout(checkUserStatus, 500);
    
    // Set up regular checking every 2 seconds (faster than before)
    kickDetectionInterval = setInterval(checkUserStatus, 2000);
    
    // Also check when user sends a message (immediate feedback)
    $(document).on('submit', '#messageForm', function() {
        setTimeout(checkUserStatus, 100);
    });
    
    // Check when user performs any significant action
    $(document).on('click', 'button', function() {
        if (!userKickedModalShown) {
            setTimeout(checkUserStatus, 100);
        }

    console.log('🔄 Starting activity tracking system...');
    
    // Initialize activity tracking
    initializeActivityTracking();
    
    // Start periodic disconnect checking (every 2 minutes)
    disconnectCheckInterval = setInterval(triggerDisconnectCheck, 2 * 60 * 1000);
    
    // Track initial join activity
    updateUserActivity('join');
    
    console.log('✅ Activity tracking system started');    
    });
    
    
// ADD THIS PART:
    // Start knock checking if user is host
    if (typeof isHost !== 'undefined' && isHost) {
        console.log('User is host, starting knock checking...');
        
        // Check for knocks every 3 seconds
        setInterval(checkForKnocks, 3000);
        
        // Check immediately after 1 second
        setTimeout(checkForKnocks, 1000);
    } else {
        console.log('User is not host or isHost not defined, no knock checking');
    }
    

    console.log('room.js loaded, roomId:', roomId);

    

    // Initialize all activity tracking
// Initialize activity tracking - called explicitly and safely
function initializeActivityTracking() {
    if (activityTrackingEnabled) {
        console.log('🔄 Activity tracking already initialized');
        return;
    }
    
    console.log('🔄 Initializing activity tracking system...');
    activityTrackingEnabled = true;
    
    // Clear any existing intervals first
    if (activityInterval) {
        clearInterval(activityInterval);
    }
    if (disconnectCheckInterval) {
        clearInterval(disconnectCheckInterval);
    }
    
    // Start activity heartbeat (every 30 seconds)
    activityInterval = setInterval(() => {
        if (userIsActive && activityTrackingEnabled) {
            updateUserActivity('heartbeat');
            userIsActive = false; // Reset until next activity
        }
    }, 30 * 1000);
    
    // Start disconnect checking (every 2 minutes)
    disconnectCheckInterval = setInterval(() => {
        if (activityTrackingEnabled) {
            triggerDisconnectCheck();
        }
    }, 2 * 60 * 1000);
    
    // Set up activity event listeners
    setupActivityListeners();
    
    // Track initial activity
    updateUserActivity('system_start');
    
    console.log('✅ Activity tracking system initialized successfully');
    console.log(`- Activity interval ID: ${activityInterval}`);
    console.log(`- Disconnect interval ID: ${disconnectCheckInterval}`);
    
    // Initial status check
    setTimeout(() => {
        if (activityDebugMode) {
            showActivityStatus();
        }
    }, 2000);
}

// Set up all activity event listeners
function setupActivityListeners() {
    console.log('🎯 Setting up activity listeners...');
    
    // Remove existing listeners first to prevent duplicates
    $(document).off('mousemove.activity keypress.activity scroll.activity click.activity');
    $(window).off('focus.activity');
    
    // Track user interaction with debouncing
    let activityTimeout;
    function markUserActive() {
        userIsActive = true;
        
        clearTimeout(activityTimeout);
        activityTimeout = setTimeout(() => {
            if (activityTrackingEnabled) {
                updateUserActivity('interaction');
            }
        }, 5000); // Debounce to every 5 seconds max
    }
    
    // Activity event listeners with namespaces to avoid conflicts
    $(document).on('mousemove.activity keypress.activity scroll.activity click.activity', markUserActive);
    
    // Track page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && activityTrackingEnabled) {
            updateUserActivity('page_focus');
        }
    });
    
    // Track window focus
    $(window).on('focus.activity', function() {
        if (activityTrackingEnabled) {
            updateUserActivity('window_focus');
        }
    });
    
    console.log('✅ Activity listeners set up successfully');
}

// Enhanced update user activity function
function updateUserActivity(activityType = 'general') {
    if (!activityTrackingEnabled) {
        if (activityDebugMode) {
            console.log(`⚠️ Activity tracking disabled, skipping update: ${activityType}`);
        }
        return;
    }
    
    // Prevent too frequent updates (max once per 3 seconds for non-heartbeat)
    const now = Date.now();
    const minInterval = activityType === 'heartbeat' ? 25000 : 3000;
    
    if (now - lastActivityUpdate < minInterval) {
        if (activityDebugMode && activityType !== 'heartbeat') {
            console.log(`⏰ Activity update throttled: ${activityType} (${now - lastActivityUpdate}ms ago)`);
        }
        return;
    }
    
    lastActivityUpdate = now;
    
    console.log(`📍 Updating activity: ${activityType}`);
    
    $.ajax({
        url: 'api/update_activity.php',
        method: 'POST',
        data: { activity_type: activityType },
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            if (response.status === 'success') {
                console.log(`✅ Activity updated: ${activityType} at ${response.timestamp}`);
            } else if (response.status === 'not_in_room') {
                console.log('❌ Not in room - stopping activity tracking');
                stopActivityTracking();
                
                // Trigger status check if available
                if (typeof forceStatusCheck === 'function') {
                    forceStatusCheck();
                }
            } else {
                console.log(`⚠️ Activity update warning: ${response.message}`);
            }
        },
        error: function(xhr, status, error) {
            console.log(`⚠️ Activity update failed: ${status} - ${error}`);
            
            // If we get a 404 or similar, the API might not exist
            if (xhr.status === 404) {
                console.log('💡 Tip: Make sure api/update_activity.php exists');
            }
        }
    });
}

// Enhanced disconnect check trigger
function triggerDisconnectCheck() {
    if (!activityTrackingEnabled) {
        console.log('⚠️ Activity tracking disabled, skipping disconnect check');
        return;
    }
    
    console.log('🔍 Triggering disconnect check...');
    
    $.ajax({
        url: 'api/check_disconnects.php',
        method: 'GET',
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.status === 'success') {
                console.log('📊 Disconnect check completed:', response.summary);
                
                const summary = response.summary;
                if (summary.users_disconnected > 0 || summary.hosts_transferred > 0 || summary.rooms_deleted > 0) {
                    console.log(`👥 Changes: ${summary.users_disconnected} disconnected, ${summary.hosts_transferred} transfers, ${summary.rooms_deleted} deleted`);
                    
                    // Refresh room data to reflect changes
                    setTimeout(() => {
                        if (typeof loadUsers === 'function') loadUsers();
                        if (typeof loadMessages === 'function') loadMessages();
                    }, 1000);
                }
            } else {
                console.log('❌ Disconnect check failed:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.log('⚠️ Disconnect check error:', error);
            
            if (xhr.status === 404) {
                console.log('💡 Tip: Make sure api/check_disconnects.php exists');
            }
        }
    });
}

// Enhanced stop activity tracking
function stopActivityTracking() {
    console.log('🛑 Stopping activity tracking system');
    activityTrackingEnabled = false;
    
    if (activityInterval) {
        clearInterval(activityInterval);
        activityInterval = null;
        console.log('- Stopped activity interval');
    }
    
    if (disconnectCheckInterval) {
        clearInterval(disconnectCheckInterval);
        disconnectCheckInterval = null;
        console.log('- Stopped disconnect interval');
    }
    
    // Remove event listeners
    $(document).off('mousemove.activity keypress.activity scroll.activity click.activity');
    $(window).off('focus.activity');
    console.log('- Removed activity listeners');
}

// Enhanced leave room function with activity cleanup
const originalLeaveRoomActivity = window.leaveRoom;
window.leaveRoom = function() {
    console.log('🚪 User leaving room - cleaning up activity tracking');
    stopActivityTracking();
    
    if (originalLeaveRoomActivity) {
        originalLeaveRoomActivity();
    }
};

// Enhanced send message with activity tracking
const originalSendMessageActivity = window.sendMessage;
window.sendMessage = function() {
    // Update activity immediately when sending message
    updateUserActivity('message_send');
    
    // Call original function
    if (originalSendMessageActivity) {
        return originalSendMessageActivity();
    }
    
    // Fallback implementation
    const messageInput = $('#message');
    const message = messageInput.val().trim();
    
    if (!message) {
        alert('Please enter a message');
        return false;
    }
    
    $.ajax({
        url: 'api/send_message.php',
        method: 'POST',
        data: {
            room_id: roomId,
            message: message
        },
        success: function(response) {
            try {
                let res = JSON.parse(response);
                if (res.status === 'success') {
                    messageInput.val('');
                    loadMessages();
                    
                    // Activity already updated above
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (e) {
                console.error('JSON parse error:', e, response);
                alert('Invalid response from server');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in sendMessage:', status, error, xhr.responseText);
            alert('AJAX error: ' + error);
        }
    });
    
    return false;
};

// Enhanced activity status display
function showActivityStatus() {
    const status = {
        activityTrackingEnabled: activityTrackingEnabled,
        activityTrackingActive: !!activityInterval,
        disconnectCheckingActive: !!disconnectCheckInterval,
        lastActivityUpdate: lastActivityUpdate,
        lastActivityTime: lastActivityUpdate ? new Date(lastActivityUpdate).toLocaleTimeString() : 'Never',
        userCurrentlyActive: userIsActive,
        activityIntervalId: activityInterval,
        disconnectIntervalId: disconnectCheckInterval
    };
    
    console.log('📊 Activity Tracking Status:');
    console.log(`- System enabled: ${status.activityTrackingEnabled}`);
    console.log(`- Activity tracking active: ${status.activityTrackingActive}`);
    console.log(`- Disconnect checking active: ${status.disconnectCheckingActive}`);
    console.log(`- Last activity update: ${status.lastActivityTime}`);
    console.log(`- User currently active: ${status.userCurrentlyActive}`);
    console.log(`- Activity interval ID: ${status.activityIntervalId}`);
    console.log(`- Disconnect interval ID: ${status.disconnectIntervalId}`);
    
    return status;
}

// Manual functions for testing
function forceActivityUpdate(type = 'manual_test') {
    console.log('🔧 Forcing activity update...');
    const oldThreshold = lastActivityUpdate;
    lastActivityUpdate = 0; // Reset throttle
    updateUserActivity(type);
    // Restore throttle after a second
    setTimeout(() => {
        if (lastActivityUpdate === 0) {
            lastActivityUpdate = oldThreshold;
        }
    }, 1000);
}

function forceDisconnectCheck() {
    console.log('🔧 Forcing disconnect check...');
    triggerDisconnectCheck();
}

function restartActivityTracking() {
    console.log('🔄 Restarting activity tracking...');
    stopActivityTracking();
    setTimeout(() => {
        initializeActivityTracking();
    }, 1000);
}

// Disconnect warning system (optional - warn users before they get disconnected)
let disconnectWarningShown = false;
let warningCheckInterval;

function startDisconnectWarning() {
    // Check every minute if user might be close to disconnecting
    warningCheckInterval = setInterval(() => {
        const timeSinceLastActivity = Date.now() - lastActivityUpdate;
        const minutesSinceActivity = timeSinceLastActivity / (1000 * 60);
        
        // Warn at 12 minutes (3 minutes before 15-minute timeout)
        if (minutesSinceActivity >= 12 && !disconnectWarningShown) {
            showDisconnectWarning();
        }
    }, 60 * 1000);
}

function showDisconnectWarning() {
    disconnectWarningShown = true;
    
    // Create warning modal
    const warningHtml = `
        <div class="modal fade" id="inactivityWarningModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-warning">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-clock"></i> Inactivity Warning
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-clock fa-3x text-warning"></i>
                        </div>
                        <h6>You've been inactive for a while</h6>
                        <p>You'll be automatically disconnected in about 3 minutes if you don't interact with the room.</p>
                        <div class="alert alert-info">
                            <i class="fas fa-mouse"></i> Move your mouse, send a message, or click anywhere to stay connected.
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-primary" onclick="dismissInactivityWarning()">
                            <i class="fas fa-check"></i> I'm Still Here
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#inactivityWarningModal').remove();
    $('body').append(warningHtml);
    $('#inactivityWarningModal').modal('show');
}

function dismissInactivityWarning() {
    $('#inactivityWarningModal').modal('hide');
    updateUserActivity('warning_dismissed');
    disconnectWarningShown = false;
}

// Make functions globally available
window.initializeActivityTracking = initializeActivityTracking;
window.updateUserActivity = updateUserActivity;
window.triggerDisconnectCheck = triggerDisconnectCheck;
window.stopActivityTracking = stopActivityTracking;
window.showActivityStatus = showActivityStatus;
window.forceActivityUpdate = forceActivityUpdate;
window.forceDisconnectCheck = forceDisconnectCheck;
window.restartActivityTracking = restartActivityTracking;

// Debug helper
window.debugActivitySystem = function() {
    console.log('🔍 Activity System Debug:');
    showActivityStatus();
    
    console.log('\n🧪 Testing API endpoints...');
    
    // Test activity update API
    $.ajax({
        url: 'api/update_activity.php',
        method: 'POST',
        data: { activity_type: 'debug_test' },
        success: function(response) {
            console.log('✅ update_activity.php working:', response);
        },
        error: function(xhr, status, error) {
            console.log('❌ update_activity.php error:', status, error);
        }
    });
    
    // Test disconnect check API
    $.ajax({
        url: 'api/check_disconnects.php',
        method: 'GET',
        success: function(response) {
            console.log('✅ check_disconnects.php working:', response);
        },
        error: function(xhr, status, error) {
            console.log('❌ check_disconnects.php error:', status, error);
        }
    });
};

console.log('🎯 Enhanced activity tracking system loaded successfully');
console.log('💡 Use showActivityStatus() to check system status');
console.log('💡 Use restartActivityTracking() if system stops working');
console.log('💡 Use debugActivitySystem() for comprehensive debugging');


// Enhanced user status checking function
function checkUserStatus() {
    // Don't check if we've already been kicked
    if (userKickedModalShown || !kickDetectionEnabled) {
        return;
    }
    
    // Prevent too frequent checks (max once per second)
    const now = Date.now();
    if (now - lastStatusCheck < 1000) {
        return;
    }
    lastStatusCheck = now;
    
    console.log('🔍 Checking user status...');
    
    $.ajax({
        url: 'api/check_user_status.php',
        method: 'GET',
        dataType: 'json',
        timeout: 5000, // 5 second timeout
        success: function(response) {
            console.log('📡 Status check result:', response);
            consecutiveErrors = 0; // Reset error counter on success
            
            switch(response.status) {
                case 'banned':
                    handleUserBanned(response);
                    break;
                    
                case 'removed':
                    handleUserKicked(response);
                    break;
                    
                case 'room_deleted':
                    handleRoomDeleted(response);
                    break;
                    
                case 'not_in_room':
                    console.log('👤 User not in room, redirecting to lounge');
                    stopKickDetection();
                    window.location.href = 'lounge.php';
                    break;
                    
                case 'active':
                    // User is still active in room - all good
                    console.log('✅ User status: Active in', response.room_name);
                    break;
                    
                case 'error':
                    console.error('❌ Server error:', response.message);
                    handleStatusCheckError();
                    break;
                    
                default:
                    console.warn('⚠️ Unknown status:', response.status);
                    break;
            }
        },
        error: function(xhr, status, error) {
            console.log('🔌 Status check failed:', { status, error, responseText: xhr.responseText });
            handleStatusCheckError();
        }
    });
}

// Handle when user has been banned
function handleUserBanned(response) {
    console.log('🚫 User has been BANNED:', response);
    
    stopKickDetection();
    
    let banMessage = response.message || 'You have been banned from this room';
    let banDetails = '';
    
    if (response.ban_info) {
        if (response.ban_info.permanent) {
            banDetails += '<div class="alert alert-danger"><strong>This is a PERMANENT ban.</strong></div>';
        } else if (response.ban_info.expires_in_minutes) {
            banDetails += `<div class="alert alert-warning"><strong>Ban expires in ${response.ban_info.expires_in_minutes} minute${response.ban_info.expires_in_minutes !== 1 ? 's' : ''}.</strong></div>`;
        }
        
        if (response.ban_info.reason) {
            banDetails += `<p><strong>Reason:</strong> ${response.ban_info.reason}</p>`;
        }
        
        if (response.ban_info.banned_by) {
            banDetails += `<p><strong>Banned by:</strong> ${response.ban_info.banned_by}</p>`;
        }
        
        if (response.ban_info.banned_at) {
            banDetails += `<p><strong>Banned at:</strong> ${response.ban_info.banned_at}</p>`;
        }
    }
    
    showKickModal('🚫 You Have Been Banned', banMessage, banDetails, 'danger');
}

// Handle when user has been kicked (but not banned)
function handleUserKicked(response) {
    console.log('👢 User has been KICKED:', response);
    
    stopKickDetection();
    
    const message = response.message || 'You have been removed from this room';
    const details = '<div class="alert alert-info">You can try to rejoin the room if it\'s still available.</div>';
    
    showKickModal('👢 Removed from Room', message, details, 'warning');
}

// Handle when room has been deleted
function handleRoomDeleted(response) {
    console.log('🏗️ Room has been DELETED:', response);
    
    stopKickDetection();
    
    const message = response.message || 'This room has been deleted';
    const details = '<div class="alert alert-info">The room no longer exists. You will be redirected to the lounge.</div>';
    
    showKickModal('🏗️ Room Deleted', message, details, 'info');
}

// Handle status check errors
function handleStatusCheckError() {
    consecutiveErrors++;
    
    if (consecutiveErrors >= 3) {
        console.warn('⚠️ Multiple consecutive errors, may have connection issues');
        
        // After 5 consecutive errors, assume something is wrong and redirect
        if (consecutiveErrors >= 5) {
            console.error('🔥 Too many errors, redirecting to lounge');
            stopKickDetection();
            alert('Connection lost. Redirecting to lounge.');
            window.location.href = 'lounge.php';
        }
    }
}

// Enhanced kick notification modal
function showKickModal(title, message, details, type) {
    userKickedModalShown = true;
    
    const typeColors = {
        'danger': { bg: 'bg-danger', icon: 'fas fa-ban' },
        'warning': { bg: 'bg-warning', icon: 'fas fa-exclamation-triangle' },
        'info': { bg: 'bg-info', icon: 'fas fa-info-circle' }
    };
    
    const typeConfig = typeColors[type] || typeColors['info'];
    
    const modalHtml = `
        <div class="modal fade" id="kickNotificationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-${type}">
                    <div class="modal-header ${typeConfig.bg} text-white">
                        <h5 class="modal-title">
                            <i class="${typeConfig.icon}"></i> ${title}
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <i class="${typeConfig.icon} fa-4x text-${type}"></i>
                        </div>
                        <h6 class="text-${type} mb-3">${message}</h6>
                        ${details}
                        <div class="alert alert-light mt-3">
                            <i class="fas fa-home"></i>
                            <strong>You will be redirected to the lounge in <span id="redirectCountdown">8</span> seconds</strong>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-primary" onclick="handleKickModalClose()">
                            <i class="fas fa-home"></i> Go to Lounge Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove any existing modal
    $('#kickNotificationModal').remove();
    
    // Add modal to page
    $('body').append(modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('kickNotificationModal'));
    modal.show();
    
    // Countdown timer
    let countdown = 8;
    const countdownInterval = setInterval(() => {
        countdown--;
        $('#redirectCountdown').text(countdown);
        
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            handleKickModalClose();
        }
    }, 1000);
    
    // Play notification sound if available
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.2);
    } catch (e) {
        console.log('Could not play notification sound:', e);
    }
}

// Handle kick modal close
function handleKickModalClose() {
    console.log('🏠 Redirecting to lounge...');
    
    // Clear any intervals
    stopKickDetection();
    
    // Clear session room_id on server side
    $.ajax({
        url: 'api/leave_room.php',
        method: 'POST',
        data: { 
            room_id: roomId,
            action: 'kicked_user_cleanup'
        },
        complete: function() {
            // Always redirect regardless of response
            window.location.href = 'lounge.php';
        }
    });
}

// Stop kick detection
function stopKickDetection() {
    console.log('🛑 Stopping kick detection system');
    kickDetectionEnabled = false;
    
    if (kickDetectionInterval) {
        clearInterval(kickDetectionInterval);
        kickDetectionInterval = null;
    }
}

// Enhanced leave room function
const originalLeaveRoom = window.leaveRoom;
window.leaveRoom = function() {
    console.log('🚪 User leaving room normally');
    stopKickDetection();
    if (originalLeaveRoom) {
        originalLeaveRoom();
    }
};

// Force immediate status check (for testing)
window.forceStatusCheck = function() {
    console.log('🔍 Forcing immediate status check...');
    userKickedModalShown = false;
    checkUserStatus();
};

// Test functions for debugging
window.testKickDetection = function() {
    console.log('🧪 Testing kick detection system...');
    console.log('Kick detection enabled:', kickDetectionEnabled);
    console.log('Modal shown:', userKickedModalShown);
    console.log('Interval active:', !!kickDetectionInterval);
    forceStatusCheck();
};

window.simulateBan = function() {
    console.log('🎭 Simulating ban...');
    handleUserBanned({
        message: 'You have been banned for testing',
        ban_info: {
            permanent: false,
            expires_in_minutes: 5,
            reason: 'Testing ban system',
            banned_by: 'TestAdmin'
        }
    });
};

window.simulateKick = function() {
    console.log('🎭 Simulating kick...');
    handleUserKicked({
        message: 'You have been kicked for testing'
    });
};

window.simulateRoomDeletion = function() {
    console.log('🎭 Simulating room deletion...');
    handleRoomDeleted({
        message: 'This room has been deleted for testing'
    });
};

// Make functions globally available
window.checkUserStatus = checkUserStatus;
window.stopKickDetection = stopKickDetection;
window.handleKickModalClose = handleKickModalClose;

console.log('🎯 Enhanced kick detection system loaded successfully');


// Add this to your existing js/room.js file - Enhanced ban/kick functions
// This ensures immediate kicks when users are banned

// Enhanced confirmBanUser function with immediate kick
window.confirmBanUser = function(userIdString, userName) {
    const duration = $('#banDuration').val();
    const reason = $('#banReason').val().trim();
    
    const durationText = duration === 'permanent' ? 'permanently' : 
                       duration == 300 ? 'for 5 minutes' :
                       duration == 1800 ? 'for 30 minutes' : 'for ' + duration + ' seconds';
    
    if (!confirm('Are you sure you want to ban ' + userName + ' ' + durationText + '?')) {
        return;
    }
    
    console.log('🔨 Banning user:', userIdString, 'for:', duration, 'reason:', reason);
    
    // Show loading state
    const banButton = $('#banUserModal .btn-danger');
    const originalText = banButton.html();
    banButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Banning...');
    
    $.ajax({
        url: 'api/ban_user_simple.php',
        method: 'POST',
        dataType: 'json',
        data: {
            room_id: roomId,
            user_id_string: userIdString,
            duration: duration,
            reason: reason
        },
        success: function(response) {
            console.log('🔨 Ban response:', response);
            
            let res = response;
            if (typeof response === 'string') {
                try {
                    res = JSON.parse(response);
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                    return;
                }
            }
            
            if (res.status === 'success') {
                alert('User banned successfully ' + durationText + '!');
                $('#banUserModal').modal('hide');
                
                // Immediately refresh users and messages
                loadUsers();
                loadMessages();
                
                // CRITICAL: Force immediate status checks for all connected users
                // This ensures the banned user gets kicked immediately
                console.log('🚀 Broadcasting immediate kick check...');
                
                // Small delay to ensure ban is processed server-side
                setTimeout(() => {
                    // Force our own status check to ensure we didn't ban ourselves
                    if (typeof forceStatusCheck === 'function') {
                        forceStatusCheck();
                    }
                }, 500);
                
            } else {
                alert('Error: ' + res.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in confirmBanUser:', status, error, xhr.responseText);
            alert('AJAX error: ' + error);
        },
        complete: function() {
            // Restore button state
            banButton.prop('disabled', false).html(originalText);
        }
    });
};

// Enhanced room deletion with immediate kick notifications
window.deleteRoom = function() {
    if (confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
        console.log('🏗️ Deleting room...');
        
        $.ajax({
            url: 'api/leave_room.php',
            method: 'POST',
            data: { 
                room_id: roomId,
                action: 'delete_room'
            },
            success: function(response) {
                console.log('🏗️ Delete room response:', response);
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
                        // Room deleted successfully
                        console.log('✅ Room deleted, redirecting to lounge');
                        
                        // Stop our kick detection since we're leaving intentionally
                        if (typeof stopKickDetection === 'function') {
                            stopKickDetection();
                        }
                        
                        alert('Room deleted successfully');
                        window.location.href = 'lounge.php';
                    } else {
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in deleteRoom:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    }
};

// Enhanced transfer host function
window.transferHost = function() {
    let newHostId = $('#newHostSelect').val();
    if (!newHostId) {
        alert('Please select a user to transfer host privileges to');
        return;
    }
    
    if (confirm('Are you sure you want to transfer host privileges and leave the room?')) {
        console.log('👑 Transferring host to:', newHostId);
        
        $.ajax({
            url: 'api/leave_room.php',
            method: 'POST',
            data: { 
                room_id: roomId,
                action: 'transfer_host',
                new_host_user_id: newHostId
            },
            success: function(response) {
                console.log('👑 Transfer host response:', response);
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
                        // Stop kick detection since we're leaving intentionally
                        if (typeof stopKickDetection === 'function') {
                            stopKickDetection();
                        }
                        
                        alert('Host privileges transferred successfully');
                        window.location.href = 'lounge.php';
                    } else {
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in transferHost:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    }
};

// Enhanced message sending with status check
const originalSendMessage = window.sendMessage;
window.sendMessage = function() {
    // Check if we're still in the room before sending
    if (typeof checkUserStatus === 'function' && !userKickedModalShown) {
        checkUserStatus();
    }
    
    // Call original function
    if (originalSendMessage) {
        return originalSendMessage();
    }
    
    // Fallback implementation
    const messageInput = $('#message');
    const message = messageInput.val().trim();
    
    if (!message) {
        alert('Please enter a message');
        return false;
    }
    
    console.log('💬 Sending message:', message);
    
    $.ajax({
        url: 'api/send_message.php',
        method: 'POST',
        data: {
            room_id: roomId,
            message: message
        },
        success: function(response) {
            try {
                let res = JSON.parse(response);
                if (res.status === 'success') {
                    messageInput.val('');
                    loadMessages();
                    
                    // Check status after sending message
                    setTimeout(() => {
                        if (typeof forceStatusCheck === 'function') {
                            forceStatusCheck();
                        }
                    }, 200);
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (e) {
                console.error('JSON parse error:', e, response);
                alert('Invalid response from server');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error in sendMessage:', status, error, xhr.responseText);
            
            // If message sending fails, check if we're still in the room
            if (typeof forceStatusCheck === 'function') {
                forceStatusCheck();
            }
            
            alert('AJAX error: ' + error);
        }
    });
    
    return false;
};

// Monitor for specific events that might indicate user removal
$(document).ready(function() {
    // Check status when page visibility changes (user switches tabs and comes back)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && typeof forceStatusCheck === 'function') {
            setTimeout(forceStatusCheck, 100);
        }
    });
    
    // Check status when window gains focus
    $(window).on('focus', function() {
        if (typeof forceStatusCheck === 'function') {
            setTimeout(forceStatusCheck, 100);
        }
    });
    
    console.log('🎯 Enhanced immediate kick system loaded');
});

// Debugging function to check system status
window.debugKickSystem = function() {
    console.log('🔍 Kick System Debug Info:');
    console.log('- Kick detection enabled:', typeof kickDetectionEnabled !== 'undefined' ? kickDetectionEnabled : 'Not loaded');
    console.log('- User kicked modal shown:', typeof userKickedModalShown !== 'undefined' ? userKickedModalShown : 'Not loaded');
    console.log('- Interval active:', typeof kickDetectionInterval !== 'undefined' ? !!kickDetectionInterval : 'Not loaded');
    console.log('- Room ID:', typeof roomId !== 'undefined' ? roomId : 'Not defined');
    console.log('- Current user:', typeof currentUserIdString !== 'undefined' ? currentUserIdString : 'Not defined');
    
    if (typeof forceStatusCheck === 'function') {
        console.log('🚀 Running immediate status check...');
        forceStatusCheck();
    } else {
        console.log('❌ forceStatusCheck function not available');
    }
};

    // Function to show room settings
    window.showRoomSettings = function() {
        console.log('Loading room settings for roomId:', roomId);
        
        $.ajax({
            url: 'api/get_room_settings.php',
            method: 'GET',
            data: { room_id: roomId },
            success: function(response) {
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
                        displayRoomSettingsModal(res.settings);
                    } else {
                        alert('Error loading room settings: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in showRoomSettings:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    };

    // Function to display room settings modal
    function displayRoomSettingsModal(settings) {
        const modalHtml = '<div class="modal fade" id="roomSettingsModal" tabindex="-1">' +
            '<div class="modal-dialog modal-lg">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title"><i class="fas fa-cog"></i> Room Settings</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<ul class="nav nav-tabs" id="settingsTabs" role="tablist">' +
            '<li class="nav-item" role="presentation">' +
            '<button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>' +
            '</li>' +
            '<li class="nav-item" role="presentation">' +
            '<button class="nav-link" id="banlist-tab" data-bs-toggle="tab" data-bs-target="#banlist" type="button" role="tab">Banlist</button>' +
            '</li>' +
            '</ul>' +
            '<div class="tab-content" id="settingsTabsContent">' +
            '<div class="tab-pane fade show active" id="general" role="tabpanel">' +
            '<form id="roomSettingsForm" class="mt-3">' +
            '<div class="row">' +
            '<div class="col-md-6">' +
            '<div class="mb-3">' +
            '<label for="settingsRoomName" class="form-label">Room Name</label>' +
            '<input type="text" class="form-control" id="settingsRoomName" value="' + settings.name + '" required>' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="settingsCapacity" class="form-label">Capacity</label>' +
            '<select class="form-select" id="settingsCapacity" required>' +
            '<option value="5"' + (settings.capacity == 5 ? ' selected' : '') + '>5</option>' +
            '<option value="10"' + (settings.capacity == 10 ? ' selected' : '') + '>10</option>' +
            '<option value="20"' + (settings.capacity == 20 ? ' selected' : '') + '>20</option>' +
            '<option value="50"' + (settings.capacity == 50 ? ' selected' : '') + '>50</option>' +
            '</select>' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="settingsPassword" class="form-label">Password</label>' +
            '<input type="password" class="form-control" id="settingsPassword" placeholder="Leave empty to keep current password">' +
            '<div class="form-text">Leave empty to keep current password, or enter new password to change it.</div>' +
            '</div>' +
            '</div>' +
            '<div class="col-md-6">' +
            '<div class="mb-3">' +
            '<label for="settingsDescription" class="form-label">Description</label>' +
            '<textarea class="form-control" id="settingsDescription" rows="3">' + (settings.description || '') + '</textarea>' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="settingsBackground" class="form-label">Background</label>' +
            '<select class="form-select" id="settingsBackground">' +
            '<option value=""' + (!settings.background ? ' selected' : '') + '>Default</option>' +
            '<option value="images/background1.jpg"' + (settings.background === 'images/background1.jpg' ? ' selected' : '') + '>Background 1</option>' +
            '<option value="images/background2.jpg"' + (settings.background === 'images/background2.jpg' ? ' selected' : '') + '>Background 2</option>' +
            '</select>' +
            '</div>' +
            '<div class="mb-3">' +
            '<div class="form-check">' +
            '<input class="form-check-input" type="checkbox" id="settingsPermanent"' + (settings.permanent ? ' checked' : '') + '>' +
            '<label class="form-check-label" for="settingsPermanent">Permanent Room (Admin only)</label>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</form>' +
            '</div>' +
            '<div class="tab-pane fade" id="banlist" role="tabpanel">' +
            '<div class="mt-3">' +
            '<h6>Banned Users</h6>' +
            '<div id="bannedUsersList">' +
            '<p>Loading banned users...</p>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="button" class="btn btn-primary" onclick="saveRoomSettings()"><i class="fas fa-save"></i> Save Settings</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('#roomSettingsModal').remove();
        $('body').append(modalHtml);
        
        $('#banlist-tab').on('click', function() {
            loadBannedUsers();
        });
        
        $('#roomSettingsModal').modal('show');
    }

    // Function to load banned users
    function loadBannedUsers() {
        console.log('Loading banned users for room:', roomId);
        
        $.ajax({
            url: 'api/get_banned_users_simple.php',
            method: 'GET',
            dataType: 'json',
            data: { room_id: roomId },
            success: function(response) {
                console.log('Banned users response:', response);
                
                let html = '';
                
                if (!Array.isArray(response)) {
                    console.error('Expected array, got:', typeof response, response);
                    if (response && response.status && response.message) {
                        html = '<p class="text-danger">Error: ' + response.message + '</p>';
                    } else {
                        html = '<p class="text-danger">Invalid response format from server.</p>';
                    }
                } else {
                    if (response.length === 0) {
                        html = '<p class="text-muted">No banned users.</p>';
                    } else {
                        response.forEach((ban) => {
                            const name = ban.username || ban.guest_name || 'Unknown User';
                            const banType = ban.ban_until === null || ban.ban_until === '' ? 'Permanent' : 'Temporary';
                            const expiry = ban.ban_until ? new Date(ban.ban_until).toLocaleString() : 'Never';
                            const reason = ban.reason || 'No reason provided';
                            
                            html += '<div class="card mb-2">' +
                                '<div class="card-body p-3">' +
                                '<div class="d-flex justify-content-between align-items-center">' +
                                '<div>' +
                                '<strong>' + name + '</strong> ' +
                                '<span class="badge ' + (banType === 'Permanent' ? 'bg-danger' : 'bg-warning') + '">' + banType + '</span>' +
                                '<br>' +
                                '<small class="text-muted">' +
                                'Expires: ' + expiry + '<br>' +
                                'Reason: ' + reason + '<br>' +
                                'User ID: ' + ban.user_id_string +
                                '</small>' +
                                '</div>' +
                                '<button class="btn btn-sm btn-outline-success" onclick="unbanUser(\'' + ban.user_id_string + '\', \'' + name.replace(/'/g, "\\'") + '\')">' +
                                '<i class="fas fa-unlock"></i> Unban' +
                                '</button>' +
                                '</div>' +
                                '</div>' +
                                '</div>';
                        });
                    }
                }
                
                $('#bannedUsersList').html(html);
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in loadBannedUsers:', status, error, xhr.responseText);
                let errorMsg = 'Error loading banned users.';
                if (xhr.responseText) {
                    errorMsg += ' Server response: ' + xhr.responseText;
                }
                $('#bannedUsersList').html('<p class="text-danger">' + errorMsg + '</p>');
            }
        });
    }

    // Function to unban user
    window.unbanUser = function(userIdString, userName) {
        if (!confirm('Are you sure you want to unban ' + userName + '?')) {
            return;
        }
        
        $.ajax({
            url: 'api/unban_user_simple.php',
            method: 'POST',
            dataType: 'json',
            data: {
                room_id: roomId,
                user_id_string: userIdString
            },
            success: function(response) {
                console.log('Unban response:', response);
                
                let res = response;
                if (typeof response === 'string') {
                    try {
                        res = JSON.parse(response);
                    } catch (e) {
                        console.error('JSON parse error:', e, response);
                        alert('Invalid response from server');
                        return;
                    }
                }
                
                if (res.status === 'success') {
                    alert(userName + ' has been unbanned successfully!');
                    loadBannedUsers();
                } else {
                    alert('Error: ' + res.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in unbanUser:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    };

    // Function to save room settings
    window.saveRoomSettings = function() {
        const formData = {
            room_id: roomId,
            name: $('#settingsRoomName').val().trim(),
            description: $('#settingsDescription').val().trim(),
            capacity: $('#settingsCapacity').val(),
            background: $('#settingsBackground').val(),
            password: $('#settingsPassword').val(),
            permanent: $('#settingsPermanent').is(':checked') ? 1 : 0
        };
        
        if (!formData.name) {
            alert('Room name is required');
            $('#settingsRoomName').focus();
            return;
        }
        
        console.log('Saving room settings:', formData);
        
        $.ajax({
            url: 'api/update_room.php',
            method: 'POST',
            data: formData,
            success: function(response) {
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
                        alert('Room settings updated successfully!');
                        $('#roomSettingsModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in saveRoomSettings:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    };

    // Function to show ban modal
    window.showBanModal = function(userIdString, userName) {
        const modalHtml = '<div class="modal fade" id="banUserModal" tabindex="-1">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title"><i class="fas fa-ban"></i> Ban User</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p>You are about to ban <strong>' + userName + '</strong> from this room.</p>' +
            '<div class="mb-3">' +
            '<label for="banDuration" class="form-label">Ban Duration</label>' +
            '<select class="form-select" id="banDuration" required>' +
            '<option value="300">5 minutes</option>' +
            '<option value="1800">30 minutes</option>' +
            '<option value="permanent">Permanent</option>' +
            '</select>' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="banReason" class="form-label">Reason (optional)</label>' +
            '<input type="text" class="form-control" id="banReason" placeholder="Enter reason for ban">' +
            '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="button" class="btn btn-danger" onclick="confirmBanUser(\'' + userIdString + '\', \'' + userName.replace(/'/g, "\\'") + '\')"><i class="fas fa-ban"></i> Ban User</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('#banUserModal').remove();
        $('body').append(modalHtml);
        $('#banUserModal').modal('show');
    };

    // Function to confirm and execute ban
    window.confirmBanUser = function(userIdString, userName) {
        const duration = $('#banDuration').val();
        const reason = $('#banReason').val().trim();
        
        const durationText = duration === 'permanent' ? 'permanently' : 
                           duration == 300 ? 'for 5 minutes' :
                           duration == 1800 ? 'for 30 minutes' : 'for ' + duration + ' seconds';
        
        if (!confirm('Are you sure you want to ban ' + userName + ' ' + durationText + '?')) {
            return;
        }
        
        console.log('Banning user:', userIdString, 'for:', duration, 'reason:', reason);
        
        $.ajax({
            url: 'api/ban_user_simple.php',
            method: 'POST',
            dataType: 'json',
            data: {
                room_id: roomId,
                user_id_string: userIdString,
                duration: duration,
                reason: reason
            },
            success: function(response) {
                console.log('Ban response:', response);
                
                let res = response;
                if (typeof response === 'string') {
                    try {
                        res = JSON.parse(response);
                    } catch (e) {
                        console.error('JSON parse error:', e, response);
                        alert('Invalid response from server');
                        return;
                    }
                }
                
                if (res.status === 'success') {
                    alert('User banned successfully ' + durationText + '!');
                    $('#banUserModal').modal('hide');
                    loadUsers();
                    loadMessages();
                } else {
                    alert('Error: ' + res.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in confirmBanUser:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    };

    // Function to load users - Enhanced with better display
    function loadUsers() {
        console.log('Loading users for roomId:', roomId);
        $.ajax({
            url: 'api/get_room_users.php',
            method: 'GET',
            data: { room_id: roomId },
            success: function(response) {
                console.log('Response from api/get_room_users.php:', response);
                try {
                    let users = JSON.parse(response);
                    let html = '';
                    if (!Array.isArray(users)) {
                        console.error('Expected array from get_room_users, got:', users);
                        html = '<p>Error loading users.</p>';
                    } else if (users.length === 0) {
                        html = '<p class="text-muted">No users in room.</p>';
                    } else {
                        users.forEach(user => {
                            const avatar = user.avatar || user.guest_avatar || 'default_avatar.jpg';
                            const name = user.display_name || user.username || user.guest_name || 'Unknown';
                            
                            html += '<div class="user-item mb-3 p-2 border rounded">' +
                                '<div class="d-flex align-items-center">' +
                                '<img src="images/' + avatar + '" width="40" height="40" class="me-2" alt="' + name + '\'s avatar">' +
                                '<div class="flex-grow-1">' +
                                '<div class="fw-bold">' + name + '</div>' +
                                '<div class="d-flex flex-wrap gap-1">';
                            
                            if (user.is_host) {
                                html += '<span class="badge bg-primary">Host</span>';
                            }
                            if (user.is_admin) {
                                html += '<span class="badge bg-danger">Admin</span>';
                            }
                            if (user.user_type === 'registered') {
                                html += '<span class="badge bg-success">Verified</span>';
                            } else {
                                html += '<span class="badge bg-secondary">Guest</span>';
                            }
                            
                            html += '</div></div>';
                            
                            if ((isHost || isAdmin) && !user.is_host && user.user_id_string !== currentUserIdString) {
                                html += '<button class="btn btn-sm btn-outline-danger" onclick="showBanModal(\'' + user.user_id_string + '\', \'' + name.replace(/'/g, "\\'") + '\')">' +
                                    '<i class="fas fa-ban"></i>' +
                                    '</button>';
                            }
                            
                            html += '</div></div>';
                        });
                    }
                    $('#userList').html(html);
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    $('#userList').html('<p class="text-danger">Error loading users</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in loadUsers:', status, error, xhr.responseText);
                $('#userList').html('<p class="text-danger">Failed to load users</p>');
            }
        });
    }

    // Function to leave room
    window.leaveRoom = function() {
        console.log('Leave room clicked for roomId:', roomId);
        
        $.ajax({
            url: 'api/leave_room.php',
            method: 'POST',
            data: { 
                room_id: roomId,
                action: 'check_options'
            },
            success: function(response) {
                console.log('Response from api/leave_room.php (check):', response);
                try {
                    let res = JSON.parse(response);
                    console.log('Parsed response:', res);
                    
                    if (res.status === 'host_leaving') {
                        console.log('User is host, showing modal with other users:', res.other_users);
                        showHostLeavingModal(
                            res.other_users || [], 
                            res.show_transfer !== false, 
                            res.last_user === true
                        );
                    } else if (res.status === 'success') {
                        console.log('Regular user leaving, redirecting to lounge');
                        window.location.href = 'lounge.php';
                    } else {
                        console.error('Error response:', res);
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, 'Raw response:', response);
                    if (response.includes('success')) {
                        window.location.href = 'lounge.php';
                    } else {
                        alert('Invalid response from server: ' + response);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in leaveRoom:', status, error, 'Response:', xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    };

    // Function to show host leaving modal
    function showHostLeavingModal(otherUsers, showTransfer, isLastUser) {
        if (showTransfer === undefined) showTransfer = true;
        if (isLastUser === undefined) isLastUser = false;
        
        let userOptions = '';
        let transferSection = '';
        
        if (showTransfer && otherUsers.length > 0) {
            otherUsers.forEach(user => {
                let displayName = user.username || user.guest_name;
                userOptions += '<option value="' + user.user_id_string + '">' + displayName + '</option>';
            });
            
            transferSection = '<div class="mb-3">' +
                '<label for="newHostSelect" class="form-label">Or transfer host privileges to:</label>' +
                '<select class="form-select mb-2" id="newHostSelect">' +
                '<option value="">Select new host...</option>' +
                userOptions +
                '</select>' +
                '<button type="button" class="btn btn-primary w-100" onclick="transferHost()">Transfer Host & Leave</button>' +
                '</div>';
        }

        let modalHtml = '<div class="modal fade" id="hostLeavingModal" tabindex="-1">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title">' + (isLastUser ? 'Last User in Room' : 'You are the Host') + '</h5>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p>' + (isLastUser ? 
                'You are the last user in this room. When you leave, the room will be deleted.' : 
                'You are the host of this room. What would you like to do?') + '</p>' +
            '<div class="mb-3">' +
            '<button type="button" class="btn btn-danger w-100 mb-2" onclick="deleteRoom()">' +
            (isLastUser ? 'Leave & Delete Room' : 'Delete Room') +
            '</button>' +
            transferSection +
            '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('#hostLeavingModal').remove();
        $('body').append(modalHtml);
        $('#hostLeavingModal').modal('show');
    }

    // Function to delete room
    window.deleteRoom = function() {
        if (confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
            $.ajax({
                url: 'api/leave_room.php',
                method: 'POST',
                data: { 
                    room_id: roomId,
                    action: 'delete_room'
                },
                success: function(response) {
                    console.log('Response from deleteRoom:', response);
                    try {
                        let res = JSON.parse(response);
                        if (res.status === 'success') {
                            alert('Room deleted successfully');
                            window.location.href = 'lounge.php';
                        } else {
                            alert('Error: ' + res.message);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e, response);
                        alert('Invalid response from server');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error in deleteRoom:', status, error, xhr.responseText);
                    alert('AJAX error: ' + error);
                }
            });
        }
    };

    // Function to transfer host
    window.transferHost = function() {
        let newHostId = $('#newHostSelect').val();
        if (!newHostId) {
            alert('Please select a user to transfer host privileges to');
            return;
        }
        
        if (confirm('Are you sure you want to transfer host privileges and leave the room?')) {
            $.ajax({
                url: 'api/leave_room.php',
                method: 'POST',
                data: { 
                    room_id: roomId,
                    action: 'transfer_host',
                    new_host_user_id: newHostId
                },
                success: function(response) {
                    console.log('Response from transferHost:', response);
                    try {
                        let res = JSON.parse(response);
                        if (res.status === 'success') {
                            alert('Host privileges transferred successfully');
                            window.location.href = 'lounge.php';
                        } else {
                            alert('Error: ' + res.message);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e, response);
                        alert('Invalid response from server');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error in transferHost:', status, error, xhr.responseText);
                    alert('AJAX error: ' + error);
                }
            });
        }
    };

    // Function to send message
    window.sendMessage = function() {
        const messageInput = $('#message');
        const message = messageInput.val().trim();
        
        if (!message) {
            alert('Please enter a message');
            return false;
        }
        
        console.log('Sending message:', message);
        
        $.ajax({
            url: 'api/send_message.php',
            method: 'POST',
            data: {
                room_id: roomId,
                message: message
            },
            success: function(response) {
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
                        messageInput.val('');
                        loadMessages();
                    } else {
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in sendMessage:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
        
        return false;
    };

    // Handle message form submission
    $(document).on('submit', '#messageForm', function(e) {
        e.preventDefault();
        sendMessage();
        return false;
    });

    // Handle Enter key in message input
    $(document).on('keypress', '#message', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            sendMessage();
            return false;
        }
    });

    // Store last scroll position and message count to prevent auto-scroll when user is scrolling
    let lastScrollTop = 0;
    let lastMessageCount = 0;
    let userIsScrolling = false;

    // Track when user is manually scrolling
    $(document).on('scroll', '#chatbox', function() {
        userIsScrolling = true;
        setTimeout(function() {
            userIsScrolling = false;
        }, 1000);
    });

    // Enhanced loadMessages function with better system message handling
    function loadMessages() {
        console.log('Loading messages for roomId:', roomId);
        $.ajax({
            url: 'api/get_messages.php',
            method: 'GET',
            data: { room_id: roomId },
            success: function(response) {
                console.log('Response from api/get_messages.php:', response);
                try {
                    let messages = JSON.parse(response);
                    let html = '';
                    if (!Array.isArray(messages)) {
                        console.error('Expected array from get_messages, got:', messages);
                        html = '<p>Error loading messages.</p>';
                    } else if (messages.length === 0) {
                        html = '<p class="text-muted text-center">No messages yet. Start the conversation!</p>';
                    } else {
                        messages.forEach(msg => {
                            const avatar = msg.avatar || msg.guest_avatar || 'default_avatar.jpg';
                            const name = msg.username || msg.guest_name || 'Unknown';
                            
                            if (msg.type === 'system' || msg.is_system) {
                                html += '<div class="chat-message system-message text-center my-2">' +
                                    '<img src="images/' + avatar + '" width="20" height="20" class="me-1" alt="System">' +
                                    '<span class="text-muted">' + msg.message + '</span>' +
                                    '</div>';
                            } else {
                                html += '<div class="chat-message mb-2">' +
                                    '<div class="d-flex align-items-start">' +
                                    '<img src="images/' + avatar + '" width="48" class="me-2" alt="' + name + '\'s avatar">' +
                                    '<div class="flex-grow-1">' +
                                    '<div class="d-flex align-items-center mb-1">' +
                                    '<strong class="me-2">' + name + '</strong>';
                                
                                if (msg.is_admin) {
                                    html += '<span class="badge bg-danger badge-sm me-1">Staff</span>';
                                }
                                if (msg.user_id) {
                                    html += '<span class="badge bg-success badge-sm me-1">Verified</span>';
                                } else {
                                    html += '<span class="badge bg-secondary badge-sm me-1">Guest</span>';
                                }
                                
                                html += '<small class="text-muted">' + new Date(msg.timestamp).toLocaleTimeString() + '</small>' +
                                    '</div>' +
                                    '<div class="message-content">' + msg.message + '</div>';
                                
                                if (isAdmin && msg.ip_address) {
                                    html += '<small class="text-muted">IP: ' + msg.ip_address + '</small>';
                                }
                                
                                html += '</div></div></div>';
                            }
                        });
                    }
                    
                    const chatbox = $('#chatbox');
                    const isAtBottom = chatbox.scrollTop() + chatbox.innerHeight() >= chatbox[0].scrollHeight - 10;
                    const newMessageCount = messages.length;
                    
                    chatbox.html(html);
                    
                    if (isAtBottom || (newMessageCount > lastMessageCount && !userIsScrolling)) {
                        chatbox.scrollTop(chatbox[0].scrollHeight);
                    }
                    
                    lastMessageCount = newMessageCount;
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    $('#chatbox').html('<p class="text-danger">Error loading messages</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in loadMessages:', status, error, xhr.responseText);
                $('#chatbox').html('<p class="text-danger">Failed to load messages</p>');
            }
        });
    }

    // Function to create test user for testing bans
    window.createTestUser = function() {
        $.ajax({
            url: 'api/create_test_user.php',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                console.log('Create test user response:', response);
                
                let res = response;
                if (typeof response === 'string') {
                    try {
                        res = JSON.parse(response);
                    } catch (e) {
                        console.error('JSON parse error:', e, response);
                        alert('Invalid response from server');
                        return;
                    }
                }
                
                if (res.status === 'success') {
                    alert('Test user created: ' + res.user.name);
                    loadUsers();
                    loadMessages();
                } else {
                    alert('Error: ' + res.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in createTestUser:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    };

    // Make loadMessages available globally
    window.loadMessages = loadMessages;

    // Initialization
    console.log('Initializing room functions for roomId:', roomId);
    loadMessages();
    loadUsers();
    setInterval(loadMessages, 3000);
    setInterval(loadUsers, 5000);
});

// ADD THESE FUNCTIONS TO THE END OF YOUR EXISTING room.js FILE
// ============================================================

// Knock notification functions for room page
function checkForKnocks() {
    // Only check if user is host
    if (!isHost) {
        return;
    }
    
    console.log('checkForKnocks: Checking for knocks in room...');
    
    $.ajax({
        url: 'api/check_knocks.php',
        method: 'GET',
        dataType: 'json',
        success: function(knocks) {
            console.log('checkForKnocks: Received response:', knocks);
            
            if (Array.isArray(knocks) && knocks.length > 0) {
                console.log('checkForKnocks: Found', knocks.length, 'knocks');
                displayKnockNotifications(knocks);
            } else {
                console.log('checkForKnocks: No knocks found');
            }
        },
        error: function(xhr, status, error) {
            console.log('checkForKnocks: Error:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
        }
    });
}

// Make it globally available
window.checkForKnocks = checkForKnocks;

function displayKnockNotifications(knocks) {
    console.log('displayKnockNotifications: Processing', knocks.length, 'knocks');
    
    knocks.forEach((knock, index) => {
        console.log('Processing knock:', knock);
        
        // Check if notification already exists
        if ($(`#knock-${knock.id}`).length > 0) {
            console.log('Notification already exists for knock', knock.id);
            return;
        }
        
        const userName = knock.username || knock.guest_name || 'Unknown User';
        const avatar = knock.avatar || 'default_avatar.jpg';
        const roomName = knock.room_name || 'This Room';
        
        // Calculate position for multiple notifications
        const topPosition = 20 + (index * 140);
        
        const notificationHtml = `
            <div class="alert alert-info knock-notification" 
                 id="knock-${knock.id}" 
                 role="alert" 
                 style="position: fixed; top: ${topPosition}px; right: 20px; z-index: 1070; max-width: 400px; min-width: 350px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); border-left: 4px solid #007bff; background: rgba(255,255,255,0.98);">
                <div class="d-flex align-items-center">
                    <img src="images/${avatar}" width="40" height="40" class="rounded-circle me-3" alt="${userName}" style="border: 2px solid #fff;">
                    <div class="flex-grow-1">
                        <h6 class="mb-1" style="color: #333;">
                            <i class="fas fa-hand-paper text-primary"></i> Knock Request
                        </h6>
                        <p class="mb-2" style="color: #555;"><strong>${userName}</strong> wants to join this room</p>
                        <div>
                            <button class="btn btn-success btn-sm me-2" onclick="respondToKnock(${knock.id}, 'accepted')">
                                <i class="fas fa-check"></i> Accept
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="respondToKnock(${knock.id}, 'denied')">
                                <i class="fas fa-times"></i> Deny
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-close" onclick="dismissKnock(${knock.id})" style="color: #333;"></button>
                </div>
            </div>
        `;
        
        console.log('Adding knock notification for knock', knock.id);
        $('body').append(notificationHtml);
        
        // Add entrance animation
        $(`#knock-${knock.id}`).hide().fadeIn(300);
        
        // Auto-dismiss after 45 seconds
        setTimeout(() => {
            console.log('Auto-dismissing knock', knock.id);
            dismissKnock(knock.id);
        }, 45000);
    });
}

// Respond to knock function
window.respondToKnock = function(knockId, response) {
    console.log('respondToKnock:', knockId, response);
    
    $.ajax({
        url: 'api/respond_knocks.php',
        method: 'POST',
        data: {
            knock_id: knockId,
            response: response
        },
        dataType: 'json',
        success: function(result) {
            console.log('Knock response result:', result);
            if (result.status === 'success') {
                dismissKnock(knockId);
                
                // Reload messages to show any system messages
                loadMessages();
                
                // Show success message
                const message = response === 'accepted' ? 
                    'Knock accepted! The user can now join the room.' : 
                    'Knock request denied.';
                alert(message);
            } else {
                alert('Error: ' + result.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error responding to knock:', error, xhr.responseText);
            alert('Error responding to knock: ' + error);
        }
    });
};

// Dismiss knock notification
window.dismissKnock = function(knockId) {
    console.log('dismissKnock:', knockId);
    $(`#knock-${knockId}`).fadeOut(300, function() {
        $(this).remove();
        repositionKnockNotifications();
    });
};

// Reposition remaining notifications
function repositionKnockNotifications() {
    $('.knock-notification').each(function(index) {
        $(this).animate({
            top: (20 + (index * 140)) + 'px'
        }, 200);
    });
}

// Test function
window.testKnockCheck = function() {
    console.log('Manual knock check...');
    checkForKnocks();
};
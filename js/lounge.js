// ===== IMPROVED LOUNGE.JS - BETTER REFRESH RATES & CLEANUP =====
// ===== DEBUG CONFIGURATION =====
const DEBUG_MODE = false;

function debugLog(message, data = null) {
    if (DEBUG_MODE) {
        if (data !== null) {
            console.log('[LOUNGE]', message, data);
        } else {
            console.log('[LOUNGE]', message);
        }
    }
}

$(document).ready(function() {
    debugLog('Lounge loaded');
    
    // Store user's room keys globally
    let userRoomKeys = [];
    
    // Load initial data
    loadUserRoomKeys();
    loadRoomsWithUsers();
    loadOnlineUsers();
    
    // Main update loop - handles most updates
    setInterval(() => {
        loadOnlineUsers();
        loadRoomsWithUsers();
        loadUserRoomKeys();
        sendHeartbeat();
    }, 5000); // Every 5 seconds
    
    // Fast knock checking for hosts
    setInterval(checkForKnocks, 3000); // Every 3 seconds
    
    // Cleanup interval
    setInterval(cleanupInactiveUsers, 60000); // Every minute
    
    // Initialize private messaging after a delay
    setTimeout(initializePrivateMessaging, 1000);
});

// NEW: Send heartbeat to keep user marked as active
function sendHeartbeat() {
    debugLog('Sending heartbeat...');
    $.ajax({
        url: 'api/heartbeat.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            debugLog('Heartbeat sent successfully');
        },
        error: function() {
            debugLog('Heartbeat failed (silent fail)');
            // Silent fail - don't show errors for heartbeat
        }
    });
}

// NEW: Clean up inactive users from the interface
function cleanupInactiveUsers() {
    debugLog('Requesting cleanup of inactive users...');
    $.ajax({
        url: 'api/cleanup_inactive_users.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            debugLog('Cleanup response:', response);
            if (response.cleaned_count > 0) {
                debugLog(`Cleaned up ${response.cleaned_count} inactive users`);
                // Immediately refresh online users list to reflect cleanup
                loadOnlineUsers();
            }
        },
        error: function() {
            debugLog('Cleanup failed (silent fail)');
            // Silent fail
        }
    });
}

// Function to load user's room keys
function loadUserRoomKeys() {
    debugLog('Loading user room keys...');
    $.ajax({
        url: 'api/check_user_room_keys.php',
        method: 'GET',
        dataType: 'json',
        success: function(keys) {
            debugLog('User room keys loaded:', keys);
            userRoomKeys = Array.isArray(keys) ? keys : [];
        },
        error: function(xhr, status, error) {
            debugLog('Error loading user room keys:', error);
            userRoomKeys = [];
        }
    });
}

// Function to check if user has a room key
function hasRoomKey(roomId) {
    return userRoomKeys.includes(parseInt(roomId));
}

// IMPROVED: Main function to load rooms with their users - with better error handling
function loadRoomsWithUsers() {
    debugLog('Loading rooms with users...');
    
    // Add subtle loading indicator only if rooms exist
    if ($('#roomsList .room-card-enhanced').length > 0) {
        $('#roomsList').addClass('updating');
    }
    
    $.ajax({
        url: 'api/get_rooms.php',
        method: 'GET',
        dataType: 'json',
        timeout: 10000, // 10 second timeout
        success: function(rooms) {
            debugLog('Rooms loaded:', rooms);
            
            if (!Array.isArray(rooms) || rooms.length === 0) {
                displayRoomsWithUsers([]);
                return;
            }
            
            // Load users for each room
            let completedRooms = 0;
            let roomsWithUsers = [];
            
            rooms.forEach((room, index) => {
                loadUsersForRoom(room, (roomWithUsers) => {
                    roomsWithUsers[index] = roomWithUsers;
                    completedRooms++;
                    
                    debugLog(`Room ${room.id} users loaded:`, roomWithUsers);
                    
                    if (completedRooms === rooms.length) {
                        // Filter out undefined entries and display
                        const validRooms = roomsWithUsers.filter(r => r !== undefined);
                        debugLog('All rooms processed, displaying:', validRooms);
                        displayRoomsWithUsers(validRooms);
                        
                        // Remove loading indicator
                        setTimeout(() => {
                            $('#roomsList').removeClass('updating');
                        }, 300);
                    }
                });
            });
            
        },
        error: function(xhr, status, error) {
            console.error('Error loading rooms:', error);
            $('#roomsList').removeClass('updating');
            
            // Show error only if it's not a timeout during background refresh
            if (status !== 'timeout' || $('#roomsList .room-card-enhanced').length === 0) {
                $('#roomsList').html(`
                    <div class="alert alert-danger" style="background: #2a2a2a; border: 1px solid #d32f2f; color: #f44336;">
                        <h5>Error loading rooms</h5>
                        <p>Error: ${error}</p>
                        <button class="btn btn-outline-light btn-sm" onclick="loadRoomsWithUsers()">
                            <i class="fas fa-retry"></i> Retry
                        </button>
                    </div>
                `);
            }
        }
    });
}

// Function to load users for a specific room
function loadUsersForRoom(room, callback) {
    debugLog(`Loading users for room ${room.id}...`);
    
    $.ajax({
        url: 'api/get_room_users.php',
        method: 'GET',
        data: { room_id: room.id },
        dataType: 'json',
        timeout: 8000, // 8 second timeout
        success: function(users) {
            debugLog(`Raw users data for room ${room.id}:`, users);
            
            try {
                // Parse users if it's a string
                let parsedUsers = users;
                if (typeof users === 'string') {
                    parsedUsers = JSON.parse(users);
                }
                
                // Ensure we have an array
                if (!Array.isArray(parsedUsers)) {
                    parsedUsers = [];
                }
                
                // Separate host and regular users
                const host = parsedUsers.find(user => parseInt(user.is_host) === 1) || null;
                const regularUsers = parsedUsers.filter(user => parseInt(user.is_host) !== 1);
                
                debugLog(`Room ${room.id} processed:`, {
                    total: parsedUsers.length,
                    host: host ? (host.display_name || host.username || host.guest_name) : 'None',
                    regularUsers: regularUsers.length
                });
                
                // Add user data to room object
                const roomWithUsers = {
                    ...room,
                    users: parsedUsers,
                    host: host,
                    regularUsers: regularUsers,
                    user_count: parsedUsers.length
                };
                
                callback(roomWithUsers);
                
            } catch (e) {
                console.error(`Error parsing users for room ${room.id}:`, e, users);
                callback({
                    ...room,
                    users: [],
                    host: null,
                    regularUsers: [],
                    user_count: 0
                });
            }
        },
        error: function(xhr, status, error) {
            console.error(`Error loading users for room ${room.id}:`, error);
            callback({
                ...room,
                users: [],
                host: null,
                regularUsers: [],
                user_count: 0
            });
        }
    });
}

// Function to display rooms with users in 2-column layout
function displayRoomsWithUsers(rooms) {
    debugLog('displayRoomsWithUsers called with:', rooms);
    let html = '';
    
    if (!Array.isArray(rooms) || rooms.length === 0) {
        html = `
            <div class="text-center py-5" style="color: #ccc;">
                <i class="fas fa-door-closed fa-3x mb-3" style="color: #555;"></i>
                <h4 style="color: #aaa;">No rooms available</h4>
                <p style="color: #777;">Be the first to create a room!</p>
            </div>
        `;
    } else {
        // Start with Bootstrap row
        html += '<div class="row">';
        
        rooms.forEach((room, index) => {
            debugLog('Processing room for display:', room);
            
            const isPasswordProtected = parseInt(room.has_password) === 1;
            const allowsKnocking = parseInt(room.allow_knocking) === 1;
            const userCount = room.user_count || 0;
            const capacity = room.capacity || 10;
            const hasKey = hasRoomKey(room.id);
            const host = room.host;
            const regularUsers = room.regularUsers || [];
            
            let headerClass = 'room-header-enhanced';
            let actionButtons = '';
            
            // Room access logic
            if (isPasswordProtected && hasKey) {
                headerClass += ' has-access';
                actionButtons = `
                    <button class="btn btn-success btn-sm" onclick="joinRoom(${room.id})">
                        <i class="fas fa-key"></i> Join Room 
                    </button>
                `;
            } else if (isPasswordProtected) {
                if (allowsKnocking) {
                    headerClass += ' knock-available';
                } else {
                    headerClass += ' password-protected';
                }
                
                actionButtons = `
                    <button class="btn btn-primary btn-sm me-2" onclick="showPasswordModal(${room.id}, '${room.name.replace(/'/g, "\\'")}');">
                        <i class="fas fa-key"></i> Enter Password
                    </button>
                `;
                
                if (allowsKnocking) {
                    actionButtons += `
                        <button class="btn btn-outline-primary btn-sm" onclick="knockOnRoom(${room.id}, '${room.name.replace(/'/g, "\\'")}');">
                            <i class="fas fa-hand-paper"></i> Knock
                        </button>
                    `;
                }
            } else {
                actionButtons = `
                    <button class="btn btn-success btn-sm" onclick="joinRoom(${room.id});">
                        <i class="fas fa-sign-in-alt"></i> Join Room
                    </button>
                `;
            }
            
            // Build host section
            let hostHtml = '';
            if (host) {
                const hostAvatar = host.avatar || host.user_avatar || host.guest_avatar || 'default_avatar.jpg';
                const hostName = host.display_name || host.username || host.guest_name || 'Unknown Host';
                const hostHue = host.avatar_hue || host.user_avatar_hue || 0;
const hostSaturation = host.avatar_saturation || host.user_avatar_saturation || 100;
                
                debugLog(`Building host HTML for ${hostName}:`, host);
                
                hostHtml = `
    <div class="room-host">
        <h6><i class="fas fa-crown"></i> Host</h6>
        <div class="d-flex align-items-center">
            <img src="images/${hostAvatar}" 
                 width="32" height="32" 
                 class="me-2" 
                 style="filter: hue-rotate(${hostHue}deg) saturate(${hostSaturation}%);"
                 alt="${hostName}">
            <div>
                <div class="fw-bold">${hostName}</div>
                <div class="user-badges">
                    ${parseInt(host.is_admin) === 1 ? '<span class="badge bg-danger badge-sm">Admin</span>' : ''}
                    ${host.user_type === 'registered' || host.user_id ? '<span class="badge bg-success badge-sm">Verified</span>' : '<span class="badge bg-secondary badge-sm">Guest</span>'}
                </div>
            </div>
        </div>
    </div>
`;
            } else {
                hostHtml = `
                    <div class="room-host">
                        <h6><i class="fas fa-crown"></i> Host</h6>
                        <div class="text-muted">No host available</div>
                    </div>
                `;
            }
            
            // Build users list in 2-column layout
            let usersHtml = '';
            if (regularUsers.length > 0) {
                debugLog(`Building users HTML for ${regularUsers.length} users:`, regularUsers);
                
                usersHtml = `
                    <div class="room-users">
                        <h6><i class="fas fa-users"></i> Users (${regularUsers.length})</h6>
                        <div class="users-grid-two-column">
                `;
                
                // Show first 8 users in 2-column layout
               regularUsers.slice(0, 8).forEach(user => {
    const userAvatar = user.avatar || user.user_avatar || user.guest_avatar || 'default_avatar.jpg';
    const userName = user.display_name || user.username || user.guest_name || 'Unknown';
    const userHue = user.avatar_hue || user.user_avatar_hue || 0;
    const userSaturation = user.avatar_saturation || user.user_avatar_saturation || 100;
                    
                    usersHtml += `
    <div class="user-item-mini d-flex align-items-center">
        <img src="images/${userAvatar}" 
             width="24" height="24" 
             class="me-2" 
             style="filter: hue-rotate(${userHue}deg) saturate(${userSaturation}%);"
             alt="${userName}">
        <div class="user-info">
            <div class="user-name">${userName}</div>
            <div class="user-badges">
                ${parseInt(user.is_admin) === 1 ? '<span class="badge bg-danger badge-xs">Admin</span>' : ''}
                ${user.user_type === 'registered' || user.user_id ? '<span class="badge bg-success badge-xs">Verified</span>' : '<span class="badge bg-secondary badge-xs">Guest</span>'}
            </div>
        </div>
    </div>
`;
                });
                
                if (regularUsers.length > 8) {
                    usersHtml += `<div class="text-muted small users-more-indicator">+ ${regularUsers.length - 8} more users</div>`;
                }
                
                usersHtml += `
                        </div>
                    </div>
                `;
            } else {
                usersHtml = `
                    <div class="room-users">
                        <h6><i class="fas fa-users"></i> Users (0)</h6>
                        <div class="text-muted small">No other users in room</div>
                    </div>
                `;
            }
            
            // Each room card takes up half width (col-lg-6) for 2-column layout
            html += `
                <div class="col-lg-6 col-12 room-card-wrapper">
                    <div class="room-card-enhanced">
                        <div class="${headerClass}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="room-title-section">
                                    <h5 class="room-title">
                                        ${room.name}
                                        ${isPasswordProtected ? '<i class="fas fa-lock" title="Password protected"></i>' : ''}
                                        ${allowsKnocking ? '<i class="fas fa-hand-paper" title="Knocking allowed"></i>' : ''}
                                        ${hasKey ? '<i class="fas fa-key" title="You have access"></i>' : ''}
                                    </h5>
                                    <div class="room-meta">
                                        <span class="capacity-info">${userCount}/${capacity} users</span>
                                    </div>
                                    ${hasKey ? '<div class="mt-1"><span class="badge bg-success"><i class="fas fa-key"></i> Access Granted</span></div>' : ''}
                                </div>
                                <div class="action-buttons">
                                    ${actionButtons}
                                </div>
                            </div>
                        </div>
                        <div class="room-content">
                            <div class="room-description">
                                <p>${room.description || 'No description'}</p>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    ${usersHtml}
                                </div>
                                <div class="col-12 mt-3">
                                    ${hostHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        // Close the Bootstrap row
        html += '</div>';
    }
    
    debugLog('Setting rooms HTML...');
    
    // IMPROVED: Smoother updates with less jarring transitions
    const $roomsList = $('#roomsList');
    if ($roomsList.children().length > 0 && !$roomsList.hasClass('updating')) {
        // Only animate if we're not already updating
        $roomsList.addClass('fade-transition');
        setTimeout(() => {
            $roomsList.html(html);
            $roomsList.removeClass('fade-transition');
        }, 150);
    } else {
        $roomsList.html(html);
    }
    /* Only apply filters if this is a new render, not just an update
if (!$roomsList.hasClass('updating')) {
    setTimeout(applyAllAvatarFilters, 200);
}*/

}

// Enhanced joinRoom function
window.joinRoom = function(roomId) {
    debugLog('joinRoom: Attempting to join room', roomId);
    
    $.ajax({
        url: 'api/join_room.php',
        method: 'POST',
        data: { room_id: roomId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                if (response.used_room_key) {
                    loadUserRoomKeys();
                }
                window.location.href = 'room.php';
            } else {
                if (response.message && response.message.toLowerCase().includes('password')) {
                    showPasswordModal(roomId, 'Room ' + roomId);
                } else {
                    alert('Error: ' + response.message);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('joinRoom error:', error);
            alert('Error joining room: ' + error);
        }
    });
};

// IMPROVED: Function to load online users with better filtering
function loadOnlineUsers() {
    $.ajax({
        url: 'api/get_online_users.php',
        method: 'GET',
        dataType: 'json',
        timeout: 8000, // 8 second timeout
        success: function(users) {
            debugLog('Online users loaded:', users);
            displayOnlineUsers(users);
        },
        error: function(xhr, status, error) {
            console.error('Error loading online users:', error);
            // Don't show error in UI for background refreshes, just log it
            if (status !== 'timeout') {
                debugLog('Failed to load online users, keeping current list');
            }
        }
    });
}

// IMPROVED: Function to display online users with last activity info
function displayOnlineUsers(users) {
    let html = '';
    
    if (!Array.isArray(users) || users.length === 0) {
        html = '<p style="color: #666;">No users online</p>';
    } else {
        users.forEach(user => {
            const name = user.username || user.guest_name || 'Unknown';
            const avatar = user.avatar || user.guest_avatar || 'default_avatar.jpg';
            const lastActivity = user.last_activity;
            const hue = user.avatar_hue || 0;
            const saturation = user.avatar_saturation || 100;
            
// In displayOnlineUsers function, update the avatar click handler:
const isRegisteredUser = user.username && user.username.trim() !== '';
const isCurrentUser = user.user_id_string === currentUser.user_id;

let avatarClickHandler = '';
if (isRegisteredUser) {
    // Registered users - anyone can click to view profile
    avatarClickHandler = `onclick="handleAvatarClick(event, '${user.user_id_string}', '${user.username.replace(/'/g, "\\'")}')" style="cursor: pointer;"`;
} else if (isCurrentUser) {
    // Current guest user - can click to edit their own profile  
    avatarClickHandler = `onclick="showProfileEditor()" style="cursor: pointer;"`;
}
            
            let activityIndicator = '';
            if (lastActivity) {
                const now = new Date();
                const lastActiveTime = new Date(lastActivity);
                const diffMinutes = Math.floor((now - lastActiveTime) / (1000 * 60));
                
                if (diffMinutes < 1) {
                    activityIndicator = '<span class="badge bg-success badge-xs">Online</span>';
                } else if (diffMinutes < 5) {
                    activityIndicator = `<span class="badge bg-warning badge-xs">${diffMinutes}m ago</span>`;
                } else {
                    activityIndicator = '<span class="badge bg-secondary badge-xs">Away</span>';
                }
            }
            
            html += `
    <div class="d-flex align-items-center mb-2">
        <img src="images/${avatar}" 
             width="30" height="30" 
             class="me-2" 
             style="filter: hue-rotate(${hue}deg) saturate(${saturation}%); border-radius: 2px; ${avatarClickHandler ? 'cursor: pointer;' : ''}"
             ${avatarClickHandler}
             alt="${name}">
        <div style="flex-grow: 1;">
            <small class="fw-bold" style="color: #fff;">${name}</small>
            <div>
                ${user.is_admin ? '<span class="badge bg-danger badge-sm">Admin</span>' : ''}
                ${activityIndicator}
            </div>
        </div>
    </div>
`;
        });
    }
    
    $('#onlineUsersList').html(html);
}

function handleAvatarClick(event, userIdString, username) {
    event.preventDefault();
    event.stopPropagation();
    
    console.log('Lounge avatar clicked - userIdString:', userIdString, 'username:', username);
    
    if (username && username.trim() !== '') {
        // For lounge, we need to get the actual user ID from username
        getUserIdFromUsername(username, function(userId) {
            if (userId) {
                if (userId == currentUser.id) {
                    // Allow both registered users and guests to edit their own profile
                    showUserProfile(userId, event.target);
                } else {
                    // Anyone can view other registered users' profiles
                    showUserProfile(userId, event.target);
                }
            }
        });
    }
}

// Add this helper function to get user ID from username
function getUserIdFromUsername(username, callback) {
    $.ajax({
        url: 'api/get_user_id_from_username.php',
        method: 'GET',
        data: { username: username },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                callback(response.user_id);
            } else {
                callback(null);
            }
        },
        error: function() {
            callback(null);
        }
    });
}

// Password modal function
window.showPasswordModal = function(roomId, roomName) {
    const modalHtml = `
        <div class="modal fade" id="passwordModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-key"></i> Password Required
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <p>Enter the password for <strong>${roomName}</strong>:</p>
                        <div class="mb-3">
                            <input type="password" class="form-control" id="roomPasswordInput" placeholder="Room password" 
                                   style="background: #333; border: 1px solid #555; color: #fff;">
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="joinRoomWithPassword(${roomId})">
                            <i class="fas fa-sign-in-alt"></i> Join Room
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#passwordModal').remove();
    $('body').append(modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
    
    $('#passwordModal').on('shown.bs.modal', function() {
        $('#roomPasswordInput').focus();
    });
    
    $('#roomPasswordInput').on('keypress', function(e) {
        if (e.which === 13) {
            joinRoomWithPassword(roomId);
        }
    });
};

// Function to join room with password
window.joinRoomWithPassword = function(roomId) {
    const password = $('#roomPasswordInput').val();
    
    if (!password) {
        alert('Please enter the password');
        $('#roomPasswordInput').focus();
        return;
    }
    
    $.ajax({
        url: 'api/join_room.php',
        method: 'POST',
        data: {
            room_id: roomId,
            password: password
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                window.location.href = 'room.php';
            } else {
                alert('Error: ' + response.message);
                $('#roomPasswordInput').val('').focus();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error joining room:', error);
            alert('Error joining room: ' + error);
        }
    });
};

// Create room modal
window.showCreateRoomModal = function() {
    const modalHtml = `
        <div class="modal fade" id="createRoomModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="background: #333; border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle"></i> Create New Room
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <form id="createRoomForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="roomName" class="form-label">Room Name</label>
                                        <input type="text" class="form-control" id="roomName" required maxlength="50" 
                                               style="background: #333; border: 1px solid #555; color: #fff;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="roomCapacity" class="form-label">Capacity</label>
                                        <select class="form-select" id="roomCapacity" required 
                                                style="background: #333; border: 1px solid #555; color: #fff;">
                                            <option value="5">5 users</option>
                                            <option value="10" selected>10 users</option>
                                            <option value="20">20 users</option>
                                            <option value="50">50 users</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="roomDescription" class="form-label">Description</label>
                                        <textarea class="form-control" id="roomDescription" rows="3" maxlength="200" 
                                                  style="background: #333; border: 1px solid #555; color: #fff;"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="hasPassword">
                                            <label class="form-check-label" for="hasPassword">
                                                <i class="fas fa-lock"></i> Password Protected
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3" id="passwordField" style="display: none;">
                                        <label for="roomPassword" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="roomPassword" 
                                               style="background: #333; border: 1px solid #555; color: #fff;">
                                    </div>
                                    <div class="mb-3" id="knockingField" style="display: none;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allowKnocking" checked>
                                            <label class="form-check-label" for="allowKnocking">
                                                <i class="fas fa-hand-paper"></i> Allow Knocking
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="createRoom()">
                            <i class="fas fa-plus"></i> Create Room
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#createRoomModal').remove();
    $('body').append(modalHtml);
    
    $('#hasPassword').on('change', function() {
        if (this.checked) {
            $('#passwordField').show();
            $('#knockingField').show();
        } else {
            $('#passwordField').hide();
            $('#knockingField').hide();
            $('#roomPassword').val('');
            $('#allowKnocking').prop('checked', true);
        }
    });
    
    $('#createRoomModal').modal('show');
};

// Create room function
window.createRoom = function() {
    const formData = {
        name: $('#roomName').val().trim(),
        description: $('#roomDescription').val().trim(),
        capacity: $('#roomCapacity').val(),
        has_password: $('#hasPassword').is(':checked') ? 1 : 0,
        password: $('#hasPassword').is(':checked') ? $('#roomPassword').val() : '',
        allow_knocking: $('#allowKnocking').is(':checked') ? 1 : 0
    };
    
    if (!formData.name) {
        alert('Room name is required');
        return;
    }
    
    if (formData.has_password && !formData.password) {
        alert('Password is required when password protection is enabled');
        return;
    }
    
    $.ajax({
        url: 'api/create_room.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#createRoomModal').modal('hide');
                alert('Room created successfully!');
                window.location.href = 'room.php';
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error creating room:', error);
            alert('Error creating room: ' + error);
        }
    });
};

// Knock system (simplified)
window.knockOnRoom = function(roomId, roomName) {
    if (!confirm(`Send a knock request to "${roomName}"?`)) {
        return;
    }
    
    $.ajax({
        url: 'api/knock_room.php',
        method: 'POST',
        data: { room_id: roomId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('Knock sent! The host will be notified.');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error knocking on room:', error);
            alert('Error sending knock: ' + error);
        }
    });
};

// Knock checking (simplified)
function checkForKnocks() {
    $.ajax({
        url: 'api/check_knocks.php',
        method: 'GET',
        dataType: 'json',
        success: function(knocks) {
            if (Array.isArray(knocks) && knocks.length > 0) {
                // Simple knock notifications - could be enhanced
                knocks.forEach(knock => {
                    if ($(`#knock-${knock.id}`).length === 0) {
                        showKnockNotification(knock);
                    }
                });
            }
        },
        error: function() {
            // Silently fail
        }
    });
}

function showKnockNotification(knock) {
    // Simple knock notification - you can enhance this
    const userName = knock.username || knock.guest_name || 'Unknown User';
    const roomName = knock.room_name || 'Unknown Room';
    
    if (confirm(`${userName} wants to join ${roomName}. Accept?`)) {
        respondToKnock(knock.id, 'accepted');
    } else {
        respondToKnock(knock.id, 'denied');
    }
}

window.respondToKnock = function(knockId, response) {
    $.ajax({
        url: 'api/respond_knocks.php',
        method: 'POST',
        data: {
            knock_id: knockId,
            response: response
        },
        dataType: 'json',
        success: function(result) {
            if (result.status === 'success') {
                if (response === 'accepted') {
                    alert('Knock accepted! User can now join.');
                    loadUserRoomKeys();
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error responding to knock:', error);
        }
    });
};


// Private Message System
let openPrivateChats = new Map();
let friends = [];

function initializePrivateMessaging() {
    if (currentUser.type !== 'user') return;
    
    loadFriends();
    checkForNewPrivateMessages();
    setInterval(checkForNewPrivateMessages, 500);
}

// Add this function to lounge.js if it doesn't exist
function openPrivateMessage(userId, username) {
    console.log('Opening private message for user:', userId, username);
    
    if (openPrivateChats && openPrivateChats.has(userId)) {
        $(`#pm-${userId}`).show();
        return;
    }
    
    // Create the private message window (similar to room.js)
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
    
    // Initialize the private chat if the system exists
    if (typeof openPrivateChats !== 'undefined') {
        openPrivateChats.set(userId, { username: username, color: 'blue' });
    }
    
    // Load messages if the function exists
    if (typeof loadPrivateMessages === 'function') {
        loadPrivateMessages(userId);
    } else {
        $('#pm-body-' + userId).html('<div style="color: #f44336; padding: 10px;">Private messaging not available in lounge</div>');
    }
}

function closePrivateMessage(userId) {
    $(`#pm-${userId}`).remove();
    if (typeof openPrivateChats !== 'undefined') {
        openPrivateChats.delete(userId);
    }
}

function sendPrivateMessage(recipientId) {
    if (typeof loadPrivateMessages === 'function') {
        // Use existing PM system
        const input = $(`#pm-input-${recipientId}`);
        const message = input.val().trim();
        
        if (!message) return false;
        
        $.ajax({
            url: 'api/private_messages.php',
            method: 'POST',
            data: {
                action: 'send',
                recipient_id: recipientId,
                message: message
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    input.val('');
                    loadPrivateMessages(recipientId);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error sending message');
            }
        });
    } else {
        alert('Private messaging not available in lounge');
    }
    
    return false;
}

function closePrivateMessage(userId) {
    $(`#pm-${userId}`).remove();
    openPrivateChats.delete(userId);
}

function sendPrivateMessage(recipientId) {
    const input = $(`#pm-input-${recipientId}`);
    const message = input.val().trim();
    
    if (!message) return;
    
    $.ajax({
        url: 'api/private_messages.php',
        method: 'POST',
        data: {
            action: 'send',
            recipient_id: recipientId,
            message: message
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                input.val('');
                loadPrivateMessages(recipientId);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error sending message');
        }
    });
}

function loadPrivateMessages(otherUserId) {
    console.log('Loading private messages with user:', otherUserId);
    
    // Store other user's color for display
    if (!openPrivateChats.get(otherUserId).color) {
        $.ajax({
            url: 'api/get_user_info.php',
            method: 'GET',
            data: { user_id: otherUserId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const chatData = openPrivateChats.get(otherUserId);
                    chatData.color = response.user.color || 'blue';
                    openPrivateChats.set(otherUserId, chatData);
                }
            }
        });
    }
    
    $.ajax({
        url: 'api/private_messages.php',
        method: 'GET',
        data: {
            action: 'get',
            other_user_id: otherUserId
        },
        dataType: 'json',
        success: function(response) {
            console.log('Load messages response:', response);
            if (response.status === 'success') {
                displayPrivateMessages(otherUserId, response.messages);
            } else {
                $(`#pm-body-${otherUserId}`).html('<div style="color: #f44336; padding: 10px;">Error: ' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Load messages error:', error, xhr.responseText);
            $(`#pm-body-${otherUserId}`).html('<div style="color: #f44336; padding: 10px;">Failed to load messages</div>');
        }
    });
}

function displayPrivateMessages(otherUserId, messages) {
    const container = $(`#pm-body-${otherUserId}`);
    
    // Check if user was at bottom before update
    const wasAtBottom = container[0] ? 
        (container.scrollTop() + container.innerHeight() >= container[0].scrollHeight - 20) : true;
    
    let html = '';
    
    if (messages.length === 0) {
        html = '<div style="text-align: center; color: #999; padding: 20px;">No messages yet</div>';
    } else {
        messages.forEach(msg => {
            const isOwn = msg.sender_id == currentUser.id;
            const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            // Get user info and color from message data
            const author = isOwn ? (currentUser.username || currentUser.name) : msg.sender_username;
            const avatar = isOwn ? (currentUser.avatar || 'default_avatar.jpg') : (msg.sender_avatar || 'default_avatar.jpg');
            const userColor = isOwn ? (currentUser.color || 'blue') : (msg.sender_color || 'blue');
            const avatarHue = isOwn ? (currentUser.avatar_hue || 0) : (msg.sender_avatar_hue || 0);
const avatarSat = isOwn ? (currentUser.avatar_saturation || 100) : (msg.sender_avatar_saturation || 100);
const bubbleHue = isOwn ? (currentUser.bubble_hue || 0) : (msg.bubble_hue || 0);
const bubbleSat = isOwn ? (currentUser.bubble_saturation || 100) : (msg.bubble_saturation || 100);

            
            html += `
    <div class="private-chat-message ${isOwn ? 'sent' : 'received'}">
        <img src="images/${avatar}" 
             class="private-message-avatar" 
             style="filter: hue-rotate(${avatarHue}deg) saturate(${avatarSat}%);"
             alt="${author}'s avatar">
                    <div class="private-message-bubble ${isOwn ? 'sent' : 'received'} user-color-${userColor}" style="filter: hue-rotate(${bubbleHue}deg) saturate(${bubbleSat}%);">
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
    
    // Only scroll to bottom if user was already at bottom or it's the first load
    if (wasAtBottom) {
        container.scrollTop(container[0].scrollHeight);
    }
}

function checkForNewPrivateMessages() {
    if (currentUser.type !== 'user') return;
    
    // Update open chat windows (but don't reload if user is typing)
    openPrivateChats.forEach((data, userId) => {
        const input = $(`#pm-input-${userId}`);
        const isTyping = input.is(':focus') && input.val().length > 0;
        
        if (!isTyping) {
            loadPrivateMessages(userId);
        }
    });
    
    // Update conversations list if friends panel is open
    if ($('#friendsPanel').is(':visible')) {
        loadConversations();
    }
}

// Initialize when page loads
$(document).ready(function() {
    initializePrivateMessaging();
});

function showFriendsPanel() {
    $('#friendsPanel').show();
    loadFriends();
    loadConversations();
}

function closeFriendsPanel() {
    $('#friendsPanel').hide();
}

function updateFriendsPanel() {
    console.log('Updating friends panel with:', friends);
    
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
                        <img src="images/${friend.avatar || 'default_avatar.jpg'}" width="24" height="24" class="me-2" style="border-radius: 2px; filter: hue-rotate(${friend.avatar_hue || 0}deg) saturate(${friend.avatar_saturation || 100}%);">                        
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
    
    // Load conversations after friends are loaded
    loadConversations();
}


function addFriend() {
    const username = $('#addFriendInput').val().trim();
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
                $('#addFriendInput').val('');
                alert('Friend request sent!');
                loadFriends();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function acceptFriend(friendId) {
    $.ajax({
        url: 'api/friends.php',
        method: 'POST',
        data: {
            action: 'accept',
            friend_id: friendId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('Friend request accepted!');
                loadFriends();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function loadConversations() {
    $.ajax({
        url: 'api/private_messages.php',
        method: 'GET',
        data: { action: 'get_conversations' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayConversations(response.conversations);
            }
        }
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
                    <img src="images/${conv.avatar}" width="24" height="24" class="me-2" style="border-radius: 2px; filter: hue-rotate(${conv.avatar_hue || 0}deg) saturate(${conv.avatar_saturation || 100}%);">
                    <div class="flex-grow-1">
                        <small>${conv.username}</small>
                        <br><small class="text-muted">${conv.last_message ? conv.last_message.substring(0, 30) + '...' : 'No messages'}</small>
                    </div>
                    ${unreadBadge}
                </div>
            `;
        });
    }
    
    $('#conversationsList').html(html);
}
function loadFriends() {
    console.log('Loading friends...');
    $.ajax({
        url: 'api/friends.php',
        method: 'GET',
        data: { action: 'get' },
        dataType: 'json',
        success: function(response) {
            console.log('Friends response:', response);
            if (response.status === 'success') {
                friends = response.friends;
                updateFriendsPanel();
            } else {
                $('#friendsList').html('<p class="text-danger">Error: ' + response.message + '</p>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Friends API error:', error, xhr.responseText);
            $('#friendsList').html('<p class="text-danger">Failed to load friends. Check console for details.</p>');
        }
    });
}

function applyAvatarFilter(imgElement, hue, saturation) {
    if (hue !== undefined && saturation !== undefined) {
        const hueValue = parseInt(hue) || 0;
        const satValue = parseInt(saturation) || 100;
        const filterValue = `hue-rotate(${hueValue}deg) saturate(${satValue}%)`;
        const filterKey = `${hueValue}-${satValue}`;
        
        // Only apply if different from current
        if (imgElement.data('filter-applied') !== filterKey) {
            imgElement.css('filter', filterValue);
            imgElement.data('filter-applied', filterKey);
            imgElement.addClass('avatar-filtered');
        }
    }
}

function applyAllAvatarFilters() {
    $('.avatar-filtered, .message-avatar, .user-avatar, .private-message-avatar').each(function() {
        const $img = $(this);
        const hue = $img.data('hue');
        const sat = $img.data('saturation');
        
        // Skip if no filter data or already processed
        if (hue === undefined || sat === undefined) return;
        
        const filterKey = `${hue}-${sat}`;
        const appliedKey = $img.data('filter-applied');
        
        // Only apply if not already applied with same values
        if (appliedKey !== filterKey) {
            const filterValue = `hue-rotate(${hue}deg) saturate(${sat}%)`;
            $img.css('filter', filterValue);
            $img.data('filter-applied', filterKey);
        }
    });
}


$(window).on('beforeunload', function() {
    // Clear any remaining intervals
    if (typeof cleanupInactiveUsers !== 'undefined') {
        clearInterval(cleanupInactiveUsers);
    }
});

window.showAvatarSelector = function() {
    showProfileEditor();
};
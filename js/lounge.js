const DEBUG_MODE = false;

function debugLog(message, data = null) {
    if (DEBUG_MODE) {
        if (data !== null) {
            debugLog('[LOUNGE]', message, data);
        } else {
            debugLog('[LOUNGE]', message);
        }
    }
}

$(document).ready(function() {
    debugLog('Lounge loaded');
    
    let userRoomKeys = [];
    
    loadUserRoomKeys();
    loadRoomsWithUsers();
    loadOnlineUsers();
    
    setInterval(() => {
        loadOnlineUsers();
        loadRoomsWithUsers();
        loadUserRoomKeys();
        sendHeartbeat();
    }, 5000); // Every 5 seconds
    
    setInterval(checkForKnocks, 3000); // Every 3 seconds
    
    setInterval(cleanupInactiveUsers, 60000); // Every minute
    
    setTimeout(initializePrivateMessaging, 1000);
});

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

function hasRoomKey(roomId) {
    return userRoomKeys.includes(parseInt(roomId));
}

function loadRoomsWithUsers() {
    debugLog('Loading rooms with users...');
    
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
            
            let completedRooms = 0;
            let roomsWithUsers = [];
            
            rooms.forEach((room, index) => {
                loadUsersForRoom(room, (roomWithUsers) => {
                    roomsWithUsers[index] = roomWithUsers;
                    completedRooms++;
                    
                    debugLog(`Room ${room.id} users loaded:`, roomWithUsers);
                    
                    if (completedRooms === rooms.length) {
                        const validRooms = roomsWithUsers.filter(r => r !== undefined);
                        debugLog('All rooms processed, displaying:', validRooms);
                        displayRoomsWithUsers(validRooms);
                        
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
                let parsedUsers = users;
                if (typeof users === 'string') {
                    parsedUsers = JSON.parse(users);
                }
                
                if (!Array.isArray(parsedUsers)) {
                    parsedUsers = [];
                }
                
                const host = parsedUsers.find(user => parseInt(user.is_host) === 1) || null;
                const regularUsers = parsedUsers.filter(user => parseInt(user.is_host) !== 1);
                
                debugLog(`Room ${room.id} processed:`, {
                    total: parsedUsers.length,
                    host: host ? (host.display_name || host.username || host.guest_name) : 'None',
                    regularUsers: regularUsers.length
                });
                
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
        rooms.sort((a, b) => {
            const aIsPermanent = Boolean(a.permanent);  // FIXED: Use Boolean() instead of parseInt()
            const bIsPermanent = Boolean(b.permanent);  // FIXED: Use Boolean() instead of parseInt()
            
            if (aIsPermanent && !bIsPermanent) return -1;
            if (!aIsPermanent && bIsPermanent) return 1;
            
            const aUserCount = a.user_count || 0;
            const bUserCount = b.user_count || 0;
            return bUserCount - aUserCount;
        });
        
        html += '<div class="row">';
        
        rooms.forEach((room, index) => {
            try {
                const isPermanent = Boolean(room.permanent);
                const isPasswordProtected = Boolean(room.has_password);
                const allowsKnocking = Boolean(room.allow_knocking);
                const isRP = Boolean(room.is_rp);
                const youtubeEnabled = Boolean(room.youtube_enabled);
                const friendsOnly = Boolean(room.friends_only);
                const inviteOnly = Boolean(room.invite_only);
                const membersOnly = Boolean(room.members_only);
                const disappearingMessages = Boolean(room.disappearing_messages);
                
                const userCount = room.user_count || 0;
                const capacity = room.capacity || 10;
                const hasKey = hasRoomKey(room.id);
                const host = room.host;
                const regularUsers = room.regularUsers || [];
                const canAccessFriendsOnly = room.can_access_friends_only !== false;

                debugLog(`🔍 Room "${room.name}": permanent=${isPermanent}, rp=${isRP}, youtube=${youtubeEnabled}, friends=${friendsOnly}`);

                let headerClass = 'room-header-enhanced';
                let actionButtons = '';
                let cardClass = 'room-card-enhanced';

                if (isPermanent) {
                 //   headerClass += ' permanent-room';
                    cardClass += ' permanent-room-card';
                }

                // Access checking logic
                if (inviteOnly) {
                    headerClass += ' access-denied';
                    actionButtons = `<button class="btn btn-danger btn-sm" onclick="alert('This room requires a valid invite code')"><i class="fas fa-ban"></i> Invite Required</button>`;
                } else if (membersOnly && currentUser.type !== 'user') {
                    headerClass += ' access-denied';
                    actionButtons = `<button class="btn btn-danger btn-sm" onclick="alert('This room is for registered members only')"><i class="fas fa-ban"></i> Members Only</button>`;
                } else if (friendsOnly && !canAccessFriendsOnly) {
                    headerClass += ' access-denied';
                    actionButtons = `<button class="btn btn-danger btn-sm" onclick="alert('This room is for friends of the host only')"><i class="fas fa-ban"></i> Friends Only</button>`;
                } else if (isPasswordProtected && hasKey) {
                    headerClass += ' has-access';
                    actionButtons = `<button class="btn btn-success btn-sm" onclick="joinRoom(${room.id})"><i class="fas fa-key"></i> Enter Room</button>`;
                } else if (isPasswordProtected) {
                    headerClass += allowsKnocking ? ' knock-available' : ' password-protected';
                    actionButtons = `<button class="btn btn-warning btn-sm" onclick="showPasswordModal(${room.id}, '${room.name.replace(/'/g, "\\'")}')"><i class="fas fa-key"></i> Enter Room</button>`;
                    if (allowsKnocking) {
                        actionButtons += `<button class="btn btn-outline-primary btn-sm" onclick="knockOnRoom(${room.id}, '${room.name.replace(/'/g, "\\'")}')"><i class="fas fa-hand-paper"></i> Knock</button>`;
                    }
                } else {
                    actionButtons = `<button class="btn btn-success btn-sm" onclick="joinRoom(${room.id})"><i class="fas fa-sign-in-alt"></i> Enter Room</button>`;
                }

                let featureIndicators = '';
                
                if (isPermanent) {
                   // featureIndicators += '<span class="room-indicator permanent-indicator" title="Permanent Room - Never deleted automatically"><i class="fas fa-star"></i> PERMANENT</span>';
                   // debugLog(`✅ Added permanent indicator for: ${room.name}`);
                }
                
                if (isRP) {
                    featureIndicators += '<span class="room-indicator rp-indicator" style="background: #e91e63; color: white;" title="Roleplay Room"><i class="fas fa-theater-masks"></i> RP</span>';
                    debugLog(`✅ Added RP indicator for: ${room.name}`);
                }
                
                if (youtubeEnabled) {
                    featureIndicators += '<span class="room-indicator youtube-indicator" style="background: #f44336; color: white;" title="YouTube Player Enabled"><i class="fab fa-youtube"></i> VIDEO</span>';
                    debugLog(`✅ Added YouTube indicator for: ${room.name}`);
                }
                
                if (friendsOnly) {
                    featureIndicators += '<span class="room-indicator friends-indicator" style="background: #2196f3; color: white;" title="Friends Only"><i class="fas fa-user-friends"></i> FRIENDS</span>';
                    debugLog(`✅ Added Friends Only indicator for: ${room.name}`);
                }
                
                if (inviteOnly) {
                    featureIndicators += '<span class="room-indicator invite-indicator" style="background: #ff9800; color: white;" title="Invite Only"><i class="fas fa-link"></i> INVITE</span>';
                    debugLog(`✅ Added Invite Only indicator for: ${room.name}`);
                }
                
                if (membersOnly) {
                    featureIndicators += '<span class="room-indicator members-indicator" style="background: #4caf50; color: white;" title="Members Only"><i class="fas fa-user-check"></i> MEMBERS</span>';
                    debugLog(`✅ Added Members Only indicator for: ${room.name}`);
                }
                
                if (disappearingMessages) {
                    const lifetime = room.message_lifetime_minutes || 30;
                    featureIndicators += `<span class="room-indicator disappearing-indicator" style="background: #9c27b0; color: white;" title="Disappearing Messages (${lifetime} minutes)"><i class="fas fa-clock"></i> TEMP</span>`;
                    debugLog(`✅ Added Disappearing Messages indicator for: ${room.name}`);
                }

                let themeClass = (room.theme && room.theme !== 'default') ? `theme-${room.theme}` : '';

                // Build host section
                let hostHtml = '';
                if (host) {
                    const hostAvatar = host.avatar || host.user_avatar || host.guest_avatar || 'default_avatar.jpg';
                    const hostName = host.display_name || host.username || host.guest_name || 'Unknown Host';
                    const hostHue = host.avatar_hue || host.user_avatar_hue || 0;
                    const hostSaturation = host.avatar_saturation || host.user_avatar_saturation || 100;
                    
                    hostHtml = `
                        <div class="room-host">
                            <h6><i class="fas fa-crown"></i> Host</h6>
                            <div class="d-flex align-items-center">
                                <img src="images/${hostAvatar}" width="32" height="32" class="me-2" style="filter: hue-rotate(${hostHue}deg) saturate(${hostSaturation}%);" alt="${hostName}">
                                <div>
                                    <div class="fw-bold">${hostName}</div>
                                    <div class="user-badges">
                                        ${parseInt(host.is_admin) === 1 ? '<span class="badge bg-danger badge-sm">Admin</span>' : ''}
                                        ${host.user_type === 'registered' || host.user_id ? '<span class="badge bg-success badge-sm">Verified</span>' : '<span class="badge bg-secondary badge-sm">Guest</span>'}
                                        ${isPermanent ? '<span class="badge bg-warning badge-sm">Offline Host</span>' : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (isPermanent) {
                    hostHtml = `
                        <div class="room-host">
                            <h6><i class="fas fa-crown"></i> Host</h6>
                            <div class="d-flex align-items-center text-warning">
                                <i class="fas fa-user-slash fa-2x me-3"></i>
                                <div>
                                    <div class="fw-bold">Host is offline</div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    hostHtml = `<div class="room-host"><h6><i class="fas fa-crown"></i> Host</h6><div class="text-muted">No host available</div></div>`;
                }

                // Build users list
                let usersHtml = '';
                if (regularUsers.length > 0) {
                    usersHtml = `<div class="room-users"><h6><i class="fas fa-users"></i> Users (${regularUsers.length})</h6><div class="users-grid-two-column">`;
                    
                    regularUsers.slice(0, 8).forEach(user => {
                        const userAvatar = user.avatar || user.user_avatar || user.guest_avatar || 'default_avatar.jpg';
                        const userName = user.display_name || user.username || user.guest_name || 'Unknown';
                        const userHue = user.avatar_hue || user.user_avatar_hue || 0;
                        const userSaturation = user.avatar_saturation || user.user_avatar_saturation || 100;
                        
                        usersHtml += `
                            <div class="user-item-mini d-flex align-items-center">
                                <img src="images/${userAvatar}" width="24" height="24" class="me-2" style="filter: hue-rotate(${userHue}deg) saturate(${userSaturation}%);" alt="${userName}">
                                <div class="user-info">
                                    <div class="user-name">${userName}</div>
                                    <div class="user-badges">
                                        ${parseInt(user.is_admin) === 1 ? '<span class="badge bg-danger badge-sm">Admin</span>' : ''}
                                        ${user.user_type === 'registered' || user.user_id ? '<span class="badge bg-success badge-sm">Verified</span>' : '<span class="badge bg-secondary badge-sm">Guest</span>'}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    if (regularUsers.length > 8) {
                        usersHtml += `<div class="text-muted small users-more-indicator">+ ${regularUsers.length - 8} more users</div>`;
                    }
                    
                    usersHtml += `</div></div>`;
                } else {
                    usersHtml = `<div class="room-users"><h6><i class="fas fa-users"></i> Users (0)</h6><div class="text-muted small">No other users in room</div></div>`;
                }

                html += `
                    <div class="col-lg-6 col-12 room-card-wrapper">
                        <div class="${cardClass} ${themeClass}">
                            <div class="${headerClass}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="room-title-section">
                                        <h5 class="room-title">
                                            ${isPermanent ? '<i class="fas fa-star permanent-star" title="Permanent Room"></i>' : ''}
                                            ${room.name}
                                            ${isPasswordProtected ? '<i class="fas fa-lock" title="Password protected"></i>' : ''}
                                            ${hasKey ? '<i class="fas fa-key" title="You have access"></i>' : ''}
                                        </h5>
                                        <div class="room-meta">
                                            <span class="capacity-info"><i class="fas fa-users"></i> ${userCount}/${capacity}</span>
                                            ${room.theme && room.theme !== 'default' ? `<span class="theme-info"><i class="fas fa-palette"></i> ${room.theme}</span>` : ''}
                                            ${isPermanent ? '<span class="permanent-info"><i class="fas fa-star"></i> Permanent</span>' : ''}
                                        </div>
                                        
                                    </div>
                                    <div class="action-buttons">${actionButtons}</div>
                                </div>
                                ${featureIndicators ? `<div class="room-features mt-2">${featureIndicators}</div>` : ''}
                                ${hasKey ? '<div class="mt-2"><span class="badge bg-success"><i class="fas fa-key"></i> Access Granted</span></div>' : ''}
                            </div>
                            <div class="room-content">
                                <div class="room-description"><p>${room.description || 'No description'}</p></div>
                                <div class="row">
                                    <div class="col-12">${usersHtml}</div>
                                    <div class="col-12 mt-3">${hostHtml}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
            } catch (error) {
                console.error('Error rendering room:', room, error);
                html += `
                    <div class="col-lg-6 col-12 room-card-wrapper">
                        <div class="room-card-enhanced">
                            <div class="room-header-enhanced">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="room-title-section">
                                        <h5 class="room-title">${room.name || 'Unknown Room'}</h5>
                                        <div class="room-meta">
                                            <span class="text-danger">Error loading room details</span>
                                        </div>
                                    </div>
                                    <div class="action-buttons">
                                        <button class="btn btn-success btn-sm" onclick="joinRoom(${room.id})">
                                            <i class="fas fa-sign-in-alt"></i> Join Room
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
        
        html += '</div>';
    }
    
    const $roomsList = $('#roomsList');
    if ($roomsList.children().length > 0 && !$roomsList.hasClass('updating')) {
        $roomsList.addClass('fade-transition');
        setTimeout(() => {
            $roomsList.html(html);
            $roomsList.removeClass('fade-transition');
        }, 150);
    } else {
        $roomsList.html(html);
    }
    
    const permanentCount = rooms.filter(r => Boolean(r.permanent)).length;
    const rpCount = rooms.filter(r => Boolean(r.is_rp)).length;
    const youtubeCount = rooms.filter(r => Boolean(r.youtube_enabled)).length;
    const friendsCount = rooms.filter(r => Boolean(r.friends_only)).length;
    
}


window.joinRoom = function(roomId) {
    debugLog('joinRoom: Attempting to join room', roomId);
    
    const button = $(`button[onclick="joinRoom(${roomId})"]`);
    const originalText = button.html();
    
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Joining...');
    
    $.ajax({
        url: 'api/join_room.php',
        method: 'POST',
        data: { room_id: roomId },
        dataType: 'json',
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if (response.status === 'success') {
                if (response.used_room_key) {
                    loadUserRoomKeys();
                }
                window.location.href = 'room.php';
            } else {
                if (response.message && response.message.toLowerCase().includes('password')) {
                    showPasswordModal(roomId, 'Room ' + roomId);
                } else if (response.message && (
                    response.message.includes('friends only') || 
                    response.message.includes('members only') || 
                    response.message.includes('invite')
                )) {
                    alert('Access Denied: ' + response.message);
                } else {
                    alert('Error: ' + response.message);
                }
                
                button.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            console.error('joinRoom error:', error);
            
            button.prop('disabled', false).html(originalText);
            
            if (status === 'timeout') {
                alert('Request timed out. Please try again.');
            } else {
                alert('Error joining room: ' + error);
            }
        }
    });
};

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
            if (status !== 'timeout') {
                debugLog('Failed to load online users, keeping current list');
            }
        }
    });
}

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
            
            const isRegisteredUser = user.username && user.username.trim() !== '';
            const isCurrentUser = user.user_id_string === currentUser.user_id;

            let avatarClickHandler = '';
            if (isRegisteredUser) {
                avatarClickHandler = `onclick="handleAvatarClick(event, '${user.user_id_string}', '${user.username.replace(/'/g, "\\'")}')" style="cursor: pointer;"`;
            } else if (isCurrentUser) {
                avatarClickHandler = `onclick="showProfileEditor()" style="cursor: pointer;"`;
            }
            
            let activityIndicator = '';
            if (lastActivity) {
                const now = new Date();
                const lastActiveTime = new Date(lastActivity.replace(' ', 'T'));
                const diffMinutes = Math.floor((now - lastActiveTime) / (1000 * 60));
                
                /*if (diffMinutes < 15) {
                    activityIndicator = '<span class="badge bg-success badge-sm">Online</span>';
                } else if (diffMinutes < 30) {
                    activityIndicator = `<span class="badge bg-warning badge-sm">${diffMinutes}m ago</span>`;
                } else {
                    activityIndicator = '<span class="badge bg-secondary badge-sm">Away</span>';
                }*/
            }

            let badges = '';
            if (user.is_admin) {
                badges += '<span class="badge bg-danger badge-sm">Admin</span> ';
            }
            if (user.is_moderator) {
                badges += '<span class="badge bg-warning badge-sm">Mod</span> ';
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
                            ${badges}
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
    
    debugLog('Lounge avatar clicked - userIdString:', userIdString, 'username:', username);
    
    if (username && username.trim() !== '') {
        getUserIdFromUsername(username, function(userId) {
            if (userId) {
                if (userId == currentUser.id) {
                    showUserProfile(userId, event.target);
                } else {
                    showUserProfile(userId, event.target);
                }
            }
        });
    }
}

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

window.showCreateRoomModal = function() {
    $('#createRoomModal').remove();

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
                        <!-- Nav Tabs -->
                        <ul class="nav nav-tabs mb-3" id="createRoomTabs" role="tablist" style="border-bottom: 1px solid #444;">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab" style="color: #fff; background: transparent; border: none;">Basic Settings</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="access-tab" data-bs-toggle="tab" data-bs-target="#access" type="button" role="tab" style="color: #fff; background: transparent; border: none;">Access Control</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab" style="color: #fff; background: transparent; border: none;">Features</button>
                            </li>
                            ${(currentUser.is_admin || currentUser.is_moderator) ? `
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab" style="color: #fff; background: transparent; border: none;">
                                    <i class="fas fa-shield-alt"></i> Admin
                                </button>
                            </li>
                            ` : ''}
                        </ul>

                        <div class="tab-content" id="createRoomTabsContent">
                            <!-- Basic Settings Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
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
                                        <div class="mb-3">
                                            <label for="roomTheme" class="form-label">Theme</label>
                                            <select class="form-select" id="roomTheme" 
                                                    style="background: #333; border: 1px solid #555; color: #fff;">
                                                <option value="default">Default</option>
                                                <option value="cyberpunk">Cyberpunk</option>
                                                <option value="forest">Forest</option>
                                                <option value="ocean">Ocean</option>
                                                <option value="sunset">Sunset</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="roomDescription" class="form-label">Description</label>
                                            <textarea class="form-control" id="roomDescription" rows="4" maxlength="200" 
                                                      style="background: #333; border: 1px solid #555; color: #fff;"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="isRP">
                                                <label class="form-check-label" for="isRP">
                                                    <i class="fas fa-theater-masks"></i> Roleplay Room
                                                </label>
                                            </div>
                                            <small class="text-muted">Mark this room as suitable for roleplay</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Access Control Tab -->
                            <div class="tab-pane fade" id="access" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
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
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="membersOnly">
                                                <label class="form-check-label" for="membersOnly">
                                                    <i class="fas fa-user-check"></i> Members Only
                                                </label>
                                            </div>
                                            <small class="text-muted">Only registered users can join</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        ${currentUser.type === 'user' ? `
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="friendsOnly">
                                                <label class="form-check-label" for="friendsOnly">
                                                    <i class="fas fa-user-friends"></i> Friends Only
                                                </label>
                                            </div>
                                            <small class="text-muted">Only your friends can join</small>
                                        </div>
                                        ` : ''}
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="inviteOnly">
                                                <label class="form-check-label" for="inviteOnly">
                                                    <i class="fas fa-link"></i> Invite Only
                                                </label>
                                            </div>
                                            <small class="text-muted">Generate a special invite link</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Features Tab -->
                            <div class="tab-pane fade" id="features" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-4">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="youtubeEnabled">
                                                <label class="form-check-label" for="youtubeEnabled">
                                                    <i class="fab fa-youtube text-danger"></i> <strong>Enable YouTube Player</strong> <span class="betatext" /> <span class="betatext2" />
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">Allow synchronized video playback for all users</small>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="disappearingMessages">
                                                <label class="form-check-label" for="disappearingMessages">
                                                    <i class="fas fa-clock"></i> <strong>Disappearing Messages</strong>
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">Messages automatically delete after a set time</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3" id="messageLifetimeField" style="display: none;">
                                            <label for="messageLifetime" class="form-label">Message Lifetime (minutes)</label>
                                            <select class="form-select" id="messageLifetime" 
                                                    style="background: #333; border: 1px solid #555; color: #fff;">
                                                <option value="5">5 minutes</option>
                                                <option value="15">15 minutes</option>
                                                <option value="30" selected>30 minutes</option>
                                                <option value="60">1 hour</option>
                                                <option value="120">2 hours</option>
                                                <option value="360">6 hours</option>
                                                <option value="720">12 hours</option>
                                                <option value="1440">24 hours</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Admin Tab (only for moderators/admins) -->
                            ${(currentUser.is_admin || currentUser.is_moderator) ? `
                            <div class="tab-pane fade" id="admin" role="tabpanel">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="alert alert-warning" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); color: #ffc107;">
                                            <i class="fas fa-shield-alt"></i> <strong>Administrator Settings</strong><br>
                                            These options are only available to moderators and administrators.
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="permanentRoom">
                                                <label class="form-check-label" for="permanentRoom">
                                                    <i class="fas fa-star text-warning"></i> <strong>Permanent Room</strong>
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">
                                                This room will never be automatically deleted, even when empty. 
                                                It will be displayed at the top of the room list with a special indicator.
                                            </small>
                                            <div class="mt-2">
                                                <small class="text-info">
                                                    <i class="fas fa-info-circle"></i> 
                                                    When the host of a permanent room leaves, they retain host privileges even while offline.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                        </div>
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
    
    $('#disappearingMessages').on('change', function() {
        if (this.checked) {
            $('#messageLifetimeField').show();
        } else {
            $('#messageLifetimeField').hide();
        }
    });
    
    $('#createRoomModal input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            createRoom();
        }
    });
    
    $('#createRoomModal').modal('show');
};

window.createRoom = function() {
    debugLog('Creating room with new features...');
    
    const createButton = $('#createRoomModal .btn-primary');
    if (createButton.prop('disabled')) {
        debugLog('Create button already disabled, preventing duplicate submission');
        return;
    }
    
    const formData = {
        name: $('#roomName').val().trim(),
        description: $('#roomDescription').val().trim(),
        capacity: $('#roomCapacity').val(),
        theme: $('#roomTheme').val(),
        has_password: $('#hasPassword').is(':checked') ? 1 : 0,
        password: $('#hasPassword').is(':checked') ? $('#roomPassword').val() : '',
        allow_knocking: $('#allowKnocking').is(':checked') ? 1 : 0,
        is_rp: $('#isRP').is(':checked') ? 1 : 0,
        youtube_enabled: $('#youtubeEnabled').is(':checked') ? 1 : 0,
        friends_only: $('#friendsOnly').is(':checked') ? 1 : 0,
        invite_only: $('#inviteOnly').is(':checked') ? 1 : 0,
        members_only: $('#membersOnly').is(':checked') ? 1 : 0,
        disappearing_messages: $('#disappearingMessages').is(':checked') ? 1 : 0,
        message_lifetime_minutes: $('#disappearingMessages').is(':checked') ? $('#messageLifetime').val() : 0,
        permanent: $('#permanentRoom').is(':checked') ? 1 : 0  // NEW: Add permanent setting
    };
    
    debugLog('Form data being sent:', formData);
    
    if (!formData.name) {
        alert('Room name is required');
        return;
    }
    
    if (formData.has_password && !formData.password) {
        alert('Password is required when password protection is enabled');
        return;
    }
    
    const originalText = createButton.html();
    createButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');
    
    const cancelButton = $('#createRoomModal .btn-secondary');
    cancelButton.prop('disabled', true);
    
    $.ajax({
        url: 'api/create_room.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 15000, // 15 second timeout
        success: function(response) {
            debugLog('Create room response:', response);
            
            if (response.status === 'success') {
                $('#createRoomModal').modal('hide');
                
                let message = 'Room created successfully!';
                if (formData.permanent) {
                    message += ' This is now a permanent room.';
                }
                if (response.invite_code) {
                    const inviteLink = window.location.origin + '/' + response.invite_link;
                    message += '\\n\\nInvite link: ' + inviteLink;
                    
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(inviteLink).then(() => {
                            message += '\\n\\n(Invite link copied to clipboard!)';
                            alert(message);
                        }).catch(() => {
                            alert(message);
                        });
                    } else {
                        alert(message);
                    }
                } else {
                    alert(message);
                }
                
                setTimeout(() => {
                    window.location.href = 'room.php';
                }, 0);
            } else {
                alert('Error: ' + response.message);
                createButton.prop('disabled', false).html(originalText);
                cancelButton.prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error creating room:', error);
            console.error('Response text:', xhr.responseText);
            alert('Error creating room: ' + error);
            
            createButton.prop('disabled', false).html(originalText);
            cancelButton.prop('disabled', false);
        }
    });
};

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

function checkForKnocks() {
    $.ajax({
        url: 'api/check_knocks.php',
        method: 'GET',
        dataType: 'json',
        success: function(knocks) {
            if (Array.isArray(knocks) && knocks.length > 0) {
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


let openPrivateChats = new Map();
let friends = [];

function initializePrivateMessaging() {
    if (currentUser.type !== 'user') return;
    
    loadFriends();
    checkForNewPrivateMessages();
    setInterval(checkForNewPrivateMessages, 500);
}

function openPrivateMessage(userId, username) {
    debugLog('Opening private message for user:', userId, username);
    
    if (openPrivateChats && openPrivateChats.has(userId)) {
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
    
    if (typeof openPrivateChats !== 'undefined') {
        openPrivateChats.set(userId, { username: username, color: 'blue' });
    }
    
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
    debugLog('Loading private messages with user:', otherUserId);
    
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
            debugLog('Load messages response:', response);
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
    
    if (wasAtBottom) {
        container.scrollTop(container[0].scrollHeight);
    }
}

function checkForNewPrivateMessages() {
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
}

$(document).ready(function() {
     setInterval(() => {
        $('.room-card-enhanced .fa-spinner').each(function() {
            debugLog('PERSISTENT SPINNER DETECTED:', this.closest('.room-card-enhanced'));
            debugLog('Parent button:', this.closest('button'));
        });
    }, 1000);
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
    debugLog('Loading friends...');
    $.ajax({
        url: 'api/friends.php',
        method: 'GET',
        data: { action: 'get' },
        dataType: 'json',
        success: function(response) {
            debugLog('Friends response:', response);
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
        
        if (hue === undefined || sat === undefined) return;
        
        const filterKey = `${hue}-${sat}`;
        const appliedKey = $img.data('filter-applied');
        
        if (appliedKey !== filterKey) {
            const filterValue = `hue-rotate(${hue}deg) saturate(${sat}%)`;
            $img.css('filter', filterValue);
            $img.data('filter-applied', filterKey);
        }
    });
}


$(window).on('beforeunload', function() {
    if (typeof cleanupInactiveUsers !== 'undefined') {
        clearInterval(cleanupInactiveUsers);
    }
});

window.showAvatarSelector = function() {
    showProfileEditor();
};

function showAnnouncementModal() {
    const modalHtml = `
        <div class="modal fade" id="announcementModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-bullhorn"></i> Send Site Announcement
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="announcementMessage" class="form-label">Announcement Message</label>
                            <textarea class="form-control" id="announcementMessage" rows="4" maxlength="500" placeholder="Enter your announcement message..." style="background: #333; border: 1px solid #555; color: #fff;"></textarea>
                            <div class="form-text text-muted">Maximum 500 characters. This will be sent to all active rooms.</div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" onclick="sendAnnouncement()">
                            <i class="fas fa-bullhorn"></i> Send Announcement
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#announcementModal').remove();
    $('body').append(modalHtml);
    $('#announcementModal').modal('show');
}

function sendAnnouncement() {
    const message = $('#announcementMessage').val().trim();
    
    if (!message) {
        alert('Please enter an announcement message');
        return;
    }
    
    const button = $('#announcementModal .btn-warning');
    const originalText = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
    
    $.ajax({
        url: 'api/send_announcement.php',
        method: 'POST',
        data: { message: message },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('Announcement sent successfully to all rooms!');
                $('#announcementModal').modal('hide');
                if (typeof loadMessages === 'function') {
                    setTimeout(loadMessages, 1000);
                }
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Failed to send announcement: ' + error);
        },
        complete: function() {
            button.prop('disabled', false).html(originalText);
        }
    });
}
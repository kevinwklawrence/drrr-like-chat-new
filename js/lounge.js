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
    
    // Set up auto-refresh
    setInterval(loadRoomsWithUsers, 5000);
    setInterval(loadOnlineUsers, 10000);
    setInterval(checkForKnocks, 3000);
    setInterval(loadUserRoomKeys, 30000);
});

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

// Main function to load rooms with their users
function loadRoomsWithUsers() {
    debugLog('Loading rooms with users...');
    $.ajax({
        url: 'api/get_rooms.php',
        method: 'GET',
        dataType: 'json',
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
                    }
                });
            });
            
        },
        error: function(xhr, status, error) {
            console.error('Error loading rooms:', error);
            $('#roomsList').html(`
                <div class="alert alert-danger" style="background: #2a2a2a; border: 1px solid #d32f2f; color: #f44336;">
                    <h5>Error loading rooms</h5>
                    <p>Error: ${error}</p>
                </div>
            `);
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

// Function to display rooms with users
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
        rooms.forEach(room => {
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
                
                debugLog(`Building host HTML for ${hostName}:`, host);
                
                hostHtml = `
                    <div class="room-host">
                        <h6><i class="fas fa-crown"></i> Host</h6>
                        <div class="d-flex align-items-center">
                            <img src="images/${hostAvatar}" width="32" height="32" class="me-2" alt="${hostName}">
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
            
            // Build users list
            let usersHtml = '';
            if (regularUsers.length > 0) {
                debugLog(`Building users HTML for ${regularUsers.length} users:`, regularUsers);
                
                usersHtml = `
                    <div class="room-users">
                        <h6><i class="fas fa-users"></i> Users (${regularUsers.length})</h6>
                        <div class="users-grid">
                `;
                
                // Show first 8 users
                regularUsers.slice(0, 8).forEach(user => {
                    const userAvatar = user.avatar || user.user_avatar || user.guest_avatar || 'default_avatar.jpg';
                    const userName = user.display_name || user.username || user.guest_name || 'Unknown';
                    
                    usersHtml += `
                        <div class="user-item-mini d-flex align-items-center">
                            <img src="images/${userAvatar}" width="24" height="24" class="me-2" alt="${userName}">
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
                    usersHtml += `<div class="text-muted small">+ ${regularUsers.length - 8} more users</div>`;
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
            
            html += `
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
                                    <span class="created-info">Created: ${new Date(room.created_at).toLocaleDateString()}</span>
                                </div>
                                ${hasKey ? '<div class="mt-1"><span class="badge bg-success"><i class="fas fa-key"></i> Access Granted</span></div>' : ''}
                            </div>
                            <div class="action-buttons">
                                ${actionButtons}
                            </div>
                        </div>
                    </div>
                    <div class="room-content">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="room-description">
                                    <p>${room.description || 'No description'}</p>
                                </div>
                                ${usersHtml}
                            </div>
                            <div class="col-md-4">
                                ${hostHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    debugLog('Setting rooms HTML...');
    $('#roomsList').html(html);
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

// Function to load online users
function loadOnlineUsers() {
    $.ajax({
        url: 'api/get_online_users.php',
        method: 'GET',
        dataType: 'json',
        success: function(users) {
            displayOnlineUsers(users);
        },
        error: function(xhr, status, error) {
            console.error('Error loading online users:', error);
        }
    });
}

// Function to display online users
function displayOnlineUsers(users) {
    let html = '';
    
    if (!Array.isArray(users) || users.length === 0) {
        html = '<p style="color: #666;">No users online</p>';
    } else {
        users.forEach(user => {
            const name = user.username || user.guest_name || 'Unknown';
            const avatar = user.avatar || user.guest_avatar || 'default_avatar.jpg';
            
            html += `
                <div class="d-flex align-items-center mb-2">
                    <img src="images/${avatar}" width="30" height="30" class="me-2" alt="${name}" style="border-radius: 4px;">
                    <div>
                        <small class="fw-bold" style="color: #fff;">${name}</small>
                        ${user.is_admin ? '<br><span class="badge bg-danger badge-sm">Admin</span>' : ''}
                    </div>
                </div>
            `;
        });
    }
    
    $('#onlineUsersList').html(html);
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

// Avatar selector
window.showAvatarSelector = function() {
    $.ajax({
        url: 'api/get_avatars.php',
        method: 'GET',
        dataType: 'json',
        success: function(avatars) {
            showAvatarModal(avatars);
        },
        error: function(xhr, status, error) {
            console.error('Error loading avatars:', error);
            alert('Error loading avatars: ' + error);
        }
    });
};

function showAvatarModal(avatars) {
    let avatarHtml = '';
    
    if (Array.isArray(avatars)) {
        avatars.forEach(avatar => {
            avatarHtml += `
                <div class="col-2 text-center mb-3">
                    <img src="images/${avatar}" 
                         width="60" height="60" 
                         class="avatar-option" 
                         onclick="selectAvatar('${avatar}')"
                         style="cursor: pointer; border: 2px solid transparent; border-radius: 4px;"
                         onmouseover="this.style.border='2px solid #007bff'"
                         onmouseout="this.style.border='2px solid transparent'"
                         alt="Avatar option">
                </div>
            `;
        });
    }
    
    const modalHtml = `
        <div class="modal fade" id="avatarModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-user-circle"></i> Choose Your Avatar
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            ${avatarHtml}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#avatarModal').remove();
    $('body').append(modalHtml);
    $('#avatarModal').modal('show');
}

window.selectAvatar = function(avatar) {
    $.ajax({
        url: 'api/update_avatar.php',
        method: 'POST',
        data: { avatar: avatar },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#currentAvatar').attr('src', 'images/' + avatar);
                $('#avatarModal').modal('hide');
                currentUser.avatar = avatar;
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error updating avatar:', error);
            alert('Error updating avatar: ' + error);
        }
    });
};
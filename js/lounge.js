$(document).ready(function() {
    console.log('Lounge loaded');
    
    // Load initial data
    loadRooms();
    loadOnlineUsers();
    
    // Set up auto-refresh
    setInterval(loadRooms, 5000);
    setInterval(loadOnlineUsers, 10000);
    setInterval(checkForKnocks, 3000);
});

// Function to load rooms
function loadRooms() {
    console.log('Loading rooms...');
    $.ajax({
        url: 'api/get_rooms.php',
        method: 'GET',
        dataType: 'json',
        success: function(rooms) {
            console.log('Rooms loaded successfully:', rooms);
            console.log('Number of rooms:', rooms.length);
            displayRooms(rooms);
        },
        error: function(xhr, status, error) {
            console.error('Error loading rooms:');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response:', xhr.responseText);
            
            $('#roomsList').html(`
                <div class="alert alert-danger">
                    <h5>Error loading rooms</h5>
                    <p>Status: ${status}</p>
                    <p>Error: ${error}</p>
                    <p>Response: ${xhr.responseText}</p>
                </div>
            `);
        }
    });
}

// REPLACE your existing displayRooms function with this one:

function displayRooms(rooms) {
    console.log('displayRooms called with:', rooms); // Debug line
    let html = '';
    
    if (!Array.isArray(rooms) || rooms.length === 0) {
        html = `
            <div class="text-center py-5">
                <i class="fas fa-door-closed fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No rooms available</h4>
                <p class="text-muted">Be the first to create a room!</p>
            </div>
        `;
    } else {
        rooms.forEach(room => {
            console.log('Processing room:', room); // Debug line
            
            const isPasswordProtected = room.has_password == 1;
            const allowsKnocking = room.allow_knocking == 1;
            const userCount = room.user_count || 0;
            const capacity = room.capacity || 10;
            
            console.log('Room password status:', isPasswordProtected, 'Knocking allowed:', allowsKnocking); // Debug
            
            let headerClass = 'room-header';
            if (isPasswordProtected && allowsKnocking) {
                headerClass += ' knock-available';
            } else if (isPasswordProtected) {
                headerClass += ' password-protected';
            }
            
            let actionButtons = '';
            if (isPasswordProtected) {
                console.log('Room has password, creating password button for room:', room.id); // Debug
                actionButtons = `
                    <button class="btn btn-primary btn-sm me-2" onclick="console.log('Password button clicked for room ${room.id}'); showPasswordModal(${room.id}, '${room.name.replace(/'/g, "\\'")}');">
                        <i class="fas fa-key"></i> Enter Password
                    </button>
                `;
                // Add knock button if knocking is allowed
                if (allowsKnocking) {
                    actionButtons += `
                        <button class="btn btn-outline-primary btn-sm" onclick="console.log('Knock button clicked for room ${room.id}'); knockOnRoom(${room.id}, '${room.name.replace(/'/g, "\\'")}');">
                            <i class="fas fa-hand-paper"></i> Knock
                        </button>
                    `;
                }
            } else {
                actionButtons = `
                    <button class="btn btn-success btn-sm" onclick="console.log('Join button clicked for room ${room.id}'); joinRoom(${room.id});">
                        <i class="fas fa-sign-in-alt"></i> Join Room
                    </button>
                `;
            }
            
            html += `
                <div class="room-card">
                    <div class="${headerClass}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">
                                    ${room.name}
                                    ${isPasswordProtected ? '<i class="fas fa-lock ms-2" title="Password protected"></i>' : ''}
                                    ${allowsKnocking ? '<i class="fas fa-hand-paper ms-1" title="Knocking allowed"></i>' : ''}
                                </h5>
                                <small class="opacity-75">${userCount}/${capacity} users</small>
                            </div>
                            <div class="text-end">
                                ${actionButtons}
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="card-text text-muted mb-2">
                            ${room.description || 'No description'}
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-user"></i> Host: ${room.host_name || 'Unknown'}
                            </small>
                            <small class="text-muted">
                                Created: ${new Date(room.created_at).toLocaleDateString()}
                            </small>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    console.log('Setting rooms HTML...'); // Debug line
    $('#roomsList').html(html);
}

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
        html = '<p class="text-muted">No users online</p>';
    } else {
        users.forEach(user => {
            const name = user.username || user.guest_name || 'Unknown';
            const avatar = user.avatar || user.guest_avatar || 'default_avatar.jpg';
            
            html += `
                <div class="d-flex align-items-center mb-2">
                    <img src="images/${avatar}" width="30" height="30" class="rounded-circle me-2" alt="${name}">
                    <div>
                        <small class="fw-bold">${name}</small>
                        ${user.is_admin ? '<br><span class="badge bg-danger badge-sm">Admin</span>' : ''}
                    </div>
                </div>
            `;
        });
    }
    
    $('#onlineUsersList').html(html);
}

// Function to show unified room access modal (password + knock)
window.showRoomAccessModal = function(roomId, roomName, allowsKnocking) {
    let knockOption = '';
    if (allowsKnocking) {
        knockOption = `
            <div class="text-center my-3">
                <p class="text-muted">Or</p>
                <button type="button" class="btn btn-outline-primary w-100" onclick="knockOnRoom(${roomId}, '${roomName}'); $('#roomAccessModal').modal('hide');">
                    <i class="fas fa-hand-paper"></i> Request Access (Knock)
                </button>
            </div>
        `;
    }
    
    const modalHtml = `
        <div class="modal fade" id="roomAccessModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-lock"></i> Access Protected Room
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <h6 class="mb-3">${roomName}</h6>
                        <p class="text-muted mb-3">This room is password protected. Enter the password to join:</p>
                        
                        <div class="mb-3">
                            <label for="roomPasswordInput" class="form-label">Password</label>
                            <input type="password" class="form-control" id="roomPasswordInput" placeholder="Enter room password">
                        </div>
                        
                        <button type="button" class="btn btn-primary w-100" onclick="joinRoomWithPassword(${roomId})">
                            <i class="fas fa-sign-in-alt"></i> Join Room
                        </button>
                        
                        ${knockOption}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#roomAccessModal').remove();
    $('body').append(modalHtml);
    $('#roomAccessModal').modal('show');
    
    // Focus on password input
    $('#roomAccessModal').on('shown.bs.modal', function() {
        $('#roomPasswordInput').focus();
    });
    
    // Handle Enter key
    $('#roomPasswordInput').on('keypress', function(e) {
        if (e.which === 13) {
            joinRoomWithPassword(roomId);
        }
    });
};

// Function to show create room modal
window.showCreateRoomModal = function() {
    const modalHtml = `
        <div class="modal fade" id="createRoomModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle"></i> Create New Room
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="createRoomForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="roomName" class="form-label">Room Name</label>
                                        <input type="text" class="form-control" id="roomName" required maxlength="50">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="roomCapacity" class="form-label">Capacity</label>
                                        <select class="form-select" id="roomCapacity" required>
                                            <option value="5">5 users</option>
                                            <option value="10" selected>10 users</option>
                                            <option value="20">20 users</option>
                                            <option value="50">50 users</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="roomBackground" class="form-label">Background</label>
                                        <select class="form-select" id="roomBackground">
                                            <option value="">Default</option>
                                            <option value="images/background1.jpg">Background 1</option>
                                            <option value="images/background2.jpg">Background 2</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="roomDescription" class="form-label">Description</label>
                                        <textarea class="form-control" id="roomDescription" rows="3" maxlength="200"></textarea>
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
                                        <input type="password" class="form-control" id="roomPassword">
                                    </div>
                                    
                                    <div class="mb-3" id="knockingField" style="display: none;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allowKnocking" checked>
                                            <label class="form-check-label" for="allowKnocking">
                                                <i class="fas fa-hand-paper"></i> Allow Knocking
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">Let users request access when they don't know the password</small>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="createRoom()">
                            <i class="fas fa-plus"></i> Create Room
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal and add new one
    $('#createRoomModal').remove();
    $('body').append(modalHtml);
    
    // Show/hide password and knocking fields
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
    
    // Show modal
    $('#createRoomModal').modal('show');
};

// Function to create room
window.createRoom = function() {
    const formData = {
        name: $('#roomName').val().trim(),
        description: $('#roomDescription').val().trim(),
        capacity: $('#roomCapacity').val(),
        background: $('#roomBackground').val(),
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
                
                // Join the newly created room
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

// Replace your existing joinRoom function with this smarter version

window.joinRoom = function(roomId) {
    console.log('joinRoom: Attempting to join room', roomId);
    
    // First try to join without password (in case user has room key or room is not protected)
    $.ajax({
        url: 'api/join_room.php',
        method: 'POST',
        data: { room_id: roomId },
        dataType: 'json',
        success: function(response) {
            console.log('joinRoom: Initial attempt response:', response);
            if (response.status === 'success') {
                // Success! User can join (either no password or has room key)
                window.location.href = 'room.php';
            } else {
                // Check if password is required
                if (response.message && response.message.toLowerCase().includes('password')) {
                    console.log('joinRoom: Password required, showing modal');
                    showPasswordModal(roomId, 'Room ' + roomId);
                } else {
                    // Some other error
                    alert('Error: ' + response.message);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('joinRoom: Error on initial attempt:', error, xhr.responseText);
            alert('Error joining room: ' + error);
        }
    });
};

// Function to knock on room
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

// Function to show avatar selector
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

// Function to show avatar modal
function showAvatarModal(avatars) {
    let avatarHtml = '';
    
    if (Array.isArray(avatars)) {
        avatars.forEach(avatar => {
            avatarHtml += `
                <div class="col-2 text-center mb-3">
                    <img src="images/${avatar}" 
                         width="60" height="60" 
                         class="rounded-circle avatar-option" 
                         onclick="selectAvatar('${avatar}')"
                         style="cursor: pointer; border: 2px solid transparent;"
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
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-circle"></i> Choose Your Avatar
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

// Function to select avatar
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
                
                // Update session
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

// Replace your checkForKnocks and displayKnockNotifications functions with these:

function checkForKnocks() {
    console.log('checkForKnocks: Checking for knocks...');
    
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
                console.log('checkForKnocks: No knocks found or empty response');
            }
        },
        error: function(xhr, status, error) {
            console.log('checkForKnocks: Error occurred:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
        }
    });
}

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
        const roomName = knock.room_name || 'Unknown Room';
        
        // Calculate position for multiple notifications
        const topPosition = 20 + (index * 130); // Stack notifications
        
        const notificationHtml = `
            <div class="alert alert-info knock-notification" 
                 id="knock-${knock.id}" 
                 role="alert" 
                 style="position: fixed; top: ${topPosition}px; right: 20px; z-index: 1060; max-width: 400px; min-width: 350px; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
                <div class="d-flex align-items-center">
                    <img src="images/${avatar}" width="40" height="40" class="rounded-circle me-3" alt="${userName}">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">
                            <i class="fas fa-hand-paper"></i> Knock Request
                        </h6>
                        <p class="mb-2"><strong>${userName}</strong> wants to join <strong>${roomName}</strong></p>
                        <div>
                            <button class="btn btn-success btn-sm me-2" onclick="respondToKnock(${knock.id}, 'accepted')">
                                <i class="fas fa-check"></i> Accept
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="respondToKnock(${knock.id}, 'denied')">
                                <i class="fas fa-times"></i> Deny
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-close" onclick="dismissKnock(${knock.id})"></button>
                </div>
            </div>
        `;
        
        console.log('Adding notification for knock', knock.id);
        $('body').append(notificationHtml);
        
        // Add entrance animation
        $(`#knock-${knock.id}`).hide().fadeIn(300);
        
        // Auto-dismiss after 30 seconds
        setTimeout(() => {
            console.log('Auto-dismissing knock', knock.id);
            dismissKnock(knock.id);
        }, 30000);
    });
}

// Make sure these functions are also available globally
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
                alert(response === 'accepted' ? 'Knock accepted! User can now join.' : 'Knock denied.');
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

window.dismissKnock = function(knockId) {
    console.log('dismissKnock:', knockId);
    $(`#knock-${knockId}`).fadeOut(300, function() {
        $(this).remove();
        // Reposition remaining notifications
        repositionKnockNotifications();
    });
};

function repositionKnockNotifications() {
    $('.knock-notification').each(function(index) {
        $(this).animate({
            top: (20 + (index * 130)) + 'px'
        }, 200);
    });
}

// Test function you can call manually
window.testKnockCheck = function() {
    console.log('Manual knock check...');
    checkForKnocks();
};

// Add this to your existing lounge.js file, don't replace the whole file

// Update the displayRooms function to show knock buttons
function displayRooms(rooms) {
    let html = '';
    
    if (!Array.isArray(rooms) || rooms.length === 0) {
        html = `
            <div class="text-center py-5">
                <i class="fas fa-door-closed fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No rooms available</h4>
                <p class="text-muted">Be the first to create a room!</p>
            </div>
        `;
    } else {
        rooms.forEach(room => {
            const isPasswordProtected = room.has_password == 1;
            const allowsKnocking = room.allow_knocking == 1;
            const userCount = room.user_count || 0;
            const capacity = room.capacity || 10;
            
            let headerClass = 'room-header';
            if (isPasswordProtected && allowsKnocking) {
                headerClass += ' knock-available';
            } else if (isPasswordProtected) {
                headerClass += ' password-protected';
            }
            
            let actionButtons = '';
            if (isPasswordProtected) {
                actionButtons = `
                    <button class="btn btn-primary btn-sm me-2" onclick="showPasswordModal(${room.id}, '${room.name}')">
                        <i class="fas fa-key"></i> Enter Password
                    </button>
                `;
                // Add knock button if knocking is allowed
                if (allowsKnocking) {
                    actionButtons += `
                        <button class="btn btn-outline-primary btn-sm" onclick="knockOnRoom(${room.id}, '${room.name}')">
                            <i class="fas fa-hand-paper"></i> Knock
                        </button>
                    `;
                }
            } else {
                actionButtons = `
                    <button class="btn btn-success btn-sm" onclick="joinRoom(${room.id})">
                        <i class="fas fa-sign-in-alt"></i> Join Room
                    </button>
                `;
            }
            
            html += `
                <div class="room-card">
                    <div class="${headerClass}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">
                                    ${room.name}
                                    ${isPasswordProtected ? '<i class="fas fa-lock ms-2"></i>' : ''}
                                    ${allowsKnocking ? '<i class="fas fa-hand-paper ms-1" title="Knocking allowed"></i>' : ''}
                                </h5>
                                <small class="opacity-75">${userCount}/${capacity} users</small>
                            </div>
                            <div class="text-end">
                                ${actionButtons}
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="card-text text-muted mb-2">
                            ${room.description || 'No description'}
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-user"></i> Host: ${room.host_name || 'Unknown'}
                            </small>
                            <small class="text-muted">
                                Created: ${new Date(room.created_at).toLocaleDateString()}
                            </small>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    $('#roomsList').html(html);
}

// Add these new functions to your existing lounge.js (keep your existing functions)

// Function to knock on room
window.knockOnRoom = function(roomId, roomName) {
    console.log('Knocking on room:', roomId, roomName);
    
    if (!confirm(`Send a knock request to "${roomName}"?`)) {
        return;
    }
    
    $.ajax({
        url: 'api/knock_room.php',
        method: 'POST',
        data: { room_id: roomId },
        dataType: 'json',
        success: function(response) {
            console.log('Knock response:', response);
            if (response.status === 'success') {
                alert('Knock sent! The host will be notified.');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error knocking on room:', error, xhr.responseText);
            alert('Error sending knock: ' + error);
        }
    });
};

// Function to check for knocks (add to your existing intervals)
function checkForKnocks() {
    $.ajax({
        url: 'api/check_knocks.php',
        method: 'GET',
        dataType: 'json',
        success: function(knocks) {
            if (Array.isArray(knocks) && knocks.length > 0) {
                console.log('Received knocks:', knocks);
                displayKnockNotifications(knocks);
            }
        },
        error: function(xhr, status, error) {
            // Silently fail - user might not be a host
            console.log('No knocks or not a host');
        }
    });
}

// Function to display knock notifications
function displayKnockNotifications(knocks) {
    knocks.forEach(knock => {
        // Check if notification already exists
        if ($(`#knock-${knock.id}`).length > 0) {
            return;
        }
        
        const userName = knock.username || knock.guest_name || 'Unknown User';
        const avatar = knock.avatar || 'default_avatar.jpg';
        
        const notificationHtml = `
            <div class="alert alert-info knock-notification" id="knock-${knock.id}" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 1050; max-width: 350px;">
                <div class="d-flex align-items-center">
                    <img src="images/${avatar}" width="40" height="40" class="rounded-circle me-3" alt="${userName}">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">
                            <i class="fas fa-hand-paper"></i> Knock Request
                        </h6>
                        <p class="mb-2">${userName} wants to join your room: <strong>${knock.room_name}</strong></p>
                        <div>
                            <button class="btn btn-success btn-sm me-2" onclick="respondToKnock(${knock.id}, 'accepted')">
                                <i class="fas fa-check"></i> Accept
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="respondToKnock(${knock.id}, 'denied')">
                                <i class="fas fa-times"></i> Deny
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-close" onclick="dismissKnock(${knock.id})"></button>
                </div>
            </div>
        `;
        
        $('body').append(notificationHtml);
        
        // Auto-dismiss after 30 seconds
        setTimeout(() => {
            dismissKnock(knock.id);
        }, 30000);
    });
}

// Function to respond to knock
window.respondToKnock = function(knockId, response) {
    console.log('Responding to knock:', knockId, response);
    
    $.ajax({
        url: 'api/respond_knocks.php',
        method: 'POST',
        data: {
            knock_id: knockId,
            response: response
        },
        dataType: 'json',
        success: function(result) {
            console.log('Response result:', result);
            if (result.status === 'success') {
                dismissKnock(knockId);
                alert(response === 'accepted' ? 'Knock accepted!' : 'Knock denied');
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

// Function to dismiss knock notification
window.dismissKnock = function(knockId) {
    $(`#knock-${knockId}`).fadeOut(300, function() {
        $(this).remove();
    });
};

// Add knock checking to your existing ready function
$(document).ready(function() {
    // Keep your existing code and add this line:
    setInterval(checkForKnocks, 3000);
});

// Add this to your lounge.js if the password modal isn't working

// Update your showPasswordModal to be more user-friendly
window.showPasswordModal = function(roomId, roomName) {
    console.log('showPasswordModal called:', roomId, roomName);
    
    const modalHtml = `
        <div class="modal fade" id="passwordModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-key"></i> Password Required
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Enter the password for <strong>${roomName}</strong>:</p>
                        <div class="mb-3">
                            <input type="password" class="form-control" id="roomPasswordInput" placeholder="Room password">
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-info-circle"></i> If your knock request was accepted, you should be able to join without entering the password.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="joinRoomWithPassword(${roomId})">
                            <i class="fas fa-sign-in-alt"></i> Join Room
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal
    $('#passwordModal').remove();
    
    // Add new modal
    $('body').append(modalHtml);
    
    // Show modal using Bootstrap 5 API
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
    
    // Focus on password input when modal is shown
    $('#passwordModal').on('shown.bs.modal', function() {
        $('#roomPasswordInput').focus();
    });
    
    // Handle Enter key
    $('#roomPasswordInput').on('keypress', function(e) {
        if (e.which === 13) {
            joinRoomWithPassword(roomId);
        }
    });
};

// Also update your joinRoomWithPassword function to be more specific
window.joinRoomWithPassword = function(roomId) {
    const password = $('#roomPasswordInput').val();
    
    if (!password) {
        alert('Please enter the password');
        $('#roomPasswordInput').focus();
        return;
    }
    
    console.log('joinRoomWithPassword: Attempting with password');
    
    $.ajax({
        url: 'api/join_room.php',
        method: 'POST',
        data: {
            room_id: roomId,
            password: password
        },
        dataType: 'json',
        success: function(response) {
            console.log('joinRoomWithPassword: Response:', response);
            if (response.status === 'success') {
                $('#passwordModal').modal('hide');
                window.location.href = 'room.php';
            } else {
                alert('Error: ' + response.message);
                $('#roomPasswordInput').val('').focus();
            }
        },
        error: function(xhr, status, error) {
            console.error('joinRoomWithPassword: Error:', error, xhr.responseText);
            alert('Error joining room: ' + error);
        }
    });
};
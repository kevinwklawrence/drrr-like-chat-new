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
            console.error('Error loading rooms:', status, error, xhr.responseText);
            $('#roomsList').html('<div class="alert alert-danger">Error loading rooms: ' + error + '</div>');
        }
    });
}

// Function to display rooms
function displayRooms(rooms) {
    let html = '';
    
    if (!Array.isArray(rooms) || rooms.length === 0) {
        html = '<div class="text-center py-5"><h4 class="text-muted">No rooms available</h4><p class="text-muted">Be the first to create a room!</p></div>';
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
                actionButtons = '<button class="btn btn-primary btn-sm me-2" onclick="showPasswordModal(' + room.id + ', \'' + room.name.replace(/'/g, "\\'") + '\')"><i class="fas fa-key"></i> Enter Password</button>';
                if (allowsKnocking) {
                    actionButtons += '<button class="btn btn-outline-primary btn-sm" onclick="knockOnRoom(' + room.id + ', \'' + room.name.replace(/'/g, "\\'") + '\')"><i class="fas fa-hand-paper"></i> Knock</button>';
                }
            } else {
                actionButtons = '<button class="btn btn-success btn-sm" onclick="joinRoom(' + room.id + ')"><i class="fas fa-sign-in-alt"></i> Join Room</button>';
            }
            
            // Add user list preview
            let userPreview = '';
            if (room.users && room.users.length > 0) {
                userPreview = '<div class="mt-2"><small class="text-muted">Users: ';
                room.users.slice(0, 3).forEach((user, index) => {
                    if (index > 0) userPreview += ', ';
                    userPreview += '<img src="images/' + (user.avatar || 'default_avatar.jpg') + '" width="16" height="16" class="rounded-circle me-1" alt="' + user.display_name + '">' + user.display_name;
                });
                if (room.users.length > 3) {
                    userPreview += ' and ' + (room.users.length - 3) + ' more';
                }
                userPreview += '</small></div>';
            }
            
            html += '<div class="room-card">' +
                '<div class="' + headerClass + '">' +
                '<div class="d-flex justify-content-between align-items-center">' +
                '<div>' +
                '<h5 class="mb-1">' + room.name;
            
            if (isPasswordProtected) {
                html += ' <i class="fas fa-lock ms-2"></i>';
            }
            if (allowsKnocking) {
                html += ' <i class="fas fa-hand-paper ms-1"></i>';
            }
            
            html += '</h5>' +
                '<small class="opacity-75">' + userCount + '/' + capacity + ' users</small>' +
                '</div>' +
                '<div class="text-end">' + actionButtons + '</div>' +
                '</div>' +
                '</div>' +
                '<div class="card-body">' +
                '<p class="card-text text-muted mb-2">' + (room.description || 'No description') + '</p>' +
                '<div class="d-flex justify-content-between align-items-center">' +
                '<small class="text-muted"><i class="fas fa-user"></i> Host: ' + (room.host_name || 'Unknown') + '</small>' +
                '<small class="text-muted">Created: ' + new Date(room.created_at).toLocaleDateString() + '</small>' +
                '</div>' +
                userPreview +
                '</div>' +
                '</div>';
        });
    }
    
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
            
            html += '<div class="d-flex align-items-center mb-2">' +
                '<img src="images/' + avatar + '" width="30" height="30" class="rounded-circle me-2" alt="' + name + '">' +
                '<div>' +
                '<small class="fw-bold">' + name + '</small>';
            
            if (user.is_admin) {
                html += '<br><span class="badge bg-danger badge-sm">Admin</span>';
            }
            
            html += '</div></div>';
        });
    }
    
    $('#onlineUsersList').html(html);
}

// Function to show create room modal
window.showCreateRoomModal = function() {
    const modalHtml = '<div class="modal fade" id="createRoomModal" tabindex="-1">' +
        '<div class="modal-dialog modal-lg">' +
        '<div class="modal-content">' +
        '<div class="modal-header bg-primary text-white">' +
        '<h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create New Room</h5>' +
        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>' +
        '</div>' +
        '<div class="modal-body">' +
        '<form id="createRoomForm">' +
        '<div class="row">' +
        '<div class="col-md-6">' +
        '<div class="mb-3">' +
        '<label for="roomName" class="form-label">Room Name</label>' +
        '<input type="text" class="form-control" id="roomName" required maxlength="50">' +
        '</div>' +
        '<div class="mb-3">' +
        '<label for="roomCapacity" class="form-label">Capacity</label>' +
        '<select class="form-select" id="roomCapacity" required>' +
        '<option value="5">5 users</option>' +
        '<option value="10" selected>10 users</option>' +
        '<option value="20">20 users</option>' +
        '<option value="50">50 users</option>' +
        '</select>' +
        '</div>' +
        '<div class="mb-3">' +
        '<label for="roomBackground" class="form-label">Background</label>' +
        '<select class="form-select" id="roomBackground">' +
        '<option value="">Default</option>' +
        '<option value="images/background1.jpg">Background 1</option>' +
        '<option value="images/background2.jpg">Background 2</option>' +
        '</select>' +
        '</div>' +
        '</div>' +
        '<div class="col-md-6">' +
        '<div class="mb-3">' +
        '<label for="roomDescription" class="form-label">Description</label>' +
        '<textarea class="form-control" id="roomDescription" rows="3" maxlength="200"></textarea>' +
        '</div>' +
        '<div class="mb-3">' +
        '<div class="form-check">' +
        '<input class="form-check-input" type="checkbox" id="hasPassword">' +
        '<label class="form-check-label" for="hasPassword"><i class="fas fa-lock"></i> Password Protected</label>' +
        '</div>' +
        '</div>' +
        '<div class="mb-3" id="passwordField" style="display: none;">' +
        '<label for="roomPassword" class="form-label">Password</label>' +
        '<input type="password" class="form-control" id="roomPassword">' +
        '</div>' +
        '<div class="mb-3" id="knockingField" style="display: none;">' +
        '<div class="form-check">' +
        '<input class="form-check-input" type="checkbox" id="allowKnocking" checked>' +
        '<label class="form-check-label" for="allowKnocking"><i class="fas fa-hand-paper"></i> Allow Knocking</label>' +
        '</div>' +
        '<small class="form-text text-muted">Let users request access when they don\'t know the password</small>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</form>' +
        '</div>' +
        '<div class="modal-footer">' +
        '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
        '<button type="button" class="btn btn-primary" onclick="createRoom()"><i class="fas fa-plus"></i> Create Room</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';
    
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

// Function to show password modal
window.showPasswordModal = function(roomId, roomName) {
    const modalHtml = '<div class="modal fade" id="passwordModal" tabindex="-1">' +
        '<div class="modal-dialog">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<h5 class="modal-title"><i class="fas fa-key"></i> Enter Password</h5>' +
        '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
        '</div>' +
        '<div class="modal-body">' +
        '<p>Enter the password for <strong>' + roomName + '</strong>:</p>' +
        '<input type="password" class="form-control" id="roomPasswordInput" placeholder="Room password">' +
        '<div class="mt-3">' +
        '<small class="text-muted">Don\'t have the password? You can try <a href="#" onclick="knockInsteadOfPassword(' + roomId + ', \'' + roomName.replace(/'/g, "\\'") + '\')">knocking</a> instead.</small>' +
        '</div>' +
        '</div>' +
        '<div class="modal-footer">' +
        '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
        '<button type="button" class="btn btn-primary" onclick="joinRoomWithPassword(' + roomId + ')"><i class="fas fa-sign-in-alt"></i> Join Room</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';
    
    $('#passwordModal').remove();
    $('body').append(modalHtml);
    $('#passwordModal').modal('show');
    
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

// Function to knock instead of password
window.knockInsteadOfPassword = function(roomId, roomName) {
    $('#passwordModal').modal('hide');
    knockOnRoom(roomId, roomName);
};

// Function to join room (no password)
window.joinRoom = function(roomId) {
    $.ajax({
        url: 'api/join_room.php',
        method: 'POST',
        data: { room_id: roomId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                window.location.href = 'room.php';
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error joining room:', error);
            alert('Error joining room: ' + error);
        }
    });
};

// Function to knock on room
window.knockOnRoom = function(roomId, roomName) {
    if (!confirm('Send a knock request to "' + roomName + '"?')) {
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
            avatarHtml += '<div class="col-2 text-center mb-3">' +
                '<img src="images/' + avatar + '" width="60" height="60" class="rounded-circle avatar-option" onclick="selectAvatar(\'' + avatar + '\')" style="cursor: pointer; border: 2px solid transparent;" onmouseover="this.style.border=\'2px solid #007bff\'" onmouseout="this.style.border=\'2px solid transparent\'" alt="Avatar option">' +
                '</div>';
        });
    }
    
    const modalHtml = '<div class="modal fade" id="avatarModal" tabindex="-1">' +
        '<div class="modal-dialog modal-lg">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<h5 class="modal-title"><i class="fas fa-user-circle"></i> Choose Your Avatar</h5>' +
        '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
        '</div>' +
        '<div class="modal-body">' +
        '<div class="row">' + avatarHtml + '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';
    
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
                
                if (typeof currentUser !== 'undefined') {
                    currentUser.avatar = avatar;
                }
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

// Function to check for knocks
function checkForKnocks() {
    $.ajax({
        url: 'api/check_knocks.php',
        method: 'GET',
        dataType: 'json',
        success: function(knocks) {
            if (Array.isArray(knocks) && knocks.length > 0) {
                displayKnockNotifications(knocks);
            }
        },
        error: function(xhr, status, error) {
            console.log('No knocks or not a host');
        }
    });
}

// Function to display knock notifications
function displayKnockNotifications(knocks) {
    knocks.forEach(knock => {
        if ($('#knock-' + knock.id).length > 0) {
            return;
        }
        
        const userName = knock.username || knock.guest_name || 'Unknown User';
        const avatar = knock.avatar || 'default_avatar.jpg';
        
        const notificationHtml = '<div class="alert alert-info knock-notification" id="knock-' + knock.id + '" role="alert">' +
            '<div class="d-flex align-items-center">' +
            '<img src="images/' + avatar + '" width="40" height="40" class="rounded-circle me-3" alt="' + userName + '">' +
            '<div class="flex-grow-1">' +
            '<h6 class="mb-1"><i class="fas fa-hand-paper"></i> Knock Request</h6>' +
            '<p class="mb-2">' + userName + ' wants to join your room</p>' +
            '<div>' +
            '<button class="btn btn-success btn-sm me-2" onclick="respondToKnock(' + knock.id + ', \'accepted\')"><i class="fas fa-check"></i> Accept</button>' +
            '<button class="btn btn-danger btn-sm" onclick="respondToKnock(' + knock.id + ', \'denied\')"><i class="fas fa-times"></i> Deny</button>' +
            '</div>' +
            '</div>' +
            '<button type="button" class="btn-close" onclick="dismissKnock(' + knock.id + ')"></button>' +
            '</div>' +
            '</div>';
        
        $('#knockNotifications').append(notificationHtml);
        
        setTimeout(() => {
            dismissKnock(knock.id);
        }, 30000);
    });
}

// Function to respond to knock
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
                dismissKnock(knockId);
                console.log('Knock ' + response);
            } else {
                alert('Error: ' + result.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error responding to knock:', error);
            alert('Error responding to knock: ' + error);
        }
    });
};

// Function to dismiss knock notification
window.dismissKnock = function(knockId) {
    $('#knock-' + knockId).fadeOut(300, function() {
        $(this).remove();
    });
};
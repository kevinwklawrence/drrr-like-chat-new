$(document).ready(function() {
    // Move roomId check to inline script in room.php
    console.log('room.js loaded, roomId:', roomId);

    // Function to show room settings
    window.showRoomSettings = function() {
        console.log('Loading room settings for roomId:', roomId);
        
        // First get current room settings
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
        const modalHtml = `
            <div class="modal fade" id="roomSettingsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-cog"></i> Room Settings
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="banlist-tab" data-bs-toggle="tab" data-bs-target="#banlist" type="button" role="tab">Banlist</button>
                                </li>
                            </ul>
                            <div class="tab-content" id="settingsTabsContent">
                                <div class="tab-pane fade show active" id="general" role="tabpanel">
                                    <form id="roomSettingsForm" class="mt-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="settingsRoomName" class="form-label">Room Name</label>
                                                    <input type="text" class="form-control" id="settingsRoomName" value="${settings.name}" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="settingsCapacity" class="form-label">Capacity</label>
                                                    <select class="form-select" id="settingsCapacity" required>
                                                        <option value="5" ${settings.capacity == 5 ? 'selected' : ''}>5</option>
                                                        <option value="10" ${settings.capacity == 10 ? 'selected' : ''}>10</option>
                                                        <option value="20" ${settings.capacity == 20 ? 'selected' : ''}>20</option>
                                                        <option value="50" ${settings.capacity == 50 ? 'selected' : ''}>50</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="settingsPassword" class="form-label">Password</label>
                                                    <input type="password" class="form-control" id="settingsPassword" placeholder="Leave empty to keep current password">
                                                    <div class="form-text">Leave empty to keep current password, or enter new password to change it.</div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="settingsDescription" class="form-label">Description</label>
                                                    <textarea class="form-control" id="settingsDescription" rows="3">${settings.description || ''}</textarea>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="settingsBackground" class="form-label">Background</label>
                                                    <select class="form-select" id="settingsBackground">
                                                        <option value="" ${!settings.background ? 'selected' : ''}>Default</option>
                                                        <option value="images/background1.jpg" ${settings.background === 'images/background1.jpg' ? 'selected' : ''}>Background 1</option>
                                                        <option value="images/background2.jpg" ${settings.background === 'images/background2.jpg' ? 'selected' : ''}>Background 2</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="settingsPermanent" ${settings.permanent ? 'checked' : ''}>
                                                        <label class="form-check-label" for="settingsPermanent">
                                                            Permanent Room (Admin only)
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="banlist" role="tabpanel">
                                    <div class="mt-3">
                                        <h6>Banned Users</h6>
                                        <div id="bannedUsersList">
                                            <p>Loading banned users...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveRoomSettings()">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal and add new one
        $('#roomSettingsModal').remove();
        $('body').append(modalHtml);
        
        // Load banlist when banlist tab is clicked
        $('#banlist-tab').on('click', function() {
            loadBannedUsers();
        });
        
        // Show modal
        $('#roomSettingsModal').modal('show');
    }

    // Function to load banned users
    function loadBannedUsers() {
        console.log('Loading banned users for room:', roomId);
        
        $.ajax({
            url: 'api/get_banned_users.php',
            method: 'GET',
            dataType: 'json',
            data: { room_id: roomId },
            success: function(response) {
                console.log('Raw banned users response:', response);
                console.log('Response type:', typeof response);
                console.log('Is array:', Array.isArray(response));
                console.log('Response length:', response ? response.length : 'null/undefined');
                
                // Handle both parsed and unparsed responses
                let bannedUsers = response;
                if (typeof response === 'string') {
                    try {
                        bannedUsers = JSON.parse(response);
                        console.log('Parsed response:', bannedUsers);
                    } catch (e) {
                        console.error('JSON parse error in loadBannedUsers:', e, response);
                        $('#bannedUsersList').html('<p class="text-danger">Error parsing server response.</p>');
                        return;
                    }
                }
                
                let html = '';
                
                if (!Array.isArray(bannedUsers)) {
                    console.error('Expected array, got:', typeof bannedUsers, bannedUsers);
                    if (bannedUsers && bannedUsers.status && bannedUsers.message) {
                        html = '<p class="text-danger">Error: ' + bannedUsers.message + '</p>';
                    } else {
                        html = '<p class="text-danger">Invalid response format from server.</p>';
                    }
                } else {
                    console.log('Processing', bannedUsers.length, 'banned users');
                    
                    if (bannedUsers.length === 0) {
                        html = '<p class="text-muted">No banned users.</p>';
                        console.log('No banned users found');
                    } else {
                        console.log('Found banned users, processing...');
                        
                        bannedUsers.forEach((ban, index) => {
                            console.log('Processing ban', index + 1, ':', ban);
                            
                            const name = ban.username || ban.guest_name || 'Unknown User';
                            const banType = ban.ban_until === null || ban.ban_until === '' ? 'Permanent' : 'Temporary';
                            const expiry = ban.ban_until ? new Date(ban.ban_until).toLocaleString() : 'Never';
                            const reason = ban.reason || 'No reason provided';
                            
                            console.log('Ban details - Name:', name, 'Type:', banType, 'Expiry:', expiry, 'Reason:', reason);
                            
                            html += `
                                <div class="card mb-2">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${name}</strong> 
                                                <span class="badge ${banType === 'Permanent' ? 'bg-danger' : 'bg-warning'}">${banType}</span>
                                                <br>
                                                <small class="text-muted">
                                                    Expires: ${expiry}<br>
                                                    Reason: ${reason}<br>
                                                    User ID: ${ban.user_id_string}
                                                </small>
                                            </div>
                                            <button class="btn btn-sm btn-outline-success" onclick="unbanUser('${ban.user_id_string}', '${name}')">
                                                <i class="fas fa-unlock"></i> Unban
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        console.log('Generated HTML length:', html.length);
                    }
                }
                
                console.log('Setting HTML content...');
                $('#bannedUsersList').html(html);
                console.log('HTML content set');
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in loadBannedUsers:');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                
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
        if (!confirm(`Are you sure you want to unban ${userName}?`)) {
            return;
        }
        
        $.ajax({
            url: 'api/unban_user.php',
            method: 'POST',
            dataType: 'json',
            data: {
                room_id: roomId,
                user_id_string: userIdString
            },
            success: function(response) {
                console.log('Unban response:', response);
                
                // Handle both parsed and unparsed responses
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
                    alert(`${userName} has been unbanned successfully!`);
                    loadBannedUsers(); // Refresh the banned users list
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
                        
                        // Reload the page to reflect changes
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
        const modalHtml = `
            <div class="modal fade" id="banUserModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-ban"></i> Ban User
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>You are about to ban <strong>${userName}</strong> from this room.</p>
                            <div class="mb-3">
                                <label for="banDuration" class="form-label">Ban Duration</label>
                                <select class="form-select" id="banDuration" required>
                                    <option value="300">5 minutes</option>
                                    <option value="1800">30 minutes</option>
                                    <option value="permanent">Permanent</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="banReason" class="form-label">Reason (optional)</label>
                                <input type="text" class="form-control" id="banReason" placeholder="Enter reason for ban">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" onclick="confirmBanUser('${userIdString}', '${userName}')">
                                <i class="fas fa-ban"></i> Ban User
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal and add new one
        $('#banUserModal').remove();
        $('body').append(modalHtml);
        
        // Show modal
        $('#banUserModal').modal('show');
    };

    // Function to confirm and execute ban
    window.confirmBanUser = function(userIdString, userName) {
        const duration = $('#banDuration').val();
        const reason = $('#banReason').val().trim();
        
        const durationText = duration === 'permanent' ? 'permanently' : 
                           duration == 300 ? 'for 5 minutes' :
                           duration == 1800 ? 'for 30 minutes' : 'for ' + duration + ' seconds';
        
        if (!confirm(`Are you sure you want to ban ${userName} ${durationText}?`)) {
            return;
        }
        
        console.log('Banning user:', userIdString, 'for:', duration, 'reason:', reason);
        
        $.ajax({
            url: 'api/ban_user.php',  // Using debug version temporarily
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
                
                // Handle both parsed and unparsed responses
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
                    alert(`User banned successfully ${durationText}!`);
                    $('#banUserModal').modal('hide');
                    loadUsers(); // Refresh the user list
                    loadMessages(); // Refresh messages to show ban message
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

    // This function is now replaced by loadMessagesImproved below

    // Function to load users
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
                        html = '<p>No users in room.</p>';
                    } else {
                        users.forEach(user => {
                            const avatar = user.avatar || user.guest_avatar || 'u0.png';
                            const name = user.username || user.guest_name || 'Unknown';
                            html += `
                                <div class="user-item mb-2">
                                    <img src="images/${avatar}" width="30" alt="${name}'s avatar" style="vertical-align: middle;">
                                    ${name}
                                    ${user.is_host ? '<span class="badge bg-primary">Host</span>' : ''}
                                    ${user.is_admin ? '<span class="badge bg-danger">Staff</span>' : ''}
                                    ${user.user_id ? '<span class="badge bg-success">Verified</span>' : ''}
                                    ${(isHost || isAdmin) && !user.is_host && user.user_id_string !== currentUserIdString ? 
                                        '<button class="btn btn-sm btn-outline-danger ms-2" onclick="showBanModal(\'' + user.user_id_string + '\', \'' + name + '\')">Ban</button>' : ''}
                                </div>`;
                        });
                    }
                    $('#userList').html(html);
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in loadUsers:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    }

    // Function to leave room
    window.leaveRoom = function() {
        console.log('Leave room clicked for roomId:', roomId);
        
        // First check if user is host and if there are other users
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
                        // Show host options modal with appropriate options
                        showHostLeavingModal(
                            res.other_users || [], 
                            res.show_transfer !== false, 
                            res.last_user === true
                        );
                    } else if (res.status === 'success') {
                        console.log('Regular user leaving, redirecting to lounge');
                        // Regular user leaving
                        window.location.href = 'lounge.php';
                    } else {
                        console.error('Error response:', res);
                        alert('Error: ' + res.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, 'Raw response:', response);
                    // If it's not JSON, treat as regular leave (fallback)
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
    function showHostLeavingModal(otherUsers, showTransfer = true, isLastUser = false) {
        let userOptions = '';
        let transferSection = '';
        
        if (showTransfer && otherUsers.length > 0) {
            // Show transfer host option when there are other users
            otherUsers.forEach(user => {
                let displayName = user.username || user.guest_name;
                userOptions += `<option value="${user.user_id_string}">${displayName}</option>`;
            });
            
            transferSection = `
                <div class="mb-3">
                    <label for="newHostSelect" class="form-label">Or transfer host privileges to:</label>
                    <select class="form-select mb-2" id="newHostSelect">
                        <option value="">Select new host...</option>
                        ${userOptions}
                    </select>
                    <button type="button" class="btn btn-primary w-100" onclick="transferHost()">Transfer Host & Leave</button>
                </div>
            `;
        }

        let modalHtml = `
            <div class="modal fade" id="hostLeavingModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${isLastUser ? 'Last User in Room' : 'You are the Host'}</h5>
                        </div>
                        <div class="modal-body">
                            <p>${isLastUser ? 
                                'You are the last user in this room. When you leave, the room will be deleted.' : 
                                'You are the host of this room. What would you like to do?'}</p>
                            <div class="mb-3">
                                <button type="button" class="btn btn-danger w-100 mb-2" onclick="deleteRoom()">
                                    ${isLastUser ? 'Leave & Delete Room' : 'Delete Room'}
                                </button>
                                ${transferSection}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        $('#hostLeavingModal').remove();
        
        // Add modal to page
        $('body').append(modalHtml);
        
        // Show modal
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
        const messageInput = $('#message');  // Changed from #messageInput to #message
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
                        messageInput.val(''); // Clear the input
                        loadMessages(); // Immediately reload messages
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
        
        return false; // Prevent form submission
    };

    // Handle message form submission
    $(document).on('submit', '#messageForm', function(e) {
        e.preventDefault(); // Prevent page refresh
        sendMessage();
        return false;
    });

    // Handle Enter key in message input
    $(document).on('keypress', '#message', function(e) {  // Changed from #messageInput to #message
        if (e.which === 13) { // Enter key
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
        }, 1000); // Reset after 1 second of no scrolling
    });

    // Modified loadMessages function to handle scrolling better
    function loadMessagesImproved() {
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
                        html = '<p>No messages yet.</p>';
                    } else {
                        messages.forEach(msg => {
                            const avatar = msg.avatar || msg.guest_avatar || 'u0.png';
                            const name = msg.username || msg.guest_name || 'Unknown';
                            if (msg.type === 'system') {
                                html += `<div class="chat-message system-message"><p><img src="images/${avatar}" width="22" alt="${name}'s avatar" style="vertical-align: middle;">${msg.message}</p></div>`;
                                return; // Skip further processing for system messages
                            }
                            html += `
                                <div class="chat-message"><p>
                                    <img src="images/${avatar}" width="30" alt="${name}'s avatar" style="vertical-align: middle;">
                                    <strong>${name}</strong>
                                    ${msg.is_admin ? '<span class="badge bg-danger">Staff</span>' : ''}
                                    ${msg.user_id ? '<span class="badge bg-success">Verified</span>' : ''}:
                                    ${msg.message}
                                </p></div>`;
                            if (isAdmin) {
                                html += `<small>IP: ${msg.ip_address || 'N/A'}</small>`;
                            }
                        });
                    }
                    
                    const chatbox = $('#chatbox');
                    const isAtBottom = chatbox.scrollTop() + chatbox.innerHeight() >= chatbox[0].scrollHeight - 10;
                    const newMessageCount = messages.length;
                    
                    // Update content
                    chatbox.html(html);
                    
                    // Only auto-scroll if user was at bottom, or if there are new messages and user isn't manually scrolling
                    if (isAtBottom || (newMessageCount > lastMessageCount && !userIsScrolling)) {
                        chatbox.scrollTop(chatbox[0].scrollHeight);
                    }
                    
                    lastMessageCount = newMessageCount;
                } catch (e) {
                    console.error('JSON parse error:', e, response);
                    alert('Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in loadMessages:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    }

    // Replace the old loadMessages function with the improved one
    window.loadMessages = loadMessagesImproved;

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
                    loadUsers(); // Refresh the user list
                    loadMessages(); // Refresh messages to show join message
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

    // Initialization
    console.log('Initializing room functions for roomId:', roomId);
    loadMessages();
    loadUsers();
    setInterval(loadMessages, 3000);
    setInterval(loadUsers, 3000);
});
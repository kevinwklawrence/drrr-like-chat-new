$(document).ready(function() {
    // Move roomId check to inline script in room.php
    console.log('room.js loaded, roomId:', roomId);

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
                        html = '<p>No messages yet.</p>';
                    } else {
                        messages.forEach(msg => {
                            const avatar = msg.avatar || msg.guest_avatar || 'default_avatar.jpg';
                            const avatarClass = avatar.replace(/\.[^/.]+$/, ''); // removes file extension
                            const name = msg.username || msg.guest_name || 'Unknown';
                            if (msg.type === 'system') {
                                html += `<div class="chat-message system-message"><p><img src="images/${avatar}" width="22" alt="${name}'s avatar" style="vertical-align: middle;">${msg.message}</p></div>`;
                                return; // Skip further processing for system messages
                            }
                            html += `
                            <div class="chat-message blue-msg">
                                <p id="${avatarClass}"> 
                                    <img src="images/${avatar}" width="58" alt="${name}'s avatar" style="vertical-align: middle;">
                                    <strong>${name}</strong>
                                    ${msg.is_admin ? '<span class="badge bg-warning rounded-circle">✓</span>' : ''}
                                    ${msg.user_id && !msg.is_admin ? '<span class="badge bg-success rounded-circle">✓</span>' : ''} 
                                    → <span>${msg.message}</span>
                                </p></div>`;
                            if (isAdmin) {
                                html += `<small>IP: ${msg.ip_address || 'N/A'}</small>`;
                            }
                        });
                    }
                    $('#chatbox').html(html);
                    $('#chatbox').scrollTop($('#chatbox')[0].scrollHeight);
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

/*
${msg.is_admin ? '<span class="badge bg-warning rounded-circle">✓</span>' : ''}
${msg.user_id ? '<span class="badge bg-success rounded-circle">✓</span>' : ''} 
${msg.message}
*/

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
                            const avatar = user.avatar || user.guest_avatar || 'default_avatar.jpg';
                            const name = user.username || user.guest_name || 'Unknown';
                            html += `
                            <div class="chat-message">
                                <p>
                                    <img src="images/${avatar}" width="30" alt="${name}'s avatar" style="vertical-align: middle;">
                                    
                                    
                                    ${name}
                                    ${user.is_admin ? '<span class="badge bg-warning rounded-circle">✓</span>' : ''}
                                    ${user.user_id && !user.is_admin ? '<span class="badge bg-success rounded-circle">✓</span>' : ''}
                                    ${user.is_host ? '<span class="badge rounded-pill bg-info">Host</span>' : ''}

                                </p></div>`;
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

    $('#messageForm').on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        const message = $('#message').val().trim();
        console.log('Message form submitted:', message);
        if (!message) {
            alert('Message cannot be empty');
            return;
        }
        $.ajax({
            url: 'api/send_message.php',
            method: 'POST',
            data: {
                room_id: roomId,
                message: message
            },
            success: function(response) {
                console.log('Response from api/send_message.php:', response);
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
                        $('#message').val('');
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
                console.error('AJAX error in send_message:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    });

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

    // Initialization
    console.log('Initializing room functions for roomId:', roomId);
    loadMessages();
    loadUsers();
    setInterval(loadMessages, 3000);
    setInterval(loadUsers, 3000);
});
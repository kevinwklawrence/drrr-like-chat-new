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
                            const name = msg.username || msg.guest_name || 'Unknown';
                            html += `
                                <p>
                                    <img src="images/${avatar}" width="30" alt="${name}'s avatar" style="vertical-align: middle;">
                                    <strong>${name}</strong>
                                    ${msg.is_admin ? '<span class="badge bg-danger">Staff</span>' : ''}
                                    ${msg.user_id ? '<span class="badge bg-success">Verified</span>' : ''}:
                                    ${msg.message}
                                </p>`;
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
                                <p>
                                    <img src="images/${avatar}" width="30" alt="${name}'s avatar" style="vertical-align: middle;">
                                    ${name}
                                    ${user.is_admin ? '<span class="badge bg-danger">Staff</span>' : ''}
                                    ${user.user_id ? '<span class="badge bg-success">Verified</span>' : ''}
                                </p>`;
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
        $.ajax({
            url: 'api/leave_room.php',
            method: 'POST',
            data: { room_id: roomId },
            success: function(response) {
                console.log('Response from api/leave_room.php:', response);
                try {
                    let res = JSON.parse(response);
                    if (res.status === 'success') {
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
                console.error('AJAX error in leaveRoom:', status, error, xhr.responseText);
                alert('AJAX error: ' + error);
            }
        });
    };

    console.log('Initializing room functions for roomId:', roomId);
    loadMessages();
    loadUsers();
    setInterval(loadMessages, 3000);
    setInterval(loadUsers, 3000);
});
$(document).ready(function() {
    console.log('script.js loaded');

    // Guest login
    $('.avatar').click(function() {
        $('.avatar').removeClass('selected');
        $(this).addClass('selected');
        $('#selectedAvatar').val($(this).data('avatar'));
    });

    $('#guestLoginForm').submit(function(e) {
        e.preventDefault();
        console.log('Guest login form submitted');
        $.ajax({
            url: 'api/join_lounge.php',
            method: 'POST',
            data: {
                guest_name: $('#guestName').val(),
                avatar: $('#selectedAvatar').val(),
                type: 'guest'
            },
            dataType: 'json',
            success: function(res) {
                console.log('Response from api/join_lounge.php:', res);
                if (res.status === 'success') {
                    window.location.href = 'lounge.php';
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in guestLogin:', status, error, 'Response:', xhr.responseText);
                alert('AJAX error: ' + error + ' (Response: ' + xhr.responseText + ')');
            }
        });
    });

    // Member login
    $('#userLoginForm').submit(function(e) {
        e.preventDefault();
        console.log('User login form submitted');
        
        // Check if avatar is selected
        if (!$('#selectedAvatar').val()) {
            alert('Please select an avatar');
            return;
        }
        
        $.ajax({
            url: 'api/login.php',
            method: 'POST',
            data: {
                username: $('#username').val(),
                password: $('#password').val(),
                avatar: $('#selectedAvatar').val(),
                type: 'user'
            },
            dataType: 'json',
            success: function(res) {
                console.log('Response from api/login.php:', res);
                if (res.status === 'success') {
                    window.location.href = 'lounge.php';
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in userLogin:', status, error, 'Response:', xhr.responseText);
                alert('AJAX error: ' + error + ' (Response: ' + xhr.responseText + ')');
            }
        });
    });

    // Lounge functions
    function loadChatrooms() {
        console.log('Loading chatrooms');
        $.ajax({
            url: 'api/get_rooms.php',
            method: 'GET',
            dataType: 'json',
            success: function(rooms) {
                console.log('Response from api/get_rooms.php:', rooms);
                let html = '';
                if (!Array.isArray(rooms)) {
                    console.error('Expected array from get_rooms, got:', rooms);
                    html = '<p>Error loading chatrooms.</p>';
                } else if (rooms.length === 0) {
                    html = '<p>No chatrooms available.</p>';
                } else {
                    rooms.forEach(room => {
                        html += `<div class="chatroom">
                            <h4>${room.name}</h4>
                            <p>${room.description}</p>
                            <p>Capacity: ${room.current_users}/${room.capacity}</p>
                            <button class="btn btn-primary" onclick="joinRoom(${room.id}, ${room.password ? 'true' : 'false'})">Join</button>
                        </div>`;
                    });
                }
                $('#chatroomList').html(html);
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in loadChatrooms:', status, error, 'Response:', xhr.responseText);
                alert('AJAX error: ' + error + ' (Response: ' + xhr.responseText + ')');
            }
        });
    }

    window.joinRoom = function(roomId, hasPassword) {
        console.log('Join room clicked: roomId=', roomId, 'hasPassword=', hasPassword);
        if (hasPassword) {
            let password = prompt("Enter room password:");
            if (!password) {
                console.log('Password prompt cancelled');
                return;
            }
            $.ajax({
                url: 'api/join_room.php',
                method: 'POST',
                data: { room_id: roomId, password: password },
                dataType: 'json',
                success: function(res) {
                    console.log('Response from api/join_room.php:', res);
                    if (res.status === 'success') {
                        window.location.href = `room.php?room_id=${roomId}`;
                    } else {
                        alert('Error: ' + (res.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error in joinRoom:', status, error, 'Response:', xhr.responseText);
                    alert('AJAX error: ' + error + ' (Response: ' + xhr.responseText + ')');
                }
            });
        } else {
            $.ajax({
                url: 'api/join_room.php',
                method: 'POST',
                data: { room_id: roomId },
                dataType: 'json',
                success: function(res) {
                    console.log('Response from api/join_room.php:', res);
                    if (res.status === 'success') {
                        window.location.href = `room.php?room_id=${roomId}`;
                    } else {
                        alert('Error: ' + (res.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error in joinRoom:', status, error, 'Response:', xhr.responseText);
                    alert('AJAX error: ' + error + ' (Response: ' + xhr.responseText + ')');
                }
            });
        }
    };

    $('#createRoomForm').submit(function(e) {
        e.preventDefault();
        console.log('Create room form submitted:', {
            name: $('#roomName').val(),
            description: $('#description').val(),
            background: $('#background').val(),
            capacity: $('#capacity').val(),
            password: $('#password').val(),
            permanent: $('#permanent').val()
        });
        $.ajax({
            url: 'api/create_room.php',
            method: 'POST',
            data: {
                name: $('#roomName').val(),
                description: $('#description').val(),
                background: $('#background').val(),
                capacity: $('#capacity').val(),
                password: $('#password').val(),
                permanent: $('#permanent').val()
            },
            dataType: 'json',
            success: function(res) {
                console.log('Response from api/create_room.php:', res);
                if (res.status === 'success') {
                    $('#createRoomModal').modal('hide');
                    $('#createRoomForm')[0].reset();
                    window.location.href = `room.php?room_id=${res.room_id}`;
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in createRoom:', status, error, 'Response:', xhr.responseText);
                alert('AJAX error: ' + error + ' (Response: ' + xhr.responseText + ')');
            }
        });
    });

    window.logout = function() {
        console.log('Logout button clicked');
        $.ajax({
            url: 'api/logout.php',
            method: 'POST',
            dataType: 'json',
            success: function(res) {
                console.log('Response from api/logout.php:', res);
                if (res.status === 'success') {
                    window.location.href = 'index.php';
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in logout:', status, error, 'Response:', xhr.responseText);
                alert('AJAX error: ' + error + ' (Response: ' + xhr.responseText + ')');
            }
        });
    };

    if (window.location.pathname.includes('lounge.php')) {
        loadChatrooms();
    }
});
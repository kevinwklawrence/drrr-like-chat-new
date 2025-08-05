$(document).ready(function() {
    console.log('lounge.js loaded');

    $('.joinRoomForm').on('submit', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        const password = $(this).find('input[name="password"]').val();
        console.log('Joining room:', roomId, 'with password:', password);

        if (!roomId || isNaN(roomId)) {
            console.error('Invalid roomId:', roomId);
            alert('Invalid room ID');
            return;
        }

        $.ajax({
            url: 'api/join_room.php',
            type: 'POST',
            data: {
                room_id: roomId,
                password: password
            },
            dataType: 'json', // Automatically parse JSON response
            success: function(res) {
                console.log('Response from api/join_room.php:', res);
                if (res.status === 'success') {
                    window.location.href = 'room.php';
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in joinRoom:', status, error, 'Response:', xhr.responseText);
                alert('Internal AJAX error: ' + error + ' (Response: ' + xhr.responseText + ')');
            }
        });
    });
});
// Mute System
let mutedUsers = new Set();

// Load muted users list on room join
function loadMutedUsers() {
    $.ajax({
        url: 'api/mute_user.php',
        method: 'POST',
        data: { action: 'get_muted_list' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                mutedUsers = new Set(response.muted_users);
                console.log('Loaded muted users:', Array.from(mutedUsers));
            }
        }
    });
}

// Mute a user
function muteUser(userIdString, username) {
    if (!confirm(`Mute ${username}? You won't see their messages and they won't be able to whisper you.`)) {
        return;
    }
    
    $.ajax({
        url: 'api/mute_user.php',
        method: 'POST',
        data: {
            action: 'mute',
            muted_user_id_string: userIdString
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                mutedUsers.add(userIdString);
                loadUsers(); // Refresh user list to update button
                loadMessages(); // Refresh messages to hide muted user's messages
                alert(`${username} has been muted`);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Failed to mute user');
        }
    });
}

// Unmute a user
function unmuteUser(userIdString, username) {
    if (!confirm(`Unmute ${username}?`)) {
        return;
    }
    
    $.ajax({
        url: 'api/mute_user.php',
        method: 'POST',
        data: {
            action: 'unmute',
            muted_user_id_string: userIdString
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                mutedUsers.delete(userIdString);
                loadUsers(); // Refresh user list to update button
                loadMessages(); // Refresh messages to show unmuted user's messages
                alert(`${username} has been unmuted`);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Failed to unmute user');
        }
    });
}

// Check if a user is muted
function isUserMuted(userIdString) {
    return mutedUsers.has(userIdString);
}
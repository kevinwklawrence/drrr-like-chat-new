const DEBUG_MODE = false;
const SHOW_SENSITIVE_DATA = false;




function debugLog(message, data = null) {
    if (DEBUG_MODE) {
        if (data !== null) {
            debugLog(message, data);
        } else {
            debugLog(message);
        }
    }
}

function debugError(message, error = null) {
    if (DEBUG_MODE) {
        if (error !== null) {
            console.error(message, error);
        } else {
            console.error(message);
        }
    }
}

function debugWarn(message, data = null) {
    if (DEBUG_MODE) {
        if (data !== null) {
            console.warn(message, data);
        } else {
            console.warn(message);
        }
    }
}

function debugLog(message, data = null) {
    if (DEBUG_MODE && SHOW_SENSITIVE_DATA) {
        if (data !== null) {
            debugLog('[SENSITIVE]', message, data);
        } else {
            debugLog('[SENSITIVE]', message);
        }
    }
}

function criticalError(message, error = null) {
    if (error !== null) {
        console.error('[CRITICAL]', message, error);
    } else {
        console.error('[CRITICAL]', message);
    }
}

$(document).ready(function() {
    debugLog('script.js loaded');
    setupInputSanitization();

    

    $('.avatar').click(function() {
        $('.avatar').removeClass('selected');
        $(this).addClass('selected');
        $('#selectedAvatar').val($(this).data('avatar'));
    });

    $('#guestLoginForm').submit(function(e) {
    e.preventDefault();
    debugLog('Guest login form submitted');
    
    // Validate and clean guest name
    const guestNameInput = $('#guestName').val();
    const validation = validateGuestName(guestNameInput);
    
    if (!validation.valid) {
        // Show error message
        alert(validation.error);
        return;
    }
    
    // Use the cleaned name
    const cleanGuestName = validation.name;
    
    $.ajax({
        url: 'api/join_lounge.php',
        method: 'POST',
        data: {
            guest_name: cleanGuestName, // Use cleaned name
            avatar: $('#selectedAvatar').val(),
            color: $('#selectedColor').val(),
            type: 'guest'
        },
        dataType: 'json',
        success: function(res) {
            debugLog('Response from api/join_lounge.php:', res);
            if (res.status === 'success') {
                window.location.href = '/lounge';
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

$('#userLoginForm').submit(function(e) {
    e.preventDefault();
    debugLog('User login form submitted');
    
    
    $.ajax({
        url: 'api/login.php',
        method: 'POST',
        data: {
            username: $('#username').val(),
            password: $('#password').val(),
            avatar: $('#selectedAvatar').val(), // Can be empty
            type: 'user'
        },
        dataType: 'json',
        success: function(res) {
            debugLog('Response from api/login.php:', res);
            if (res.status === 'success') {
                window.location.href = '/lounge';

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

    function loadChatrooms() {
        debugLog('Loading chatrooms');
        $.ajax({
            url: 'api/get_rooms.php',
            method: 'GET',
            dataType: 'json',
            success: function(rooms) {
                debugLog('Response from api/get_rooms.php:', rooms);
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
        debugLog('Join room clicked: roomId=', roomId, 'hasPassword=', hasPassword);
        if (hasPassword) {
            let password = prompt("Enter room password:");
            if (!password) {
                debugLog('Password prompt cancelled');
                return;
            }
            $.ajax({
                url: 'api/join_room.php',
                method: 'POST',
                data: { room_id: roomId, password: password },
                dataType: 'json',
                success: function(res) {
                    debugLog('Response from api/join_room.php:', res);
                    if (res.status === 'success') {
                        window.location.href = `/room?room_id=${roomId}`;
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
                    debugLog('Response from api/join_room.php:', res);
                    if (res.status === 'success') {
                        window.location.href = `/room?room_id=${roomId}`;
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
        debugLog('Create room form submitted:', {
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
                debugLog('Response from api/create_room.php:', res);
                if (res.status === 'success') {
                    $('#createRoomModal').modal('hide');
                    $('#createRoomForm')[0].reset();
                    window.location.href = `/room?room_id=${res.room_id}`;
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
        debugLog('Logout button clicked');
        $.ajax({
            url: 'api/logout.php',
            method: 'POST',
            dataType: 'json',
            success: function(res) {
                debugLog('Response from api/logout.php:', res);
                if (res.status === 'success') {
                    window.location.href = '/guest';
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

function showNotification(message, type = 'info') {
    // Remove existing notifications
    $('.notification-toast').remove();
    
    const typeColors = {
        success: '#28a745',
        error: '#dc3545', 
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    const toast = $(`
        <div class="notification-toast" style="
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${typeColors[type] || typeColors.info};
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            max-width: 300px;
            font-size: 14px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        ">
            ${message}
        </div>
    `);
    
    $('body').append(toast);
    
    // Animate in
    setTimeout(() => {
        toast.css({
            opacity: 1,
            transform: 'translateX(0)'
        });
    }, 10);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        toast.css({
            opacity: 0,
            transform: 'translateX(100%)'
        });
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Mobile-optimized profile popup positioning
function adjustProfilePopupForMobile() {
    const popup = $('.profile-popup');
    if (!popup.length) return;
    
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile) {
        popup.css({
            position: 'fixed',
            left: '50%',
            top: '50%',
            transform: 'translate(-50%, -50%)',
            maxWidth: '90vw',
            maxHeight: '80vh',
            overflow: 'auto'
        });
    }
}

// Call on window resize
$(window).on('resize', adjustProfilePopupForMobile);

// OPTIONAL: Add real-time validation feedback as user types
// Add this to script.js for better user experience

$('#guestName').on('input', function() {
    const guestNameInput = $(this).val();
    const validation = validateGuestName(guestNameInput);
    const $input = $(this);
    const $feedback = $('#guestNameFeedback'); // Add this div to your HTML
    
    // Remove existing validation classes
    $input.removeClass('is-valid is-invalid');
    
    if (guestNameInput.length === 0) {
        // Empty input - neutral state
        $feedback.hide();
        return;
    }
    
    if (validation.valid) {
        // Valid name
        $input.addClass('is-valid');
        $feedback.removeClass('invalid-feedback').addClass('valid-feedback')
                 .text(`✓ Name will be: "${validation.name}"`)
                 .show();
    } else {
        // Invalid name
        $input.addClass('is-invalid');
        $feedback.removeClass('valid-feedback').addClass('invalid-feedback')
                 .text(validation.error)
                 .show();
    }
});

// Update the guest name input to show cleaned version in real-time
$('#guestName').on('blur', function() {
    const guestNameInput = $(this).val();
    const validation = validateGuestName(guestNameInput);
    
    if (validation.valid && validation.name !== guestNameInput) {
        // Show the cleaned version to the user
        $(this).val(validation.name);
        $('#guestNameFeedback').text(`✓ Name cleaned to: "${validation.name}"`);
    }
});



// Sanitize text input (removes dangerous characters, preserves UTF-8)
function sanitizeInput(input, maxLength = 3000) {
    if (!input) return '';
    
    // Convert to string and trim
    input = String(input).trim();
    
    // Truncate to max length
    if (input.length > maxLength) {
        input = input.substring(0, maxLength);
    }
    
    // Remove null bytes and other dangerous characters
    input = input.replace(/\0/g, '');
    
    return input;
}

// Validate and sanitize username (alphanumeric and underscore only)
function sanitizeUsername(username, maxLength = 20) {
    username = String(username).trim();
    
    // Remove everything except alphanumeric and underscore
    username = username.replace(/[^a-zA-Z0-9_]/g, '');
    
    if (username.length > maxLength) {
        username = username.substring(0, maxLength);
    }
    
    return username;
}

// Real-time input sanitization on form fields
function setupInputSanitization() {
    // Sanitize text inputs on blur (when user leaves field)
    $('input[type="text"], textarea').on('blur', function() {
        const maxLength = parseInt($(this).attr('maxlength')) || 3000;
        const sanitized = sanitizeInput($(this).val(), maxLength);
        $(this).val(sanitized);
    });
    
    // Sanitize username fields in real-time
    $('input[name="username"], input[id*="username"]').on('input', function() {
        const maxLength = parseInt($(this).attr('maxlength')) || 20;
        const sanitized = sanitizeUsername($(this).val(), maxLength);
        $(this).val(sanitized);
    });
}

// Add character counter to inputs (helpful for mobile users)
function addCharacterCounters() {
    $('textarea, input[maxlength]').each(function() {
        const maxLength = parseInt($(this).attr('maxlength')) || 3000;
        const $input = $(this);
        
        // Create counter element
        const $counter = $('<small class="text-muted character-counter"></small>');
        $input.after($counter);
        
        // Update counter
        const updateCounter = () => {
            const remaining = maxLength - $input.val().length;
            $counter.text(`${remaining} characters remaining`);
            
            // Change color if close to limit
            if (remaining < 50) {
                $counter.addClass('text-danger').removeClass('text-muted');
            } else {
                $counter.addClass('text-muted').removeClass('text-danger');
            }
        };
        
        // Update on input
        $input.on('input', updateCounter);
        updateCounter();
    });
}

// Prevent XSS in dynamic content
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Initialize on document ready

// Export for use in other scripts
window.sanitizeInput = sanitizeInput;
window.sanitizeUsername = sanitizeUsername;
window.escapeHtml = escapeHtml;
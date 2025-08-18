// Global Profile System
let activeProfilePopup = null;

function showUserProfile(userId, avatarElement) {
    if (userId == currentUser.id) {
        showProfileEditor();
        return;
    }
    
    // Close existing popup
    closeProfilePopup();
    
    $.ajax({
        url: 'api/get_user_profile.php',
        method: 'GET',
        data: { user_id: userId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayProfilePopup(response.user, avatarElement);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Failed to load profile');
        }
    });
}

function displayProfilePopup(user, avatarElement) {
    const rect = avatarElement.getBoundingClientRect();
    const links = user.hyperlinks || [];
    
    // Detect if we're in a room or lounge
    const isInRoom = typeof roomId !== 'undefined' && roomId;
    
    let linksHtml = '';
    if (links.length > 0) {
        linksHtml = '<div class="profile-links">';
        links.forEach(link => {
            linksHtml += `<a href="${link.url}" target="_blank" class="profile-link">${link.title}</a>`;
        });
        linksHtml += '</div>';
    }
    
    const coverPhoto = user.cover_photo ? 
        `<div class="profile-cover" style="background-image: url('images/covers/${user.cover_photo}')"></div>` : 
        '<div class="profile-cover profile-cover-default"></div>';
    
    // Different actions based on context and friendship status
    let actionsHtml = '';
    if (currentUser.type === 'user') {
        actionsHtml = '<div class="profile-popup-actions">';
        
        // Friend button based on status
        switch(user.friendship_status) {
            case 'friends':
                actionsHtml += `
                    <span class="btn btn-sm btn-success profile-status-btn">
                        <i class="fas fa-check"></i> Friends
                    </span>
                `;
                break;
            case 'request_sent':
                actionsHtml += `
                    <span class="btn btn-sm btn-warning profile-status-btn">
                        <i class="fas fa-clock"></i> Request Sent
                    </span>
                `;
                break;
            case 'request_received':
                actionsHtml += `
                    <button class="btn btn-sm btn-info profile-action-btn" onclick="acceptFriendFromProfile(${user.id}); event.stopPropagation();">
                        <i class="fas fa-user-check"></i> Accept Request
                    </button>
                `;
                break;
            default: // 'none'
                actionsHtml += `
                    <button class="btn btn-sm btn-primary profile-action-btn" onclick="addFriendFromProfile('${user.username}'); event.stopPropagation();">
                        <i class="fas fa-user-plus"></i> Add Friend
                    </button>
                `;
                break;
        }
        
        // Message/PM button
        if (isInRoom) {
            actionsHtml += `
                <button class="btn btn-sm btn-secondary profile-action-btn" onclick="openWhisper('user_${user.id}', '${user.username}'); event.stopPropagation();">
                    <i class="fas fa-comment"></i> Message
                </button>
            `;
        } else {
            actionsHtml += `
                <button class="btn btn-sm btn-secondary profile-action-btn" onclick="openPrivateMessage(${user.id}, '${user.username}'); event.stopPropagation();">
                    <i class="fas fa-envelope"></i> PM
                </button>
            `;
        }
        
        actionsHtml += '</div>';
    }
    
    const popupHtml = `
        <div class="profile-popup" id="profilePopup">
            ${coverPhoto}
            <div class="profile-popup-content">
                <div class="profile-popup-header">
                    <img src="images/${user.avatar}" 
                         style="filter: hue-rotate(${user.avatar_hue || 0}deg) saturate(${user.avatar_saturation || 100}%);"
                         class="profile-popup-avatar" alt="Avatar">
                    <div class="profile-popup-info">
                        <h6 class="profile-popup-name">${user.username}</h6>
                        ${user.status ? `<div class="profile-popup-status">${user.status}</div>` : ''}
                    </div>
                    <button class="profile-popup-close" onclick="closeProfilePopup()">&times;</button>
                </div>
                ${user.bio ? `<div class="profile-popup-bio">${user.bio}</div>` : ''}
                ${linksHtml}
                ${actionsHtml}
            </div>
        </div>
    `;
    
    $('body').append(popupHtml);
    
    const popup = $('#profilePopup');
    popup.css({
        left: rect.left + rect.width + 10,
        top: Math.max(10, rect.top - 100)
    });
    
    activeProfilePopup = popup;
    
    // Prevent event bubbling on profile action buttons
    $('.profile-action-btn').on('click', function(e) {
        e.stopPropagation();
    });
    
    // Close on outside click
    setTimeout(() => {
        $(document).on('click.profilePopup', function(e) {
            if (!$(e.target).closest('.profile-popup, .user-avatar, .message-avatar').length) {
                closeProfilePopup();
            }
        });
    }, 100);
}

// Add function to accept friend requests from profile
function acceptFriendFromProfile(userId) {
    if (confirm('Accept this friend request?')) {
        // First get the friend request ID
        $.ajax({
            url: 'api/friends.php',
            method: 'GET',
            data: { action: 'get' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const pendingRequest = response.friends.find(friend => 
                        friend.friend_user_id == userId && 
                        friend.status === 'pending' && 
                        friend.request_type === 'received'
                    );
                    
                    if (pendingRequest) {
                        $.ajax({
                            url: 'api/friends.php',
                            method: 'POST',
                            data: {
                                action: 'accept',
                                friend_id: pendingRequest.id
                            },
                            dataType: 'json',
                            success: function(acceptResponse) {
                                if (acceptResponse.status === 'success') {
                                    alert('Friend request accepted!');
                                    closeProfilePopup();
                                    
                                    // Refresh UI
                                    if (typeof clearFriendshipCache === 'function') {
                                        clearFriendshipCache();
                                    }
                                    if (typeof loadUsers === 'function') {
                                        loadUsers();
                                    }
                                    if (typeof loadOnlineUsers === 'function') {
                                        loadOnlineUsers();
                                    }
                                } else {
                                    alert('Error: ' + acceptResponse.message);
                                }
                            }
                        });
                    } else {
                        alert('Friend request not found');
                    }
                } else {
                    alert('Error loading friend requests');
                }
            }
        });
    }
}

// Add this new function for adding friends from profile
function addFriendFromProfile(username) {
    if (!username) {
        alert('Invalid username');
        return;
    }
    
    if (confirm('Send friend request to ' + username + '?')) {
        $.ajax({
            url: 'api/friends.php',
            method: 'POST',
            data: {
                action: 'add',
                friend_username: username
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Friend request sent to ' + username + '!');
                    closeProfilePopup();
                    
                    // Clear friendship cache and refresh UI if functions exist
                    if (typeof clearFriendshipCache === 'function') {
                        clearFriendshipCache();
                    }
                    if (typeof loadUsers === 'function') {
                        loadUsers(); // Refresh user list in room
                    }
                    if (typeof loadOnlineUsers === 'function') {
                        loadOnlineUsers(); // Refresh online users in lounge
                    }
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Send friend request error:', error);
                alert('Error sending friend request: ' + error);
            }
        });
    }
}

function closeProfilePopup() {
    if (activeProfilePopup) {
        activeProfilePopup.remove();
        activeProfilePopup = null;
    }
    $(document).off('click.profilePopup');
}

function showProfileEditor() {
    closeProfilePopup();
    
    $.ajax({
        url: 'api/get_user_profile.php',
        method: 'GET',
        data: { user_id: currentUser.id },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayProfileEditor(response.user);
            }
        }
    });
}

function displayProfileEditor(user) {
    const links = user.hyperlinks || [];
    let linksHtml = '';
    links.forEach((link, index) => {
        linksHtml += `
            <div class="link-input-group">
                <input type="text" class="form-control" placeholder="Link Title" value="${link.title}" data-link-index="${index}" data-link-field="title">
                <input type="url" class="form-control" placeholder="https://" value="${link.url}" data-link-index="${index}" data-link-field="url">
                <button type="button" class="btn btn-outline-danger" onclick="removeLinkInput(${index})"><i class="fas fa-trash"></i></button>
            </div>
        `;
    });
    
    const modalHtml = `
        <div class="modal fade" id="profileEditorModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: #2a2a2a; color: #fff;">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Cover Photo</label>
                            <div class="cover-photo-preview" id="coverPhotoPreview">
                                ${user.cover_photo ? 
                                    `<img src="images/covers/${user.cover_photo}" alt="Cover">` : 
                                    '<div class="no-cover">No cover photo</div>'
                                }
                                <div class="cover-photo-overlay">
                                    <input type="file" id="coverPhotoInput" accept="image/*" style="display: none;">
                                    <button type="button" class="btn btn-light" onclick="$('#coverPhotoInput').click()">
                                        <i class="fas fa-camera"></i> Change Cover
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="profileStatus" class="form-label">Status</label>
                            <input type="text" class="form-control" id="profileStatus" value="${user.status || ''}" maxlength="100" placeholder="What's on your mind?">
                        </div>
                        <div class="mb-3">
                            <label for="profileBio" class="form-label">Bio</label>
                            <textarea class="form-control" id="profileBio" rows="3" maxlength="500" placeholder="Tell us about yourself...">${user.bio || ''}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Links</label>
                            <div id="linksContainer">${linksHtml}</div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addLinkInput()">
                                <i class="fas fa-plus"></i> Add Link
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveProfile()">Save Profile</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#profileEditorModal').remove();
    $('body').append(modalHtml);
    $('#profileEditorModal').modal('show');
    
    // Handle cover photo upload
    $('#coverPhotoInput').on('change', function() {
        const file = this.files[0];
        if (file) {
            uploadCoverPhoto(file);
        }
    });
}

function addLinkInput() {
    const container = $('#linksContainer');
    const index = container.children().length;
    const html = `
        <div class="link-input-group">
            <input type="text" class="form-control" placeholder="Link Title" data-link-index="${index}" data-link-field="title">
            <input type="url" class="form-control" placeholder="https://" data-link-index="${index}" data-link-field="url">
            <button type="button" class="btn btn-outline-danger" onclick="removeLinkInput(${index})"><i class="fas fa-trash"></i></button>
        </div>
    `;
    container.append(html);
}

function removeLinkInput(index) {
    $(`.link-input-group:eq(${index})`).remove();
}

function uploadCoverPhoto(file) {
    const formData = new FormData();
    formData.append('cover_photo', file);
    
    $.ajax({
        url: 'api/upload_cover_photo.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.status === 'success') {
                $('#coverPhotoPreview').html(`
                    <img src="images/covers/${response.filename}" alt="Cover">
                    <div class="cover-photo-overlay">
                        <input type="file" id="coverPhotoInput" accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-light" onclick="$('#coverPhotoInput').click()">
                            <i class="fas fa-camera"></i> Change Cover
                        </button>
                    </div>
                `);
                $('#coverPhotoInput').on('change', function() {
                    const file = this.files[0];
                    if (file) {
                        uploadCoverPhoto(file);
                    }
                });
            } else {
                alert('Upload failed: ' + response.message);
            }
        },
        error: function() {
            alert('Upload failed');
        }
    });
}

function saveProfile() {
    const bio = $('#profileBio').val();
    const status = $('#profileStatus').val();
    
    // Collect links
    const links = [];
    $('.link-input-group').each(function() {
        const title = $(this).find('[data-link-field="title"]').val().trim();
        const url = $(this).find('[data-link-field="url"]').val().trim();
        if (title && url) {
            links.push({ title, url });
        }
    });
    
    $.ajax({
        url: 'api/update_user_profile.php',
        method: 'POST',
        data: {
            bio: bio,
            status: status,
            hyperlinks: JSON.stringify(links)
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#profileEditorModal').modal('hide');
                alert('Profile updated successfully!');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Failed to save profile');
        }
    });
}
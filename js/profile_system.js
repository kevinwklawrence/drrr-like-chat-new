let activeProfilePopup = null;

let selectedAvatar = null;
let selectedColor = null;

if (typeof window.escapeHtml !== 'function') {
    window.escapeHtml = function(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    };
}

function showUserProfile(userId, avatarElement) {
    if (userId == currentUser.id) {
        showProfileEditor();
        return;
    }
    
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
    
    let actionsHtml = '';
    if (currentUser.type === 'user') {
        actionsHtml = '<div class="profile-popup-actions">';
        
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
                // FIXED: Don't use escapeHtml on numeric ID
                actionsHtml += `
                    <button class="btn btn-sm btn-info profile-action-btn" onclick="acceptFriendFromProfile(${user.id}); event.stopPropagation();">
                        <i class="fas fa-user-check"></i> Accept Request
                    </button>
                `;
                break;
            default: // 'none'
                // FIXED: Only escape the username string, not the ID
                actionsHtml += `
                    <button class="btn btn-sm btn-primary profile-action-btn" onclick="addFriendFromProfile('${escapeHtml(user.username)}'); event.stopPropagation();">
                        <i class="fas fa-user-plus"></i>
                    </button>
                `;
                break;
        }
        
        if (isInRoom) {
            // FIXED: Don't use escapeHtml on numeric ID
            actionsHtml += `
                <button class="btn btn-sm btn-secondary profile-action-btn" onclick="openWhisper('user_${user.id}', '${escapeHtml(user.username)}'); event.stopPropagation();">
                    <i class="fas fa-comment"></i> Message
                </button>
            `;
        } else {
            // FIXED: Don't use escapeHtml on numeric ID
            actionsHtml += `
                <button class="btn btn-sm btn-secondary profile-action-btn" onclick="openPrivateMessage(${user.id}, '${escapeHtml(user.username)}'); event.stopPropagation();">
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
    
    $('.profile-action-btn').on('click', function(e) {
        e.stopPropagation();
    });
    
    setTimeout(() => {
        $(document).on('click.profilePopup', function(e) {
            if (!$(e.target).closest('.profile-popup, .user-avatar, .message-avatar').length) {
                closeProfilePopup();
            }
        });
    }, 100);
}

function acceptFriendFromProfile(userId) {
    if (!confirm('Accept this friend request?')) return;
    
    // Show loading state
    showNotification('Processing friend request...', 'info');
    
    $.ajax({
        url: 'api/friends.php',
        method: 'GET', 
        data: { action: 'get' },
        dataType: 'json',
        timeout: 10000,
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
                        timeout: 10000,
                        success: function(acceptResponse) {
                            if (acceptResponse.status === 'success') {
                                showNotification('Friend request accepted!', 'success');
                                closeProfilePopup();
                                
                                // Update UI
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
                                showNotification('Error: ' + (acceptResponse.message || 'Unknown error'), 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            showNotification('Error accepting friend request', 'error');
                        }
                    });
                } else {
                    showNotification('Friend request not found', 'error');
                }
            } else {
                showNotification('Error loading friend requests', 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error loading friend data', 'error');
        }
    });
}


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
    
    const isRegistered = currentUser.type === 'user';
    
    if (isRegistered) {
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
    } else {
        displayProfileEditor(currentUser);
    }
}

function displayProfileEditor(user) {
    const isRegistered = currentUser.type === 'user';
    
    let profileInfoTab = '';
    let profileInfoPanel = '';
    
    if (isRegistered) {
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
        
        profileInfoTab = `
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-info-tab" data-bs-toggle="tab" data-bs-target="#profile-info-panel" type="button" role="tab" style="background: transparent; border: none; color: #e0e0e0;">
                    <i class="fas fa-user"></i> Profile Info
                </button>
            </li>
        `;
        
        profileInfoPanel = `
            <div class="tab-pane fade show active" id="profile-info-panel" role="tabpanel">
                <div class="p-4">
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
                        <input type="text" class="form-control" id="profileStatus" value="${user.status || ''}" maxlength="100" placeholder="What's on your mind?" style="background: #333; border: 1px solid #555; color: #fff;">
                    </div>
                    <div class="mb-3">
                        <label for="profileBio" class="form-label">Bio</label>
                        <textarea class="form-control" id="profileBio" rows="3" maxlength="500" placeholder="Tell us about yourself..." style="background: #333; border: 1px solid #555; color: #fff;">${user.bio || ''}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Links</label>
                        <div id="linksContainer">${linksHtml}</div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addLinkInput()">
                            <i class="fas fa-plus"></i> Add Link
                        </button>
                    </div>
                </div>
            </div>
        `;
        
    }
    
    const modalHtml = `
        <div class="modal fade" id="profileEditorModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="background: linear-gradient(45deg, #333, #444); border-bottom: 1px solid #555;">
                        <h5 class="modal-title">
                            <i class="fas fa-user-edit"></i> Profile Editor
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                    </div>
                    <div class="modal-body p-0">
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs" id="profileTabs" role="tablist" style="background: #333; border-bottom: 1px solid #555; margin: 0;">
                            ${profileInfoTab}
                            <li class="nav-item" role="presentation">
                                <button class="nav-link ${!isRegistered ? 'active' : ''}" id="avatar-tab" data-bs-toggle="tab" data-bs-target="#avatar-panel" type="button" role="tab" style="background: transparent; border: none; color: #e0e0e0;">
                                    <i class="fas fa-user-circle"></i> Avatar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="color-tab" data-bs-toggle="tab" data-bs-target="#color-panel" type="button" role="tab" style="background: transparent; border: none; color: #e0e0e0;">
                                    <i class="fas fa-palette"></i> Chat Color
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                <button class="nav-link" id="shop-tab" data-bs-toggle="tab" data-bs-target="#shop-panel" type="button" role="tab" style="background: transparent; border: none; color: #e0e0e0;" onclick="loadShopItems()">
                    <i class="fas fa-store"></i> Shop
                </button>
            </li>
             <li class="nav-item" role="presentation">
                <button class="nav-link" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory-panel" type="button" role="tab" style="background: transparent; border: none; color: #e0e0e0;" onclick="loadInventory()">
                    <i class="fas fa-box"></i> Inventory
                </button>
            </li>
            ${isRegistered ? `
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="invites-tab" data-bs-toggle="tab" data-bs-target="#invites-panel" type="button" role="tab" style="background: transparent; border: none; color: #e0e0e0;" onclick="loadInvitesAndKeys()">
            <i class="fas fa-ticket-alt"></i> Invites & Keys
        </button>
    </li>
` : ''}

                        </ul>
                        
                        <div class="tab-content" id="profileTabsContent">
                            ${profileInfoPanel}
                            
                            <!-- Avatar Selection Panel -->
                            <div class="tab-pane fade ${!isRegistered ? 'show active' : ''}" id="avatar-panel" role="tabpanel">
                                <div class="row g-0">
                                    <!-- Avatar Preview Sidebar -->
<div class="col-md-4" style="background: #333; border-right: 1px solid #555; min-height: 500px;">
    <div class="p-4 text-center">
        <h6 class="mb-3" style="color: #e0e0e0;">
            <i class="fas fa-eye"></i> Preview
        </h6>
        <div id="avatarPreviewContainer" class="mb-3">
            <img id="selectedAvatarPreview" 
                 src="images/${currentUser.avatar || 'default_avatar.jpg'}" 
                 width="116" height="116" 
                 style="border: 3px solid #007bff; border-radius: 8px; filter: hue-rotate(${currentUser.avatar_hue || 0}deg) saturate(${currentUser.avatar_saturation || 100}%);"
                 alt="Selected avatar">
        </div>
        <p class="small text-muted mb-3">Current Selection</p>

        <div class="mb-3" style="background: #444; border-radius: 8px; padding: 15px;">
    <h6 style="color: #e0e0e0; margin-bottom: 10px;">
        <i class="fas fa-signature"></i> ${isRegistered ? 'Username' : 'Guest Name'}
    </h6>
    <input type="text" 
           class="form-control" 
           id="profileNameInput"
           value="${isRegistered ? (user.username || '') : (user.name || '')}"
           placeholder="${isRegistered ? 'Enter username' : 'Enter guest name'}"
           style="background: #333; border: 1px solid #555; color: #fff;">
    <small class="text-muted mt-1 d-block">No restrictions on name</small>
</div>

        <!-- Avatar Customization Sliders -->
        <div class="avatar-customization mb-4" style="background: #444; border-radius: 8px; padding: 15px;">
            <h6 style="color: #e0e0e0; margin-bottom: 15px;">
                <i class="fas fa-sliders-h"></i> Customize Avatar Colors
            </h6>
            
            <!-- Avatar Hue Slider -->
            <div class="mb-3">
                <label for="avatarHueSlider" class="form-label" style="color: #ccc; font-size: 0.9rem;">
                    Hue: <span id="avatarHueValue">${currentUser.avatar_hue || 0}</span>°
                </label>
                <input type="range" 
                       class="form-range avatar-slider" 
                       id="avatarHueSlider" 
                       min="0" 
                       max="360" 
                       value="${currentUser.avatar_hue || 0}"
                       oninput="updateAvatarHue(this.value)"
                       style="background: linear-gradient(to right, #ff0000, #ffff00, #00ff00, #00ffff, #0000ff, #ff00ff, #ff0000);">
            </div>
            
            <!-- Avatar Saturation Slider -->
            <div class="mb-3">
                <label for="avatarSatSlider" class="form-label" style="color: #ccc; font-size: 0.9rem;">
                    Saturation: <span id="avatarSatValue">${currentUser.avatar_saturation || 100}</span>%
                </label>
                <input type="range" 
                       class="form-range avatar-slider" 
                       id="avatarSatSlider" 
                       min="0" 
                       max="200" 
                       value="${currentUser.avatar_saturation || 100}"
                       oninput="updateAvatarSaturation(this.value)"
                       style="background: linear-gradient(to right, #888, #fff);">
            </div>
            
            <!-- Reset Avatar Button -->
            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="resetAvatarCustomization()">
                <i class="fas fa-undo"></i> Reset Avatar Colors
            </button>
        </div>
                                            
                                            <!-- Avatar Controls -->
                                            <div class="mb-3">
                                                <div class="row mb-2">
                                                    <div class="col-6">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="clearAvatarSelection()">
                                                            <i class="fas fa-times"></i> Clear
                                                        </button>
                                                    </div>
                                                    <div class="col-6">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="randomAvatarSelection()">
                                                            <i class="fas fa-random"></i> Random
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Avatar Stats -->
                                            <div class="avatar-stats p-3" style="background: #444; border-radius: 8px;">
                                                <div class="row text-center">
                                                    <div class="col-12 mb-2">
                                                        <small class="text-muted">Available Avatars</small>
                                                    </div>
                                                    <div class="col-6">
                                                        <div style="color: #28a745; font-weight: bold;" id="visibleAvatarCount">0</div>
                                                        <small class="text-muted">Visible</small>
                                                    </div>
                                                    <div class="col-6">
                                                        <div style="color: #ffc107; font-weight: bold;" id="totalAvatarCount">0</div>
                                                        <small class="text-muted">Total</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Avatar Grid -->
                                    <div class="col-md-8">
                                        <div class="p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0" style="color: #e0e0e0;">
                                                    <i class="fas fa-images"></i> Choose Your Avatar
                                                </h6>
                                                <small class="text-muted">
                                                    ${isRegistered ? 'Full Collection' : 'Guest Collection'}
                                                </small>
                                            </div>
                                            
                                            <div id="avatarGridContainer" style="max-height: 570px; overflow-y: auto; border: 1px solid #555; border-radius: 8px; padding: 15px; background: #1a1a1a;">
                                                <div class="text-center py-4">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <p class="mt-2 mb-0 text-muted">Loading content...</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Color Selection Panel -->
                            <div class="tab-pane fade" id="color-panel" role="tabpanel">
                                <div class="row g-0">
                                    <!-- Color Preview Sidebar -->
                                    <div class="col-md-4" style="background: #333; border-right: 1px solid #555; min-height: 500px;">
                                        <div class="p-4 text-center">
                                            <h6 class="mb-3" style="color: #e0e0e0;">
                                                <i class="fas fa-paint-brush"></i> Preview
                                            </h6>
                                            <div class="mb-3">
                                                
                                            <div id="colorPreviewCircle" class="mx-auto mb-3" style="width: 220px; height: 80px; border-radius: 8px; border: 3px solid rgba(255,255,255,0.3);"></div>
                                            <br>
                                                <h6 id="colorPreviewName" style="color: #e0e0e0;">Black</h6>
                                                <p class="small text-muted">Your chat bubble color</p>
                                            </div>
                                            <br>
                                            <!-- Sample Message Preview -->
                                            <div class="mb-3">
                                                <div class="sample-message-preview p-3" style="background: #222; border-radius: 12px; border: 1px solid #555;">
                                                    <small class="text-muted d-block mb-2">Message Preview:</small>
                                                    <div class="mini-message-bubble user-color-black" id="sampleMessageBubble" style="
                                                        background: var(--user-gradient);
                                                        color: var(--user-text-color) !important;
                                                        border-radius: 12px;
                                                        padding: 8px 12px;
                                                        border: 2px solid var(--user-border-color);
                                                        position: relative;
                                                        filter: hue-rotate(${currentUser.bubble_hue || 0}deg) saturate(${currentUser.bubble_saturation || 100}%);
                                                    ">
                                                        <div style="color: var(--user-text-color); font-size: 0.8rem;">
                                                            Hello! This is how your messages will look.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Bubble Customization Controls -->
                                            <div class="bubble-customization" style="background: #444; border-radius: 8px; padding: 15px;">
                                                <h6 style="color: #e0e0e0; margin-bottom: 15px;">
                                                    <i class="fas fa-sliders-h"></i> Customize Bubble Colors
                                                </h6>
                                                
                                                <!-- Bubble Hue Slider -->
                                                <div class="mb-3">
                                                    <label for="bubbleHueSlider" class="form-label" style="color: #ccc; font-size: 0.9rem;">
                                                        Hue: <span id="bubbleHueValue">${currentUser.bubble_hue || 0}</span>°
                                                    </label>
                                                    <input type="range" 
                                                           class="form-range bubble-slider" 
                                                           id="bubbleHueSlider" 
                                                           min="0" 
                                                           max="360" 
                                                           value="${currentUser.bubble_hue || 0}"
                                                           oninput="updateBubbleHue(this.value)"
                                                           style="background: linear-gradient(to right, #ff0000, #ffff00, #00ff00, #00ffff, #0000ff, #ff00ff, #ff0000);">
                                                </div>
                                                
                                                <!-- Bubble Saturation Slider -->
                                                <div class="mb-3">
                                                    <label for="bubbleSatSlider" class="form-label" style="color: #ccc; font-size: 0.9rem;">
                                                        Saturation: <span id="bubbleSatValue">${currentUser.bubble_saturation || 100}</span>%
                                                    </label>
                                                    <input type="range" 
                                                           class="form-range bubble-slider" 
                                                           id="bubbleSatSlider" 
                                                           min="0" 
                                                           max="200" 
                                                           value="${currentUser.bubble_saturation || 100}"
                                                           oninput="updateBubbleSaturation(this.value)"
                                                           style="background: linear-gradient(to right, #888, #fff);">
                                                </div>
                                                
                                                <!-- Reset Bubble Button -->
                                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="resetBubbleCustomization()">
                                                    <i class="fas fa-undo"></i> Reset Bubble Colors
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Color Grid -->
                                    <div class="col-md-8">
                                        <div class="p-4">
                                            <h6 class="mb-3" style="color: #e0e0e0;">
                                                <i class="fas fa-palette"></i> Choose Your Chat Color
                                            </h6>
                                            
                                            <div id="colorGrid" class="color-grid" style="
                                                display: grid;
                                                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
                                                gap: 15px;
                                                padding: 20px;
                                                background: #1a1a1a;
                                                border-radius: 12px;
                                                border: 1px solid #555;
                                                max-height: 400px;
                                                overflow-y: auto;
                                            ">
                                                <!-- Color options will be populated here -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Shop Panel -->
                            <div class="tab-pane fade" id="shop-panel" role="tabpanel">
                                <div class="row g-0">
                                    <div class="col-12 p-4">
                                        <h5 class="mb-4" style="color: #e0e0e0;">
                                            <i class="fas fa-store"></i> Shop
                                        </h5>
                                        
                                        <!-- Currency Display -->
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <div class="currency-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 20px; color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div>
                                                            <div style="font-size: 0.9rem; opacity: 0.9;">💎 Dura Balance</div>
                                                            <div style="font-size: 2rem; font-weight: bold;" id="shopDuraBalance">${currentUser.dura || 0}</div>
                                                        </div>
                                                        <i class="fas fa-gem fa-3x" style="opacity: 0.3;"></i>
                                                    </div>
                                                    <div class="mt-2" style="font-size: 0.8rem; opacity: 0.8;">
                                                        <i class="fas fa-clock"></i> Uhhhhhh
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="currency-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 12px; padding: 20px; color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div>
                                                            <div style="font-size: 0.9rem; opacity: 0.9;">🎫 Token Balance</div>
                                                            <div style="font-size: 2rem; font-weight: bold;" id="shopTokenBalance">${currentUser.tokens || 0}</div>
                                                        </div>
                                                        <i class="fas fa-ticket-alt fa-3x" style="opacity: 0.3;"></i>
                                                    </div>
                                                    <div class="mt-2" style="font-size: 0.8rem; opacity: 0.8;">
                                                        <i class="fas fa-clock"></i> Tokens regenerate every 12 hours
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Shop Items -->
                                        <h6 class="mb-3" style="color: #e0e0e0; border-bottom: 1px solid #555; padding-bottom: 10px;">
                                            <i class="fas fa-shopping-cart"></i> Available Items
                                        </h6>
                                        
                                        <div class="row" id="shopItemsContainer">
                                            <!--<div class="text-center py-4">
                                                <i class="fas fa-spinner fa-spin fa-2x"></i>
                                                <p class="mt-2">Loading shop items...</p>
                                            </div>-->
                                        </div>

                                        
                                        <div class="alert alert-info mt-4" style="background: rgba(23, 162, 184, 0.1); border: 1px solid rgba(23, 162, 184, 0.3); color: #17a2b8;">
                                            <i class="fas fa-info-circle"></i> <strong>Note:</strong> More items coming soon! Keep collecting Dura and Tokens.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Inventory Panel -->
                            <div class="tab-pane fade" id="inventory-panel" role="tabpanel">
                                <div class="row g-0">
                                    <div class="col-12 p-4">
                                        <h5 class="mb-4" style="color: #e0e0e0;">
                                            <i class="fas fa-box"></i> Your Inventory
                                        </h5>
                                        
                                        <div class="alert alert-info mb-4" style="background: rgba(23, 162, 184, 0.1); border: 1px solid rgba(23, 162, 184, 0.3); color: #17a2b8;">
                                            <i class="fas fa-info-circle"></i> <strong>Tip:</strong> Equip titles to show them as badges next to your name!
                                        </div>
                                        
                                        <div id="inventoryContainer">
                                            <div class="text-center py-4">
                                                <i class="fas fa-spinner fa-spin fa-2x"></i>
                                                <p class="mt-2">Loading inventory...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            ${isRegistered ? `
    <div class="tab-pane fade" id="invites-panel" role="tabpanel">
        <div class="p-4">
            <div id="invitesContent">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading...</p>
                </div>
            </div>
        </div>
    </div>
` : ''}


                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #555; background: #333;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="saveProfileChanges()">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#profileEditorModal').remove();
    $('body').append(modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('profileEditorModal'));
    modal.show();
    
    loadAvatarsForEditor(isRegistered);
    loadColorsForEditor();
    
    if (isRegistered) {
        $('#coverPhotoInput').on('change', function() {
            const file = this.files[0];
            if (file) {
                uploadCoverPhoto(file);
            }
        });
    }
}

function loadAvatarsForEditor(isRegistered) {
    $.ajax({
        url: 'api/get_organized_avatars.php',
        method: 'GET',
        data: { user_type: isRegistered ? 'registered' : 'guest' },
        dataType: 'json',
        success: function(response) {
            displayAvatarsInEditor(response, isRegistered);
        },
        error: function() {
            $('#avatarGridContainer').html(`
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                    <p class="text-muted">Error loading content</p>
                </div>
            `);
        }
    });
}

function displayAvatarsInEditor(avatarData, isRegistered) {
    let html = '';
    let totalAvatars = 0;
    
    if (!isRegistered) {
        ['time-limited', 'community', 'default', 'mushoku', 'secret', 'drrrjp', 'drrrkari', 'drrrx2', 'drrrcom'].forEach(folder => {
            if (avatarData[folder] && avatarData[folder].length > 0) {
                html += createAvatarSection(folder, avatarData[folder]);
                totalAvatars += avatarData[folder].length;
            }
        });
    } else {
        ['time-limited', 'community', 'default', 'mushoku', 'secret', 'drrrjp', 'drrrkari', 'drrrx2',  'drrrcom'].forEach(folder => {
            if (avatarData[folder] && avatarData[folder].length > 0) {
                html += createAvatarSection(folder, avatarData[folder], true);
                totalAvatars += avatarData[folder].length;
            }
        });
        
        Object.keys(avatarData).forEach(folder => {
            if (!['time-limited', 'community', 'default', 'mushoku', 'secret', 'drrrjp', 'drrrkari', 'drrrx2',  'drrrcom'].includes(folder) && avatarData[folder].length > 0) {
                html += createAvatarSection(folder, avatarData[folder]);
                totalAvatars += avatarData[folder].length;
            }
        });
    }
    
    if (html === '') {
        html = `
            <div class="text-center py-4">
                <i class="fas fa-images fa-2x text-muted mb-2"></i>
                <p class="text-muted">No avatars available</p>
            </div>
        `;
    }
    
    $('#avatarGridContainer').html(html);
    updateAvatarStats();
    
    const currentAvatarPath = currentUser.avatar || 'default_avatar.jpg';
    $(`.editor-avatar[data-avatar="${currentAvatarPath}"]`).addClass('selected');
}

function createAvatarSection(folderName, avatars, isPriority = false) {
    const displayName = folderName.charAt(0).toUpperCase() + folderName.slice(1).replace('-', ' ');
    const iconClass = isPriority ? 'fas fa-star' : 'fas fa-folder';
    
    let html = `
        <div class="avatar-section mb-4" data-folder="${folderName}">
            <h6 style="color: #667eea; font-weight: 600; margin-bottom: 15px; padding: 8px 12px; background: #333; border-radius: 6px;">
                <i class="${iconClass}"></i> ${displayName} 
                <span class="badge bg-secondary ms-2">${avatars.length}</span>
            </h6>
            <div class="d-flex flex-wrap justify-content-center">
    `;
    
    avatars.forEach(avatar => {
        html += `
            <img src="images/${avatar}" 
                 class="editor-avatar" 
                 data-avatar="${avatar}"
                 onclick="selectAvatarInEditor('${avatar}')"
                 style="width: 60px; height: 60px; margin: 3px; border: 2px solid #555; border-radius: 6px; cursor: pointer; transition: all 0.2s ease;"
                 onmouseover="this.style.borderColor='#007bff'; this.style.transform='scale(1.05)'"
                 onmouseout="this.style.borderColor='#555'; this.style.transform='scale(1)'"
                 alt="Avatar option">
        `;
    });
    
    html += `</div></div>`;
    return html;
}

function loadColorsForEditor() {
    const colors = [
        { name: 'black', displayName: 'Black' },
        { name: 'policeman2', displayName: 'Black?' },
        { name: 'negative', displayName: 'Negative' },
        { name: 'cnegative', displayName: 'Color-Negative' },
        { name: 'caution', displayName: 'Caution' },
        { name: 'gray', displayName: 'Gray' },
        { name: 'darkgray', displayName: 'Dark Gray' },
        { name: 'tan', displayName: 'Tan' },
        { name: 'blue', displayName: 'Blue' },
        { name: 'cobalt', displayName: 'Cobalt' },
        { name: 'teal2', displayName: 'Teal' },
        { name: 'navy', displayName: 'Navy' },
        { name: 'cyan', displayName: 'Cyan' },
        { name: 'purple', displayName: 'Purple' },
        { name: 'lavender', displayName: 'Lavender' },
        { name: 'lavender2', displayName: 'Lavender2' },
        { name: 'pink', displayName: 'Pink' },
        { name: 'orange', displayName: 'Orange' },
        { name: 'orange2', displayName: 'Blorange' },
        { name: 'peach', displayName: 'Peach' },
        { name: 'green', displayName: 'Green' },
        { name: 'urban', displayName: 'Urban' },
        { name: 'mudgreen', displayName: 'Mud Green' },
        { name: 'palegreen', displayName: 'Pale Green' },
        { name: 'red', displayName: 'Red' },
        { name: 'toyred', displayName: 'Toy Red' },
        { name: 'rose', displayName: 'Rose' },
        { name: 'yellow', displayName: 'Yellow' },
        { name: 'bbyellow', displayName: 'Yellow2' },
        { name: 'brown', displayName: 'Brown' },
        { name: 'deepbrown', displayName: 'Brown2' },
        { name: 'chiipink', displayName: 'Brown2' },
        { name: 'forest', displayName: 'Brown2' },
        { name: 'babyblue', displayName: 'Babyblue' },
        { name: 'rust', displayName: 'Rust' },
        { name: 'sepia', displayName: 'Sepia' },
        { name: 'spooky', displayName: 'Spooky' },
        { name: 'spooky2', displayName: 'Spooky' },
        { name: 'spooky3', displayName: 'Spooky' },
        { name: 'spooky4', displayName: 'Spooky' },
        { name: 'spooky5', displayName: 'Spooky' },
        { name: 'spooky6', displayName: 'Spooky' },
    ];
    
    let html = '';
    colors.forEach(color => {
        const isSelected = currentUser.color === color.name ? 'selected' : '';
        html += `
            <div class="color-option color-${color.name} ${isSelected}" 
                 data-color="${color.name}" 
                 onclick="selectColorInEditor('${color.name}')"
                 style="position: relative; width: 70px; height: 70px; border-radius: 8px; border: 3px solid ${isSelected ? '#fff' : 'rgba(255,255,255,0.4)'}; cursor: pointer; display: flex; align-items: center; justify-content: center; background-size: 200% 200%; background-position: center; box-shadow: 0 4px 12px rgba(0,0,0,0.3); transition: all 0.2s ease;">
                <div class="color-name" style="background: rgba(0,0,0,0.5); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.8);">
                    ${color.displayName}
                </div>
                ${isSelected ? `
                <div class="selected-indicator" style="position: absolute; top: -8px; right: -8px; background: #28a745; color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; border: 2px solid #2a2a2a;">
                    <i class="fas fa-check"></i>
                </div>
                ` : ''}
            </div>
        `;
    });
    
    $('#colorGrid').html(html);
    updateColorPreview();
}

function selectAvatarInEditor(avatarPath) {
    $('.editor-avatar').removeClass('selected').css({
        'border-color': '#555',
        'box-shadow': 'none'
    });
    
    $(`.editor-avatar[data-avatar="${avatarPath}"]`).addClass('selected').css({
        'border-color': '#007bff',
        'box-shadow': '0 0 15px rgba(0, 123, 255, 0.5)'
    });
    
    $('#selectedAvatarPreview').attr('src', 'images/' + avatarPath);
    selectedAvatar = avatarPath;
    
    updateAvatarPreview();
}

function selectColorInEditor(colorName) {
    $('.color-option').removeClass('selected').css('border-color', 'rgba(255,255,255,0.4)');
    $('.color-option .selected-indicator').remove();
    
    const colorElement = $(`.color-option[data-color="${colorName}"]`);
    colorElement.addClass('selected').css('border-color', '#fff');
    colorElement.append(`
        <div class="selected-indicator" style="position: absolute; top: -8px; right: -8px; background: #28a745; color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; border: 2px solid #2a2a2a;">
            <i class="fas fa-check"></i>
        </div>
    `);
    
    selectedColor = colorName;
    updateColorPreview();
}

function updateColorPreview() {
    const color = selectedColor || currentUser.color || 'black';
    $('#colorPreviewCircle').removeClass().addClass(`color-${color}`);
    $('#colorPreviewName').text(color.charAt(0).toUpperCase() + color.slice(1));
    
    const bubbleHue = selectedBubbleHue !== null ? selectedBubbleHue : (currentUser.bubble_hue || 0);
    const bubbleSat = selectedBubbleSaturation !== null ? selectedBubbleSaturation : (currentUser.bubble_saturation || 100);
    
    $('#sampleMessageBubble').removeClass().addClass(`mini-message-bubble user-color-${color}`)
        .css('filter', `hue-rotate(${bubbleHue}deg) saturate(${bubbleSat}%)`);
}

function updateAvatarStats() {
    const totalAvatars = $('.editor-avatar').length;
    const visibleAvatars = $('.editor-avatar:visible').length;
    $('#totalAvatarCount').text(totalAvatars);
    $('#visibleAvatarCount').text(visibleAvatars);
}

function clearAvatarSelection() {
    $('.editor-avatar').removeClass('selected').css({
        'border-color': '#555',
        'box-shadow': 'none'
    });
    
    selectedAvatar = null;
    $('#selectedAvatarPreview').attr('src', 'images/' + (currentUser.avatar || 'default_avatar.jpg'));
}

function randomAvatarSelection() {
    const visibleAvatars = $('.editor-avatar:visible');
    if (visibleAvatars.length > 0) {
        const randomIndex = Math.floor(Math.random() * visibleAvatars.length);
        const randomAvatar = $(visibleAvatars[randomIndex]);
        const avatarPath = randomAvatar.data('avatar');
        selectAvatarInEditor(avatarPath);
    }
}

function saveProfileChanges() {
    const isRegistered = currentUser.type === 'user';
    const changes = {};
    let hasChanges = false;
    
    // Check for name change
    const newName = $('#profileNameInput').val().trim();
    const currentName = isRegistered ? currentUser.username : currentUser.name;
    
    if (newName && newName !== currentName) {
        changes.name = newName;
        hasChanges = true;
    }
    
    // ... rest of existing code for avatar, color, etc.
    
    if (selectedAvatar && selectedAvatar !== currentUser.avatar) {
        changes.avatar = selectedAvatar;
        hasChanges = true;
    }
    
    if (selectedColor && selectedColor !== currentUser.color) {
        changes.color = selectedColor;
        hasChanges = true;
    }
    
    if (selectedAvatarHue !== null && selectedAvatarHue !== (currentUser.avatar_hue || 0)) {
        changes.avatar_hue = selectedAvatarHue;
        hasChanges = true;
    }
    
    if (selectedAvatarSaturation !== null && selectedAvatarSaturation !== (currentUser.avatar_saturation || 100)) {
        changes.avatar_saturation = selectedAvatarSaturation;
        hasChanges = true;
    }
    
    if (selectedBubbleHue !== null && selectedBubbleHue !== (currentUser.bubble_hue || 0)) {
        changes.bubble_hue = selectedBubbleHue;
        hasChanges = true;
    }
    
    if (selectedBubbleSaturation !== null && selectedBubbleSaturation !== (currentUser.bubble_saturation || 100)) {
        changes.bubble_saturation = selectedBubbleSaturation;
        hasChanges = true;
    }
    
    if (isRegistered) {
        const bio = $('#profileBio').val();
        const status = $('#profileStatus').val();
        
        const links = [];
        $('.link-input-group').each(function() {
            const title = $(this).find('[data-link-field="title"]').val().trim();
            const url = $(this).find('[data-link-field="url"]').val().trim();
            if (title && url) {
                links.push({ title, url });
            }
        });
        
        changes.bio = bio;
        changes.status = status;
        changes.hyperlinks = JSON.stringify(links);
        hasChanges = true;
    }
    
    if (!hasChanges) {
        $('#profileEditorModal').modal('hide');
        return;
    }
    
    const saveBtn = $('.modal-footer .btn-primary');
    const originalText = saveBtn.html();
    saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
    
    const savePromises = [];
    
    // Add name update promise
    if (changes.name) {
        savePromises.push(
            $.ajax({
                url: 'api/update_name.php',
                method: 'POST',
                data: { name: changes.name },
                dataType: 'json'
            })
        );
    }
    
    if (changes.avatar) {
        savePromises.push(
            $.ajax({
                url: 'api/update_avatar.php',
                method: 'POST',
                data: { avatar: changes.avatar },
                dataType: 'json'
            })
        );
    }
    
    if (changes.color) {
        savePromises.push(
            $.ajax({
                url: 'api/update_user_color.php',
                method: 'POST',
                data: { color: changes.color },
                dataType: 'json'
            })
        );
    }
    
    // ... rest of existing save code for avatar customization, profile info, etc.
    
    if (changes.avatar_hue !== undefined || changes.avatar_saturation !== undefined || 
        changes.bubble_hue !== undefined || changes.bubble_saturation !== undefined) {
        const customData = {};
        if (changes.avatar_hue !== undefined) customData.avatar_hue = changes.avatar_hue;
        if (changes.avatar_saturation !== undefined) customData.avatar_saturation = changes.avatar_saturation;
        if (changes.bubble_hue !== undefined) customData.bubble_hue = changes.bubble_hue;
        if (changes.bubble_saturation !== undefined) customData.bubble_saturation = changes.bubble_saturation;
        
        savePromises.push(
            $.ajax({
                url: 'api/update_avatar_customization.php',
                method: 'POST',
                data: customData,
                dataType: 'json'
            })
        );
    }
    
    if (isRegistered && (changes.bio || changes.status || changes.hyperlinks)) {
        savePromises.push(
            $.ajax({
                url: 'api/update_user_profile.php',
                method: 'POST',
                data: {
                    bio: changes.bio,
                    status: changes.status,
                    hyperlinks: changes.hyperlinks
                },
                dataType: 'json'
            })
        );
    }
    
    Promise.all(savePromises)
        .then(responses => {
            const allSuccess = responses.every(r => r.status === 'success');
            
            if (allSuccess) {
                // Update local currentUser object
                if (changes.name) {
                    if (isRegistered) {
                        currentUser.username = changes.name;
                    } else {
                        currentUser.name = changes.name;
                    }
                }
                if (changes.avatar) {
                    currentUser.avatar = changes.avatar;
                    $('#currentAvatar').attr('src', 'images/' + changes.avatar);
                }
                if (changes.color) {
                    currentUser.color = changes.color;
                }
                if (changes.avatar_hue !== undefined) {
                    currentUser.avatar_hue = changes.avatar_hue;
                }
                if (changes.avatar_saturation !== undefined) {
                    currentUser.avatar_saturation = changes.avatar_saturation;
                }
                if (changes.bubble_hue !== undefined) {
                    currentUser.bubble_hue = changes.bubble_hue;
                }
                if (changes.bubble_saturation !== undefined) {
                    currentUser.bubble_saturation = changes.bubble_saturation;
                }
                
                // Update the main avatar with new filters
                if (changes.avatar_hue !== undefined || changes.avatar_saturation !== undefined) {
                    const newHue = currentUser.avatar_hue || 0;
                    const newSat = currentUser.avatar_saturation || 100;
                    $('#currentAvatar').css('filter', `hue-rotate(${newHue}deg) saturate(${newSat}%)`);
                }
                
                $('#profileEditorModal').modal('hide');
                showProfileSuccessMessage(changes);
                
                if (typeof loadOnlineUsers === 'function') {
                    loadOnlineUsers();
                }
                if (typeof loadRoomsWithUsers === 'function') {
                    loadRoomsWithUsers();
                }
            } else {
                throw new Error('Some updates failed');
            }
        })
        .catch(error => {
            console.error('Error saving profile changes:', error);
            alert('Error saving changes. Please try again.');
        })
        .finally(() => {
            saveBtn.prop('disabled', false).html(originalText);
        });
}

function showProfileSuccessMessage(changes) {
    let message = 'Profile updated successfully!';
    
    const changedItems = [];
    if (changes.name) changedItems.push('name');
    if (changes.avatar) changedItems.push('avatar');
    if (changes.color) changedItems.push('chat color');
    if (changes.avatar_hue !== undefined || changes.avatar_saturation !== undefined) changedItems.push('avatar colors');
    if (changes.bubble_hue !== undefined || changes.bubble_saturation !== undefined) changedItems.push('bubble colors');
    
    if (changedItems.length > 0) {
        if (changedItems.length === 1) {
            message = `${changedItems[0].charAt(0).toUpperCase() + changedItems[0].slice(1)} updated successfully!`;
        } else if (changedItems.length === 2) {
            message = `${changedItems[0].charAt(0).toUpperCase() + changedItems[0].slice(1)} and ${changedItems[1]} updated successfully!`;
        } else {
            message = `${changedItems.slice(0, -1).join(', ')} and ${changedItems[changedItems.length - 1]} updated successfully!`;
        }
    }
    
    const toast = `
        <div class="toast align-items-center text-white bg-success border-0" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 1080;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    $('body').append(toast);
    const toastElement = $('.toast').last()[0];
    const bootstrapToast = new bootstrap.Toast(toastElement);
    bootstrapToast.show();
    
    $(toastElement).on('hidden.bs.toast', function() {
        $(this).remove();
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

let selectedAvatarHue = null;
let selectedAvatarSaturation = null;
let selectedBubbleHue = null;
let selectedBubbleSaturation = null;

function updateAvatarHue(value) {
    selectedAvatarHue = parseInt(value);
    $('#avatarHueValue').text(value);
    updateAvatarPreview();
}

function updateAvatarSaturation(value) {
    selectedAvatarSaturation = parseInt(value);
    $('#avatarSatValue').text(value);
    updateAvatarPreview();
}

function updateBubbleHue(value) {
    selectedBubbleHue = parseInt(value);
    $('#bubbleHueValue').text(value);
    updateBubblePreview();
}

function updateBubbleSaturation(value) {
    selectedBubbleSaturation = parseInt(value);
    $('#bubbleSatValue').text(value);
    updateBubblePreview();
}

function updateAvatarPreview() {
    const hue = selectedAvatarHue !== null ? selectedAvatarHue : (currentUser.avatar_hue || 0);
    const saturation = selectedAvatarSaturation !== null ? selectedAvatarSaturation : (currentUser.avatar_saturation || 100);
    
    const filterValue = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
    $('#selectedAvatarPreview').css('filter', filterValue);
    
    $('.editor-avatar.selected').css('filter', filterValue);
}

function updateBubblePreview() {
    const hue = selectedBubbleHue !== null ? selectedBubbleHue : (currentUser.bubble_hue || 0);
    const saturation = selectedBubbleSaturation !== null ? selectedBubbleSaturation : (currentUser.bubble_saturation || 100);
    
    const filterValue = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
    $('#sampleMessageBubble').css('filter', filterValue);
}

function resetAvatarCustomization() {
    selectedAvatarHue = 0;
    selectedAvatarSaturation = 100;
    
    $('#avatarHueSlider').val(0);
    $('#avatarSatSlider').val(100);
    $('#avatarHueValue').text('0');
    $('#avatarSatValue').text('100');
    
    updateAvatarPreview();
}

function resetBubbleCustomization() {
    selectedBubbleHue = 0;
    selectedBubbleSaturation = 100;
    
    $('#bubbleHueSlider').val(0);
    $('#bubbleSatSlider').val(100);
    $('#bubbleHueValue').text('0');
    $('#bubbleSatValue').text('100');
    
    updateBubblePreview();
}

function loadInvitesAndKeys() {
    $.ajax({
        url: 'api/get_invites_keys.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayInvitesAndKeys(response);
            }
        },
        error: function() {
            $('#invitesContent').html('<div class="alert alert-danger">Failed to load invites</div>');
        }
    });
}

function displayInvitesAndKeys(data) {
    let html = '';
    
    // Restricted warning
    if (data.is_restricted) {
        html += `<div class="alert alert-danger">
            <i class="fas fa-ban me-2"></i>Your account is restricted. All codes and keys are invalid.
        </div>`;
    }
    
    // Stats
    html += `<div class="alert alert-info">
        <i class="fas fa-chart-line me-2"></i>
        <strong>Stats:</strong> ${data.stats.total_invites} people used your codes | 
        ${data.stats.accounts_created} accounts created
    </div>`;
    
    // Invite Codes Section
    html += `<div class="mb-4">
        <h5><i class="fas fa-ticket-alt me-2"></i>Your Invite Codes</h5>
        <p class="text-muted small">You have ${data.invite_codes.length} active codes. Share these to invite others.</p>
        <div class="row">`;
    
    data.invite_codes.forEach(code => {
        html += `
            <div class="col-md-6 mb-3">
                <div class="code-box" style="background: #333; border: 1px solid #555; padding: 15px; border-radius: 8px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <code style="font-size: 1.1rem; color: #4CAF50;">${code.code}</code>
                        <button class="btn btn-sm btn-outline-light" onclick="copyToClipboard('${code.code}')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Regenerates: ${new Date(code.regenerates_at).toLocaleDateString()}
                    </small>
                </div>
            </div>
        `;
    });
    
    html += `</div></div>`;
    
    // Personal Keys Section
    html += `<div class="mb-4">
        <h5><i class="fas fa-key me-2"></i>Personal Keys</h5>
        <p class="text-muted small">Create custom keys for instant auto-login. Keys are alphanumeric only (6-64 chars).</p>
        
        <div class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" id="customKeyInput" placeholder="Enter custom key (letters & numbers only)" maxlength="64" style="background: #333; border: 1px solid #555; color: #fff;">
                <button class="btn btn-primary" onclick="createCustomKey()">
                    <i class="fas fa-plus me-1"></i>Create Key
                </button>
            </div>
            <small class="text-muted">Example: MySecretKey2024</small>
        </div>
        
        <div class="keys-list">`;
    
    if (data.personal_keys.length === 0) {
        html += '<p class="text-muted"><em>No personal keys created yet.</em></p>';
    } else {
        data.personal_keys.forEach(key => {
            html += `
                <div class="key-item mb-2" style="background: #333; border: 1px solid #555; padding: 12px; border-radius: 8px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <code style="color: #2196F3; font-size: 1rem;">${key.key_value}</code>
                            <br>
                            <small class="text-muted">
                                Created: ${new Date(key.created_at).toLocaleDateString()}
                                ${key.last_used ? ' | Last used: ' + new Date(key.last_used).toLocaleDateString() : ''}
                            </small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-light me-1" onclick="copyToClipboard('${key.key_value}')">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePersonalKey(${key.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    html += `</div></div>`;
    
    $('#invitesContent').html(html);
}

function createCustomKey() {
    const customKey = $('#customKeyInput').val().trim();
    
    if (!customKey) {
        alert('Please enter a key');
        return;
    }
    
    if (!/^[a-zA-Z0-9]+$/.test(customKey)) {
        alert('Key can only contain letters and numbers');
        return;
    }
    
    if (customKey.length < 6 || customKey.length > 64) {
        alert('Key must be 6-64 characters long');
        return;
    }
    
    $.ajax({
        url: 'api/create_custom_key.php',
        method: 'POST',
        data: { action: 'create', custom_key: customKey },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('Personal key created successfully!');
                $('#customKeyInput').val('');
                loadInvitesAndKeys(); // Reload
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('Error creating key');
        }
    });
}

function deletePersonalKey(keyId) {
    if (!confirm('Delete this personal key? This cannot be undone.')) {
        return;
    }
    
    $.ajax({
        url: 'api/create_custom_key.php',
        method: 'POST',
        data: { action: 'delete', key_id: keyId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('Key deleted');
                loadInvitesAndKeys(); // Reload
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('Error deleting key');
        }
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show temporary success message
        const btn = event.target.closest('button');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
            btn.innerHTML = originalHtml;
        }, 1000);
    });
}
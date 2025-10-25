// js/news_modal.js - News Modal System with tabs for Announcements, Events, and Updates

function showNewsModal() {
    // Remove existing modal if present
    $('#newsModal').remove();
    
    const modalHtml = `
        <div class="modal fade" id="newsModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="background: #333; border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-newspaper"></i> News & Updates
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-3" id="newsTabs" role="tablist" style="border-bottom: 1px solid #444;">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="announcements-tab" data-bs-toggle="tab" 
                                        data-bs-target="#announcements" type="button" role="tab"
                                        style="color: #fff; background: transparent; border: none;">
                                    <i class="fas fa-bullhorn"></i> Announcements
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="events-tab" data-bs-toggle="tab" 
                                        data-bs-target="#events" type="button" role="tab"
                                        style="color: #fff; background: transparent; border: none;">
                                    <i class="fas fa-calendar-alt"></i> Events
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="updates-tab" data-bs-toggle="tab" 
                                        data-bs-target="#updates" type="button" role="tab"
                                        style="color: #fff; background: transparent; border: none;">
                                    <i class="fas fa-sync-alt"></i> Updates
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="newsTabContent">
                            <!-- Announcements -->
                            <div class="tab-pane fade show active" id="announcements" role="tabpanel">
                                <div id="announcementsContent" class="news-content">
                                    <div class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                        <p>Loading announcements...</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Events -->
                            <div class="tab-pane fade" id="events" role="tabpanel">
                                <div id="eventsContent" class="news-content">
                                    <div class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                        <p>Loading events...</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Updates -->
                            <div class="tab-pane fade" id="updates" role="tabpanel">
                                <div id="updatesContent" class="news-content">
                                    <div class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                        <p>Loading updates...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #333; border-top: 1px solid #444;">
                        ${(typeof currentUser !== 'undefined' && (currentUser.is_admin || currentUser.is_moderator)) ? 
                            '<button type="button" class="btn btn-primary" onclick="showCreatePostModal()"><i class="fas fa-plus"></i> Add Post</button>' : 
                            ''}
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modalHtml);
    const newsModal = new bootstrap.Modal(document.getElementById('newsModal'));
    newsModal.show();
    
    // Load content for each tab
    loadNewsPosts('announcements');
    
    // Set up tab change listeners
    $('#events-tab').on('click', function() {
        if ($('#eventsContent').find('.fa-spinner').length > 0) {
            loadNewsPosts('events');
        }
    });
    
    $('#updates-tab').on('click', function() {
        if ($('#updatesContent').find('.fa-spinner').length > 0) {
            loadNewsPosts('updates');
        }
    });
}

function loadNewsPosts(category) {
    const contentId = category + 'Content';
    
    $.ajax({
        url: 'api/news_posts.php',
        method: 'GET',
        data: { category: category },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayNewsPosts(contentId, response.posts, category);
            } else {
                $('#' + contentId).html(`
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <p class="text-muted">${response.message || 'Error loading posts'}</p>
                    </div>
                `);
            }
        },
        error: function() {
            $('#' + contentId).html(`
                <div class="text-center py-4">
                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                    <p class="text-muted">Failed to load ${category}</p>
                </div>
            `);
        }
    });
}

function displayNewsPosts(contentId, posts, category) {
    let html = '';
    
    if (posts.length === 0) {
        html = `
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                <p class="text-muted">No ${category} to display</p>
            </div>
        `;
    } else {
        posts.forEach(post => {
            const date = new Date(post.created_at).toLocaleDateString();
            html += `
                <div class="news-post" style="background: #333; border: 1px solid #444; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0" style="color: #4a9eff;">${post.title}</h6>
                        <small class="text-muted">${date}</small>
                    </div>
                    <div class="news-post-content" style="color: #e0e0e0; line-height: 1.6;">
                        ${post.content}
                    </div>
                    ${(typeof currentUser !== 'undefined' && (currentUser.is_admin || currentUser.is_moderator)) ? 
                        `<div class="mt-2">
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteNewsPost('${post.filename}', '${category}')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>` : ''}
                </div>
            `;
        });
    }
    
    $('#' + contentId).html(html);
}

function showCreatePostModal() {
    const createModalHtml = `
        <div class="modal fade" id="createPostModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #fff;">
                    <div class="modal-header" style="background: #333; border-bottom: 1px solid #444;">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle"></i> Create News Post
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="createPostForm">
                            <div class="mb-3">
                                <label for="postCategory" class="form-label">Category</label>
                                <select class="form-select" id="postCategory" required style="background: #333; border: 1px solid #555; color: #fff;">
                                    <option value="announcements">Announcements</option>
                                    <option value="events">Events</option>
                                    <option value="updates">Updates</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="postTitle" class="form-label">Title</label>
                                <input type="text" class="form-control" id="postTitle" required 
                                       style="background: #333; border: 1px solid #555; color: #fff;"
                                       placeholder="Post title">
                            </div>
                            <div class="mb-3">
                                <label for="postImages" class="form-label">
                                    <i class="fas fa-image"></i> Upload Images (optional)
                                </label>
                                <input type="file" class="form-control" id="postImages" multiple accept="image/*"
                                       style="background: #333; border: 1px solid #555; color: #fff;">
                                <small class="text-muted">Upload images to include in your post. Max 5MB per image.</small>
                                <div id="imagePreview" class="mt-2"></div>
                            </div>
                            <div class="mb-3">
                                <label for="postContent" class="form-label">Content (Markdown supported)</label>
                                <textarea class="form-control" id="postContent" rows="8" required
                                          style="background: #333; border: 1px solid #555; color: #fff; font-family: monospace;"
                                          placeholder="Write your post content here...&#10;&#10;Markdown formatting:&#10;**bold** *italic* __underline__&#10;# Heading&#10;- List item&#10;&#10;For images:&#10;![alt text](image-url)&#10;Or upload images above to auto-insert"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer" style="background: #333; border-top: 1px solid #444;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="createNewsPost()">
                            <i class="fas fa-save"></i> Create Post
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(createModalHtml);
    const createModal = new bootstrap.Modal(document.getElementById('createPostModal'));
    createModal.show();
    
    // Image preview handler
    $('#postImages').on('change', function(e) {
        const files = e.target.files;
        const previewContainer = $('#imagePreview');
        previewContainer.empty();
        
        if (files.length > 0) {
            previewContainer.append('<div class="d-flex flex-wrap gap-2 mt-2"></div>');
            const flexContainer = previewContainer.find('.d-flex');
            
            Array.from(files).forEach((file, index) => {
                if (file.size > 5 * 1024 * 1024) {
                    alert(`Image "${file.name}" is too large (max 5MB)`);
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    flexContainer.append(`
                        <div class="position-relative" style="width: 80px; height: 80px;">
                            <img src="${e.target.result}" class="img-thumbnail" 
                                 style="width: 100%; height: 100%; object-fit: cover;">
                            <small class="d-block text-center text-muted" style="font-size: 0.7rem;">Image ${index + 1}</small>
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            });
        }
    });
    
    // Clean up on close
    $('#createPostModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

function createNewsPost() {
    const category = $('#postCategory').val();
    const title = $('#postTitle').val().trim();
    let content = $('#postContent').val().trim();
    const imageFiles = $('#postImages')[0].files;
    
    if (!title || !content) {
        alert('Please fill in all fields');
        return;
    }
    
    // If images are uploaded, upload them first
    if (imageFiles.length > 0) {
        const formData = new FormData();
        formData.append('category', category);
        
        for (let i = 0; i < imageFiles.length; i++) {
            formData.append('images[]', imageFiles[i]);
        }
        
        // Show loading state
        const createBtn = $('#createPostModal .btn-primary');
        const originalText = createBtn.html();
        createBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading images...');
        
        // Upload images first
        $.ajax({
            url: 'api/upload_news_images.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Add image markdown to content
                    content += '\n\n';
                    response.images.forEach((img, index) => {
                        content += `![Image ${index + 1}](${img.url})\n`;
                    });
                    
                    // Now create the post with images
                    submitNewsPost(category, title, content, createBtn, originalText);
                } else {
                    createBtn.prop('disabled', false).html(originalText);
                    alert('Error uploading images: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                createBtn.prop('disabled', false).html(originalText);
                alert('Failed to upload images. Please try again.');
            }
        });
    } else {
        // No images, just create the post
        submitNewsPost(category, title, content);
    }
}

function submitNewsPost(category, title, content, createBtn, originalText) {
    if (createBtn) {
        createBtn.html('<i class="fas fa-spinner fa-spin"></i> Creating post...');
    }
    
    $.ajax({
        url: 'api/create_news_post.php',
        method: 'POST',
        data: {
            category: category,
            title: title,
            content: content
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Close create modal
                bootstrap.Modal.getInstance(document.getElementById('createPostModal')).hide();
                
                // Reload the posts for that category
                loadNewsPosts(category);
                
                // Switch to the appropriate tab
                $('#' + category + '-tab').click();
                
                alert('Post created successfully!');
            } else {
                if (createBtn) {
                    createBtn.prop('disabled', false).html(originalText);
                }
                alert('Error: ' + (response.message || 'Failed to create post'));
            }
        },
        error: function() {
            if (createBtn) {
                createBtn.prop('disabled', false).html(originalText);
            }
            alert('Failed to create post. Please try again.');
        }
    });
}

function deleteNewsPost(filename, category) {
    if (!confirm('Are you sure you want to delete this post?')) {
        return;
    }
    
    $.ajax({
        url: 'api/delete_news_post.php',
        method: 'POST',
        data: {
            filename: filename,
            category: category
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                loadNewsPosts(category);
                alert('Post deleted successfully');
            } else {
                alert('Error: ' + (response.message || 'Failed to delete post'));
            }
        },
        error: function() {
            alert('Failed to delete post. Please try again.');
        }
    });
}

/*function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}*/
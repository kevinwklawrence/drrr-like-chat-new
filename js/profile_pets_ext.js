// js/profile_pets_ext.js - Extension for Profile System to Display Pets

/**
 * This file extends the existing profile system to include pet display in modals
 * Include this file after the main profile_system.js
 */

// Override or extend the existing openUserProfile function
(function() {
    // Store original function if it exists
    const originalOpenUserProfile = window.openUserProfile;
    
    // New enhanced function
    window.openUserProfile = function(username) {
        fetch(`api/get_user_profile.php?username=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showProfileModal(data.user);
                } else {
                    console.error('Failed to load profile');
                }
            })
            .catch(error => {
                console.error('Error loading profile:', error);
            });
    };
    
    function showProfileModal(user) {
        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="userProfileModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content" style="background: #2a2a2a; border: 1px solid #444; color: #e0e0e0;">
                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); border-bottom: 1px solid #555;">
                            <h5 class="modal-title">
                                <i class="fas fa-user"></i> ${escapeHtml(user.username)}'s Profile
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Avatar and Basic Info -->
                            <div class="text-center mb-3">
                                <img src="${user.avatar || 'images/default/default_avatar.png'}" 
                                     alt="${user.username}" 
                                     style="width: 80px; height: 80px; border-radius: 8px; border: 2px solid #667eea;"
                                     onerror="this.src='images/default/default_avatar.png'">
                                <h4 class="mt-2">${escapeHtml(user.username)}</h4>
                                ${user.status ? `<p class="text-muted fst-italic">"${escapeHtml(user.status)}"</p>` : ''}
                            </div>
                            
                            <!-- Titles -->
                            ${user.titles && user.titles.length > 0 ? `
                                <div class="mb-3">
                                    <h6><i class="fas fa-award"></i> Titles</h6>
                                    <div>
                                        ${user.titles.map(title => `
                                            <span class="badge bg-${getRarityColor(title.rarity)} me-1">
                                                ${title.icon} ${title.name}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}
                            
                            <!-- Bio -->
                            ${user.bio ? `
                                <div class="mb-3">
                                    <h6><i class="fas fa-info-circle"></i> Bio</h6>
                                    <p class="text-muted">${escapeHtml(user.bio)}</p>
                                </div>
                            ` : ''}
                            
                            <!-- Links -->
                            ${user.hyperlinks && user.hyperlinks.length > 0 ? `
                                <div class="mb-3">
                                    <h6><i class="fas fa-link"></i> Links</h6>
                                    ${user.hyperlinks.map(link => `
                                        <a href="${link.url}" target="_blank" class="btn btn-sm btn-outline-primary me-1 mb-1">
                                            <i class="fas fa-external-link-alt"></i> ${escapeHtml(link.label || 'Link')}
                                        </a>
                                    `).join('')}
                                </div>
                            ` : ''}
                            
                            <!-- Stats -->
                            <div class="mb-3">
                                <h6><i class="fas fa-chart-bar"></i> Stats</h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #333; padding: 0.8rem; border-radius: 8px;">
                                            <div class="stat-value" style="font-size: 1.5rem; font-weight: bold; color: #667eea;">
                                                ${user.stats?.message_count || 0}
                                            </div>
                                            <div class="stat-label" style="font-size: 0.8rem; color: #999;">Messages</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #333; padding: 0.8rem; border-radius: 8px;">
                                            <div class="stat-value" style="font-size: 1.5rem; font-weight: bold; color: #667eea;">
                                                ${user.stats?.rooms_created || 0}
                                            </div>
                                            <div class="stat-label" style="font-size: 0.8rem; color: #999;">Rooms Created</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #333; padding: 0.8rem; border-radius: 8px;">
                                            <div class="stat-value" style="font-size: 1.5rem; font-weight: bold; color: #667eea;">
                                                ${user.badge_count || 0}
                                            </div>
                                            <div class="stat-label" style="font-size: 0.8rem; color: #999;">Badges</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dura-mates (Pets) -->
                            ${user.pets && user.pets.length > 0 ? `
                                <div class="profile-pets-section">
                                    <h6><i class="fas fa-paw"></i> Dura-mates</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        ${user.pets.map(pet => `
                                            <div class="profile-pet-mini">
                                                <img src="${pet.image_url}" alt="${pet.custom_name}"
                                                     onerror="this.src='images/pets/default.png'">
                                                <div class="pet-name">${escapeHtml(pet.custom_name)}</div>
                                                <div class="bond-level">Lvl ${pet.bond_level}</div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}
                            
                            <!-- Member Since -->
                            <div class="mt-3 text-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> Member since ${formatDate(user.created_at)}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        const existingModal = document.getElementById('userProfileModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Append new modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('userProfileModal'));
        modal.show();
        
        // Cleanup on close
        document.getElementById('userProfileModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }
    
    function getRarityColor(rarity) {
        const colors = {
            'common': 'secondary',
            'rare': 'primary',
            'strange': 'info',
            'legendary': 'warning',
            'event': 'danger'
        };
        return colors[rarity] || 'secondary';
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'Unknown';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
})();

// Make mini profile cards clickable to open modal (for chat/lounge pages)
document.addEventListener('DOMContentLoaded', function() {
    // Attach click handlers to any user avatars or names with data-username attribute
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-username]');
        if (target && target.dataset.username) {
            e.preventDefault();
            openUserProfile(target.dataset.username);
        }
    });
});
// User Actions Dropdown/Modal Management
let currentActionsModal = null;

function toggleUserActionsMenu(userIdString, event, username) {
    event.stopPropagation();
    
    // Check if we're in a modal context or on mobile
    const isInModal = !!event.target.closest('.modal');
    const isMobile = window.innerWidth <= 768;
    
    if (isInModal || isMobile) {
        // Use modal mode
        showUserActionsModal(userIdString, username || 'User');
    } else {
        // Use dropdown mode
        const menu = document.getElementById(`user-actions-menu-${userIdString}`);
        const allMenus = document.querySelectorAll('.user-actions-menu');
        
        // Close all other menus
        allMenus.forEach(m => {
            if (m !== menu) {
                m.classList.remove('show');
            }
        });
        
        // Toggle current menu
        if (menu) {
            menu.classList.toggle('show');
        }
    }
}

function showUserActionsModal(userIdString, username) {
    // Close existing modal if any
    closeUserActionsModal();
    
    const menu = document.getElementById(`user-actions-menu-${userIdString}`);
    if (!menu) return;
    
    // Clone the menu items
    const menuItems = menu.innerHTML;
    
    // Create modal HTML
    const modalHtml = `
        <div class="user-actions-modal-backdrop" id="userActionsModalBackdrop" onclick="closeUserActionsModal()"></div>
        <div class="user-actions-modal" id="userActionsModal">
            <div class="user-actions-modal-header">
                <div class="user-actions-modal-title">${username}</div>
                <button class="user-actions-modal-close" onclick="closeUserActionsModal()">&times;</button>
            </div>
            <div class="user-actions-modal-body">
                ${menuItems}
            </div>
        </div>
    `;
    
    // Add to body
    const container = document.createElement('div');
    container.id = 'userActionsModalContainer';
    container.innerHTML = modalHtml;
    document.body.appendChild(container);
    
    // Show with animation
    setTimeout(() => {
        document.getElementById('userActionsModalBackdrop').classList.add('show');
        document.getElementById('userActionsModal').classList.add('show');
    }, 10);
    
    currentActionsModal = container;
}

function closeUserActionsModal() {
    if (currentActionsModal) {
        const backdrop = document.getElementById('userActionsModalBackdrop');
        const modal = document.getElementById('userActionsModal');
        
        if (backdrop) backdrop.classList.remove('show');
        if (modal) modal.classList.remove('show');
        
        setTimeout(() => {
            currentActionsModal.remove();
            currentActionsModal = null;
        }, 300);
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.user-actions-dropdown')) {
        document.querySelectorAll('.user-actions-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// Prevent menu from closing when clicking inside
document.addEventListener('click', function(event) {
    if (event.target.closest('.user-actions-menu-item')) {
        const menu = event.target.closest('.user-actions-menu');
        if (menu) {
            menu.classList.remove('show');
        }
        // Close modal if open
        closeUserActionsModal();
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeUserActionsModal();
    }
});
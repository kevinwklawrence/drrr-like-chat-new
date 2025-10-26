// js/hideout.js - Pet System Frontend Logic

let currentPets = [];
let petModal = null;
let shopModal = null;

document.addEventListener('DOMContentLoaded', function() {
    petModal = new bootstrap.Modal(document.getElementById('petModal'));
    shopModal = new bootstrap.Modal(document.getElementById('petShopModal'));
    loadPets();
    
    // Auto-refresh every 60 seconds
    setInterval(loadPets, 60000);
});

function loadPets() {
    fetch('api/pets.php?action=get')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                currentPets = data.pets;
                renderPetGrid();
            } else {
                showError('Failed to load pets');
            }
        })
        .catch(error => {
            console.error('Error loading pets:', error);
            showError('Connection error');
        });
}

function renderPetGrid() {
    const grid = document.getElementById('petGrid');
    
    if (currentPets.length === 0) {
        grid.innerHTML = `
            <div class="empty-pets col-span-full">
                <i class="fas fa-paw"></i>
                <h3>No Dura-mates Yet</h3>
                <p class="text-muted">Visit the Pet Shop to adopt your first companion!</p>
                <button class="btn btn-primary mt-3" onclick="openPetShop()">
                    <i class="fas fa-store"></i> Go to Pet Shop
                </button>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = currentPets.map(pet => `
        <div class="pet-card" onclick="openPetModal(${pet.id})">
            <i class="fas fa-star favorite-star ${pet.is_favorited ? 'active' : ''}" 
               onclick="event.stopPropagation(); toggleFavorite(${pet.id});"></i>
            <div class="bond-badge">Lvl ${pet.bond_level}</div>
            
            <div class="pet-card-header">
                <img src="${pet.image_url}" alt="${pet.custom_name}" class="pet-image" 
                     onerror="this.src='images/pets/default.png'">
                <div class="pet-info">
                    <h4>${escapeHtml(pet.custom_name)}</h4>
                    <div class="pet-type">${pet.type_name}</div>
                </div>
            </div>
            
            <div class="stat-bar">
                <div class="stat-label">
                    <span><i class="fas fa-heart"></i> Happiness</span>
                    <span>${pet.happiness}%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar happiness-bar" style="width: ${pet.happiness}%"></div>
                </div>
            </div>
            
            <div class="stat-bar">
                <div class="stat-label">
                    <span><i class="fas fa-drumstick-bite"></i> Hunger</span>
                    <span>${pet.hunger}%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar hunger-bar" style="width: ${pet.hunger}%"></div>
                </div>
            </div>
            
            <div class="stat-bar">
                <div class="stat-label">
                    <span><i class="fas fa-star"></i> Bond Progress</span>
                    <span>${pet.bond_xp} XP</span>
                </div>
                <div class="progress">
                    <div class="progress-bar xp-bar" style="width: ${Math.min(pet.xp_progress, 100)}%"></div>
                </div>
            </div>
            
            <div class="dura-collect">
                <span class="dura-amount">${pet.accumulated_dura} <i class="fas fa-coins"></i></span>
                <div class="dura-rate">${pet.dura_per_hour} Dura/hour</div>
                ${pet.accumulated_dura > 0 ? '<small class="text-success">Click to interact and collect!</small>' : ''}
            </div>
        </div>
    `).join('');
}

function openPetModal(petId) {
    const pet = currentPets.find(p => p.id == petId);
    if (!pet) return;
    
    const modalTitle = document.getElementById('petModalTitle');
    const modalBody = document.getElementById('petModalBody');
    
    modalTitle.innerHTML = `
        <i class="fas fa-paw"></i> ${escapeHtml(pet.custom_name)}
        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="promptRename(${pet.id})">
            <i class="fas fa-edit"></i> Rename
        </button>
    `;
    
    modalBody.innerHTML = `
        <div class="text-center">
            <img src="${pet.image_url}" alt="${pet.custom_name}" class="pet-modal-image"
                 onerror="this.src='images/pets/default.png'">
            <p class="text-muted mt-2">${pet.description || pet.type_name}</p>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="stat-bar">
                    <div class="stat-label">
                        <span><i class="fas fa-heart"></i> Happiness</span>
                        <span>${pet.happiness}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar happiness-bar" style="width: ${pet.happiness}%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-bar">
                    <div class="stat-label">
                        <span><i class="fas fa-drumstick-bite"></i> Hunger</span>
                        <span>${pet.hunger}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar hunger-bar" style="width: ${pet.hunger}%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="stat-bar">
            <div class="stat-label">
                <span><i class="fas fa-star"></i> Bond Level ${pet.bond_level}</span>
                <span>${pet.bond_xp} / ${pet.xp_to_next_level + pet.bond_xp} XP</span>
            </div>
            <div class="progress">
                <div class="progress-bar xp-bar" style="width: ${Math.min(pet.xp_progress, 100)}%"></div>
            </div>
        </div>
        
        <div class="dura-collect mt-3">
            <span class="dura-amount">${pet.accumulated_dura} <i class="fas fa-coins"></i> Ready to Collect</span>
            <div class="dura-rate">${pet.dura_per_hour} Dura/hour generation rate</div>
        </div>
        
        <div class="pet-actions">
            <button class="pet-action-btn feed-btn" onclick="feedPet(${pet.id})" 
                    ${pet.feed_cooldown > 0 ? 'disabled' : ''}>
                <i class="fas fa-drumstick-bite fa-2x"></i>
                <span>Feed</span>
                ${pet.feed_cooldown > 0 ? `<span class="cooldown-timer">${formatTime(pet.feed_cooldown)}</span>` : '<small>Free or 50 Dura</small>'}
            </button>
            
            <button class="pet-action-btn play-btn" onclick="playWithPet(${pet.id})"
                    ${pet.play_cooldown > 0 ? 'disabled' : ''}>
                <i class="fas fa-gamepad fa-2x"></i>
                <span>Play</span>
                ${pet.play_cooldown > 0 ? `<span class="cooldown-timer">${formatTime(pet.play_cooldown)}</span>` : '<small>+Happiness +XP</small>'}
            </button>
            
            <button class="pet-action-btn care-btn" onclick="carePet(${pet.id})"
                    ${pet.pet_cooldown > 0 ? 'disabled' : ''}>
                <i class="fas fa-hand-holding-heart fa-2x"></i>
                <span>Pet</span>
                ${pet.pet_cooldown > 0 ? `<span class="cooldown-timer">${formatTime(pet.pet_cooldown)}</span>` : '<small>Quick Boost</small>'}
            </button>
            
            <button class="pet-action-btn collect-btn" onclick="collectDura(${pet.id})"
                    ${pet.accumulated_dura < 1 ? 'disabled' : ''}>
                <i class="fas fa-coins fa-2x"></i>
                <span>Collect Dura</span>
                <small>${pet.accumulated_dura} available</small>
            </button>
        </div>
    `;
    
    petModal.show();
}

function feedPet(petId) {
    fetch('api/pets.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=feed&pet_id=${petId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showSuccess(data.message);
            loadPets();
            petModal.hide();
        } else {
            showError(data.message);
        }
    })
    .catch(() => showError('Connection error'));
}

function playWithPet(petId) {
    fetch('api/pets.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=play&pet_id=${petId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showSuccess(data.message);
            loadPets();
            petModal.hide();
        } else {
            showError(data.message);
        }
    })
    .catch(() => showError('Connection error'));
}

function carePet(petId) {
    fetch('api/pets.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=pet&pet_id=${petId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showSuccess(data.message);
            loadPets();
            petModal.hide();
        } else {
            showError(data.message);
        }
    })
    .catch(() => showError('Connection error'));
}

function collectDura(petId) {
    const btn = event.target.closest('button');
    btn.classList.add('collecting');
    
    fetch('api/pets.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=collect&pet_id=${petId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showSuccess(data.message);
            loadPets();
            setTimeout(() => petModal.hide(), 1000);
        } else {
            showError(data.message);
        }
        btn.classList.remove('collecting');
    })
    .catch(() => {
        showError('Connection error');
        btn.classList.remove('collecting');
    });
}

function promptRename(petId) {
    const pet = currentPets.find(p => p.id == petId);
    const newName = prompt('Enter new name (1-20 characters):', pet.custom_name);
    
    if (newName && newName.trim() && newName.trim() !== pet.custom_name) {
        fetch('api/pets.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=rename&pet_id=${petId}&custom_name=${encodeURIComponent(newName.trim())}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showSuccess(data.message);
                loadPets();
                petModal.hide();
            } else {
                showError(data.message);
            }
        })
        .catch(() => showError('Connection error'));
    }
}

function toggleFavorite(petId) {
    fetch('api/pets.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=favorite&pet_id=${petId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showSuccess(data.message);
            loadPets();
        } else {
            showError(data.message);
        }
    })
    .catch(() => showError('Connection error'));
}

function openPetShop() {
    fetch('api/pet_shop.php?action=get_available')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                renderPetShop(data.pets, data.user_dura);
                shopModal.show();
            } else {
                showError('Failed to load shop');
            }
        })
        .catch(() => showError('Connection error'));
}

function renderPetShop(pets, userDura) {
    const shopBody = document.getElementById('petShopBody');
    
    shopBody.innerHTML = `
        <div class="mb-3">
            <h5>Your Dura: <span class="text-warning">${userDura} <i class="fas fa-coins"></i></span></h5>
        </div>
        
        ${pets.map(pet => `
            <div class="shop-pet-card ${pet.owned ? 'owned' : ''}">
                <img src="${pet.image_url}" alt="${pet.name}" class="shop-pet-image"
                     onerror="this.src='images/pets/default.png'">
                <div class="shop-pet-info">
                    <div class="shop-pet-name">
                        ${pet.name}
                        ${pet.is_starter ? '<span class="rarity-badge starter-badge">Starter</span>' : ''}
                        ${!pet.is_starter && pet.shop_price >= 5000 ? '<span class="rarity-badge rarity-legendary">Rare</span>' : ''}
                        ${!pet.is_starter && pet.shop_price < 5000 ? '<span class="rarity-badge rarity-common">Common</span>' : ''}
                    </div>
                    <div class="shop-pet-desc">${pet.description || 'A wonderful companion'}</div>
                    <div class="shop-pet-price">${pet.shop_price} <i class="fas fa-coins"></i> Dura</div>
                </div>
                <div>
                    ${pet.owned ? 
                        '<span class="badge bg-success">Owned</span>' :
                        `<button class="btn btn-primary" onclick="purchasePet('${pet.type_id}')" 
                                ${userDura < pet.shop_price ? 'disabled' : ''}>
                            <i class="fas fa-shopping-cart"></i> Adopt
                        </button>`
                    }
                </div>
            </div>
        `).join('')}
    `;
}

function purchasePet(petType) {
    if (!confirm('Adopt this Dura-mate?')) return;
    
    fetch('api/pet_shop.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=purchase&pet_type=${petType}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showSuccess(data.message);
            shopModal.hide();
            loadPets();
        } else {
            showError(data.message);
        }
    })
    .catch(() => showError('Connection error'));
}

// Utility Functions
function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (hours > 0) return `${hours}h ${mins}m`;
    if (mins > 0) return `${mins}m`;
    return `${seconds}s`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showSuccess(message) {
    // Use existing notification system or basic alert
    if (typeof showNotification === 'function') {
        showNotification(message, 'success');
    } else {
        alert(message);
    }
}

function showError(message) {
    if (typeof showNotification === 'function') {
        showNotification(message, 'error');
    } else {
        alert(message);
    }
}
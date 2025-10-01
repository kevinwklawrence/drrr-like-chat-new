// Inventory System

// Load user inventory
function loadInventory() {
    if (currentUser.type !== 'user') return;
    
    $.ajax({
        url: 'api/inventory.php',
        method: 'GET',
        data: { action: 'get' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayInventory(response.inventory);
            }
        },
        error: function() {
            $('#inventoryContainer').html('<div class="alert alert-danger">Failed to load inventory</div>');
        }
    });
}

// Display inventory items
function displayInventory(items) {
    const container = $('#inventoryContainer');
    
    if (!items || items.length === 0) {
        container.html(`
            <div class="empty-inventory">
                <i class="fas fa-box-open"></i>
                <h5>Your inventory is empty</h5>
                <p>Visit the Shop to purchase items!</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="row">';
    
    items.forEach(item => {
        const equippedClass = item.is_equipped == 1 ? 'equipped' : '';
        const actionBtn = item.is_equipped == 1 
            ? `<button class="btn btn-sm btn-secondary" onclick="unequipItem('${item.item_id}')">Unequip</button>`
            : `<button class="btn btn-sm btn-success" onclick="equipItem('${item.item_id}')">Equip</button>`;
        
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="inventory-item ${item.rarity} ${equippedClass}">
                    <div class="inventory-item-icon">${item.icon || '📦'}</div>
                    <div class="inventory-item-name">${item.name}</div>
                    <div class="inventory-item-description">${item.description || ''}</div>
                    <div class="inventory-item-footer">
                        <span class="rarity-badge ${item.rarity}">${item.rarity}</span>
                        ${actionBtn}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.html(html);
}

// Equip item
function equipItem(itemId) {
    $.ajax({
        url: 'api/inventory.php',
        method: 'POST',
        data: {
            action: 'equip',
            item_id: itemId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                loadInventory(); // Reload to show updated state
                // Clear title cache so badges refresh
                if (window.userTitlesCache && currentUser.id) {
                    window.userTitlesCache.delete(currentUser.id);
                }
            } else {
                alert('Failed to equip item: ' + response.message);
            }
        },
        error: function() {
            alert('Failed to equip item');
        }
    });
}

// Unequip item
function unequipItem(itemId) {
    $.ajax({
        url: 'api/inventory.php',
        method: 'POST',
        data: {
            action: 'unequip',
            item_id: itemId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                loadInventory(); // Reload to show updated state
                // Clear title cache so badges refresh
                if (window.userTitlesCache && currentUser.id) {
                    window.userTitlesCache.delete(currentUser.id);
                }
            } else {
                alert('Failed to unequip item: ' + response.message);
            }
        },
        error: function() {
            alert('Failed to unequip item');
        }
    });
}

// Load shop items with ownership status
function loadShopItems() {
    if (currentUser.type !== 'user') return;
    
    $.ajax({
        url: 'api/shop.php',
        method: 'POST',
        data: { action: 'get_items' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                displayShopItems(response.items);
            }
        },
        error: function() {
            $('#shopItemsContainer').html('<div class="alert alert-danger">Failed to load shop items</div>');
        }
    });
}

// Display shop items
function displayShopItems(items) {
    const container = $('#shopItemsContainer');
    
    if (!items || items.length === 0) {
        container.html('<div class="text-muted">No items available</div>');
        return;
    }
    
    let html = '';
    
    items.forEach(item => {
        const currencyIcon = item.currency === 'dura' ? '<i class="fas fa-gem" style="color: #667eea;"></i>' : '<i class="fas fa-ticket-alt" style="color: #f093fb;"></i>';
        const currencyLabel = item.currency === 'dura' ? 'Dura' : 'Tokens';
        const ownedClass = item.owned == 1 ? 'owned' : '';
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="shop-item ${ownedClass}" style="background: #333; border: 1px solid #555; border-radius: 8px; padding: 20px; transition: all 0.2s ease;">
                    <div class="d-flex align-items-start">
                        <div class="shop-item-icon" style="font-size: 2.5rem; margin-right: 15px;">
                            ${item.icon || '📦'}
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 style="color: #fff; margin: 0;">${item.name}</h6>
                                <span class="rarity-badge ${item.rarity}">${item.rarity}</span>
                            </div>
                            <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 12px;">${item.description || ''}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="shop-item-price" style="background: #444; padding: 6px 12px; border-radius: 6px; font-weight: bold;">
                                    ${currencyIcon} ${item.cost.toLocaleString()} ${currencyLabel}
                                </div>
                                ${item.owned == 1 ? '' : `
                                    <button class="btn btn-sm btn-warning" onclick="purchaseShopItem('${item.item_id}')" style="font-weight: 500;">
                                        <i class="fas fa-shopping-cart"></i> Buy
                                    </button>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.html(html);
}

// Updated purchase function
function purchaseShopItem(itemId) {
    if (currentUser.type !== 'user') {
        alert('Only registered users can purchase items');
        return;
    }
    
    if (!confirm('Purchase this item?')) {
        return;
    }
    
    $.ajax({
        url: 'api/shop.php',
        method: 'POST',
        data: {
            action: 'purchase',
            item_id: itemId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert('✓ ' + response.item_name + ' purchased successfully!');
                updateDuraBalance();
                updateShopBalances();
                loadShopItems(); // Reload shop to update owned status
            } else {
                alert('Purchase failed: ' + response.message);
            }
        },
        error: function() {
            alert('Purchase failed. Please try again.');
        }
    });
}
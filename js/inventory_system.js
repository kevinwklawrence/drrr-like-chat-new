// Inventory System

let currentShopTab = 'all';
let allShopItems = [];
let eventCurrencyConfig = {name: 'Event Currency', icon: '🎉'};

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
            ? `<button class="btn btn-sm btn-secondary" onclick="unequipItem('${item.item_id}', '${item.type}')">Unequip</button>`
            : `<button class="btn btn-sm btn-success" onclick="equipItem('${item.item_id}', '${item.type}')">Equip</button>`;
        
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="inventory-item ${item.rarity} ${equippedClass}">
                    <div class="inventory-item-icon">${item.icon && item.type === 'avatar' ? `<img src="images/${item.icon}" style="max-width:80px; max-height:80px;">` : (item.icon || '📦')}</div>
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
function equipItem(itemId, itemType) {
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
                if (itemType === 'avatar') {
                    // Avatar equipped - reload page to update avatar display everywhere
                    alert('✓ ' + response.item_name + ' equipped! Reloading to update your avatar...');
                    location.reload();
                } else {
                    // Non-avatar item (titles, etc.)
                    loadInventory();
                    if (window.userTitlesCache && currentUser.id) {
                        window.userTitlesCache.delete(currentUser.id);
                    }
                    alert('✓ ' + response.item_name + ' equipped!');
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
function unequipItem(itemId, itemType) {
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
                if (itemType === 'avatar') {
                    // Avatar unequipped - reload page
                    alert('✓ Avatar unequipped! Reloading...');
                    location.reload();
                } else {
                    // Non-avatar item
                    loadInventory();
                    if (window.userTitlesCache && currentUser.id) {
                        window.userTitlesCache.delete(currentUser.id);
                    }
                    alert('✓ Item unequipped!');
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

// Load event currency config
function loadEventCurrencyConfig(callback) {
    $.ajax({
        url: 'api/shop.php',
        method: 'POST',
        data: { action: 'get_event_currency_config' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success' && response.config) {
                eventCurrencyConfig = response.config;
            }
            if (callback) callback();
        },
        error: function() {
            if (callback) callback();
        }
    });
}

// Load shop items with ownership status
function loadShopItems() {
    if (currentUser.type !== 'user') return;
    
    loadEventCurrencyConfig(function() {
        $.ajax({
            url: 'api/shop.php',
            method: 'POST',
            data: { action: 'get_items' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    allShopItems = response.items;
                    initializeShopTabs();
                    displayShopItemsByTab(currentShopTab);
                }
            },
            error: function() {
                $('#shopItemsContainer').html('<div class="alert alert-danger">Failed to load shop items</div>');
            }
        });
    });
}

// Initialize shop tabs
function initializeShopTabs() {
    const container = $('#shopItemsContainer').parent();
    
    // Check if tabs already exist
    if ($('#shopTabs').length > 0) {
        return;
    }
    
    const tabsHtml = `
        <ul class="nav nav-pills mb-3" id="shopTabs" style="border-bottom: 1px solid #555; padding-bottom: 10px;">
            <li class="nav-item">
                <button class="nav-link active shop-tab-btn" data-tab="all" onclick="filterShopByTab('all')" style="background: #444; color: #fff; margin-right: 5px;">
                    <i class="fas fa-th"></i> All
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link shop-tab-btn" data-tab="title" onclick="filterShopByTab('title')" style="background: transparent; color: #aaa; margin-right: 5px;">
                    <i class="fas fa-tag"></i> Titles
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link shop-tab-btn" data-tab="avatar" onclick="filterShopByTab('avatar')" style="background: transparent; color: #aaa; margin-right: 5px;">
                    <i class="fas fa-user-circle"></i> Avatars
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link shop-tab-btn" data-tab="special" onclick="filterShopByTab('special')" style="background: transparent; color: #aaa; margin-right: 5px;">
                    <i class="fas fa-star"></i> Special
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link shop-tab-btn" data-tab="event" onclick="filterShopByTab('event')" style="background: transparent; color: #aaa;">
                    ${eventCurrencyConfig.icon} Event
                </button>
            </li>
        </ul>
    `;
    
    container.find('#shopItemsContainer').before(tabsHtml);
    
    // Add event currency display
    updateEventCurrencyDisplay();
}

// Update event currency display
function updateEventCurrencyDisplay() {
    const eventBalance = currentUser.event_currency || 0;
    const eventCard = `
        <div class="col-md-6 mb-3">
            <div class="currency-card" style="background: linear-gradient(135deg, #f76b1c 0%, #fad961 100%); border-radius: 12px; padding: 20px; color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">${eventCurrencyConfig.icon} ${eventCurrencyConfig.name}</div>
                        <div style="font-size: 2rem; font-weight: bold;" id="shopEventBalance">${eventBalance}</div>
                    </div>
                    <i class="fas fa-trophy fa-3x" style="opacity: 0.3;"></i>
                </div>
                <div class="mt-2" style="font-size: 0.8rem; opacity: 0.8;">
                    <i class="fas fa-info-circle"></i> Earned from special events
                </div>
            </div>
        </div>
    `;
    
    if ($('#shopEventBalance').length === 0) {
        $('.currency-card').last().parent().after(eventCard);
    } else {
        $('#shopEventBalance').text(eventBalance);
    }
}

// Filter shop items by tab
function filterShopByTab(tab) {
    currentShopTab = tab;
    
    // Update active tab button
    $('.shop-tab-btn').removeClass('active').css({background: 'transparent', color: '#aaa'});
    $(`.shop-tab-btn[data-tab="${tab}"]`).addClass('active').css({background: '#444', color: '#fff'});
    
    displayShopItemsByTab(tab);
}

// Display shop items filtered by tab
function displayShopItemsByTab(tab) {
    let filteredItems = allShopItems;
    
    if (tab === 'title') {
        filteredItems = allShopItems.filter(item => item.type === 'title');
    } else if (tab === 'avatar') {
        filteredItems = allShopItems.filter(item => item.type === 'avatar');
    } else if (tab === 'special') {
        filteredItems = allShopItems.filter(item => item.type === 'special');
    } else if (tab === 'event') {
        filteredItems = allShopItems.filter(item => item.rarity === 'event');
    }
    
    displayShopItems(filteredItems);
}

// Display shop items
function displayShopItems(items) {
    const container = $('#shopItemsContainer');
    
    if (!items || items.length === 0) {
        container.html('<div class="text-muted">No items available in this category</div>');
        return;
    }
    
    let html = '';
    
    items.forEach(item => {
        let currencyIcon, currencyLabel;
        
        if (item.currency === 'event') {
            currencyIcon = `<span style="font-size: 1.2rem;">${eventCurrencyConfig.icon}</span>`;
            currencyLabel = eventCurrencyConfig.name;
        } else if (item.currency === 'dura') {
            currencyIcon = '<i class="fas fa-gem" style="color: #667eea;"></i>';
            currencyLabel = 'Dura';
        } else {
            currencyIcon = '<i class="fas fa-ticket-alt" style="color: #f093fb;"></i>';
            currencyLabel = 'Tokens';
        }
        
        const ownedClass = item.owned == 1 ? 'owned' : '';
        
        // Show avatar preview for avatar items
        const itemIcon = (item.type === 'avatar' && item.icon) 
            ? `<img src="images/${item.icon}" style="max-width:58px; max-height:58px;">`
            : (item.icon || '📦');
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="shop-item ${ownedClass}" style="background: #333; border: 1px solid #555; border-radius: 8px; padding: 20px; transition: all 0.2s ease;">
                    <div class="d-flex align-items-start">
                        <div class="shop-item-icon" style="font-size: 2.5rem; margin-right: 15px;">
                            ${itemIcon}
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
                
                // Update currency displays
                if (response.currency === 'dura') {
                    currentUser.dura = response.new_balance;
                    $('#shopDuraBalance').text(response.new_balance);
                    if (typeof updateDuraBalance === 'function') {
                        updateDuraBalance();
                    }
                } else if (response.currency === 'tokens') {
                    currentUser.tokens = response.new_balance;
                    $('#shopTokenBalance').text(response.new_balance);
                } else if (response.currency === 'event') {
                    currentUser.event_currency = response.new_balance;
                    $('#shopEventBalance').text(response.new_balance);
                }
                
                // Reload shop to mark item as owned
                loadShopItems();
            } else {
                alert('Purchase failed: ' + response.message);
            }
        },
        error: function() {
            alert('Failed to purchase item');
        }
    });
}
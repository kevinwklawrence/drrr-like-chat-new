// Inventory System

let currentShopTab = 'all';
let allShopItems = [];
let eventCurrencyConfig = {name: 'Event Currency', icon: '🎉'};
let allBundles = [];


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
        // Skip avatars, colors, special items, and bundles - managed elsewhere
        if (item.type === 'avatar' || item.type === 'color' || item.type === 'special' || item.type === 'bundle') {
            return;
        }
        
        const equippedClass = item.is_equipped == 1 ? 'equipped' : '';
        const actionBtn = item.is_equipped == 1 
            ? `<button class="btn btn-sm btn-secondary" onclick="unequipItem('${item.item_id}', '${item.type}')">Unequip</button>`
            : `<button class="btn btn-sm btn-success" onclick="equipItem('${item.item_id}', '${item.type}')">Equip</button>`;
        
        // Show appropriate icon for effects
        let displayIcon = item.icon || '📦';
        if (item.type === 'effect') {
            displayIcon = getEffectPreview(item.item_id);
        }
        
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="inventory-item ${item.type === 'effect' ? 'effect-card' : ''} ${item.rarity} ${equippedClass}">
                    <div class="inventory-item-icon">${displayIcon}</div>
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
    
    if (html === '<div class="row"></div>') {
        html = `
            <div class="empty-inventory">
                <i class="fas fa-box-open"></i>
                <h5>No equippable items</h5>
                <p>Avatars and colors are managed in the Profile Editor. Purchase effects and titles in the Shop!</p>
            </div>
        `;
    }
    
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
        // NEW: Clear effects cache for effects
        if (itemType === 'effect' && typeof userEffectsCache !== 'undefined' && currentUser.id) {
            userEffectsCache.delete(currentUser.id);
        }
        
        if (itemType === 'avatar' || itemType === 'color') {
            alert('✓ ' + response.item_name + ' equipped! Reloading...');
            location.reload();
        } else {
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
                if (itemType === 'avatar' || itemType === 'color') {
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
                <button class="nav-link shop-tab-btn" data-tab="bundle" onclick="filterShopByTab('bundle')" style="background: transparent; color: #aaa; margin-right: 5px;">
                    <i class="fas fa-box"></i> Bundles
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
                <button class="nav-link shop-tab-btn" data-tab="color" onclick="filterShopByTab('color')" style="background: transparent; color: #aaa; margin-right: 5px;">
                    <i class="fas fa-palette"></i> Colors
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link shop-tab-btn" data-tab="effect" onclick="filterShopByTab('effect')" style="background: transparent; color: #aaa; margin-right: 5px;">
                    <i class="fas fa-magic"></i> Effects
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
    } else if (tab === 'color') {
        filteredItems = allShopItems.filter(item => item.type === 'color');
    } else if (tab === 'effect') {
        // FIXED: Was filtering for 'color', now correctly filters for 'effect'
        filteredItems = allShopItems.filter(item => item.type === 'effect');
    } else if (tab === 'bundle') {
        loadAndDisplayBundles();
        return; // Bundles have their own display function
    }
    
    displayShopItems(filteredItems);
}

function loadAndDisplayBundles() {
    if (currentUser.type !== 'user') return;
    
    $.ajax({
        url: 'api/shop.php',
        method: 'POST',
        data: { action: 'get_bundles' },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                allBundles = response.bundles;
                displayBundles(allBundles);
            }
        },
        error: function() {
            $('#shopItemsContainer').html('<div class="alert alert-danger">Failed to load bundles</div>');
        }
    });
}

function displayBundles(bundles) {
    const container = $('#shopItemsContainer');
    
    if (!bundles || bundles.length === 0) {
        container.html('<div class="text-muted">No bundles available</div>');
        return;
    }
    
    let html = '';
    
    bundles.forEach(bundle => {
        let currencyIcon, currencyLabel;
        
        if (bundle.currency === 'event') {
            currencyIcon = `<span style="font-size: 1.2rem;">${eventCurrencyConfig.icon}</span>`;
            currencyLabel = eventCurrencyConfig.name;
        } else if (bundle.currency === 'dura') {
            currencyIcon = '<i class="fas fa-gem" style="color: #667eea;"></i>';
            currencyLabel = 'Dura';
        } else {
            currencyIcon = '<i class="fas fa-ticket-alt" style="color: #f093fb;"></i>';
            currencyLabel = 'Tokens';
        }
        
        const ownedClass = bundle.owned == 1 ? 'owned' : '';
        
        // Build bundle contents display
        let bundleContentsHtml = '';
        if (bundle.bundle_contents && bundle.bundle_contents.length > 0) {
            bundleContentsHtml = '<div style="display: flex; gap: 8px; margin: 10px 0; flex-wrap: wrap;">';
            bundle.bundle_contents.forEach(item => {
                let itemDisplay = '';
                if (item.type === 'avatar' && item.icon) {
                    itemDisplay = `<img src="images/${item.icon}" style="width:40px; height:40px; border-radius:4px; border:1px solid #555;">`;
                } else if (item.type === 'color' && item.icon) {
                    itemDisplay = `<img class="color-${item.icon}" style="width:40px; height:40px; border-radius:4px; border:1px solid #555;"></img>`;
                } else {
                    itemDisplay = `<div style="font-size:2rem;">${item.icon || '📦'}</div>`;
                }
                
                bundleContentsHtml += `
                    <div style="text-align: center; min-width: 60px;">
                        ${itemDisplay}
                        <div style="font-size: 0.7rem; color: #aaa; margin-top: 4px;">${item.name}</div>
                        <div class="rarity-badge ${item.rarity}" style="font-size: 0.6rem; padding: 2px 4px; margin-top: 2px;">${item.rarity}</div>
                    </div>
                `;
            });
            bundleContentsHtml += '</div>';
        }
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="shop-item ${ownedClass}" style="background: #333; border: 2px solid #667eea; border-radius: 8px; padding: 20px; transition: all 0.2s ease;">
                    <div class="d-flex align-items-start mb-3">
                        <div class="shop-item-icon" style="font-size: 2.5rem; margin-right: 15px;">
                            ${bundle.icon || '📦'}
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 style="color: #fff; margin: 0;">${bundle.name}</h6>
                                <span class="rarity-badge ${bundle.rarity}">${bundle.rarity}</span>
                            </div>
                            <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 12px;">${bundle.description || ''}</p>
                        </div>
                    </div>
                    
                    <div style="background: #2a2a2a; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                        <div style="color: #667eea; font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-gift"></i> Bundle Contains:
                        </div>
                        ${bundleContentsHtml}
                    </div>
                    
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="shop-item-price" style="background: #444; padding: 6px 12px; border-radius: 6px; font-weight: bold;">
                            ${currencyIcon} ${bundle.cost.toLocaleString()} ${currencyLabel}
                        </div>
                        ${bundle.owned == 1 ? '' : `
                            <button class="btn btn-sm btn-warning" onclick="purchaseShopItem('${bundle.item_id}')" style="font-weight: 500;">
                                <i class="fas fa-shopping-cart"></i> Buy Bundle
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.html(html);
}

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
        
        // Show appropriate preview based on item type
        let itemIcon;
        if (item.type === 'avatar' && item.icon) {
            itemIcon = `<img src="images/${item.icon}" style="max-width:58px; max-height:58px;">`;
        } else if (item.type === 'color' && item.icon) {
            itemIcon = `<div class="color-${item.icon}" style="width:58px; height:58px; border-radius:8px; border:2px solid #555;"></div>`;
        } else if (item.type === 'effect') {
            // NEW: Effect preview with animation
            itemIcon = getEffectPreview(item.item_id);
        } else {
            itemIcon = item.icon || '📦';
        }
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="shop-item ${item.type === 'effect' ? 'effect-card' : ''} ${ownedClass}" style="background: #333; border: 1px solid #555; border-radius: 8px; padding: 20px; transition: all 0.2s ease;">
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

// Helper function to get effect preview icon/animation
// Enhanced effect preview function with more detailed visuals
function getEffectPreview(effectItemId) {
    const parts = effectItemId.split('_');
    if (parts.length < 3) return '<div class="effect-preview">✨</div>';
    
    const effectType = parts[1]; // glow, overlay, bubble
    const effectName = parts.slice(2).join('_'); // fire, rainbow, etc.
    
    let previewHtml = '';
    
    if (effectType === 'glow') {
        // Show a circular avatar-like shape with the glow effect
        previewHtml = `
            <div class="effect-preview effect-preview-glow" style="position: relative; width: 58px; height: 58px; display: flex; align-items: center; justify-content: center;">
                <div class="avatar-glow glow-${effectName}" style="position: absolute; top: 0px; left: 0px; right: 0px; bottom: 0px; border-radius: 4px;"></div>
                <div style="
                    width: 58px; 
                    height: 58px; 
                    background: url('../images/default/_u0.png') no-repeat center/cover;
                    border-radius: 4px; 
                    position: relative; 
                    z-index: 1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #aaa;
                    font-size: 1.5rem;
                "></div>
            </div>
        `;
        
    } else if (effectType === 'overlay') {
        // Show a circular avatar with the overlay on top
        const overlayIcons = {
            'crown': '👑',
            'halo': '😇',
            'cat_ears': '🐱',
            'devil_horns': '😈',
            'sparkles': '✨',
            'hearts': '💕',
            'snow': '❄️',
            'witch_hat': '🧙',
            'party_hat': '🎉'
        };
        
        const centerIcon = overlayIcons[effectName] || '';
        
        previewHtml = `
            <div class="effect-preview effect-preview-overlay" style="position: relative; width: 58px; height: 58px; display: inline-block;">
                <div style="
                    width: 58px; 
                    height: 58px; 
                    background: url('../images/default/_u0.png') no-repeat center/cover;
                    border-radius: 4px; 
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #aaa;
                    font-size: 1.5rem;
                "></div>
                <div class="avatar-overlay overlay-${effectName}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></div>
            </div>
        `;
        
    } else if (effectType === 'bubble') {
        // Show a mini message bubble with the animation effect
        const bubbleIcons = {
            'float': '💬',
            'shake': '📳',
            'rainbow': '🌈',
            'glow': '✨',
            'fire': '🔥',
            'frost': '❄️',
            'neon': '💡',
            'sparkles': '✨',
            'bounce': '⚾'
        };
        
        const bubbleIcon = bubbleIcons[effectName] || '💬';
        
        previewHtml = `
            <div class="effect-preview effect-preview-bubble" style="position: relative; width: 58px; height: 58px; display: flex; align-items: center; justify-content: center; padding: 5px;">
                <div class="bubble-effect-${effectName}" style="
                    width: 100%; 
                    min-height: 42px; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 12px;
                    border: 2px solid rgba(255,255,255,0.3);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.3rem;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                "></div>
            </div>
        `;
    } else {
        // Fallback for unknown types
        previewHtml = `
            <div class="effect-preview" style="
                width: 58px; 
                height: 58px; 
                background: #444; 
                border-radius: 8px; 
                border: 2px solid #666;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
            "></div>
        `;
    }
    
    return previewHtml;
}

// Optional: Function to get effect description text
function getEffectDescription(effectItemId) {
    const parts = effectItemId.split('_');
    if (parts.length < 3) return 'Special visual effect';
    
    const effectType = parts[1];
    const effectName = parts.slice(2).join(' ');
    
    const descriptions = {
        'glow': `A ${effectName} aura that glows around your avatar`,
        'overlay': `A ${effectName} overlay that appears on top of your avatar`,
        'bubble': `Your message bubbles will have a ${effectName} effect`
    };
    
    return descriptions[effectType] || 'Special visual effect';
}


// Function to apply effects to avatars in the UI
function applyAvatarEffects(userId, avatarElement) {
    // This would fetch user's equipped effects and apply them
    // Implementation depends on how you store equipped effects
    
    // Example:
    $.ajax({
        url: 'api/get_equipped_effects.php',
        method: 'GET',
        data: { user_id: userId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const effects = response.effects;
                
                // Wrap avatar if not already wrapped
                if (!avatarElement.parent().hasClass('avatar-with-effects')) {
                    avatarElement.wrap('<div class="avatar-with-effects"></div>');
                }
                
                const wrapper = avatarElement.parent();
                
                // Clear existing effects
                wrapper.find('.avatar-glow, .avatar-overlay').remove();
                
                // Apply glow effect
                if (effects.avatar_glow) {
                    wrapper.prepend(`<div class="avatar-glow glow-${effects.avatar_glow}"></div>`);
                }
                
                // Apply overlay effect
                if (effects.avatar_overlay) {
                    wrapper.append(`<div class="avatar-overlay overlay-${effects.avatar_overlay}"></div>`);
                }
            }
        }
    });
}

// Function to apply effects to message bubbles
function applyBubbleEffect(userId, bubbleElement) {
    // Fetch and apply bubble effect
    $.ajax({
        url: 'api/get_equipped_effects.php',
        method: 'GET',
        data: { user_id: userId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success' && response.effects.bubble_effect) {
                const effectClass = `bubble-effect-${response.effects.bubble_effect}`;
                bubbleElement.addClass(effectClass);
            }
        }
    });
}
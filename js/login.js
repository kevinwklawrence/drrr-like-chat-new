let userManuallySelectedColor = false;
let userHasInteractedWithSliders = false;
let userHasInteractedWithBubbleSliders = false;
let loadedUserCustomization = null; // Store loaded user settings

$(document).ready(function() {
    // NUCLEAR OPTION: Completely disable native form submission
    $('#userLoginForm').attr('onsubmit', 'return false;');
    $('#userLoginForm').removeAttr('action');
    $('#userLoginForm').removeAttr('method');
    
    // Remove any existing event handlers
    $('#userLoginForm').off('submit');
    $('button[type="submit"]').off('click');
    
    // Monitor ALL possible submission triggers
    $(document).on('submit', '#userLoginForm', function(e) {
        debugLog('🚨 LOGIN FORM SUBMIT EVENT - BLOCKED');
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    });
    
    $(document).on('click', '#userLoginForm button[type="submit"]', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        debugLog('🚨 LOGIN SUBMIT BUTTON CLICK - REDIRECTED TO CUSTOM HANDLER');
        handleUserLogin();
        return false;
    });
    
    $(document).on('keypress', '#userLoginForm input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            debugLog('🚨 LOGIN ENTER KEY PRESSED - REDIRECTED TO CUSTOM HANDLER');
            handleUserLogin();
            return false;
        }
    });
    
    // Initialize sliders WITHOUT triggering interaction flags
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    $('#bubbleHueSlider').val(0);
    $('#bubbleSaturationSlider').val(100);
    updateAvatarFilter();
    updateBubbleFilter();
    
    // Track slider interactions for avatar customization
    $('#hueSlider, #saturationSlider').on('input change', function() {
        userHasInteractedWithSliders = true;
        debugLog('User interacted with avatar sliders');
        updateAvatarFilter();
    });
    
    // Track slider interactions for bubble customization
    $('#bubbleHueSlider, #bubbleSaturationSlider').on('input change', function() {
        userHasInteractedWithBubbleSliders = true;
        debugLog('User interacted with bubble sliders');
        updateBubbleFilter();
    });
    
    updateAvatarStats();
    
    // Avatar selection handling
    $(document).on('click', '.avatar', function() {
        $('.avatar').removeClass('selected').css('filter', '');
        $(this).addClass('selected');
        
        const avatarPath = $(this).data('avatar');
        $('#selectedAvatar').val(avatarPath);
        
        // Auto-select color based on avatar (only if user hasn't manually changed it)
        if (!userManuallySelectedColor) {
            const defaultColor = typeof getAvatarDefaultColor === 'function' ? getAvatarDefaultColor(avatarPath) : 'black';
            selectColor(defaultColor, document.querySelector(`[data-color="${defaultColor}"]`), true);
        }
        
        $('#selectedAvatarImg').attr('src', $(this).attr('src'));
        $('#selectedAvatarPreview').show();
        $('#noAvatarSelected').hide();
        
        updateAvatarFilter();
        syncModalPreviews(); // Sync with modals
    });

    // Filter dropdown
    $('#avatarSort').on('change', function() {
        filterAvatars();
    });
    
    // Initialize color selection
    selectColor('black', document.querySelector('[data-color="black"]'), true);

    // Enhanced username field with customization preview
    let usernameTimeout;
    $('#username').on('input', function() {
        clearTimeout(usernameTimeout);
        const username = $(this).val().trim();
        
        if (username.length >= 3) {
            usernameTimeout = setTimeout(function() {
                fetchUserCustomization(username);
            }, 500); // Debounce for 500ms
        } else {
            // Clear preview when username is too short
            clearUserCustomizationPreview();
        }
    });

    $('#customizationModal').on('show.bs.modal', function() {
    syncModalPreviews();
});

    // Add this to track manual color clicks
    $(document).on('click', '.color-option', function() {
        const colorName = $(this).data('color');
        selectColor(colorName, this, false); // false = manual selection
    });

    function fetchUserCustomization(username) {
        $.ajax({
            url: 'api/get_user_customization.php',
            method: 'GET',
            data: { username: username },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && res.customization) {
                    loadedUserCustomization = res.customization;
                    showUserCustomizationPreview(res.customization);
                } else {
                    clearUserCustomizationPreview();
                }
            },
            error: function() {
                clearUserCustomizationPreview();
            }
        });
    }

    function showUserCustomizationPreview(customization) {
        debugLog('Loading user customization:', customization);
        
        // Only show avatar preview if no avatar is manually selected
        if ($('#selectedAvatar').val() === '') {
            $('#selectedAvatarImg').attr('src', 'images/' + customization.avatar);
            $('#selectedAvatarPreview').show();
            $('#noAvatarSelected').hide();
            $('#noAvatarSelected .text-muted p').text('Your saved avatar');
        }
        
        // Update sliders to show saved values (without triggering interaction flags)
        if (!userHasInteractedWithSliders) {
            $('#hueSlider').val(customization.avatar_hue);
            $('#saturationSlider').val(customization.avatar_saturation);
            updateAvatarFilter(); // Apply the saved filters
        }
        
        if (!userHasInteractedWithBubbleSliders) {
            $('#bubbleHueSlider').val(customization.bubble_hue);
            $('#bubbleSaturationSlider').val(customization.bubble_saturation);
            updateBubbleFilter(); // Apply the saved bubble filters
        }
        
        // Update color selection (only if user hasn't manually selected)
        if (!userManuallySelectedColor) {
            const colorElement = document.querySelector(`[data-color="${customization.color}"]`);
            if (colorElement) {
                selectColor(customization.color, colorElement, true);
            }
        }
        
        // Add visual indicator that settings are loaded
        showSettingsLoadedIndicator();
        syncModalPreviews(); // Sync with modals
    }

    function clearUserCustomizationPreview() {
        loadedUserCustomization = null;
        
        // Only clear if no avatar is manually selected
        if ($('#selectedAvatar').val() === '') {
            $('#selectedAvatarPreview').hide();
            $('#noAvatarSelected').show();
            $('#noAvatarSelected .text-muted p').text('Using saved/custom avatar');
        }
        
        // Reset sliders to defaults if user hasn't interacted with them
        if (!userHasInteractedWithSliders) {
            $('#hueSlider').val(0);
            $('#saturationSlider').val(100);
            updateAvatarFilter();
        }
        
        if (!userHasInteractedWithBubbleSliders) {
            $('#bubbleHueSlider').val(0);
            $('#bubbleSaturationSlider').val(100);
            updateBubbleFilter();
        }
        
        // Reset color to default if user hasn't manually selected
        if (!userManuallySelectedColor) {
            selectColor('black', document.querySelector('[data-color="black"]'), true);
        }
        
        hideSettingsLoadedIndicator();
        syncModalPreviews(); // Sync with modals
    }

    function showSettingsLoadedIndicator() {
        // Add a small indicator to show that saved settings are loaded
        if (!$('#settingsLoadedIndicator').length) {
            $('#noAvatarSelected').append('<div id="settingsLoadedIndicator" class="mt-2"><small class="text-success"><i class="fas fa-check-circle"></i> Saved settings loaded</small></div>');
        }
    }

    function hideSettingsLoadedIndicator() {
        $('#settingsLoadedIndicator').remove();
    }
});

let userLoginInProgress = false;

function handleUserLogin() {
    if (userLoginInProgress) {
        debugLog('🛑 User login already in progress - BLOCKED');
        return false;
    }

    userLoginInProgress = true;
    
    const username = $('#username').val().trim();
    const password = $('#password').val();
    const selectedAvatar = $('#selectedAvatar').val();
    const selectedColor = $('#selectedColor').val();
    
    if (!username || !password) {
        userLoginInProgress = false;
        alert('Please enter both username and password');
        return false;
    }
    
    const submitBtn = $('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Logging in...');
    $('#userLoginForm').find('input, button, select').prop('disabled', true);
    
    const submissionId = 'login_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    // Build form data conditionally
    const formData = {
        username: username,
        password: password,
        type: 'user',
        submission_id: submissionId
    };
    
    // Only include avatar if one was selected
    if (selectedAvatar) {
        formData.avatar = selectedAvatar;
    }
    
    // Only include color if user manually selected one
    if (userManuallySelectedColor && selectedColor) {
        formData.color = selectedColor;
    }
    
    // Only include avatar customization if user interacted with sliders
    if (userHasInteractedWithSliders) {
        formData.avatar_hue = $('#hueSlider').val() || 0;
        formData.avatar_saturation = $('#saturationSlider').val() || 100;
        debugLog('Including avatar customization - hue:', formData.avatar_hue, 'sat:', formData.avatar_saturation);
    } else {
        debugLog('User did not interact with avatar sliders - preserving saved values');
    }
    
    // Only include bubble customization if user interacted with bubble sliders
    if (userHasInteractedWithBubbleSliders) {
        formData.bubble_hue = $('#bubbleHueSlider').val() || 0;
        formData.bubble_saturation = $('#bubbleSaturationSlider').val() || 100;
        debugLog('Including bubble customization - hue:', formData.bubble_hue, 'sat:', formData.bubble_saturation);
    } else {
        debugLog('User did not interact with bubble sliders - preserving saved values');
    }
    
    debugLog('Sending login data:', formData);
    
    $.ajax({
        url: 'api/login.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 15000,
        cache: false,
        success: function(res) {
            if (res.status === 'success') {
                setTimeout(function() {
                    window.location.href = 'lounge.php';
                }, 100);
            } else {
                resetLoginForm(submitBtn, originalText);
                alert('Error: ' + (res.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            resetLoginForm(submitBtn, originalText);
            alert('Connection error: ' + error);
        }
    });
    
    return false;
}

function resetLoginForm(submitBtn, originalText) {
    userLoginInProgress = false;
    $('#userLoginForm').find('input, button, select').prop('disabled', false);
    submitBtn.prop('disabled', false).html(originalText);
}

function filterAvatars() {
    const selectedGroup = $('#avatarSort').val();
    
    $('.avatar-group').each(function() {
        const groupName = $(this).data('group');
        
        let showGroup = true;
        
        // Filter by dropdown selection
        if (selectedGroup !== 'all' && groupName !== selectedGroup) {
            showGroup = false;
        }
        
        if (showGroup) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    
    updateAvatarStats();
    checkNoResults();
}

function updateAvatarStats() {
    const totalAvatars = $('.avatar').length;
    const visibleAvatars = $('.avatar-group:visible .avatar').length;
    const selectedCount = $('.avatar.selected').length;
    
    $('#totalAvatars').text(totalAvatars);
    $('#visibleAvatars').text(visibleAvatars);
    $('#selectedCount').text(selectedCount);
}

function checkNoResults() {
    const visibleGroups = $('.avatar-group:visible').length;
    if (visibleGroups === 0) {
        $('#noResults').show();
    } else {
        $('#noResults').hide();
    }
}

function clearSelection() {
    $('.avatar').removeClass('selected').css('filter', '');
    $('#selectedAvatar').val('');
    $('#selectedAvatarPreview').hide();
    $('#noAvatarSelected').show();
    
    // Re-trigger username preview if username exists
    const username = $('#username').val().trim();
    if (username.length >= 3 && loadedUserCustomization) {
        showUserCustomizationPreview(loadedUserCustomization);
    }
    
    updateAvatarStats();
}

function skipAvatarSelection() {
    // Clear any selection and show that we're using saved avatar
    clearSelection();
    $('#noAvatarSelected .text-muted p').text('Using your saved avatar');
}

function randomAvatar() {
    const visibleAvatars = $('.avatar-group:visible .avatar');
    if (visibleAvatars.length > 0) {
        const randomIndex = Math.floor(Math.random() * visibleAvatars.length);
        $(visibleAvatars[randomIndex]).click();
    }
}

function selectColor(colorName, element, isAutomatic = false) {
    // Track if this was a manual selection (not automatic from avatar)
    if (!isAutomatic) {
        userManuallySelectedColor = true;
    }
    
    // Remove selected class from all options
    document.querySelectorAll('.color-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Add selected class to clicked option
    element.classList.add('selected');
    
    // Update hidden input
    document.getElementById('selectedColor').value = colorName;
    
    // Update main preview
    const preview = document.getElementById('selectedColorPreview');
    preview.className = `preview-circle color-${colorName}`;
    
    // Update modal preview
    const modalPreview = document.getElementById('modalSelectedColorPreview');
    if (modalPreview) {
        modalPreview.className = `preview-circle color-${colorName}`;
    }
    
    // Update color name (if element exists)
    const colorNameElement = document.getElementById('selectedColorName');
    if (colorNameElement) {
        colorNameElement.textContent = colorName.charAt(0).toUpperCase() + colorName.slice(1);
    }
    
    updateColorPreview();
}

function updateAvatarFilter() {
    const hue = $('#hueSlider').val() || 0;
    const saturation = $('#saturationSlider').val() || 100;
    
    $('#hueValue').text(hue + '°');
    $('#saturationValue').text(saturation + '%');
    
    const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
    
    // Apply filter to main selected avatar
    const selectedAvatar = $('.avatar.selected');
    if (selectedAvatar.length > 0) {
        selectedAvatar.css('filter', filter);
        selectedAvatar.addClass('avatar-customized');
    }
    
    // Apply to main preview image
    const previewImg = $('#selectedAvatarImg');
    if (previewImg.length > 0 && previewImg.is(':visible')) {
        previewImg.css('filter', filter);
    }
    
    // Apply to modal preview image
    const modalAvatarImg = $('#modalSelectedAvatarImg');
    if (modalAvatarImg.length > 0) {
        modalAvatarImg.css('filter', filter);
    }
}

function updateBubbleFilter() {
    const hue = $('#bubbleHueSlider').val() || 0;
    const saturation = $('#bubbleSaturationSlider').val() || 100;
    
    $('#bubbleHueValue').text(hue + '°');
    $('#bubbleSaturationValue').text(saturation + '%');
    
    // Update preview if needed
    updateColorPreview();
}

function updateColorPreview() {
    const selectedColor = $('#selectedColor').val() || 'black';
    const hue = $('#bubbleHueSlider').val() || 0;
    const saturation = $('#bubbleSaturationSlider').val() || 100;
    
    const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
    
    // Update main preview
    const preview = $('#selectedColorPreview');
    preview.css('filter', filter);
    
    // Update modal preview
    const modalPreview = $('#modalSelectedColorPreview');
    if (modalPreview.length > 0) {
        modalPreview.css('filter', filter);
    }
}

// Updated individual reset functions
function resetAvatarSliders() {
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    $('#hueValue').text('0°');
    $('#saturationValue').text('100%');
    updateAvatarFilter(); // This will automatically update modal preview
    
    // For login.js - mark as interacted so it gets saved
    if (typeof userHasInteractedWithSliders !== 'undefined') {
        userHasInteractedWithSliders = true;
    }
}

function resetChatColorSettings() {
    // Reset to black color
    const blackOption = document.querySelector('[data-color="black"]');
    if (blackOption) {
        selectColor('black', blackOption, true); // This will automatically update modal preview
        // For login.js - mark as manually selected
        if (typeof userManuallySelectedColor !== 'undefined') {
            userManuallySelectedColor = true;
        }
    }
    
    // Reset sliders
    $('#bubbleHueSlider').val(0);
    $('#bubbleSaturationSlider').val(100);
    $('#bubbleHueValue').text('0°');
    $('#bubbleSaturationValue').text('100%');
    updateBubbleFilter(); // This will automatically update modal preview
    
    // For login.js - mark as interacted so it gets saved
    if (typeof userHasInteractedWithBubbleSliders !== 'undefined') {
        userHasInteractedWithBubbleSliders = true;
    }
}

// Add this to track manual color clicks:
$(document).on('click', '.color-option', function() {
    const colorName = $(this).data('color');
    selectColor(colorName, this, false); // false = manual selection
});

// Add performance optimization classes (keeping the existing code)
// ... (keep all the existing performance optimization classes from the original login.js)
class LazyAvatarLoader {
    constructor() {
        this.observer = null;
        this.loadedImages = new Set();
        this.initializeLazyLoading();
    }

    initializeLazyLoading() {
        // Check if Intersection Observer is supported
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, {
                root: document.getElementById('avatarContainer'),
                rootMargin: '100px' // Start loading 100px before image is visible
            });
        }
    }

    loadImage(img) {
        const dataSrc = img.getAttribute('data-src');
        if (dataSrc && !this.loadedImages.has(img)) {
            img.src = dataSrc;
            img.removeAttribute('data-src');
            img.classList.remove('lazy');
            this.loadedImages.add(img);
            
            // Add fade-in effect
            img.style.opacity = '0';
            img.onload = () => {
                img.style.transition = 'opacity 0.3s ease';
                img.style.opacity = '1';
            };
        }
    }

    observeImage(img) {
        if (this.observer) {
            this.observer.observe(img);
        } else {
            // Fallback for browsers without Intersection Observer
            this.loadImage(img);
        }
    }
}

// Initialize the lazy loader
const lazyLoader = new LazyAvatarLoader();

// Modified avatar rendering function
function renderAvatarsWithLazyLoading() {
    // Replace your existing PHP image rendering with this approach
    const avatarContainer = document.getElementById('avatarContainer');
    
    // Example of how to modify your existing avatar generation
    // You'll need to modify your PHP to output data-src instead of src
    const avatars = avatarContainer.querySelectorAll('img.avatar[data-src]');
    
    avatars.forEach(img => {
        // Add lazy class for styling
        img.classList.add('lazy');
        
        // Add placeholder while loading
        img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTgiIGhlaWdodD0iNTgiIHZpZXdCb3g9IjAgMCA1OCA1OCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjU4IiBoZWlnaHQ9IjU4IiBmaWxsPSIjZjhmOWZhIi8+CjxjaXJjbGUgY3g9IjI5IiBjeT0iMjkiIHI9IjgiIGZpbGw9IiNkZGUiLz4KPC9zdmc+';
        
        // Observe for lazy loading
        lazyLoader.observeImage(img);
    });
}

// Add CSS for lazy loading
const lazyLoadingCSS = `
<style>
.avatar.lazy {
    background: linear-gradient(45deg, #f8f9fa 25%, transparent 25%), 
                linear-gradient(-45deg, #f8f9fa 25%, transparent 25%), 
                linear-gradient(45deg, transparent 75%, #f8f9fa 75%), 
                linear-gradient(-45deg, transparent 75%, #f8f9fa 75%);
    background-size: 8px 8px;
    background-position: 0 0, 0 4px, 4px -4px, -4px 0px;
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 0.6; }
}
</style>
`;

// Add the CSS to your page
document.head.insertAdjacentHTML('beforeend', lazyLoadingCSS);


// ====================
// 1. IMAGE PRELOADING STRATEGY
// ====================

class ImagePreloader {
    constructor() {
        this.preloadQueue = [];
        this.preloadedImages = new Map();
        this.maxConcurrentLoads = 3;
        this.currentLoads = 0;
    }

    preloadImage(src) {
        return new Promise((resolve, reject) => {
            if (this.preloadedImages.has(src)) {
                resolve(this.preloadedImages.get(src));
                return;
            }

            const img = new Image();
            img.onload = () => {
                this.preloadedImages.set(src, img);
                resolve(img);
            };
            img.onerror = reject;
            img.src = src;
        });
    }

    async preloadBatch(imageUrls) {
        const chunks = [];
        for (let i = 0; i < imageUrls.length; i += this.maxConcurrentLoads) {
            chunks.push(imageUrls.slice(i, i + this.maxConcurrentLoads));
        }

        for (const chunk of chunks) {
            await Promise.allSettled(chunk.map(url => this.preloadImage(url)));
        }
    }
}

// ====================
// 2. VIRTUAL SCROLLING FOR LARGE LISTS
// ====================

class VirtualScrollAvatars {
    constructor(container, itemHeight = 70, itemsPerRow = 8) {
        this.container = container;
        this.itemHeight = itemHeight;
        this.itemsPerRow = itemsPerRow;
        this.visibleItems = [];
        this.allItems = [];
        this.scrollTop = 0;
        this.containerHeight = container.clientHeight;
        
        this.init();
    }

    init() {
        this.container.addEventListener('scroll', this.onScroll.bind(this));
        window.addEventListener('resize', this.onResize.bind(this));
    }

    setItems(items) {
        this.allItems = items;
        this.render();
    }

    onScroll() {
        this.scrollTop = this.container.scrollTop;
        this.render();
    }

    onResize() {
        this.containerHeight = this.container.clientHeight;
        this.render();
    }

    render() {
        const startRow = Math.floor(this.scrollTop / this.itemHeight);
        const endRow = Math.min(
            startRow + Math.ceil(this.containerHeight / this.itemHeight) + 1,
            Math.ceil(this.allItems.length / this.itemsPerRow)
        );

        const startIndex = startRow * this.itemsPerRow;
        const endIndex = Math.min(endRow * this.itemsPerRow, this.allItems.length);

        this.visibleItems = this.allItems.slice(startIndex, endIndex);
        
        this.container.innerHTML = '';
        
        // Add spacer for items above viewport
        if (startIndex > 0) {
            const spacer = document.createElement('div');
            spacer.style.height = `${startRow * this.itemHeight}px`;
            this.container.appendChild(spacer);
        }

        // Render visible items
        this.visibleItems.forEach((item, index) => {
            const element = this.createItemElement(item, startIndex + index);
            this.container.appendChild(element);
        });

        // Add spacer for items below viewport
        const remainingItems = this.allItems.length - endIndex;
        if (remainingItems > 0) {
            const spacer = document.createElement('div');
            const remainingRows = Math.ceil(remainingItems / this.itemsPerRow);
            spacer.style.height = `${remainingRows * this.itemHeight}px`;
            this.container.appendChild(spacer);
        }
    }

    createItemElement(item, index) {
        const img = document.createElement('img');
        img.src = item.web_path;
        img.className = 'avatar';
        img.dataset.avatar = item.path;
        img.alt = 'Avatar option';
        img.addEventListener('click', () => this.selectAvatar(item));
        return img;
    }

    selectAvatar(item) {
        debugLog('Selected avatar:', item);
        // Your avatar selection logic here
    }
}

// ====================
// 3. PERFORMANCE MONITORING
// ====================

class PerformanceMonitor {
    constructor() {
        this.metrics = {
            imagesLoaded: 0,
            totalLoadTime: 0,
            averageLoadTime: 0,
            failedLoads: 0,
            memoryUsage: 0
        };
        this.startTime = performance.now();
    }

    recordImageLoad(loadTime) {
        this.metrics.imagesLoaded++;
        this.metrics.totalLoadTime += loadTime;
        this.metrics.averageLoadTime = this.metrics.totalLoadTime / this.metrics.imagesLoaded;
        this.updateDisplay();
    }

    recordFailedLoad() {
        this.metrics.failedLoads++;
        this.updateDisplay();
    }

    updateMemoryUsage() {
        if ('memory' in performance) {
            this.metrics.memoryUsage = Math.round(performance.memory.usedJSHeapSize / 1024 / 1024);
        }
    }

    updateDisplay() {
        const displayElement = document.getElementById('performance-stats');
        if (displayElement) {
            displayElement.innerHTML = `
                Images Loaded: ${this.metrics.imagesLoaded} | 
                Avg Load Time: ${Math.round(this.metrics.averageLoadTime)}ms | 
                Failed: ${this.metrics.failedLoads} | 
                Memory: ${this.metrics.memoryUsage}MB
            `;
            
        }
    }

    getReport() {
        return {
            ...this.metrics,
            totalTime: performance.now() - this.startTime,
            successRate: ((this.metrics.imagesLoaded / (this.metrics.imagesLoaded + this.metrics.failedLoads)) * 100).toFixed(2)
        };
    }
}

// ====================
// 4. INTELLIGENT CACHING
// ====================

class ImageCache {
    constructor(maxSize = 50) {
        this.cache = new Map();
        this.maxSize = maxSize;
        this.accessOrder = [];
    }

    get(key) {
        if (this.cache.has(key)) {
            // Move to end (most recently used)
            this.accessOrder = this.accessOrder.filter(k => k !== key);
            this.accessOrder.push(key);
            return this.cache.get(key);
        }
        return null;
    }

    set(key, value) {
        if (this.cache.size >= this.maxSize && !this.cache.has(key)) {
            // Remove least recently used
            const lru = this.accessOrder.shift();
            this.cache.delete(lru);
        }

        this.cache.set(key, value);
        this.accessOrder = this.accessOrder.filter(k => k !== key);
        this.accessOrder.push(key);
    }

    clear() {
        this.cache.clear();
        this.accessOrder = [];
    }
}

// ====================
// 5. USAGE EXAMPLE
// ====================

// Initialize all optimization systems
document.addEventListener('DOMContentLoaded', function() {
    const imageCache = new ImageCache(100);
    const preloader = new ImagePreloader();
    const perfMonitor = new PerformanceMonitor();
    
    // Enhanced lazy loading with performance monitoring
    class EnhancedLazyLoader extends LazyAvatarLoader {
        loadImage(img) {
            const startTime = performance.now();
            const dataSrc = img.getAttribute('data-src');
            
            if (dataSrc && !this.loadedImages.has(img)) {
                // Check cache first
                const cached = imageCache.get(dataSrc);
                if (cached) {
                    img.src = dataSrc;
                    img.removeAttribute('data-src');
                    img.classList.remove('lazy');
                    this.loadedImages.add(img);
                    perfMonitor.recordImageLoad(0); // Cached load
                    return;
                }

                img.src = dataSrc;
                img.removeAttribute('data-src');
                img.classList.remove('lazy');
                this.loadedImages.add(img);
                
                img.onload = () => {
                    const loadTime = performance.now() - startTime;
                    perfMonitor.recordImageLoad(loadTime);
                    imageCache.set(dataSrc, img);
                    img.style.transition = 'opacity 0.3s ease';
                    img.style.opacity = '1';
                };
                
                img.onerror = () => {
                    perfMonitor.recordFailedLoad();
                };
            }
        }
    }

    // Use enhanced loader
    const enhancedLoader = new EnhancedLazyLoader();
    
    // Monitor memory usage periodically
    setInterval(() => {
        perfMonitor.updateMemoryUsage();
        perfMonitor.updateDisplay();
    }, 5000);
    
    // Add performance stats display to your page
    const statsDiv = document.createElement('div');
    statsDiv.id = 'performance-stats';
    statsDiv.style.cssText = 'position: fixed; top: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 1000;';
    document.body.appendChild(statsDiv);
});


function syncModalPreviews() {
    // Sync avatar previews
    const mainAvatarPreview = $('#selectedAvatarPreview');
    const mainAvatarImg = $('#selectedAvatarImg');
    const mainNoAvatar = $('#noAvatarSelected');
    
    // Modal preview elements
    const modalAvatarPreview = $('#modalSelectedAvatarPreview');
    const modalAvatarImg = $('#modalSelectedAvatarImg');
    const modalNoAvatar = $('#modalNoAvatarSelected');
    
    if (mainAvatarPreview.is(':visible')) {
        // Avatar is selected - show in modal
        modalAvatarImg.attr('src', mainAvatarImg.attr('src'));
        
        // Apply current avatar filter to modal image
        const currentFilter = mainAvatarImg.css('filter') || '';
        modalAvatarImg.css('filter', currentFilter);
        
        modalAvatarPreview.show();
        modalNoAvatar.hide();
    } else {
        // No avatar selected
        modalAvatarPreview.hide();
        modalNoAvatar.show();
    }
    
    // Sync color previews
    const mainColorPreview = $('#selectedColorPreview');
    const modalColorPreview = $('#modalSelectedColorPreview');
    
    // Copy class and filter from main preview
    modalColorPreview.attr('class', mainColorPreview.attr('class'));
    
    const currentColorFilter = mainColorPreview.css('filter') || '';
    modalColorPreview.css('filter', currentColorFilter);
}

function resetAllSettings() {
    resetAvatarSliders();
    resetChatColorSettings();
}
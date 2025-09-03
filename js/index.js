let userManuallySelectedColor = false;
$(document).ready(function() {
    // Initialize bubble sliders
    $('#bubbleHueSlider').val(0);
    $('#bubbleSaturationSlider').val(100);
    updateBubbleFilter();

    $('#bubbleHueSlider, #bubbleSaturationSlider').on('input change', function() {
        updateBubbleFilter();
    });

    // NUCLEAR OPTION: Completely disable native form submission
    $('#guestLoginForm').attr('onsubmit', 'return false;');
    $('#guestLoginForm').removeAttr('action');
    $('#guestLoginForm').removeAttr('method');
    
    // Remove any existing event handlers
    $('#guestLoginForm').off('submit');
    $('button[type="submit"]').off('click');
    
    // Track ALL form interactions
    let submitAttempts = 0;
    
    // Monitor ALL possible submission triggers
    $(document).on('submit', '#guestLoginForm', function(e) {
        submitAttempts++;
        debugLog(`🚨 FORM SUBMIT EVENT #${submitAttempts} - BLOCKED`);
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    });
    
    $(document).on('click', 'button[type="submit"]', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        debugLog('🚨 SUBMIT BUTTON CLICK - REDIRECTED TO CUSTOM HANDLER');
        handleGuestLogin();
        return false;
    });
    
    // Monitor for Enter key in form fields
    $(document).on('keypress', '#guestLoginForm input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            debugLog('🚨 ENTER KEY PRESSED - REDIRECTED TO CUSTOM HANDLER');
            handleGuestLogin();
            return false;
        }
    });
    
    // Initialize sliders
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    updateAvatarFilter();
    
    // Add real-time slider tracking
    $('#hueSlider, #saturationSlider').on('input change', function() {
        debugLog('Slider changed - hue:', $('#hueSlider').val(), 'sat:', $('#saturationSlider').val());
        updateAvatarFilter();
    });
    
    // Initialize color selection
    selectColor('black', document.querySelector('[data-color="black"]'), true);
    
    // Avatar click handler
    $('.avatar').click(function() {
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

    // Modal event listeners
    $('#customizationModal').on('show.bs.modal', function() {
    syncModalPreviews();
});

    // Add this to track manual color clicks
    $(document).on('click', '.color-option', function() {
        const colorName = $(this).data('color');
        selectColor(colorName, this, false); // false = manual selection
    });
});

// Function to sync modal previews with main previews
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

// Separate function for handling guest login
let guestLoginInProgress = false;

function handleGuestLogin() {
    debugLog('=== CUSTOM GUEST LOGIN HANDLER ===');
    
    if (guestLoginInProgress) {
        debugLog('🛑 Guest login already in progress - BLOCKED');
        return false;
    }
    
    guestLoginInProgress = true;
    
    const guestName = $('#guestName').val().trim();
    const selectedAvatar = $('#selectedAvatar').val();
    const selectedColor = $('#selectedColor').val();
    
    // Get slider values with extensive logging
    const hueElement = document.getElementById('hueSlider');
    const satElement = document.getElementById('saturationSlider');
    
    debugLog('Hue element:', hueElement);
    debugLog('Sat element:', satElement);
    debugLog('Hue value:', hueElement ? hueElement.value : 'NULL');
    debugLog('Sat value:', satElement ? satElement.value : 'NULL');
    
    const hueShift = hueElement ? parseInt(hueElement.value) || 0 : 0;
    const saturation = satElement ? parseInt(satElement.value) || 100 : 100;
    const bubbleHue = document.getElementById('bubbleHueSlider') ? parseInt(document.getElementById('bubbleHueSlider').value) || 0 : 0;
    const bubbleSaturation = document.getElementById('bubbleSaturationSlider') ? parseInt(document.getElementById('bubbleSaturationSlider').value) || 100 : 100;

    debugLog('Final values - hue:', hueShift, 'sat:', saturation);
    
    if (!guestName) {
        guestLoginInProgress = false;
        alert('Please enter your display name');
        $('#guestName').focus();
        return false;
    }
    
    if (!selectedAvatar) {
        guestLoginInProgress = false;
        alert('Please select an avatar');
        return false;
    }
    
    // Show loading state
    const submitBtn = $('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Joining...');
    
    // Disable ALL form elements
    $('#guestLoginForm').find('input, button, select').prop('disabled', true);
    
    // Create unique submission ID
    const submissionId = 'guest_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    const formData = {
        guest_name: guestName,
        avatar: selectedAvatar,
        color: selectedColor,
        avatar_hue: hueShift,
        avatar_saturation: saturation,
        bubble_hue: bubbleHue,
        bubble_saturation: bubbleSaturation,
        type: 'guest',
        submission_id: submissionId
    };
    
    debugLog('Sending data with submission ID:', submissionId);
    debugLog('Form data:', formData);
    
    $.ajax({
        url: 'api/join_lounge.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 15000,
        cache: false,
        success: function(res) {
            debugLog('Response received:', res);
            if (res.status === 'success') {
                debugLog('Success - redirecting...');
                setTimeout(function() {
                    window.location.href = 'lounge.php';
                }, 100);
            } else {
                resetGuestForm(submitBtn, originalText);
                alert('Error: ' + (res.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            resetGuestForm(submitBtn, originalText);
            alert('Connection error: ' + error);
        }
    });
    
    return false;
}

function resetGuestForm(submitBtn, originalText) {
    guestLoginInProgress = false;
    $('#guestLoginForm').find('input, button, select').prop('disabled', false);
    submitBtn.prop('disabled', false).html(originalText);
}

// Color selection functions
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

// New combined reset function for the "Reset All" button
function resetAllSettings() {
    resetAvatarSliders();
    resetChatColorSettings();
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

// Modal-specific functions
function resetAvatarSliders() {
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    $('#hueValue').text('0°');
    $('#saturationValue').text('100%');
    updateAvatarFilter();
}

function resetChatColorSettings() {
    // Reset to black color
    const blackOption = document.querySelector('[data-color="black"]');
    if (blackOption) {
        selectColor('black', blackOption, true);
    }
    
    // Reset sliders
    $('#bubbleHueSlider').val(0);
    $('#bubbleSaturationSlider').val(100);
    $('#bubbleHueValue').text('0°');
    $('#bubbleSaturationValue').text('100%');
    updateBubbleFilter();
}

// Add this to track manual color clicks:
$(document).on('click', '.color-option', function() {
    const colorName = $(this).data('color');
    selectColor(colorName, this, false); // false = manual selection
});
let userManuallySelectedColor = false;
$(document).ready(function() {
    $('#bubbleHueSlider').val(0);
    $('#bubbleSaturationSlider').val(100);
    updateBubbleFilter();

    $('#bubbleHueSlider, #bubbleSaturationSlider').on('input change', function() {
        updateBubbleFilter();
    });

    $('#guestLoginForm').attr('onsubmit', 'return false;');
    $('#guestLoginForm').removeAttr('action');
    $('#guestLoginForm').removeAttr('method');
    
    $('#guestLoginForm').off('submit');
    $('button[type="submit"]').off('click');
    
    let submitAttempts = 0;
    
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
    
    $(document).on('keypress', '#guestLoginForm input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            debugLog('🚨 ENTER KEY PRESSED - REDIRECTED TO CUSTOM HANDLER');
            handleGuestLogin();
            return false;
        }
    });
    
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    updateAvatarFilter();
    
    $('#hueSlider, #saturationSlider').on('input change', function() {
        debugLog('Slider changed - hue:', $('#hueSlider').val(), 'sat:', $('#saturationSlider').val());
        updateAvatarFilter();
    });
    
    selectColor('black', document.querySelector('[data-color="black"]'), true);
    
    $('.avatar').click(function() {
        $('.avatar').removeClass('selected').css('filter', '');
        $(this).addClass('selected');
        
        const avatarPath = $(this).data('avatar');
        $('#selectedAvatar').val(avatarPath);
        
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

    $('#customizationModal').on('show.bs.modal', function() {
    syncModalPreviews();
});

    $(document).on('click', '.color-option', function() {
        const colorName = $(this).data('color');
        selectColor(colorName, this, false); // false = manual selection
    });
});

function syncModalPreviews() {
    const mainAvatarPreview = $('#selectedAvatarPreview');
    const mainAvatarImg = $('#selectedAvatarImg');
    const mainNoAvatar = $('#noAvatarSelected');
    
    const modalAvatarPreview = $('#modalSelectedAvatarPreview');
    const modalAvatarImg = $('#modalSelectedAvatarImg');
    const modalNoAvatar = $('#modalNoAvatarSelected');
    
    if (mainAvatarPreview.is(':visible')) {
        modalAvatarImg.attr('src', mainAvatarImg.attr('src'));
        
        const currentFilter = mainAvatarImg.css('filter') || '';
        modalAvatarImg.css('filter', currentFilter);
        
        modalAvatarPreview.show();
        modalNoAvatar.hide();
    } else {
        modalAvatarPreview.hide();
        modalNoAvatar.show();
    }
    
    const mainColorPreview = $('#selectedColorPreview');
    const modalColorPreview = $('#modalSelectedColorPreview');
    
    modalColorPreview.attr('class', mainColorPreview.attr('class'));
    
    const currentColorFilter = mainColorPreview.css('filter') || '';
    modalColorPreview.css('filter', currentColorFilter);
}

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
    
    const submitBtn = $('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Joining...');
    
    $('#guestLoginForm').find('input, button, select').prop('disabled', true);
    
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

function selectColor(colorName, element, isAutomatic = false) {
    if (!isAutomatic) {
        userManuallySelectedColor = true;
    }
    
    document.querySelectorAll('.color-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    element.classList.add('selected');
    
    document.getElementById('selectedColor').value = colorName;
    
    const preview = document.getElementById('selectedColorPreview');
    preview.className = `preview-circle color-${colorName}`;
    
    const modalPreview = document.getElementById('modalSelectedColorPreview');
    if (modalPreview) {
        modalPreview.className = `preview-circle color-${colorName}`;
    }
    
    const colorNameElement = document.getElementById('selectedColorName');
    if (colorNameElement) {
        colorNameElement.textContent = colorName.charAt(0).toUpperCase() + colorName.slice(1);
    }
    
    updateColorPreview();
}

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
    
    const selectedAvatar = $('.avatar.selected');
    if (selectedAvatar.length > 0) {
        selectedAvatar.css('filter', filter);
        selectedAvatar.addClass('avatar-customized');
    }
    
    const previewImg = $('#selectedAvatarImg');
    if (previewImg.length > 0 && previewImg.is(':visible')) {
        previewImg.css('filter', filter);
    }
    
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
    
    updateColorPreview();
}

function updateColorPreview() {
    const selectedColor = $('#selectedColor').val() || 'black';
    const hue = $('#bubbleHueSlider').val() || 0;
    const saturation = $('#bubbleSaturationSlider').val() || 100;
    
    const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
    
    const preview = $('#selectedColorPreview');
    preview.css('filter', filter);
    
    const modalPreview = $('#modalSelectedColorPreview');
    if (modalPreview.length > 0) {
        modalPreview.css('filter', filter);
    }
}

function resetAvatarSliders() {
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    $('#hueValue').text('0°');
    $('#saturationValue').text('100%');
    updateAvatarFilter();
}

function resetChatColorSettings() {
    const blackOption = document.querySelector('[data-color="black"]');
    if (blackOption) {
        selectColor('black', blackOption, true);
    }
    
    $('#bubbleHueSlider').val(0);
    $('#bubbleSaturationSlider').val(100);
    $('#bubbleHueValue').text('0°');
    $('#bubbleSaturationValue').text('100%');
    updateBubbleFilter();
}

$(document).on('click', '.color-option', function() {
    const colorName = $(this).data('color');
    selectColor(colorName, this, false); // false = manual selection
});
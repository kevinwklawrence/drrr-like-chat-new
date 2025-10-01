let userManuallySelectedColor = false;
let userHasInteractedWithSliders = false;
let userHasInteractedWithBubbleSliders = false;
let loadedUserCustomization = null; // Store loaded user settings

$(document).ready(function() {
    $('#userLoginForm').attr('onsubmit', 'return false;');
    $('#userLoginForm').removeAttr('action');
    $('#userLoginForm').removeAttr('method');
    
    $('#userLoginForm').off('submit');
    $('button[type="submit"]').off('click');
    
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
    
    $('#hueSlider').val(0);
    $('#saturationSlider').val(100);
    $('#bubbleHueSlider').val(0);
    $('#bubbleSaturationSlider').val(100);
    updateAvatarFilter();
    updateBubbleFilter();
    
    $('#hueSlider, #saturationSlider').on('input change', function() {
        userHasInteractedWithSliders = true;
        debugLog('User interacted with avatar sliders');
        updateAvatarFilter();
    });
    
    $('#bubbleHueSlider, #bubbleSaturationSlider').on('input change', function() {
        userHasInteractedWithBubbleSliders = true;
        debugLog('User interacted with bubble sliders');
        updateBubbleFilter();
    });
    
    updateAvatarStats();
    
    $(document).on('click', '.avatar', function() {
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

    $('#avatarSort').on('change', function() {
        filterAvatars();
    });
    
    selectColor('black', document.querySelector('[data-color="black"]'), true);

    let usernameTimeout;
    $('#username').on('input', function() {
        clearTimeout(usernameTimeout);
        const username = $(this).val().trim();
        
        if (username.length >= 3) {
            usernameTimeout = setTimeout(function() {
                fetchUserCustomization(username);
            }, 500); 
        } else {
            clearUserCustomizationPreview();
        }
    });

    $('#customizationModal').on('show.bs.modal', function() {
    syncModalPreviews();
    });

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
        
        if ($('#selectedAvatar').val() === '') {
            $('#selectedAvatarImg').attr('src', 'images/' + customization.avatar);
            $('#selectedAvatarPreview').show();
            $('#noAvatarSelected').hide();
            $('#noAvatarSelected .text-muted p').text('Your saved avatar');
        }
        
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
        
        if (!userManuallySelectedColor) {
            const colorElement = document.querySelector(`[data-color="${customization.color}"]`);
            if (colorElement) {
                selectColor(customization.color, colorElement, true);
            }
        }
        
        showSettingsLoadedIndicator();
        syncModalPreviews(); // Sync with modals
    }

    function clearUserCustomizationPreview() {
        loadedUserCustomization = null;
        
        if ($('#selectedAvatar').val() === '') {
            $('#selectedAvatarPreview').hide();
            $('#noAvatarSelected').show();
            $('#noAvatarSelected .text-muted p').text('Using saved/custom avatar');
        }
        
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
        
        if (!userManuallySelectedColor) {
            selectColor('black', document.querySelector('[data-color="black"]'), true);
        }
        
        hideSettingsLoadedIndicator();
        syncModalPreviews(); // Sync with modals
    }

    function showSettingsLoadedIndicator() {
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
    
    const formData = {
        username: username,
        password: password,
        type: 'user',
        submission_id: submissionId
    };
    
    if (selectedAvatar) {
        formData.avatar = selectedAvatar;
    }
    
    if (userManuallySelectedColor && selectedColor) {
        formData.color = selectedColor;
    }
    
    if (userHasInteractedWithSliders) {
        formData.avatar_hue = $('#hueSlider').val() || 0;
        formData.avatar_saturation = $('#saturationSlider').val() || 100;
        debugLog('Including avatar customization - hue:', formData.avatar_hue, 'sat:', formData.avatar_saturation);
    } else {
        debugLog('User did not interact with avatar sliders - preserving saved values');
    }
    
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
                    window.location.href = '/lounge';
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
    
    const username = $('#username').val().trim();
    if (username.length >= 3 && loadedUserCustomization) {
        showUserCustomizationPreview(loadedUserCustomization);
    }
    
    updateAvatarStats();
}

function skipAvatarSelection() {
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
    updateAvatarFilter(); // This will automatically update modal preview
    
    if (typeof userHasInteractedWithSliders !== 'undefined') {
        userHasInteractedWithSliders = true;
    }
}

function resetChatColorSettings() {
    const blackOption = document.querySelector('[data-color="black"]');
    if (blackOption) {
        selectColor('black', blackOption, true); // This will automatically update modal preview
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
    
    if (typeof userHasInteractedWithBubbleSliders !== 'undefined') {
        userHasInteractedWithBubbleSliders = true;
    }
}

$(document).on('click', '.color-option', function() {
    const colorName = $(this).data('color');
    selectColor(colorName, this, false); // false = manual selection
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

function resetAllSettings() {
    resetAvatarSliders();
    resetChatColorSettings();
}
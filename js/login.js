let userManuallySelectedColor = false;
let userHasInteractedWithSliders = false;
let userHasInteractedWithBubbleSliders = false;
let loadedUserCustomization = null; // Store loaded user settings

$(document).ready(function() {
    $('#customizeCollapse').on('show.bs.collapse', function () {
        $('#customizeChevron').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    });

    $('#customizeCollapse').on('hide.bs.collapse', function () {
        $('#customizeChevron').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });

    // NUCLEAR OPTION: Completely disable native form submission
    $('#userLoginForm').attr('onsubmit', 'return false;');
    $('#userLoginForm').removeAttr('action');
    $('#userLoginForm').removeAttr('method');
    
    // Remove any existing event handlers
    $('#userLoginForm').off('submit');
    $('button[type="submit"]').off('click');
    
    // Monitor ALL possible submission triggers
    $(document).on('submit', '#userLoginForm', function(e) {
        console.log('🚨 LOGIN FORM SUBMIT EVENT - BLOCKED');
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    });
    
    $(document).on('click', '#userLoginForm button[type="submit"]', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        console.log('🚨 LOGIN SUBMIT BUTTON CLICK - REDIRECTED TO CUSTOM HANDLER');
        handleUserLogin();
        return false;
    });
    
    $(document).on('keypress', '#userLoginForm input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            console.log('🚨 LOGIN ENTER KEY PRESSED - REDIRECTED TO CUSTOM HANDLER');
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
        console.log('User interacted with avatar sliders');
        updateAvatarFilter();
    });
    
    // Track slider interactions for bubble customization
    $('#bubbleHueSlider, #bubbleSaturationSlider').on('input change', function() {
        userHasInteractedWithBubbleSliders = true;
        console.log('User interacted with bubble sliders');
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
            const defaultColor = getAvatarDefaultColor(avatarPath);
            selectColor(defaultColor, document.querySelector(`[data-color="${defaultColor}"]`), true);
        }
        
        $('#selectedAvatarImg').attr('src', $(this).attr('src'));
        $('#selectedAvatarPreview').show();
        $('#noAvatarSelected').hide();
        
        updateAvatarFilter();
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
        console.log('Loading user customization:', customization);
        
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
        console.log('🛑 User login already in progress - BLOCKED');
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
        console.log('Including avatar customization - hue:', formData.avatar_hue, 'sat:', formData.avatar_saturation);
    } else {
        console.log('User did not interact with avatar sliders - preserving saved values');
    }
    
    // Only include bubble customization if user interacted with bubble sliders
    if (userHasInteractedWithBubbleSliders) {
        formData.bubble_hue = $('#bubbleHueSlider').val() || 0;
        formData.bubble_saturation = $('#bubbleSaturationSlider').val() || 100;
        console.log('Including bubble customization - hue:', formData.bubble_hue, 'sat:', formData.bubble_saturation);
    } else {
        console.log('User did not interact with bubble sliders - preserving saved values');
    }
    
    console.log('Sending login data:', formData);
    
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
    // Track if this was a manual selection (not automatic from avatar or saved settings)
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
    
    // Update preview
    const preview = document.getElementById('selectedColorPreview');
    preview.className = `preview-circle color-${colorName}`;
    
    // Update color name
    document.getElementById('selectedColorName').textContent = colorName.charAt(0).toUpperCase() + colorName.slice(1);
    
    // Update bubble filter preview
    updateColorPreview();
}

function updateAvatarFilter() {
    const hue = $('#hueSlider').val() || 0;
    const saturation = $('#saturationSlider').val() || 100;
    
    $('#hueValue').text(hue + '°');
    $('#saturationValue').text(saturation + '%');
    
    // Apply filter to any selected avatar
    const selectedAvatar = $('.avatar.selected');
    if (selectedAvatar.length > 0) {
        const filter = `hue-rotate(${hue}deg) saturate(${saturation/100})`;
        selectedAvatar.css('filter', filter);
        selectedAvatar.addClass('avatar-customized');
    }
    
    // Also apply to preview image if it exists
    const previewImg = $('#selectedAvatarImg');
    if (previewImg.length > 0 && previewImg.is(':visible')) {
        const filter = `hue-rotate(${hue}deg) saturate(${saturation/100})`;
        previewImg.css('filter', filter);
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
    
    const preview = $('#selectedColorPreview');
    const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
    preview.css('filter', filter);
}

// Add this to track manual color clicks:
$(document).on('click', '.color-option', function() {
    const colorName = $(this).data('color');
    selectColor(colorName, this, false); // false = manual selection
});
        let userManuallySelectedColor = false;
$(document).ready(function() {

$('#bubbleHueSlider').val(0);
$('#bubbleSaturationSlider').val(100);
updateBubbleFilter();

$('#bubbleHueSlider, #bubbleSaturationSlider').on('input change', function() {
    updateBubbleFilter();
});

    $('#customizeCollapse').on('show.bs.collapse', function () {
    $('#customizeChevron').removeClass('fa-chevron-down').addClass('fa-chevron-up');
});

$('#customizeCollapse').on('hide.bs.collapse', function () {
    $('#customizeChevron').removeClass('fa-chevron-up').addClass('fa-chevron-down');
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
        console.log(`🚨 FORM SUBMIT EVENT #${submitAttempts} - BLOCKED`);
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    });
    
    $(document).on('click', 'button[type="submit"]', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        console.log('🚨 SUBMIT BUTTON CLICK - REDIRECTED TO CUSTOM HANDLER');
        handleGuestLogin();
        return false;
    });
    
    // Monitor for Enter key in form fields
    $(document).on('keypress', '#guestLoginForm input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            console.log('🚨 ENTER KEY PRESSED - REDIRECTED TO CUSTOM HANDLER');
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
        console.log('Slider changed - hue:', $('#hueSlider').val(), 'sat:', $('#saturationSlider').val());
        updateAvatarFilter();
    });
    
    // Initialize color selection
    // Change the initialization to mark it as automatic:
selectColor('black', document.querySelector('[data-color="black"]'), true);
    
    // Update the avatar click handler in index.php:
$('.avatar').click(function() {
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
});

// Separate function for handling guest login
let guestLoginInProgress = false;

function handleGuestLogin() {
    console.log('=== CUSTOM GUEST LOGIN HANDLER ===');
    
    if (guestLoginInProgress) {
        console.log('🛑 Guest login already in progress - BLOCKED');
        return false;
    }
    
    guestLoginInProgress = true;
    
    const guestName = $('#guestName').val().trim();
    const selectedAvatar = $('#selectedAvatar').val();
    const selectedColor = $('#selectedColor').val();
    
    // Get slider values with extensive logging
    const hueElement = document.getElementById('hueSlider');
    const satElement = document.getElementById('saturationSlider');
    
    console.log('Hue element:', hueElement);
    console.log('Sat element:', satElement);
    console.log('Hue value:', hueElement ? hueElement.value : 'NULL');
    console.log('Sat value:', satElement ? satElement.value : 'NULL');
    
    const hueShift = hueElement ? parseInt(hueElement.value) || 0 : 0;
const saturation = satElement ? parseInt(satElement.value) || 100 : 100;
const bubbleHue = document.getElementById('bubbleHueSlider') ? parseInt(document.getElementById('bubbleHueSlider').value) || 0 : 0;
const bubbleSaturation = document.getElementById('bubbleSaturationSlider') ? parseInt(document.getElementById('bubbleSaturationSlider').value) || 100 : 100;

    
    
    console.log('Final values - hue:', hueShift, 'sat:', saturation);

    
    
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
    
    console.log('Sending data with submission ID:', submissionId);
    console.log('Form data:', formData);
    
    $.ajax({
        url: 'api/join_lounge.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 15000,
        cache: false,
        success: function(res) {
            console.log('Response received:', res);
            if (res.status === 'success') {
                console.log('Success - redirecting...');
                // Add a small delay to prevent browser race conditions
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
    
    // Update preview
    const preview = document.getElementById('selectedColorPreview');
    preview.className = `preview-circle color-${colorName}`;
    
    // Update color name
    document.getElementById('selectedColorName').textContent = colorName.charAt(0).toUpperCase() + colorName.slice(1);
}

function resetColorToDefault() {
    selectColor('black', document.querySelector('[data-color="black"]'));
}

function updateAvatarFilter() {
    const hue = $('#hueSlider').val() || 0;
    const saturation = $('#saturationSlider').val() || 100;
    
    console.log('UpdateAvatarFilter called - hue:', hue, 'sat:', saturation);
    
    $('#hueValue').text(hue + '°');
    $('#saturationValue').text(saturation + '%');
    
    // Apply filter to selected avatar
    const selectedAvatar = $('.avatar.selected');
    if (selectedAvatar.length > 0) {
        const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
        selectedAvatar.css('filter', filter);
        selectedAvatar.addClass('avatar-customized');
        console.log('Applied filter to avatar:', filter);
    }
    
    // Also apply to preview image if it exists
    const previewImg = $('#selectedAvatarImg');
    if (previewImg.length > 0 && previewImg.is(':visible')) {
        const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
        previewImg.css('filter', filter);
        console.log('Applied filter to preview:', filter);
    }
}

// Add this to track manual color clicks:
$(document).on('click', '.color-option', function() {
    const colorName = $(this).data('color');
    selectColor(colorName, this, false); // false = manual selection
});

// Add these new functions
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
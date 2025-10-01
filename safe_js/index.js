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

    $(document).on('click', '.avatar', function() {
        $('.avatar').removeClass('selected').css('filter', '');
        $(this).addClass('selected');
        
        const avatarPath = $(this).data('avatar');
        $('#selectedAvatar').val(avatarPath);
        
        if (!userManuallySelectedColor) {
            const defaultColor = typeof getAvatarDefaultColor === 'function' ?
                getAvatarDefaultColor(avatarPath) : 'black';
            selectColor(defaultColor, document.querySelector(`[data-color="${defaultColor}"]`), true);
        }
        
        // Update preview
        $('#selectedAvatarImg').attr('src', $(this).attr('src'));
        $('#selectedAvatarPreview').show();
        $('#noAvatarSelected').hide();
        
        updateAvatarFilter();
        syncModalPreviews(); // Sync with modals if they exist
    });

    // Initialize pagination
    const totalPages = document.querySelectorAll('.avatar-page').length;
    if (totalPages > 0) {
        currentPage = 1;
        updatePaginationUI();
        
        // Ensure only the first page is visible
        document.querySelectorAll('.avatar-page').forEach((page, index) => {
            page.style.display = index === 0 ? 'block' : 'none';
        });
    }
});

function syncModalPreviews() {
    const mainAvatarPreview = document.getElementById('selectedAvatarPreview');
    const mainAvatarImg = document.getElementById('selectedAvatarImg');
    const mainNoAvatar = document.getElementById('noAvatarSelected');
    
    const modalAvatarPreview = document.getElementById('modalSelectedAvatarPreview');
    const modalAvatarImg = document.getElementById('modalSelectedAvatarImg');
    const modalNoAvatar = document.getElementById('modalNoAvatarSelected');
    
    if (mainAvatarPreview && mainAvatarPreview.style.display !== 'none') {
        if (modalAvatarImg && mainAvatarImg) {
            modalAvatarImg.src = mainAvatarImg.src;
            
            const currentFilter = mainAvatarImg.style.filter || '';
            modalAvatarImg.style.filter = currentFilter;
        }
        
        if (modalAvatarPreview) modalAvatarPreview.style.display = 'block';
        if (modalNoAvatar) modalNoAvatar.style.display = 'none';
    } else {
        if (modalAvatarPreview) modalAvatarPreview.style.display = 'none';
        if (modalNoAvatar) modalNoAvatar.style.display = 'block';
    }
    
    const mainColorPreview = document.getElementById('selectedColorPreview');
    const modalColorPreview = document.getElementById('modalSelectedColorPreview');
    
    if (modalColorPreview && mainColorPreview) {
        modalColorPreview.className = mainColorPreview.className;
        
        const currentColorFilter = mainColorPreview.style.filter || '';
        modalColorPreview.style.filter = currentColorFilter;
    }
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
                    window.location.href = '/lounge';
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
    const hueSlider = document.getElementById('hueSlider');
    const saturationSlider = document.getElementById('saturationSlider');
    
    if (!hueSlider || !saturationSlider) return;
    
    const hue = hueSlider.value || 0;
    const saturation = saturationSlider.value || 100;
    
    const hueValue = document.getElementById('hueValue');
    const saturationValue = document.getElementById('saturationValue');
    
    if (hueValue) hueValue.textContent = hue + '°';
    if (saturationValue) saturationValue.textContent = saturation + '%';
    
    const filter = `hue-rotate(${hue}deg) saturate(${saturation}%)`;
    
    const selectedAvatar = document.querySelector('.avatar.selected');
    if (selectedAvatar) {
        selectedAvatar.style.filter = filter;
        selectedAvatar.classList.add('avatar-customized');
    }
    
    const previewImg = document.getElementById('selectedAvatarImg');
    if (previewImg && previewImg.style.display !== 'none') {
        previewImg.style.filter = filter;
    }
    
    const modalAvatarImg = document.getElementById('modalSelectedAvatarImg');
    if (modalAvatarImg) {
        modalAvatarImg.style.filter = filter;
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

// Additional JavaScript functions for index.js pagination support

// Initialize pagination variables
let currentPage = 1;

// Update pagination UI function
function updatePaginationUI() {
    const totalPages = document.querySelectorAll('.avatar-page').length;
    if (totalPages === 0) return;
    
    const currentPageInfo = document.getElementById('currentPageInfo');
    if (currentPageInfo) {
        currentPageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    }
    
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    // Update disabled states
    if (prevBtn) {
        if (currentPage === 1) {
            prevBtn.disabled = true;
            prevBtn.classList.add('disabled');
        } else {
            prevBtn.disabled = false;
            prevBtn.classList.remove('disabled');
        }
    }
    
    if (nextBtn) {
        if (currentPage === totalPages) {
            nextBtn.disabled = true;
            nextBtn.classList.add('disabled');
        } else {
            nextBtn.disabled = false;
            nextBtn.classList.remove('disabled');
        }
    }
}

// Enhanced page change function
function changePage(direction) {
    const totalPages = document.querySelectorAll('.avatar-page').length;
    const newPage = currentPage + direction;
    
    if (newPage >= 1 && newPage <= totalPages) {
        // Hide current page
        const currentPageElement = document.querySelector(`.avatar-page[data-page="${currentPage}"]`);
        if (currentPageElement) {
            currentPageElement.style.display = 'none';
        }
        
        // Show new page
        currentPage = newPage;
        const newPageElement = document.querySelector(`.avatar-page[data-page="${currentPage}"]`);
        if (newPageElement) {
            newPageElement.style.display = 'block';
        }
        
        updatePaginationUI();
        
        // Smooth scroll to avatar container on mobile for better UX
        if (window.innerWidth <= 768) {
            const avatarContainer = document.querySelector('.avatar-container');
            if (avatarContainer) {
                avatarContainer.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        }
    }
}

// Random avatar selection that works with pagination
function randomAvatar() {
    const currentPageElement = document.querySelector(`.avatar-page[data-page="${currentPage}"]`);
    if (!currentPageElement) return;
    
    const visibleAvatars = currentPageElement.querySelectorAll('.avatar');
    
    if (visibleAvatars.length > 0) {
        const randomIndex = Math.floor(Math.random() * visibleAvatars.length);
        const randomAvatar = visibleAvatars[randomIndex];
        
        // Trigger click event on the random avatar
        if (randomAvatar.click) {
            randomAvatar.click();
        } else {
            // Fallback for older browsers
            const event = new Event('click', { bubbles: true });
            randomAvatar.dispatchEvent(event);
        }
    }
}

// Clear avatar selection function that works with pagination
function clearAvatarSelection() {
    // Remove selection from all avatars across all pages
    document.querySelectorAll('.avatar').forEach(avatar => {
        avatar.classList.remove('selected');
        avatar.style.border = '';
        avatar.style.boxShadow = '';
        if (avatar.style.filter) {
            avatar.style.filter = '';
        }
    });
    
    // Clear any stored avatar selection
    const selectedAvatarInput = document.getElementById('selectedAvatar');
    if (selectedAvatarInput) {
        selectedAvatarInput.value = '';
    }
    
    // Update preview if it exists
    const avatarPreview = document.getElementById('selectedAvatarPreview');
    if (avatarPreview) {
        avatarPreview.style.display = 'none';
    }
    
    const noAvatarSelected = document.getElementById('noAvatarSelected');
    if (noAvatarSelected) {
        noAvatarSelected.style.display = 'block';
    }
}

// Initialize pagination when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Set up pagination
    const totalPages = document.querySelectorAll('.avatar-page').length;
    if (totalPages > 0) {
        currentPage = 1;
        updatePaginationUI();
        
        // Ensure only the first page is visible
        document.querySelectorAll('.avatar-page').forEach((page, index) => {
            page.style.display = index === 0 ? 'block' : 'none';
        });
    }
    
    // Add keyboard navigation support
    document.addEventListener('keydown', function(e) {
        // Only handle keys when not typing in input fields
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        if (e.key === 'ArrowLeft' && currentPage > 1) {
            e.preventDefault();
            changePage(-1);
        } else if (e.key === 'ArrowRight' && currentPage < totalPages) {
            e.preventDefault();
            changePage(1);
        }
    });
});

// Swipe support for mobile devices
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
});

function handleSwipe() {
    const swipeThreshold = 50; // Minimum distance for a swipe
    const swipeDistance = touchEndX - touchStartX;
    
    if (Math.abs(swipeDistance) > swipeThreshold) {
        if (swipeDistance > 0 && currentPage > 1) {
            // Swipe right - go to previous page
            changePage(-1);
        } else if (swipeDistance < 0 && currentPage < document.querySelectorAll('.avatar-page').length) {
            // Swipe left - go to next page
            changePage(1);
        }
    }
}


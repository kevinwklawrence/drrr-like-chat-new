// js/user_settings.js - User Settings Management System

class UserSettings {
    constructor() {
        this.settings = {
            notificationVolume: 50,
            notificationMuted: false,
            youtubeVolume: 50,
            youtubeMuted: false,
            theatreMode: false,
            dimmerLevel: 100,
            excludeYouTubeFromDimmer: true
        };
        
        this.modalInitialized = false;
        this.youtubeVolumeUpdateTimeout = null;
        
        this.loadSettings();
        this.initializeModal();
        this.setupEventListeners();
        
        // Apply dimmer effect on initialization
        setTimeout(() => this.updateDimmerEffect(), 100);
    }

    loadSettings() {
        try {
            const saved = localStorage.getItem('userSettings');
            if (saved) {
                const parsedSettings = JSON.parse(saved);
                this.settings = { ...this.settings, ...parsedSettings };
            }
        } catch (e) {
            console.warn('Could not load user settings:', e);
            this.resetToDefaults();
        }
    }

    saveSettings() {
        try {
            localStorage.setItem('userSettings', JSON.stringify(this.settings));
        } catch (e) {
            console.warn('Could not save user settings:', e);
        }
    }

    resetToDefaults() {
        this.settings = {
            notificationVolume: 50,
            notificationMuted: false,
            youtubeVolume: 50,
            youtubeMuted: false,
            theatreMode: false,
            dimmerLevel: 100,
            excludeYouTubeFromDimmer: true
        };
        this.saveSettings();
    }

    initializeModal() {
        if (this.modalInitialized) return;
        
        const modalHTML = `
        <!-- User Settings Modal -->
        <div class="modal fade user-settings-modal" id="userSettingsModal" tabindex="-1" aria-labelledby="userSettingsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="userSettingsModalLabel">
                            <i class="fas fa-cog"></i>
                            User Settings
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Audio Settings Section -->
                        <div class="settings-section">
                            <h6><i class="fas fa-volume-up"></i> Audio Settings</h6>
                            
                            <div class="volume-control">
                                <label for="notificationVolume">Notification Sounds</label>
                                <div class="volume-row">
                                    <button class="mute-btn" id="notificationMuteBtn" onclick="userSettingsManager.toggleNotificationMute()">
                                        <i class="fas fa-volume-up"></i>
                                    </button>
                                    <input type="range" class="volume-slider" id="notificationVolume" min="0" max="100" value="50">
                                    <span class="volume-value" id="notificationVolumeValue">50</span>
                                </div>
                        </div>

                        
                            </div>

                            

                        <!-- YouTube Settings Section -->
                        <div class="settings-section" id="youtubeSettingsSection" style="display: none;">
                            <h6><i class="fab fa-youtube"></i> YouTube Settings</h6>
                            
                            <div class="volume-control">
                                <label for="youtubeVolume">YouTube Video Volume</label>
                                <div class="volume-row">
                                    <button class="mute-btn" id="youtubeMuteBtn" onclick="userSettingsManager.toggleYouTubeMute()">
                                        <i class="fas fa-volume-up"></i>
                                    </button>
                                    <input type="range" class="volume-slider" id="youtubeVolume" min="0" max="100" value="50">
                                    <span class="volume-value" id="youtubeVolumeValue">50</span>
                                </div>
                            </div>

                           <!-- <div class="theatre-toggle" onclick="userSettingsManager.toggleTheatreMode()">
                                <label for="theatreMode">
                                    <i class="fas fa-expand"></i>
                                    Theatre Mode
                                </label>
                                <div class="theatre-switch" id="theatreSwitch"></div>
                            </div>-->
                        </div>

                        <!-- Screen Dimmer Settings Section -->
                        <div class="settings-section">
                            <h6><i class="fas fa-adjust"></i> Screen Dimmer</h6>
                            
                            <div class="volume-control">
                                <label for="dimmerLevel">Screen Brightness</label>
                                <div class="volume-row">
                                    <button class="mute-btn" id="dimmerToggleBtn" onclick="userSettingsManager.toggleDimmer()">
                                        <i class="fas fa-lightbulb"></i>
                                    </button>
                                    <input type="range" class="volume-slider" id="dimmerLevel" min="10" max="100" value="100">
                                    <span class="volume-value" id="dimmerLevelValue">100</span>
                                </div>
                            </div>

                            <!--<div class="theatre-toggle" onclick="userSettingsManager.toggleYouTubeExclusion()">
                                <label for="excludeYouTube">
                                    <i class="fab fa-youtube"></i>
                                    Exclude YouTube from Dimming
                                </label>
                                <div class="theatre-switch" id="youtubeExclusionSwitch"></div>
                            </div>-->
                        </div>

                        <div class="settings-footer">
                            <button class="reset-btn" onclick="userSettingsManager.resetUserSettings()">
                                <i class="fas fa-undo"></i> Reset to Defaults
                            </button>
                            <small class="text-muted">Settings are saved automatically</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Theatre Mode Exit Button (hidden by default) -->
        <button class="theatre-exit-btn" id="theatreExitBtn" onclick="userSettingsManager.exitTheatreMode()" style="display: none;">
            <i class="fas fa-compress"></i> Exit Theatre
        </button>
        `;

        // Add modal to body
        $('body').append(modalHTML);
        this.modalInitialized = true;
        
        // Initialize controls after modal is added
        setTimeout(() => this.initializeControls(), 100);
    }

    initializeControls() {
        // Notification volume controls
        const notificationSlider = document.getElementById('notificationVolume');
        const notificationValue = document.getElementById('notificationVolumeValue');
        const notificationMuteBtn = document.getElementById('notificationMuteBtn');

        if (notificationSlider) {
            notificationSlider.value = this.settings.notificationVolume;
            notificationValue.textContent = this.settings.notificationVolume;
            
            notificationSlider.addEventListener('input', (e) => {
                this.settings.notificationVolume = parseInt(e.target.value);
                notificationValue.textContent = this.settings.notificationVolume;
                
                // Update slider visual
                this.updateSliderVisual(notificationSlider, this.settings.notificationVolume);
                this.saveSettings();
                
                if (this.settings.notificationVolume > 0) {
                    this.settings.notificationMuted = false;
                    this.updateMuteButton(notificationMuteBtn, false);
                }
            });
            
            this.updateSliderVisual(notificationSlider, this.settings.notificationVolume);
        }

        // YouTube volume controls
        const youtubeSlider = document.getElementById('youtubeVolume');
        const youtubeValue = document.getElementById('youtubeVolumeValue');
        const youtubeMuteBtn = document.getElementById('youtubeMuteBtn');

        if (youtubeSlider) {
            youtubeSlider.value = this.settings.youtubeVolume;
            youtubeValue.textContent = this.settings.youtubeVolume;
            
            youtubeSlider.addEventListener('input', (e) => {
                this.settings.youtubeVolume = parseInt(e.target.value);
                youtubeValue.textContent = this.settings.youtubeVolume;
                
                // Update slider visual
                this.updateSliderVisual(youtubeSlider, this.settings.youtubeVolume);
                this.saveSettings();
                
                // Debounce YouTube volume updates
                if (this.youtubeVolumeUpdateTimeout) {
                    clearTimeout(this.youtubeVolumeUpdateTimeout);
                }
                this.youtubeVolumeUpdateTimeout = setTimeout(() => {
                    this.updateYouTubeVolume();
                }, 100);
                
                if (this.settings.youtubeVolume > 0) {
                    this.settings.youtubeMuted = false;
                    this.updateMuteButton(youtubeMuteBtn, false);
                }
            });
            
            this.updateSliderVisual(youtubeSlider, this.settings.youtubeVolume);
        }

        // Dimmer controls
        const dimmerSlider = document.getElementById('dimmerLevel');
        const dimmerValue = document.getElementById('dimmerLevelValue');
        const dimmerToggleBtn = document.getElementById('dimmerToggleBtn');

        if (dimmerSlider) {
            dimmerSlider.value = this.settings.dimmerLevel;
            dimmerValue.textContent = this.settings.dimmerLevel;
            
            dimmerSlider.addEventListener('input', (e) => {
                this.settings.dimmerLevel = parseInt(e.target.value);
                dimmerValue.textContent = this.settings.dimmerLevel;
                
                // Update slider visual
                this.updateSliderVisual(dimmerSlider, this.settings.dimmerLevel);
                this.saveSettings();
                this.updateDimmerEffect();
            });
            
            this.updateSliderVisual(dimmerSlider, this.settings.dimmerLevel);
        }

        // YouTube exclusion toggle
        const youtubeExclusionSwitch = document.getElementById('youtubeExclusionSwitch');
        if (youtubeExclusionSwitch) {
            if (this.settings.excludeYouTubeFromDimmer) {
                youtubeExclusionSwitch.classList.add('active');
            }
        }

        // Theatre mode toggle
        const theatreSwitch = document.getElementById('theatreSwitch');
        if (theatreSwitch) {
            if (this.settings.theatreMode) {
                theatreSwitch.classList.add('active');
            }
        }

        // Update mute button states
        this.updateMuteButton(notificationMuteBtn, this.settings.notificationMuted);
        this.updateMuteButton(youtubeMuteBtn, this.settings.youtubeMuted);
        
        // Check YouTube availability
        this.checkYouTubeAvailability();
        
        // Apply initial dimmer effect
        this.updateDimmerEffect();
    }

    updateSliderVisual(slider, value) {
        if (slider) {
            slider.style.setProperty('--volume-percent', `${value}%`);
        }
    }

    updateMuteButton(button, isMuted) {
        if (!button) return;
        
        const icon = button.querySelector('i');
        if (isMuted) {
            button.classList.add('muted');
            icon.className = 'fas fa-volume-mute';
        } else {
            button.classList.remove('muted');
            icon.className = 'fas fa-volume-up';
        }
    }

    checkYouTubeAvailability() {
        // Show YouTube settings if YouTube is enabled in the room
        const youtubeContainer = document.querySelector('.youtube-player-container');
        const youtubeSection = document.getElementById('youtubeSettingsSection');
        
        if (youtubeContainer && youtubeSection) {
            youtubeSection.style.display = 'block';
        }
    }

    setupEventListeners() {
        // Handle escape key for theatre mode
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.settings.theatreMode) {
                this.exitTheatreMode();
            }
        });

        // Handle window resize in theatre mode
        window.addEventListener('resize', () => {
            if (this.settings.theatreMode) {
                this.adjustTheatreModeLayout();
            }
        });

        // Modal event listeners
        $(document).on('show.bs.modal', '#userSettingsModal', () => {
            if (this.settings.theatreMode) {
                const container = document.querySelector('.youtube-player-container');
                if (container) container.style.zIndex = '9998';
            }
        });

        $(document).on('hide.bs.modal', '#userSettingsModal', () => {
            if (this.settings.theatreMode) {
                const container = document.querySelector('.youtube-player-container');
                if (container) container.style.zIndex = '9999';
            }
        });
    }

    adjustTheatreModeLayout() {
        const youtubeContainer = document.querySelector('.youtube-player-container.theatre-mode');
        if (youtubeContainer) {
            const player = youtubeContainer.querySelector('#youtube-player');
            if (player) {
                player.style.height = `calc(100vh - 180px)`;
            }
        }
    }

    updateYouTubeVolume() {
        if (typeof youtubePlayer !== 'undefined' && youtubePlayer && typeof youtubePlayer.setVolume === 'function') {
            try {
                const volume = this.settings.youtubeMuted ? 0 : this.settings.youtubeVolume;
                youtubePlayer.setVolume(volume);
            } catch (e) {
                console.warn('Could not set YouTube volume:', e);
            }
        }
    }

    updateDimmerEffect() {
        const htmlElement = document.documentElement;
        const youtubeIframe = document.querySelector('#youtube-player iframe') || document.querySelector('#youtube-player');
        
        // Apply brightness filter to HTML element
        const brightness = this.settings.dimmerLevel / 100;
        htmlElement.style.filter = `brightness(${brightness})`;
        
        // Exclude YouTube iframe if option is enabled
        if (this.settings.excludeYouTubeFromDimmer && youtubeIframe) {
            youtubeIframe.style.filter = 'brightness(1.0)';
        } else if (youtubeIframe) {
            youtubeIframe.style.filter = '';
        }
    }

    toggleDimmer() {
        // Toggle between current level and 100% (full brightness)
        if (this.settings.dimmerLevel < 100) {
            this.settings.dimmerLevel = 100;
        } else {
            this.settings.dimmerLevel = 70; // Default dimmed level
        }
        
        this.saveSettings();
        this.updateDimmerEffect();
        
        // Update UI controls
        const dimmerSlider = document.getElementById('dimmerLevel');
        const dimmerValue = document.getElementById('dimmerLevelValue');
        
        if (dimmerSlider) {
            dimmerSlider.value = this.settings.dimmerLevel;
            this.updateSliderVisual(dimmerSlider, this.settings.dimmerLevel);
        }
        if (dimmerValue) {
            dimmerValue.textContent = this.settings.dimmerLevel;
        }
    }

    toggleYouTubeExclusion() {
        this.settings.excludeYouTubeFromDimmer = !this.settings.excludeYouTubeFromDimmer;
        this.saveSettings();
        
        const youtubeExclusionSwitch = document.getElementById('youtubeExclusionSwitch');
        if (youtubeExclusionSwitch) {
            if (this.settings.excludeYouTubeFromDimmer) {
                youtubeExclusionSwitch.classList.add('active');
            } else {
                youtubeExclusionSwitch.classList.remove('active');
            }
        }
        
        this.updateDimmerEffect();
    }

    playNotificationSound(soundFile = '/sounds/message_notification.mp3') {
        if (this.settings.notificationMuted || this.settings.notificationVolume === 0) {
            return;
        }

        try {
            const audio = new Audio(soundFile);
            audio.volume = this.settings.notificationVolume / 100;
            audio.play().catch(e => {
                console.log('Could not play notification sound:', e);
            });
        } catch (e) {
            console.warn('Error creating audio:', e);
        }
    }

    toggleNotificationMute() {
        this.settings.notificationMuted = !this.settings.notificationMuted;
        this.saveSettings();
        
        const button = document.getElementById('notificationMuteBtn');
        this.updateMuteButton(button, this.settings.notificationMuted);
        
        // Test sound when unmuting
        if (!this.settings.notificationMuted) {
            setTimeout(() => this.playNotificationSound(), 100);
        }
    }

    toggleYouTubeMute() {
        this.settings.youtubeMuted = !this.settings.youtubeMuted;
        this.saveSettings();
        
        const button = document.getElementById('youtubeMuteBtn');
        this.updateMuteButton(button, this.settings.youtubeMuted);
        
        this.updateYouTubeVolume();
    }

    toggleTheatreMode() {
        this.settings.theatreMode = !this.settings.theatreMode;
        this.saveSettings();
        
        if (this.settings.theatreMode) {
            this.enterTheatreMode();
        } else {
            this.exitTheatreMode();
        }
    }

    enterTheatreMode() {
        const theatreSwitch = document.getElementById('theatreSwitch');
        const youtubeContainer = document.querySelector('.youtube-player-container');
        const theatreExitBtn = document.getElementById('theatreExitBtn');
        
        if (theatreSwitch) theatreSwitch.classList.add('active');
        
        if (youtubeContainer) {
            youtubeContainer.classList.add('theatre-mode');
            document.body.classList.add('theatre-mode-active');
            
            if (theatreExitBtn) theatreExitBtn.style.display = 'block';
            
            // Adjust layout
            setTimeout(() => this.adjustTheatreModeLayout(), 100);
        }
    }

    exitTheatreMode() {
        this.settings.theatreMode = false;
        this.saveSettings();
        
        const theatreSwitch = document.getElementById('theatreSwitch');
        const youtubeContainer = document.querySelector('.youtube-player-container');
        const theatreExitBtn = document.getElementById('theatreExitBtn');
        
        if (theatreSwitch) theatreSwitch.classList.remove('active');
        
        if (youtubeContainer) {
            youtubeContainer.classList.remove('theatre-mode');
            document.body.classList.remove('theatre-mode-active');
            
            if (theatreExitBtn) theatreExitBtn.style.display = 'none';
        }
    }

    resetUserSettings() {
        if (confirm('Reset all settings to defaults? This will reset volumes, disable theatre mode, and restore normal brightness.')) {
            this.exitTheatreMode();
            this.resetToDefaults();
            this.initializeControls();
            this.updateYouTubeVolume();
            this.updateDimmerEffect();
        }
    }

    openModal() {
        if (!this.modalInitialized) {
            this.initializeModal();
        }
        
        try {
            const modal = new bootstrap.Modal(document.getElementById('userSettingsModal'));
            modal.show();
        } catch (e) {
            console.warn('Could not open settings modal:', e);
            // Fallback for older Bootstrap versions
            $('#userSettingsModal').modal('show');
        }
    }
}

// Global instance
let userSettingsManager;

// Initialize when DOM and jQuery are ready
function initializeUserSettings() {
    userSettingsManager = new UserSettings();
    
    // Override existing notification functions to use user settings
    window.originalPlayMessageNotification = window.playMessageNotification;
    window.playMessageNotification = function() {
        if (userSettingsManager) {
            userSettingsManager.playNotificationSound('/sounds/message_notification.mp3');
        } else if (window.originalPlayMessageNotification) {
            window.originalPlayMessageNotification();
        }
    };

    window.originalPlayReplyOrMentionSound = window.playReplyOrMentionSound;
    window.playReplyOrMentionSound = function() {
        if (userSettingsManager) {
            userSettingsManager.playNotificationSound('/sounds/reply_or_mention_notification.mp3');
        } else if (window.originalPlayReplyOrMentionSound) {
            window.originalPlayReplyOrMentionSound();
        }
    };

    window.originalPlayFriendNotificationSound = window.playFriendNotificationSound;
    window.playFriendNotificationSound = function() {
        if (userSettingsManager) {
            userSettingsManager.playNotificationSound('/sounds/private_message_notification.mp3');
        } else if (window.originalPlayFriendNotificationSound) {
            window.originalPlayFriendNotificationSound();
        }
    };

    window.originalPlayNotificationSound = window.playNotificationSound;
    window.playNotificationSound = function() {
        if (userSettingsManager) {
            userSettingsManager.playNotificationSound('/sounds/notification.mp3');
        } else if (window.originalPlayNotificationSound) {
            window.originalPlayNotificationSound();
        }
    };
}

// Wait for jQuery to be available
function waitForJQuery(callback) {
    if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined') {
        if (document.readyState === 'loading') {
            $(document).ready(callback);
        } else {
            callback();
        }
    } else {
        setTimeout(() => waitForJQuery(callback), 100);
    }
}

// Initialize when everything is ready
waitForJQuery(initializeUserSettings);

// Global function for opening settings modal
function openUserSettings() {
    if (userSettingsManager) {
        userSettingsManager.openModal();
    }
}

// Hook into YouTube player ready event
$(window).on('load', function() {
    // Set up YouTube integration after page load
    if (typeof youtubePlayer !== 'undefined' || typeof window.onYouTubeIframeAPIReady !== 'undefined') {
        const originalOnReady = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = function() {
            if (originalOnReady) originalOnReady();
            
            setTimeout(() => {
                if (userSettingsManager) {
                    userSettingsManager.updateYouTubeVolume();
                    userSettingsManager.updateDimmerEffect(); // Reapply dimmer to new iframe
                }
            }, 1000);
        };
    }
    
    // Also hook into the global YouTube player ready function if it exists
    if (typeof window.onYouTubePlayerReady !== 'undefined') {
        const originalPlayerReady = window.onYouTubePlayerReady;
        window.onYouTubePlayerReady = function(event) {
            if (originalPlayerReady) originalPlayerReady(event);
            
            setTimeout(() => {
                if (userSettingsManager) {
                    userSettingsManager.updateYouTubeVolume();
                    userSettingsManager.updateDimmerEffect(); // Reapply dimmer to new iframe
                }
            }, 1000);
        };
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { UserSettings, userSettingsManager };
}
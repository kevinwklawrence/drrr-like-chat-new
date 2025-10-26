
<!-- ========================================
     DM MODAL - Direct Messages & Whispers
     ======================================== -->
<div id="dmModal" class="dm-modal hidden">
    <!-- Header -->
    <div class="dm-modal-header">
        <h6 class="dm-modal-title">
            <i class="fas fa-envelope"></i> Messages
        </h6>
        <div class="dm-modal-controls">
            <button type="button" onclick="minimizeDMModal()" title="Minimize">
                <i class="fas fa-minus"></i>
            </button>
            <button type="button" onclick="closeDMModal()" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="dm-modal-tabs">
        <button type="button" 
                class="dm-modal-tab active" 
                data-tab="private-messages" 
                onclick="switchDMTab('private-messages')">
            <i class="fas fa-envelope"></i> Private Messages
        </button>
        <button type="button" 
                class="dm-modal-tab" 
                data-tab="whispers" 
                onclick="switchDMTab('whispers')">
            <i class="fas fa-comment-dots"></i> Whispers
        </button>
    </div>
    
    <!-- Body (messages display) -->
    <div class="dm-modal-body">
        <div id="privateMessagesTab" class="dm-tab-content active">
            <!-- Private messages will be rendered here by JavaScript -->
        </div>
        <div id="whispersTab" class="dm-tab-content">
            <!-- Whisper messages will be rendered here by JavaScript -->
        </div>
    </div>
    
    <!-- Footer (input area) -->
    <div class="dm-modal-footer">
        <input type="text" 
               id="dmMessageInput" 
               placeholder="Type a message..." 
               autocomplete="off"
               onkeypress="if(event.key==='Enter'){sendDMMessage(event);return false;}">
        <button type="button" 
                onclick="sendDMMessage(event);return false;" 
                title="Send message">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- ========================================
     FRIENDS SIDEBAR - Desktop View
     ======================================== -->
<div id="friendsSidebar" class="friends-sidebar">
    <!-- Sidebar Header -->
    <div class="friends-sidebar-header">
        <h5><i class="fas fa-user-friends"></i> Friends</h5>
    </div>
    
    <!-- Sidebar Content (Scrollable) -->
    <div class="friends-sidebar-content" id="friendsSidebarContent">
        
        <!-- Friend Requests Section (Hidden by default, shown when there are requests) -->
        <div id="friendRequestsSection" class="sidebar-section" style="display: none;">
            <div class="sidebar-section-header">
                <h6>
                    <i class="fas fa-user-plus"></i> Friend Requests
                    <span class="badge bg-warning" id="friendRequestsCount">0</span>
                </h6>
            </div>
            <div id="friendRequestsList" class="sidebar-section-content">
                <!-- Friend requests will be rendered here by JavaScript -->
            </div>
        </div>
        
        <!-- Friends List Section -->
        <div class="sidebar-section">
            <div class="sidebar-section-header">
                <h6>
                    <i class="fas fa-users"></i> Friends
                    <span class="badge bg-primary" id="friendsCount">0</span>
                </h6>
            </div>
            <div id="friendsList" class="sidebar-section-content">
                <!-- Friends list will be rendered here by JavaScript -->
            </div>
        </div>
        
        <!-- Add Friend Section -->
        <div class="sidebar-section">
            <div class="sidebar-section-header">
                <h6><i class="fas fa-user-plus"></i> Add Friend</h6>
            </div>
            <div class="sidebar-section-content">
                <div class="add-friend-form">
                    <input type="text" 
                           id="addFriendInput" 
                           placeholder="Enter username..." 
                           autocomplete="off"
                           onkeypress="if(event.key==='Enter'){addFriend();return false;}">
                    <button type="button" 
                            onclick="addFriend()" 
                            title="Send friend request">
                        <i class="fas fa-user-plus"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Recent Conversations Section -->
        <div class="sidebar-section">
            <div class="sidebar-section-header">
                <h6><i class="fas fa-comments"></i> Recent Conversations</h6>
            </div>
            <div id="conversationsList" class="sidebar-section-content">
                <!-- Conversations list will be rendered here by JavaScript -->
            </div>
        </div>
        
    </div>
</div>

<!-- ========================================
     MOBILE FRIENDS MODAL
     ======================================== -->
<div class="modal fade" id="friendsMobileModal" tabindex="-1" aria-labelledby="friendsMobileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-scrollable">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="friendsMobileModalLabel">
                    <i class="fas fa-user-friends"></i> Friends & Messages
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body" id="friendsMobileContent">
                <!-- Content will be synced from desktop sidebar by JavaScript -->
            </div>
        </div>
    </div>
</div>
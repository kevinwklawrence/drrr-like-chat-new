<?php
// moderator.php - Main moderator dashboard
session_start();

require_once 'security_config.php';


if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    header("Location: /guest");
    exit;
}

include 'db_connect.php';

// Include site ban check
include 'check_site_ban.php';
checkSiteBan($conn, true);

// Check if user is moderator or admin
$user_id = $_SESSION['user']['id'];
$is_moderator = false;
$is_admin = false;

$stmt = $conn->prepare("SELECT is_moderator, is_admin, username FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_moderator = ($user_data['is_moderator'] == 1);
        $is_admin = ($user_data['is_admin'] == 1);
        $username = $user_data['username'];
    }
    $stmt->close();
}

if (!$is_moderator && !$is_admin) {
    header("Location: /lounge");
    exit;
}

// Clean up expired bans
cleanupExpiredSiteBans($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Dashboard | Duranu</title>
    <?php include 'fav.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/lounge.css" rel="stylesheet">
    <link href="css/moderator.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        /* Use lounge.css styling as base */
        .mod-card {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 8px;
            color: #e0e0e0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .mod-card .card-header {
            background: #333;
            border-bottom: 1px solid #404040;
            border-radius: 8px 8px 0 0 !important;
            color: #e0e0e0;
            padding: 12px 16px;
            font-weight: 500;
        }
        
        .message-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        
        .message-item.announcement {
            border-left-color: #43e97b;
        }
        
        .message-meta {
            font-size: 0.85rem;
            color: #aaa;
            margin-bottom: 0.5rem;
        }
        
        .message-content {
            background: rgba(0, 0, 0, 0.2);
            padding: 0.75rem;
            border-radius: 6px;
            margin: 0.5rem 0;
            color: #e0e0e0;
        }
        
        .ban-item {
            background: rgba(245, 87, 108, 0.1);
            border: 1px solid rgba(245, 87, 108, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="lounge-header">
    <div class="header-centered-logo">
        <img src="images/duranu.png" alt="Duranu Logo" class="site-logo">
        <h1 class="lounge-title h4">
            <i class="fas fa-shield-alt"></i> Moderator Dashboard
            <?php if ($is_admin): ?>
                <span class="badge bg-danger ms-2">Admin</span>
            <?php else: ?>
                <span class="badge bg-warning ms-2">Moderator</span>
            <?php endif; ?>
        </h1>
        <small class="text-muted">Welcome back, <?php echo htmlspecialchars($username); ?></small>
    </div>
    <div class="text-center">
        <a href="/restrict_user" class="btn logout-btn">
            <i class="fas fa-arrow-left"></i> Restrict User Invites
        </a>
        <a href="/code_manager" class="btn logout-btn">
            <i class="fas fa-arrow-left"></i> Manage Redemption Codes
        </a>
        <a href="/item_creator" class="btn logout-btn">
            <i class="fas fa-arrow-left"></i> Item Creator
        </a>
        <a href="/lounge" class="btn logout-btn">
            <i class="fas fa-arrow-left"></i> Back to Lounge
        </a>
        <a href="/room" class="btn logout-btn">
            <i class="fas fa-arrow-left"></i> Back to Room
        </a>
    </div>
</div>
        <div class="row">
            <!-- Left Sidebar - Quick Actions -->
            <div class="col-lg-3 col-md-4">
                <div class="card mod-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Send Announcement -->
                        <div class="mb-3">
                            <button class="btn create-room-btn w-100" onclick="showAnnouncementModal()">
                                <i class="fas fa-bullhorn"></i> Send Announcement
                            </button>
                        </div>
                        
                        <!-- Site Ban User -->
                        <div class="mb-3">
                            <button class="btn btn-danger w-100" onclick="showSiteBanModal()">
                                <i class="fas fa-ban"></i> Site Ban User
                            </button>
                        </div>
                        
                        <!-- Refresh Data -->
                        <div class="mb-3">
                            <button class="btn refresh-btn w-100" onclick="refreshAllData()">
                                <i class="fas fa-sync-alt"></i> Refresh Data
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Site Bans Summary -->
                <div class="card mod-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-ban"></i> Active Site Bans
                            <button class="btn btn-sm btn-outline-light float-end" onclick="loadSiteBans()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="siteBansSummary">
                            <div class="text-center">
                                <div class="loading-spinner"></div>
                                <div>Loading bans...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="col-lg-9 col-md-8">
                <!-- Message Filters -->
                <div class="card mod-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter"></i> Message Monitoring
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <select class="form-select" id="messageTypeFilter">
                                    <option value="all">All Messages</option>
                                    <option value="chat">Chat Messages</option>
                                    <option value="announcement">Announcements</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="searchFilter" placeholder="Search messages...">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="userFilter" placeholder="Filter by user...">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="roomFilter" placeholder="Filter by room...">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col">
                                <button class="btn create-room-btn" onclick="loadMessages()">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <button class="btn btn-outline-light" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                                <span class="badge bg-info ms-2" id="messageCount">0 messages</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Messages Display -->
                <div class="card mod-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-comments"></i> All Messages
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div id="messagesContainer">
                            <div class="text-center py-4">
                                <div class="loading-spinner"></div>
                                <div class="mt-2">Loading messages...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-bullhorn"></i> Send Site Announcement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="announcementMessage" class="form-label">Announcement Message</label>
                        <textarea class="form-control" id="announcementMessage" rows="4" maxlength="500" placeholder="Enter your announcement message..."></textarea>
                        <div class="form-text text-muted">Maximum 500 characters. This will be sent to all active rooms.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn create-room-btn" onclick="sendAnnouncement()">
                        <i class="fas fa-bullhorn"></i> Send Announcement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Site Ban Modal -->
    <div class="modal fade" id="siteBanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-ban"></i> Site Ban User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="banUserInput" class="form-label">User to Ban</label>
                        <input type="text" class="form-control" id="banUserInput" placeholder="Username, User ID, or IP Address">
                        <div class="form-text text-muted">Enter username for registered users, or IP address for guests</div>
                    </div>
                    <div class="mb-3">
                        <label for="banDuration" class="form-label">Ban Duration</label>
                        <select class="form-select" id="banDuration">
                            <option value="3600">1 Hour</option>
                            <option value="21600">6 Hours</option>
                            <option value="86400">24 Hours</option>
                            <option value="604800">7 Days</option>
                            <option value="2592000">30 Days</option>
                            <option value="permanent">Permanent</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="banReason" class="form-label">Reason (Optional)</label>
                        <textarea class="form-control" id="banReason" rows="3" placeholder="Enter reason for ban..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="siteBanUser()">
                        <i class="fas fa-ban"></i> Ban User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Global variables
    const currentUser = <?php echo json_encode($_SESSION['user']); ?>;
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    
    // Initialize dashboard
    $(document).ready(function() {
        loadMessages();
        loadSiteBansSummary();
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            loadMessages();
            loadSiteBansSummary();
        }, 30000);
    });
    
    // Load messages with filters
    function loadMessages() {
        const filters = {
            type: $('#messageTypeFilter').val(),
            search: $('#searchFilter').val(),
            user_filter: $('#userFilter').val(),
            room_filter: $('#roomFilter').val(),
            limit: 50
        };
        
        $.ajax({
            url: 'api/get_all_messages.php',
            method: 'GET',
            data: filters,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    displayMessages(response.messages);
                    $('#messageCount').text(response.count + ' messages');
                } else {
                    $('#messagesContainer').html('<div class="alert alert-danger">Error: ' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Load messages error:', error);
                $('#messagesContainer').html('<div class="alert alert-danger">Failed to load messages: ' + error + '</div>');
            }
        });
    }
    
    // Display messages in HTML format
    function displayMessages(messages) {
        let html = '';
        
        if (messages.length === 0) {
            html = '<div class="text-center py-4"><i class="fas fa-inbox fa-3x mb-3 text-muted"></i><div>No messages found</div></div>';
        } else {
            messages.forEach(msg => {
                const typeClass = msg.type || 'chat';
                const typeIcon = getMessageTypeIcon(typeClass);
                const userName = msg.username || msg.guest_name || msg.user_id_string || 'Unknown';
                const roomName = msg.room_name || 'Unknown Room';
                const timestamp = new Date(msg.timestamp).toLocaleString();
                
                // Show user info with IP and user_id_string
                let userInfo = `<strong>${userName}</strong>`;
                if (msg.user_id_string) {
                    userInfo += ` <small class="text-muted">(ID: ${msg.user_id_string})</small>`;
                }
                if (msg.ip_address) {
                    userInfo += ` <small class="text-muted">(IP: ${msg.ip_address})</small>`;
                }
                
                html += `
                    <div class="message-item ${typeClass}">
                        <div class="message-meta d-flex justify-content-between">
                            <div>
                                ${typeIcon} ${userInfo}
                                ${msg.room_name ? ` in <em>${roomName}</em>` : ''}
                                ${msg.is_admin ? '<span class="badge bg-danger ms-1">Admin</span>' : ''}
                                ${msg.is_moderator ? '<span class="badge bg-warning ms-1">Mod</span>' : ''}
                            </div>
                            <div>
                                <small>${timestamp}</small>
                            </div>
                        </div>
                        <div class="message-content">
                            ${msg.message}
                        </div>
                        ${msg.user_id_string && msg.user_id_string !== 'SYSTEM' && msg.user_id_string !== 'SYSTEM_ANNOUNCEMENT' ? 
                            `<div class="message-actions mt-2">
                                <button class="btn btn-sm btn-outline-danger" onclick="showBanUserFromMessage('${msg.user_id_string}', '${userName}', '${msg.ip_address || ''}')">
                                    <i class="fas fa-ban"></i> Ban User
                                </button>
                            </div>` : ''}
                    </div>
                `;
            });
        }
        
        $('#messagesContainer').html(html);
    }
    
    // Get message type icon
    function getMessageTypeIcon(type) {
        const icons = {
            'chat': '<i class="fas fa-comment text-primary"></i>',
            'announcement': '<i class="fas fa-bullhorn text-success"></i>',
            'system': '<i class="fas fa-cog text-info"></i>'
        };
        return icons[type] || '<i class="fas fa-comment"></i>';
    }
    
    // Show announcement modal
    function showAnnouncementModal() {
        $('#announcementMessage').val('');
        $('#announcementModal').modal('show');
    }
    
    // Send announcement
    function sendAnnouncement() {
        const message = $('#announcementMessage').val().trim();
        
        if (!message) {
            alert('Please enter an announcement message');
            return;
        }
        
        const button = $('#announcementModal .create-room-btn');
        const originalText = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        
        $.ajax({
            url: 'api/send_announcement.php',
            method: 'POST',
            data: { message: message },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Announcement sent successfully to all rooms!');
                    $('#announcementModal').modal('hide');
                    setTimeout(loadMessages, 1000); // Refresh after 1 second
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to send announcement: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    // Show site ban modal
    function showSiteBanModal() {
        $('#banUserInput').val('');
        $('#banReason').val('');
        $('#siteBanModal').modal('show');
    }
    
    // Show ban user modal with pre-filled data
    function showBanUserFromMessage(userIdString, username, ipAddress) {
        $('#banUserInput').val(username || userIdString || ipAddress);
        $('#banReason').val('');
        $('#siteBanModal').modal('show');
    }
    
    // Site ban user
    function siteBanUser() {
        const userInput = $('#banUserInput').val().trim();
        const duration = $('#banDuration').val();
        const reason = $('#banReason').val().trim();
        
        if (!userInput) {
            alert('Please enter a user to ban');
            return;
        }
        
        const button = $('#siteBanModal .btn-danger');
        const originalText = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Banning...');
        
        // Send as username - the API will handle the lookup
        const banData = {
            username: userInput,
            duration: duration,
            reason: reason
        };
        
        $.ajax({
            url: 'api/site_ban_user.php',
            method: 'POST',
            data: banData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    $('#siteBanModal').modal('hide');
                    loadSiteBansSummary();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to ban user: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    // Load site bans summary
    function loadSiteBansSummary() {
        $.ajax({
            url: 'api/get_site_bans.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    displaySiteBansSummary(response.bans);
                } else {
                    $('#siteBansSummary').html('<div class="text-danger">Error loading bans</div>');
                }
            },
            error: function() {
                $('#siteBansSummary').html('<div class="text-danger">Failed to load bans</div>');
            }
        });
    }
    
    // Display site bans summary
    function displaySiteBansSummary(bans) {
        let html = '';
        
        if (bans.length === 0) {
            html = '<div class="text-center text-muted"><i class="fas fa-check-circle"></i><br>No active bans</div>';
        } else {
            html = `<div class="mb-2"><strong>${bans.length} active ban${bans.length !== 1 ? 's' : ''}</strong></div>`;
            
            bans.slice(0, 5).forEach(ban => {
                const username = ban.username || ban.user_id_string || 'Unknown';
                const banType = ban.ban_until ? 'Temporary' : 'Permanent';
                
                html += `
                    <div class="ban-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${username}</strong>
                                <br><small class="text-muted">${banType} - ${ban.ip_address}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-success" onclick="removeSiteBan(${ban.id}, '${username}')">
                                <i class="fas fa-unlock"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            if (bans.length > 5) {
                html += `<div class="text-center"><small class="text-muted">... and ${bans.length - 5} more</small></div>`;
            }
        }
        
        $('#siteBansSummary').html(html);
    }
    
    // Remove site ban
    function removeSiteBan(banId, username) {
        if (!confirm('Remove site ban for ' + username + '?')) {
            return;
        }
        
        $.ajax({
            url: 'api/remove_site_ban.php',
            method: 'POST',
            data: { ban_id: banId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Ban removed successfully');
                    loadSiteBansSummary();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to remove ban: ' + error);
            }
        });
    }
    
    // Utility functions
    function clearFilters() {
        $('#messageTypeFilter').val('all');
        $('#searchFilter').val('');
        $('#userFilter').val('');
        $('#roomFilter').val('');
        loadMessages();
    }
    
    function refreshAllData() {
        loadMessages();
        loadSiteBansSummary();
    }
    </script>
</body>
</html>
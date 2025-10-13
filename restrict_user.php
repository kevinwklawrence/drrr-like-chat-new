<?php
session_start();
include 'db_connect.php';

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

// Handle restriction toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $target_user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action === 'toggle_restrict') {
        $stmt = $conn->prepare("UPDATE users SET restricted = NOT restricted WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        if ($stmt->execute()) {
            // Get new status
            $check = $conn->prepare("SELECT restricted FROM users WHERE id = ?");
            $check->bind_param("i", $target_user_id);
            $check->execute();
            $result = $check->get_result()->fetch_assoc();
            $check->close();
            
            echo json_encode(['status' => 'success', 'restricted' => $result['restricted']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update']);
        }
        $stmt->close();
        exit;
    }
}

// Get all users with restriction status
$users = $conn->query("
    SELECT u.id, u.username, u.email, u.restricted, u.created_at,
           COUNT(DISTINCT ic.id) as invite_count,
           COUNT(DISTINCT iu.id) as invites_used
    FROM users u
    LEFT JOIN invite_codes ic ON u.id = ic.owner_user_id
    LEFT JOIN invite_usage iu ON u.id = iu.inviter_user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - User Restrictions | Duranu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 20px; }
        .table-container { background: white; padding: 20px; border-radius: 10px; }
        .restricted-row { background-color: #ffe6e6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-shield-alt me-2"></i>User Restrictions</h2>
            <a href="/lounge" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Lounge
            </a>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Restricting a user will make all their invite codes and personal keys invalid.
        </div>

        <div id="message"></div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Registered</th>
                            <th>Invite Codes</th>
                            <th>Invites Used</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr class="<?php echo $user['restricted'] ? 'restricted-row' : ''; ?>" id="user-<?php echo $user['id']; ?>">
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td><?php echo $user['invite_count']; ?></td>
                            <td><?php echo $user['invites_used']; ?></td>
                            <td>
                                <span class="status-badge badge <?php echo $user['restricted'] ? 'bg-danger' : 'bg-success'; ?>">
                                    <?php echo $user['restricted'] ? 'RESTRICTED' : 'ACTIVE'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm <?php echo $user['restricted'] ? 'btn-success' : 'btn-danger'; ?>" 
                                        onclick="toggleRestrict(<?php echo $user['id']; ?>)">
                                    <i class="fas <?php echo $user['restricted'] ? 'fa-check' : 'fa-ban'; ?> me-1"></i>
                                    <?php echo $user['restricted'] ? 'Unrestrict' : 'Restrict'; ?>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleRestrict(userId) {
            if (!confirm('Are you sure you want to toggle restriction for this user?')) {
                return;
            }

            $.post('admin_restrict_user.php', {
                action: 'toggle_restrict',
                user_id: userId
            }).done(function(data) {
                if (data.status === 'success') {
                    const row = $('#user-' + userId);
                    const btn = row.find('button');
                    const badge = row.find('.status-badge');
                    
                    if (data.restricted == 1) {
                        row.addClass('restricted-row');
                        badge.removeClass('bg-success').addClass('bg-danger').text('RESTRICTED');
                        btn.removeClass('btn-danger').addClass('btn-success')
                           .html('<i class="fas fa-check me-1"></i>Unrestrict');
                    } else {
                        row.removeClass('restricted-row');
                        badge.removeClass('bg-danger').addClass('bg-success').text('ACTIVE');
                        btn.removeClass('btn-success').addClass('btn-danger')
                           .html('<i class="fas fa-ban me-1"></i>Restrict');
                    }
                    
                    $('#message').html('<div class="alert alert-success">User restriction updated!</div>');
                    setTimeout(() => $('#message').html(''), 3000);
                } else {
                    $('#message').html('<div class="alert alert-danger">Error: ' + data.message + '</div>');
                }
            });
        }
    </script>
</body>
</html>
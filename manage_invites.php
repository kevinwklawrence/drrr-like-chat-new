<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    header("Location: /login");
    exit;
}

$user_id = $_SESSION['user']['id'];

// Handle personal key creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'create_key') {
        $key = bin2hex(random_bytes(32));
        $stmt = $conn->prepare("INSERT INTO personal_keys (user_id, key_value) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $key);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'key' => $key]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create key']);
        }
        $stmt->close();
        exit;
    }
    
    if ($_POST['action'] === 'delete_key') {
        $key_id = (int)$_POST['key_id'];
        $stmt = $conn->prepare("DELETE FROM personal_keys WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $key_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete key']);
        }
        $stmt->close();
        exit;
    }
}

// Get user's invite codes
$stmt = $conn->prepare("SELECT code, created_at, regenerates_at, is_active 
    FROM invite_codes 
    WHERE owner_user_id = ? 
    ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$invite_codes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get code usage stats
$stmt = $conn->prepare("SELECT iu.code, iu.first_used_at, iu.account_created, u.username 
    FROM invite_usage iu 
    LEFT JOIN users u ON iu.invitee_user_id = u.id 
    WHERE iu.inviter_user_id = ? 
    ORDER BY iu.first_used_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$usage_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get personal keys
$stmt = $conn->prepare("SELECT id, key_value, created_at, last_used, is_active 
    FROM personal_keys 
    WHERE user_id = ? 
    ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$personal_keys = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if restricted
$stmt = $conn->prepare("SELECT restricted FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$is_restricted = $user_data['restricted'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Invites | Duranu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 20px; }
        .card { margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .code-box { 
            background: #f8f9fa; 
            padding: 10px; 
            border-radius: 5px; 
            font-family: monospace; 
            word-break: break-all;
        }
        .copy-btn { cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-ticket-alt me-2"></i>Manage Invites & Keys</h2>
            <a href="/lounge" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Lounge
            </a>
        </div>

        <?php if ($is_restricted): ?>
        <div class="alert alert-danger">
            <i class="fas fa-ban me-2"></i>
            Your account is restricted. All your invite codes and personal keys are invalid.
        </div>
        <?php endif; ?>

        <!-- Invite Codes -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Your Invite Codes</h5>
            </div>
            <div class="card-body">
                <?php if ($is_restricted): ?>
                    <p class="text-muted">Your codes are currently inactive due to account restrictions.</p>
                <?php else: ?>
                    <p class="text-muted">You have <?php echo count($invite_codes); ?> invite codes. They regenerate monthly (max 3).</p>
                <?php endif; ?>
                
                <div class="row">
                    <?php foreach ($invite_codes as $code): ?>
                    <div class="col-md-6 mb-3">
                        <div class="code-box">
                            <strong>Code:</strong> <?php echo htmlspecialchars($code['code']); ?>
                            <button class="btn btn-sm btn-outline-primary copy-btn float-end" 
                                    onclick="copyCode('<?php echo htmlspecialchars($code['code']); ?>')">
                                <i class="fas fa-copy"></i>
                            </button>
                            <br>
                            <small class="text-muted">
                                Regenerates: <?php echo date('M d, Y', strtotime($code['regenerates_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Usage Stats -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Invite Usage Stats</h5>
            </div>
            <div class="card-body">
                <?php if (empty($usage_stats)): ?>
                    <p class="text-muted">No one has used your codes yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Used</th>
                                    <th>Status</th>
                                    <th>Username</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usage_stats as $stat): ?>
                                <tr>
                                    <td><code><?php echo substr(htmlspecialchars($stat['code']), 0, 8); ?>...</code></td>
                                    <td><?php echo date('M d, Y', strtotime($stat['first_used_at'])); ?></td>
                                    <td>
                                        <?php if ($stat['account_created']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Account Created</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Trial Period</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $stat['username'] ? htmlspecialchars($stat['username']) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Personal Keys -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Personal Keys</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Personal keys auto-login you to your account when used at the firewall.</p>
                
                <button class="btn btn-primary mb-3" onclick="createKey()">
                    <i class="fas fa-plus me-2"></i>Create New Key
                </button>
                
                <div id="message"></div>
                
                <?php if (empty($personal_keys)): ?>
                    <p class="text-muted">No keys created yet.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($personal_keys as $key): ?>
                        <div class="col-md-12 mb-3">
                            <div class="code-box d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Key:</strong> <?php echo htmlspecialchars($key['key_value']); ?>
                                    <button class="btn btn-sm btn-outline-primary copy-btn ms-2" 
                                            onclick="copyCode('<?php echo htmlspecialchars($key['key_value']); ?>')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <br>
                                    <small class="text-muted">
                                        Created: <?php echo date('M d, Y', strtotime($key['created_at'])); ?>
                                        <?php if ($key['last_used']): ?>
                                            | Last used: <?php echo date('M d, Y', strtotime($key['last_used'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-danger" onclick="deleteKey(<?php echo $key['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                alert('Code copied to clipboard!');
            });
        }

        function createKey() {
            $.post('manage_invites.php', {action: 'create_key'})
                .done(function(data) {
                    if (data.status === 'success') {
                        $('#message').html('<div class="alert alert-success">Key created! <code>' + data.key + '</code></div>');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        $('#message').html('<div class="alert alert-danger">' + data.message + '</div>');
                    }
                });
        }

        function deleteKey(keyId) {
            if (confirm('Delete this personal key? This cannot be undone.')) {
                $.post('manage_invites.php', {action: 'delete_key', key_id: keyId})
                    .done(function(data) {
                        if (data.status === 'success') {
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            }
        }
    </script>
</body>
</html>
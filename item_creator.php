<?php
session_start();
include 'db_connect.php';

// Check admin/moderator authorization
if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'user') {
    header("Location: /guest");
    exit;
}

$user_id = $_SESSION['user']['id'];
$is_authorized = false;

$stmt = $conn->prepare("SELECT is_moderator, is_admin FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $is_authorized = ($user_data['is_moderator'] == 1 || $user_data['is_admin'] == 1);
    }
    $stmt->close();
}

if (!$is_authorized) {
    header("Location: /lounge");
    exit;
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_item') {
        $item_id = trim($_POST['item_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $rarity = $_POST['rarity'];
        $cost = (int)$_POST['cost'];
        $currency = $_POST['currency'];
        $icon = $_POST['icon'];
        $is_available = (int)$_POST['is_available'];

        // Validate item_id format
        $valid_patterns = ['_title$', '^avatar_', '^color_', '^bundle_', '^effect_glow_', '^effect_overlay_', '^effect_bubble_', '^effect_'];
        $pattern_valid = false;
        foreach ($valid_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/', $item_id)) {
                $pattern_valid = true;
                break;
            }
        }

        if (!$pattern_valid) {
            $message = 'Invalid item_id format. Must match one of the required patterns.';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO shop_items (item_id, name, description, type, rarity, cost, currency, icon, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssissi", $item_id, $name, $description, $type, $rarity, $cost, $currency, $icon, $is_available);
                
                if ($stmt->execute()) {
                    $message = 'Item created successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error: ' . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } elseif ($_POST['action'] === 'upload_icon' && isset($_FILES['icon_file'])) {
        $upload_dir = 'images/icon/item/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file = $_FILES['icon_file'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= 2 * 1024 * 1024) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'icon_' . time() . '_' . uniqid() . '.' . $extension;
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $message = 'Icon uploaded successfully: ' . $filename;
                $message_type = 'success';
            } else {
                $message = 'Failed to upload icon';
                $message_type = 'danger';
            }
        } else {
            $message = 'Invalid file type or size (max 2MB)';
            $message_type = 'danger';
        }
    }
}

// Get available icons
$icon_dir = 'images/icon/item/';
$available_icons = [];
if (is_dir($icon_dir)) {
    $files = array_diff(scandir($icon_dir), ['.', '..']);
    foreach ($files as $file) {
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
            $available_icons[] = $file;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Item Creator | Admin</title>
    <?php include 'fav.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #1a1a1a;
            color: #e0e0e0;
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin-top: 30px;
        }
        .creator-card {
            background: #2a2a2a;
            border: 1px solid #404040;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .form-label {
            color: #b0b0b0;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            background: #1a1a1a;
            border: 1px solid #404040;
            color: #e0e0e0;
            padding: 10px;
            border-radius: 6px;
        }
        .form-control:focus, .form-select:focus {
            background: #1a1a1a;
            border-color: #0d6efd;
            color: #e0e0e0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            background: #1a1a1a;
            border: 1px solid #404040;
            border-radius: 8px;
        }
        .icon-item {
            position: relative;
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 8px;
            padding: 8px;
            transition: all 0.2s;
            background: #2a2a2a;
        }
        .icon-item:hover {
            border-color: #0d6efd;
            transform: scale(1.05);
        }
        .icon-item.selected {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }
        .icon-item img {
            width: 100%;
            height: 64px;
            object-fit: contain;
        }
        .icon-item .icon-name {
            font-size: 10px;
            text-align: center;
            margin-top: 4px;
            word-break: break-all;
            color: #888;
        }
        .btn-primary {
            background: #0d6efd;
            border: none;
            padding: 10px 30px;
            border-radius: 6px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background: #0b5ed7;
        }
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
        }
        .upload-section {
            background: #1a1a1a;
            border: 2px dashed #404040;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
        }
        .pattern-info {
            background: rgba(13, 110, 253, 0.1);
            border-left: 3px solid #0d6efd;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .icon-grid {
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            }
            .creator-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-plus-circle"></i> Shop Item Creator</h2>
            <a href="room.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="creator-card">
            <form method="POST" id="itemForm">
                <input type="hidden" name="action" value="create_item">

                <div class="mb-3">
                    <label class="form-label">Item ID <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="item_id" required>
                    <div class="pattern-info mt-2">
                        <strong>Valid patterns:</strong> x_title, avatar_x, color_x, bundle_x, effect_glow_x, effect_overlay_x, effect_bubble_x, effect_x
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Display Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="type" id="typeSelect" required>
                            <option value="title">Title</option>
                            <option value="avatar">Avatar</option>
                            <option value="color">Color</option>
                            <option value="bundle">Bundle</option>
                            <option value="effect">Effect</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Rarity <span class="text-danger">*</span></label>
                        <select class="form-select" name="rarity" required>
                            <option value="common">Common</option>
                            <option value="rare">Rare</option>
                            <option value="strange">Strange</option>
                            <option value="legendary">Legendary</option>
                            <option value="event">Event</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cost <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="cost" min="0" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Currency <span class="text-danger">*</span></label>
                        <select class="form-select" name="currency" required>
                            <option value="dura">Dura</option>
                            <option value="event">Event</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3" id="iconSection">
                    <label class="form-label">Icon <span class="text-danger">*</span></label>
                    <input type="hidden" name="icon" id="iconInput" required>
                    
                    <div id="uploadSection" style="display: none;">
                        <div class="upload-section">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #0d6efd;"></i>
                            <p>Upload a new icon (max 2MB)</p>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('iconFileInput').click()">
                                <i class="fas fa-upload"></i> Choose File
                            </button>
                        </div>
                    </div>

                    <div id="iconGridSection" style="display: none;">
                        <?php if (!empty($available_icons)): ?>
                            <div class="icon-grid">
                                <?php foreach ($available_icons as $icon): ?>
                                    <div class="icon-item" data-icon="icon/item/<?= htmlspecialchars($icon) ?>">
                                        <img src="images/icon/item/<?= htmlspecialchars($icon) ?>" alt="<?= htmlspecialchars($icon) ?>">
                                        <div class="icon-name"><?= htmlspecialchars($icon) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No icons available. Upload one above.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Availability <span class="text-danger">*</span></label>
                    <select class="form-select" name="is_available" required>
                        <option value="1">Available (1)</option>
                        <option value="0">Unavailable (0)</option>
                    </select>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-check"></i> Create Item
                    </button>
                </div>
            </form>
        </div>

        <!-- Hidden upload form -->
        <form method="POST" enctype="multipart/form-data" id="uploadForm" style="display: none;">
            <input type="hidden" name="action" value="upload_icon">
            <input type="file" name="icon_file" id="iconFileInput" accept="image/*">
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show icon selection based on type
        document.getElementById('typeSelect').addEventListener('change', function() {
            const isAvatar = this.value === 'avatar';
            document.getElementById('uploadSection').style.display = isAvatar ? 'block' : 'none';
            document.getElementById('iconGridSection').style.display = isAvatar ? 'block' : 'none';
            
            if (!isAvatar) {
                document.getElementById('iconInput').removeAttribute('required');
                document.getElementById('iconInput').value = '';
            } else {
                document.getElementById('iconInput').setAttribute('required', 'required');
            }
        });

        // Icon selection
        document.querySelectorAll('.icon-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.icon-item').forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('iconInput').value = this.dataset.icon;
            });
        });

        // File upload handling
        document.getElementById('iconFileInput').addEventListener('change', function() {
            if (this.files.length > 0) {
                if (confirm('Upload this icon? The page will refresh.')) {
                    document.getElementById('uploadForm').submit();
                }
            }
        });

        // Trigger initial change
        document.getElementById('typeSelect').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
<?php $conn->close(); ?>
<?php
// === database_helpers.php - Utility functions for moderation ===

// Function to sync moderator status to global_users
function syncModeratorStatusToGlobal($conn) {
    try {
        // Ensure is_moderator column exists in global_users
        $check_column = $conn->query("SHOW COLUMNS FROM global_users LIKE 'is_moderator'");
        if ($check_column->num_rows === 0) {
            $conn->query("ALTER TABLE global_users ADD COLUMN is_moderator TINYINT(1) DEFAULT 0 NOT NULL");
        }
        
        // Sync moderator status from users table
        $sync_stmt = $conn->prepare("
            UPDATE global_users gu
            JOIN users u ON gu.user_id_string = u.user_id
            SET gu.is_moderator = u.is_moderator
        ");
        
        if ($sync_stmt) {
            $sync_stmt->execute();
            $affected_rows = $sync_stmt->affected_rows;
            $sync_stmt->close();
            return $affected_rows;
        }
        
        return 0;
    } catch (Exception $e) {
        error_log("Sync moderator status error: " . $e->getMessage());
        return 0;
    }
}

// Function to check if IP is banned
function isIPBanned($conn, $ip_address) {
    try {
        $stmt = $conn->prepare("
            SELECT id, ban_until, reason 
            FROM site_bans 
            WHERE ip_address = ? 
            AND (ban_until IS NULL OR ban_until > NOW())
            LIMIT 1
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $ban = $result->fetch_assoc();
            $stmt->close();
            return [
                'banned' => true,
                'ban_id' => $ban['id'],
                'expires' => $ban['ban_until'],
                'reason' => $ban['reason'],
                'permanent' => ($ban['ban_until'] === null)
            ];
        }
        
        $stmt->close();
        return ['banned' => false];
        
    } catch (Exception $e) {
        error_log("IP ban check error: " . $e->getMessage());
        return ['banned' => false];
    }
}

// Function to log moderator action
function logModeratorAction($conn, $moderator_id, $moderator_username, $action_type, $target_user_id = null, $target_username = null, $target_ip = null, $details = '') {
    try {
        $stmt = $conn->prepare("
            INSERT INTO moderator_logs 
            (moderator_id, moderator_username, action_type, target_user_id, target_username, target_ip, details) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("issssss", $moderator_id, $moderator_username, $action_type, $target_user_id, $target_username, $target_ip, $details);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Log moderator action error: " . $e->getMessage());
        return false;
    }
}

// Function to get recent announcements
function getRecentAnnouncements($conn, $limit = 5) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM announcements 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $announcements = [];
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        $stmt->close();
        
        return $announcements;
        
    } catch (Exception $e) {
        error_log("Get announcements error: " . $e->getMessage());
        return [];
    }
}

// Function to clean up old moderator logs (call periodically)
function cleanupOldModeratorLogs($conn, $days = 90) {
    try {
        $stmt = $conn->prepare("
            DELETE FROM moderator_logs 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        if (!$stmt) {
            return 0;
        }
        
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows;
        
    } catch (Exception $e) {
        error_log("Cleanup moderator logs error: " . $e->getMessage());
        return 0;
    }
}

// Function to get site ban statistics
function getSiteBanStats($conn) {
    try {
        $stats = [
            'total_active_bans' => 0,
            'permanent_bans' => 0,
            'temporary_bans' => 0,
            'bans_this_week' => 0
        ];
        
        // Total active bans
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM site_bans 
            WHERE ban_until IS NULL OR ban_until > NOW()
        ");
        
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['total_active_bans'] = (int)$row['count'];
            }
            $stmt->close();
        }
        
        // Permanent bans
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM site_bans WHERE ban_until IS NULL");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['permanent_bans'] = (int)$row['count'];
            }
            $stmt->close();
        }
        
        // Temporary bans
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM site_bans 
            WHERE ban_until IS NOT NULL AND ban_until > NOW()
        ");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['temporary_bans'] = (int)$row['count'];
            }
            $stmt->close();
        }
        
        // Bans this week
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM site_bans 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['bans_this_week'] = (int)$row['count'];
            }
            $stmt->close();
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Get ban stats error: " . $e->getMessage());
        return [
            'total_active_bans' => 0,
            'permanent_bans' => 0,
            'temporary_bans' => 0,
            'bans_this_week' => 0
        ];
    }
}
?>
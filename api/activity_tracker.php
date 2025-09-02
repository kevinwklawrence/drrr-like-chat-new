<?php
// api/activity_tracker.php - Universal activity tracking system
// Include this in all scripts that need to update user activity

require_once __DIR__ . '/../config/activity_config.php';

class ActivityTracker {
    private $conn;
    private $user_id_string;
    private $room_id;
    
    public function __construct($conn, $user_id_string, $room_id = null) {
        $this->conn = $conn;
        $this->user_id_string = $user_id_string;
        $this->room_id = $room_id;
        
        // Ensure required columns exist
        ensureActivityColumns($conn);
    }
    
    /**
     * Update user activity in all relevant tables
     * This is the main function to call whenever a user performs an action
     */
    public function updateActivity($activity_type = 'general', $additional_data = []) {
        if (!isValidActivityType($activity_type)) {
            logActivity("Invalid activity type: $activity_type");
            return false;
        }
        
        logActivity("Updating activity for user {$this->user_id_string}, type: $activity_type");
        
        try {
            $this->conn->begin_transaction();
            
            // Update global_users activity
            $this->updateGlobalUserActivity($activity_type);
            
            // Update room-specific activity if in a room
            if ($this->room_id) {
                $this->updateRoomUserActivity($activity_type);
            }
            
            $this->conn->commit();
            
            logActivity("Successfully updated activity for user {$this->user_id_string}");
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            logActivity("Failed to update activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update activity in global_users table
     */
    private function updateGlobalUserActivity($activity_type) {
        $stmt = $this->conn->prepare("
            UPDATE global_users 
            SET last_activity = NOW() 
            WHERE user_id_string = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare global users update: " . $this->conn->error);
        }
        
        $stmt->bind_param("s", $this->user_id_string);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Failed to update global users activity: " . $this->conn->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        logActivity("Updated global_users activity, affected rows: $affected_rows");
        
        return $affected_rows > 0;
    }
    
    /**
     * Update activity in chatroom_users table and handle AFK status
     */
    private function updateRoomUserActivity($activity_type) {
        // First, get current user status
        $status_stmt = $this->conn->prepare("
            SELECT is_afk, manual_afk, username, guest_name 
            FROM chatroom_users 
            WHERE room_id = ? AND user_id_string = ?
        ");
        
        if (!$status_stmt) {
            throw new Exception("Failed to prepare status check: " . $this->conn->error);
        }
        
        $status_stmt->bind_param("is", $this->room_id, $this->user_id_string);
        $status_stmt->execute();
        $result = $status_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $status_stmt->close();
            throw new Exception("User not found in room");
        }
        
        $user_data = $result->fetch_assoc();
        $was_afk = (bool)$user_data['is_afk'];
        $was_manual_afk = (bool)$user_data['manual_afk'];
        $display_name = $user_data['username'] ?: $user_data['guest_name'] ?: 'Unknown User';
        $status_stmt->close();
        
        // Update last_activity and handle AFK status
        if ($was_afk && !$was_manual_afk) {
            // User was auto-AFK, clear it since they're now active
            $update_stmt = $this->conn->prepare("
                UPDATE chatroom_users 
                SET last_activity = NOW(), is_afk = 0, afk_since = NULL, manual_afk = 0 
                WHERE room_id = ? AND user_id_string = ?
            ");
            
            if (!$update_stmt) {
                throw new Exception("Failed to prepare AFK clear update: " . $this->conn->error);
            }
            
            $update_stmt->bind_param("is", $this->room_id, $this->user_id_string);
            
            if (!$update_stmt->execute()) {
                $update_stmt->close();
                throw new Exception("Failed to clear AFK status: " . $this->conn->error);
            }
            
            $update_stmt->close();
            
            // Add system message for returning from AFK
            $this->addSystemMessage("$display_name is back from AFK.", 'active.png');
            
            logActivity("Cleared auto-AFK status for user: $display_name");
            
        } else {
            // Just update activity (don't clear manual AFK)
            $update_stmt = $this->conn->prepare("
                UPDATE chatroom_users 
                SET last_activity = NOW() 
                WHERE room_id = ? AND user_id_string = ?
            ");
            
            if (!$update_stmt) {
                throw new Exception("Failed to prepare activity update: " . $this->conn->error);
            }
            
            $update_stmt->bind_param("is", $this->room_id, $this->user_id_string);
            
            if (!$update_stmt->execute()) {
                $update_stmt->close();
                throw new Exception("Failed to update room activity: " . $this->conn->error);
            }
            
            $update_stmt->close();
            logActivity("Updated room activity for user: $display_name");
        }
    }
    
    /**
     * Add a system message to the room
     */
    private function addSystemMessage($message, $avatar = 'system.png') {
        if (!$this->room_id) return;
        
        $stmt = $this->conn->prepare("
            INSERT INTO messages (room_id, user_id_string, message, is_system, timestamp, avatar, type) 
            VALUES (?, '', ?, 1, NOW(), ?, 'system')
        ");
        
        if ($stmt) {
            $stmt->bind_param("iss", $this->room_id, $message, $avatar);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Get user's current activity status
     */
    public function getUserActivityStatus() {
        $status = [
            'global_active' => false,
            'room_active' => false,
            'is_afk' => false,
            'manual_afk' => false,
            'last_activity' => null,
            'afk_since' => null
        ];
        
        // Check global activity
        $global_stmt = $this->conn->prepare("
            SELECT last_activity, 
                   TIMESTAMPDIFF(SECOND, last_activity, NOW()) as seconds_inactive
            FROM global_users 
            WHERE user_id_string = ?
        ");
        
        if ($global_stmt) {
            $global_stmt->bind_param("s", $this->user_id_string);
            $global_stmt->execute();
            $result = $global_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $status['last_activity'] = $row['last_activity'];
                $status['global_active'] = $row['seconds_inactive'] < SESSION_TIMEOUT;
            }
            
            $global_stmt->close();
        }
        
        // Check room activity if in a room
        if ($this->room_id) {
            $room_stmt = $this->conn->prepare("
                SELECT last_activity, is_afk, manual_afk, afk_since,
                       TIMESTAMPDIFF(SECOND, last_activity, NOW()) as seconds_inactive
                FROM chatroom_users 
                WHERE room_id = ? AND user_id_string = ?
            ");
            
            if ($room_stmt) {
                $room_stmt->bind_param("is", $this->room_id, $this->user_id_string);
                $room_stmt->execute();
                $result = $room_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $status['room_active'] = $row['seconds_inactive'] < DISCONNECT_TIMEOUT;
                    $status['is_afk'] = (bool)$row['is_afk'];
                    $status['manual_afk'] = (bool)$row['manual_afk'];
                    $status['afk_since'] = $row['afk_since'];
                }
                
                $room_stmt->close();
            }
        }
        
        return $status;
    }
    
    /**
     * Static method to quickly update activity from anywhere
     */
    public static function quickUpdate($conn, $user_id_string, $room_id = null, $activity_type = 'general') {
        $tracker = new self($conn, $user_id_string, $room_id);
        return $tracker->updateActivity($activity_type);
    }
}
?>
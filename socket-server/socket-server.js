// socket-server.js - SIMPLIFIED VERSION (No PHP session dependency)
const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2/promise');

// Configuration - UPDATE THESE VALUES
const config = {
    port: 3001,
    database: {
        host: 'localhost',
        user: 'root',              // UPDATE: Your database username
        password: '',              // UPDATE: Your database password (empty for XAMPP default)
        database: 'drrr_clone',    // UPDATE: Your database name
        charset: 'utf8mb4'
    }
};

const app = express();

// FIXED: Add CORS middleware for Express routes
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
    if (req.method === 'OPTIONS') {
        res.sendStatus(200);
    } else {
        next();
    }
});

const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*", // Configure for your domain in production
        methods: ["GET", "POST"],
        credentials: true
    }
});

// Database connection pool
const dbPool = mysql.createPool({
    ...config.database,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

// Active connections tracking
const activeConnections = new Map(); // userId -> {socket, roomId, user}
const roomUsers = new Map(); // roomId -> Set of userIds

// Utility Functions
function debugLog(message, data = null) {
    const timestamp = new Date().toISOString();
    if (data) {
        console.log(`[${timestamp}] ${message}`, data);
    } else {
        console.log(`[${timestamp}] ${message}`);
    }
}

// SIMPLIFIED: Verify user exists in database and is in a room
async function verifyUserInDatabase(userId, roomId) {
    try {
        if (!userId || !roomId) {
            debugLog('Missing userId or roomId for verification');
            return null;
        }
        
        const connection = await dbPool.getConnection();
        try {
            // Check if user exists in the specified room
            const [rows] = await connection.execute(`
                SELECT cu.*, u.username, u.is_admin, u.is_moderator
                FROM chatroom_users cu
                LEFT JOIN users u ON cu.user_id = u.id
                WHERE cu.room_id = ? AND cu.user_id_string = ?
                LIMIT 1
            `, [roomId, userId]);
            
            if (rows.length > 0) {
                const user = rows[0];
                debugLog(`User verified in database: ${userId} in room ${roomId}`);
                return {
                    user_id_string: user.user_id_string,
                    username: user.username,
                    guest_name: user.guest_name,
                    display_name: user.username || user.guest_name || 'Unknown',
                    avatar: user.avatar || user.guest_avatar || 'default_avatar.jpg',
                    color: user.color || 'blue',
                    avatar_hue: parseInt(user.avatar_hue) || 0,
                    avatar_saturation: parseInt(user.avatar_saturation) || 100,
                    bubble_hue: parseInt(user.bubble_hue) || 0,
                    bubble_saturation: parseInt(user.bubble_saturation) || 100,
                    is_admin: Boolean(user.is_admin),
                    is_moderator: Boolean(user.is_moderator),
                    is_host: Boolean(user.is_host),
                    is_afk: Boolean(user.is_afk)
                };
            } else {
                debugLog(`User not found in database: ${userId} in room ${roomId}`);
                return null;
            }
        } finally {
            connection.release();
        }
    } catch (error) {
        debugLog('Database verification error:', error.message);
        return null;
    }
}

async function updateUserActivity(userId, roomId = null) {
    try {
        const connection = await dbPool.getConnection();
        
        // Update global_users if exists
        try {
            await connection.execute(`
                UPDATE global_users 
                SET last_activity = NOW() 
                WHERE user_id_string = ?
            `, [userId]);
        } catch (e) {
            // Table might not exist, that's ok
        }
        
        // Update chatroom_users if in room
        if (roomId) {
            await connection.execute(`
                UPDATE chatroom_users 
                SET last_activity = NOW(), is_afk = 0, afk_since = NULL 
                WHERE room_id = ? AND user_id_string = ?
            `, [roomId, userId]);
        }
        
        connection.release();
        return true;
    } catch (error) {
        debugLog('Error updating user activity:', error.message);
        return false;
    }
}

async function getRoomUsers(roomId) {
    try {
        const connection = await dbPool.getConnection();
        const [rows] = await connection.execute(`
            SELECT cu.*, u.username, u.is_admin, u.is_moderator
            FROM chatroom_users cu
            LEFT JOIN users u ON cu.user_id = u.id
            WHERE cu.room_id = ?
            ORDER BY cu.joined_at ASC
        `, [roomId]);
        
        connection.release();
        return rows.map(row => ({
            user_id_string: row.user_id_string,
            username: row.username,
            guest_name: row.guest_name,
            display_name: row.username || row.guest_name || 'Unknown',
            avatar: row.avatar || row.guest_avatar || 'default_avatar.jpg',
            color: row.color || 'blue',
            avatar_hue: parseInt(row.avatar_hue) || 0,
            avatar_saturation: parseInt(row.avatar_saturation) || 100,
            is_admin: Boolean(row.is_admin),
            is_moderator: Boolean(row.is_moderator),
            is_host: Boolean(row.is_host),
            is_afk: Boolean(row.is_afk),
            last_activity: row.last_activity
        }));
    } catch (error) {
        debugLog('Error getting room users:', error.message);
        return [];
    }
}

async function saveMessage(roomId, userId, message, replyTo = null) {
    try {
        const connection = await dbPool.getConnection();
        
        // Get user data for message
        const [userRows] = await connection.execute(`
            SELECT cu.*, u.username, u.is_admin, u.is_moderator
            FROM chatroom_users cu
            LEFT JOIN users u ON cu.user_id = u.id
            WHERE cu.room_id = ? AND cu.user_id_string = ?
        `, [roomId, userId]);
        
        if (userRows.length === 0) {
            connection.release();
            debugLog(`No user found for message: ${userId} in room ${roomId}`);
            return null;
        }
        
        const user = userRows[0];
        
        // Insert message with stored customization
        const [result] = await connection.execute(`
            INSERT INTO messages (
                room_id, user_id, user_id_string, guest_name, message, avatar,
                color, avatar_hue, avatar_saturation, bubble_hue, bubble_saturation,
                reply_to_message_id, timestamp, type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'chat')
        `, [
            roomId,
            user.user_id || null,
            userId,
            user.guest_name,
            message,
            user.avatar || user.guest_avatar,
            user.color || 'blue',
            parseInt(user.avatar_hue) || 0,
            parseInt(user.avatar_saturation) || 100,
            parseInt(user.bubble_hue) || 0,
            parseInt(user.bubble_saturation) || 100,
            replyTo
        ]);
        
        // Get the complete message for broadcasting
        const [messageRows] = await connection.execute(`
            SELECT m.*, u.username, u.is_admin, u.is_moderator
            FROM messages m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.id = ?
        `, [result.insertId]);
        
        connection.release();
        
        if (messageRows.length > 0) {
            const messageData = messageRows[0];
            return {
                id: messageData.id,
                message: messageData.message,
                timestamp: messageData.timestamp,
                type: messageData.type,
                user_id_string: messageData.user_id_string,
                display_name: messageData.username || messageData.guest_name || 'Unknown',
                avatar: messageData.avatar,
                color: messageData.color,
                avatar_hue: parseInt(messageData.avatar_hue) || 0,
                avatar_saturation: parseInt(messageData.avatar_saturation) || 100,
                bubble_hue: parseInt(messageData.bubble_hue) || 0,
                bubble_saturation: parseInt(messageData.bubble_saturation) || 100,
                is_admin: Boolean(messageData.is_admin),
                is_moderator: Boolean(messageData.is_moderator),
                is_host: Boolean(user.is_host),
                reply_to_message_id: messageData.reply_to_message_id
            };
        }
        
        return null;
    } catch (error) {
        debugLog('Error saving message:', error.message);
        return null;
    }
}

// SIMPLIFIED: Socket.io Connection Handler
io.on('connection', async (socket) => {
    debugLog('New socket connection:', socket.id);
    
    let authenticatedUser = null;
    let userId = null;
    
    // Wait for authentication from client
    socket.on('authenticate', async (authData) => {
        debugLog('Authentication attempt:', authData);
        
        const { user_id_string, room_id } = authData;
        
        if (!user_id_string || !room_id) {
            debugLog('Missing authentication data');
            socket.emit('auth_error', { message: 'Missing user ID or room ID' });
            return;
        }
        
        // Verify user exists in database and is in the room
        const user = await verifyUserInDatabase(user_id_string, room_id);
        
        if (!user) {
            debugLog('User verification failed');
            socket.emit('auth_error', { message: 'User not found in room' });
            return;
        }
        
        // Authentication successful
        authenticatedUser = user;
        userId = user.user_id_string;
        
        debugLog(`User authenticated: ${userId} (${user.display_name})`);
        
        // Store connection
        activeConnections.set(userId, {
            socket: socket,
            roomId: room_id,
            lastActivity: new Date(),
            user: user
        });
        
        socket.emit('auth_success', { user: user });
        
        // Auto-join the room
        socket.join(`room_${room_id}`);
        
        // Add to room tracking
        if (!roomUsers.has(room_id)) {
            roomUsers.set(room_id, new Set());
        }
        roomUsers.get(room_id).add(userId);
        
        // Update activity
        await updateUserActivity(userId, room_id);
        
        // Get and send room data
        const users = await getRoomUsers(room_id);
        
        socket.emit('room_joined', {
            room_id: room_id,
            users: users
        });
        
        // Broadcast to room that user joined
        socket.to(`room_${room_id}`).emit('user_joined', {
            user: user,
            users: users
        });
        
        debugLog(`User ${userId} authenticated and joined room ${room_id}`);
    });
    
    // All other socket events require authentication
    socket.on('send_message', async (data) => {
        if (!authenticatedUser) {
            socket.emit('error', { message: 'Not authenticated' });
            return;
        }
        
        const connection = activeConnections.get(userId);
        if (!connection || !connection.roomId) {
            socket.emit('error', { message: 'Not in a room' });
            return;
        }
        
        const { message, reply_to } = data;
        if (!message || message.trim().length === 0) {
            socket.emit('error', { message: 'Message cannot be empty' });
            return;
        }
        
        // Update activity
        await updateUserActivity(userId, connection.roomId);
        
        // Save message to database
        const savedMessage = await saveMessage(connection.roomId, userId, message.trim(), reply_to);
        
        if (savedMessage) {
            // Broadcast to all users in the room (including sender)
            io.to(`room_${connection.roomId}`).emit('new_message', savedMessage);
            debugLog(`Message sent in room ${connection.roomId} by ${userId}`);
        } else {
            socket.emit('error', { message: 'Failed to send message' });
        }
    });
    
    // Activity Update
    socket.on('activity', async (data) => {
        if (!authenticatedUser) return;
        
        const connection = activeConnections.get(userId);
        if (connection) {
            connection.lastActivity = new Date();
            await updateUserActivity(userId, connection.roomId);
            
            // Broadcast typing indicators
            if (data.type === 'typing' && connection.roomId) {
                socket.to(`room_${connection.roomId}`).emit('user_typing', {
                    user_id_string: userId,
                    user_name: authenticatedUser.display_name,
                    typing: data.typing
                });
            }
        }
    });
    
    // Leave Room
    socket.on('leave_room', async () => {
        if (!authenticatedUser) return;
        
        const connection = activeConnections.get(userId);
        if (connection && connection.roomId) {
            const roomId = connection.roomId;
            
            socket.leave(`room_${roomId}`);
            connection.roomId = null;
            
            // Remove from tracking
            const roomUsersSet = roomUsers.get(roomId);
            if (roomUsersSet) {
                roomUsersSet.delete(userId);
                
                // Broadcast user left
                socket.to(`room_${roomId}`).emit('user_left', {
                    user_id_string: userId,
                    users: await getRoomUsers(roomId)
                });
            }
            
            debugLog(`User ${userId} left room ${roomId}`);
        }
    });
    
    // Disconnect
    socket.on('disconnect', async () => {
        if (!userId) return;
        
        debugLog(`User ${userId} disconnected`);
        
        const connection = activeConnections.get(userId);
        if (connection && connection.roomId) {
            const roomId = connection.roomId;
            
            // Remove from room tracking
            const roomUsersSet = roomUsers.get(roomId);
            if (roomUsersSet) {
                roomUsersSet.delete(userId);
                
                // Broadcast user left
                socket.to(`room_${roomId}`).emit('user_left', {
                    user_id_string: userId,
                    users: await getRoomUsers(roomId)
                });
            }
        }
        
        activeConnections.delete(userId);
    });
});

// Cleanup inactive connections
setInterval(() => {
    const now = new Date();
    const timeout = 2 * 60 * 1000; // 2 minutes
    
    for (const [userId, connection] of activeConnections) {
        if (now - connection.lastActivity > timeout) {
            debugLog(`Cleaning up inactive connection for user ${userId}`);
            connection.socket.disconnect();
            activeConnections.delete(userId);
        }
    }
}, 60000); // Check every minute

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        connections: activeConnections.size,
        uptime: process.uptime(),
        rooms: Array.from(roomUsers.keys()).map(roomId => ({
            room_id: roomId,
            user_count: roomUsers.get(roomId).size
        }))
    });
});

// Start server
server.listen(config.port, () => {
    debugLog(`Socket.io server running on port ${config.port}`);
    debugLog('Database configuration:', {
        host: config.database.host,
        database: config.database.database
    });
});

// Graceful shutdown
process.on('SIGTERM', () => {
    debugLog('Shutting down socket server...');
    server.close(() => {
        process.exit(0);
    });
});
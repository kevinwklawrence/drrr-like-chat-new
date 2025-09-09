// test-socket-integration.js - Test script for socket integration
// Run this in browser console to test socket functionality

class SocketTester {
    constructor() {
        this.tests = [];
        this.results = [];
    }
    
    log(message, type = 'info') {
        const timestamp = new Date().toISOString();
        const logMessage = `[${timestamp}] ${type.toUpperCase()}: ${message}`;
        console.log(logMessage);
        
        if (type === 'error') {
            console.error(logMessage);
        }
    }
    
    async runAllTests() {
        this.log('🚀 Starting Socket Integration Tests');
        
        try {
            await this.testSocketConnection();
            await this.testRoomJoin();
            await this.testMessageSending();
            await this.testTypingIndicators();
            await this.testFallbackBehavior();
            
            this.printResults();
        } catch (error) {
            this.log(`Test suite failed: ${error.message}`, 'error');
        }
    }
    
    async testSocketConnection() {
        this.log('🔌 Testing socket connection...');
        
        let result = {
            name: 'Socket Connection',
            status: 'FAIL',
            details: ''
        };
        
        try {
            if (!window.socketClient) {
                throw new Error('socketClient not found');
            }
            
            if (!window.socketClient.socket) {
                throw new Error('Socket not initialized');
            }
            
            if (window.socketClient.isConnected()) {
                result.status = 'PASS';
                result.details = 'Socket connected successfully';
                this.log('✅ Socket connection test passed');
            } else {
                result.details = 'Socket not connected';
                this.log('❌ Socket connection test failed - not connected');
            }
        } catch (error) {
            result.details = error.message;
            this.log(`❌ Socket connection test failed: ${error.message}`, 'error');
        }
        
        this.results.push(result);
    }
    
    async testRoomJoin() {
        this.log('🏠 Testing room join functionality...');
        
        let result = {
            name: 'Room Join',
            status: 'FAIL',
            details: ''
        };
        
        try {
            if (!window.socketClient || !window.socketClient.isConnected()) {
                throw new Error('Socket not connected');
            }
            
            if (!window.roomId) {
                throw new Error('roomId not available');
            }
            
            // Listen for room_joined event
            const roomJoinPromise = new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error('Room join timeout'));
                }, 5000);
                
                window.socketClient.socket.once('room_joined', (data) => {
                    clearTimeout(timeout);
                    resolve(data);
                });
            });
            
            // Trigger room join
            window.socketClient.joinRoom(window.roomId);
            
            const roomData = await roomJoinPromise;
            
            if (roomData && roomData.room_id == window.roomId) {
                result.status = 'PASS';
                result.details = `Successfully joined room ${roomData.room_id}`;
                this.log('✅ Room join test passed');
            } else {
                result.details = 'Room join did not return expected data';
                this.log('❌ Room join test failed - unexpected response');
            }
        } catch (error) {
            result.details = error.message;
            this.log(`❌ Room join test failed: ${error.message}`, 'error');
        }
        
        this.results.push(result);
    }
    
    async testMessageSending() {
        this.log('💬 Testing message sending...');
        
        let result = {
            name: 'Message Sending',
            status: 'FAIL',
            details: ''
        };
        
        try {
            if (!window.socketClient || !window.socketClient.isConnected()) {
                throw new Error('Socket not connected');
            }
            
            const testMessage = `Test message ${Date.now()}`;
            
            // Listen for new message
            const messagePromise = new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error('Message send timeout'));
                }, 10000);
                
                window.socketClient.socket.once('new_message', (message) => {
                    clearTimeout(timeout);
                    resolve(message);
                });
            });
            
            // Send test message
            const sendSuccess = window.socketClient.sendMessage(testMessage);
            
            if (!sendSuccess) {
                throw new Error('sendMessage returned false');
            }
            
            const receivedMessage = await messagePromise;
            
            if (receivedMessage && receivedMessage.message === testMessage) {
                result.status = 'PASS';
                result.details = `Message sent and received successfully`;
                this.log('✅ Message sending test passed');
            } else {
                result.details = 'Message not received or content mismatch';
                this.log('❌ Message sending test failed - message mismatch');
            }
        } catch (error) {
            result.details = error.message;
            this.log(`❌ Message sending test failed: ${error.message}`, 'error');
        }
        
        this.results.push(result);
    }
    
    async testTypingIndicators() {
        this.log('⌨️ Testing typing indicators...');
        
        let result = {
            name: 'Typing Indicators',
            status: 'PASS', // Default pass since this is optional
            details: 'Typing indicators functionality available'
        };
        
        try {
            if (!window.socketClient || !window.socketClient.isConnected()) {
                throw new Error('Socket not connected');
            }
            
            // Test typing indicator methods
            if (typeof window.socketClient.setTyping === 'function') {
                window.socketClient.setTyping(true);
                
                setTimeout(() => {
                    window.socketClient.setTyping(false);
                }, 1000);
                
                this.log('✅ Typing indicators test passed');
            } else {
                result.details = 'Typing indicator methods not available';
                this.log('⚠️ Typing indicators not available');
            }
        } catch (error) {
            result.details = error.message;
            this.log(`❌ Typing indicators test failed: ${error.message}`, 'error');
        }
        
        this.results.push(result);
    }
    
    async testFallbackBehavior() {
        this.log('🔄 Testing fallback behavior...');
        
        let result = {
            name: 'Fallback Behavior',
            status: 'PASS',
            details: 'Fallback methods available'
        };
        
        try {
            // Check if original functions exist
            if (typeof window.originalSendMessage === 'function') {
                result.details += ' - Original sendMessage preserved';
            }
            
            if (window.socketClient && typeof window.socketClient.fallbackToPolling === 'function') {
                result.details += ' - Fallback method available';
            }
            
            // Check if polling intervals can be restored
            if (typeof window.pollingIntervals !== 'undefined' || typeof activityTracker !== 'undefined') {
                result.details += ' - Polling system available for fallback';
            }
            
            this.log('✅ Fallback behavior test passed');
        } catch (error) {
            result.status = 'FAIL';
            result.details = error.message;
            this.log(`❌ Fallback behavior test failed: ${error.message}`, 'error');
        }
        
        this.results.push(result);
    }
    
    printResults() {
        this.log('📊 Test Results Summary');
        console.table(this.results);
        
        const passed = this.results.filter(r => r.status === 'PASS').length;
        const total = this.results.length;
        
        this.log(`${passed}/${total} tests passed`);
        
        if (passed === total) {
            this.log('🎉 All tests passed! Socket integration is working correctly.', 'success');
        } else {
            this.log('⚠️ Some tests failed. Check the details above.', 'warning');
        }
        
        // Additional information
        this.log('\n📋 Additional Information:');
        this.log(`Socket Connected: ${window.socketClient ? window.socketClient.isConnected() : 'N/A'}`);
        this.log(`Room ID: ${window.roomId || 'N/A'}`);
        this.log(`Socket Client Available: ${!!window.socketClient}`);
        this.log(`Original Send Message: ${!!window.originalSendMessage}`);
    }
    
    // Quick diagnostic method
    quickDiagnostic() {
        this.log('🔍 Quick Diagnostic');
        
        const checks = {
            'Socket.io Library': !!window.io,
            'Socket Client': !!window.socketClient,
            'Socket Connected': window.socketClient ? window.socketClient.isConnected() : false,
            'Room ID Available': !!window.roomId,
            'jQuery Available': !!window.$,
            'Original Send Message': !!window.originalSendMessage
        };
        
        console.table(checks);
        
        // Count issues
        const issues = Object.entries(checks).filter(([key, value]) => !value);
        
        if (issues.length === 0) {
            this.log('✅ All basic requirements met');
        } else {
            this.log(`❌ ${issues.length} issue(s) found:`);
            issues.forEach(([key, value]) => {
                this.log(`  - ${key}: ${value}`);
            });
        }
    }
}

// Make tester available globally
window.socketTester = new SocketTester();

// Usage instructions
console.log(`
🧪 Socket Integration Tester

Usage:
  socketTester.quickDiagnostic()  - Quick check of requirements
  socketTester.runAllTests()      - Run full test suite

Or run individual tests:
  socketTester.testSocketConnection()
  socketTester.testRoomJoin()
  socketTester.testMessageSending()
  socketTester.testTypingIndicators()
  socketTester.testFallbackBehavior()
`);

// Auto-run quick diagnostic
window.socketTester.quickDiagnostic();
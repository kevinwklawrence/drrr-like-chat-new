<!DOCTYPE html>
<html>
<head>
    <title>Test Friend System</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        button { padding: 8px 16px; margin: 5px; }
        input { padding: 5px; margin: 5px; }
        .result { margin: 10px 0; padding: 10px; background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Friend System Test Page</h1>
    
    <div class="test-section">
        <h3>1. Send Friend Request</h3>
        <input type="text" id="friendUsername" placeholder="Enter username to add">
        <button onclick="sendFriendRequest()">Send Friend Request</button>
        <div id="sendResult" class="result"></div>
    </div>
    
    <div class="test-section">
        <h3>2. Get Friends List</h3>
        <button onclick="getFriends()">Load Friends</button>
        <div id="friendsResult" class="result"></div>
    </div>
    
    <div class="test-section">
        <h3>3. Accept Friend Request</h3>
        <input type="number" id="requestId" placeholder="Request ID to accept">
        <button onclick="acceptFriend()">Accept Request</button>
        <div id="acceptResult" class="result"></div>
    </div>
    
    <div class="test-section">
        <h3>4. System Status</h3>
        <button onclick="checkStatus()">Check System Status</button>
        <div id="statusResult" class="result"></div>
    </div>

    <script>
        function sendFriendRequest() {
            const username = $('#friendUsername').val().trim();
            if (!username) {
                $('#sendResult').html('<span class="error">Please enter a username</span>');
                return;
            }
            
            $('#sendResult').html('<span class="info">Sending request...</span>');
            
            $.ajax({
                url: 'api/friends.php',
                method: 'POST',
                data: {
                    action: 'add',
                    friend_username: username
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#sendResult').html('<span class="success">✓ ' + response.message + '</span>');
                    } else {
                        $('#sendResult').html('<span class="error">❌ ' + response.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#sendResult').html('<span class="error">❌ Network error: ' + error + '</span>');
                    console.error('Full error:', xhr.responseText);
                }
            });
        }
        
        function getFriends() {
            $('#friendsResult').html('<span class="info">Loading friends...</span>');
            
            $.ajax({
                url: 'api/friends.php',
                method: 'GET',
                data: { action: 'get' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        let html = '<span class="success">✓ Friends loaded</span><br>';
                        if (response.friends.length === 0) {
                            html += 'No friends found.';
                        } else {
                            html += '<strong>Friends/Requests:</strong><br>';
                            response.friends.forEach(friend => {
                                html += `ID: ${friend.id}, User: ${friend.username}, Status: ${friend.status}, Type: ${friend.request_type}<br>`;
                            });
                        }
                        $('#friendsResult').html(html);
                    } else {
                        $('#friendsResult').html('<span class="error">❌ ' + response.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#friendsResult').html('<span class="error">❌ Network error: ' + error + '</span>');
                    console.error('Full error:', xhr.responseText);
                }
            });
        }
        
        function acceptFriend() {
            const requestId = $('#requestId').val().trim();
            if (!requestId) {
                $('#acceptResult').html('<span class="error">Please enter a request ID</span>');
                return;
            }
            
            $('#acceptResult').html('<span class="info">Accepting request...</span>');
            
            $.ajax({
                url: 'api/friends.php',
                method: 'POST',
                data: {
                    action: 'accept',
                    friend_id: requestId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#acceptResult').html('<span class="success">✓ ' + response.message + '</span>');
                    } else {
                        $('#acceptResult').html('<span class="error">❌ ' + response.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#acceptResult').html('<span class="error">❌ Network error: ' + error + '</span>');
                    console.error('Full error:', xhr.responseText);
                }
            });
        }
        
        function checkStatus() {
            $('#statusResult').html('<span class="info">Checking status...</span>');
            
            // Test multiple endpoints
            let tests = [];
            
            // Test 1: Basic friends API
            tests.push(
                $.ajax({
                    url: 'api/friends.php',
                    method: 'GET',
                    data: { action: 'get' },
                    dataType: 'json'
                }).then(
                    response => ({ test: 'Friends API', status: 'success', data: response }),
                    error => ({ test: 'Friends API', status: 'error', data: error.responseText })
                )
            );
            
            // Test 2: Database debug
            tests.push(
                $.ajax({
                    url: 'debug_friends.php',
                    method: 'GET'
                }).then(
                    response => ({ test: 'Database Debug', status: 'success', data: 'Check debug_friends.php' }),
                    error => ({ test: 'Database Debug', status: 'error', data: 'File not found' })
                )
            );
            
            Promise.all(tests).then(results => {
                let html = '<strong>System Status:</strong><br>';
                results.forEach(result => {
                    const icon = result.status === 'success' ? '✓' : '❌';
                    const className = result.status === 'success' ? 'success' : 'error';
                    html += `<span class="${className}">${icon} ${result.test}: ${result.status}</span><br>`;
                });
                $('#statusResult').html(html);
            });
        }
        
        // Auto-load friends on page load
        $(document).ready(function() {
            getFriends();
        });
    </script>
</body>
</html>
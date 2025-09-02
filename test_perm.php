<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Permanent Rooms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background: #1a1a1a; 
            color: #fff; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        
        .debug-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #2a2a2a;
            border-radius: 8px;
        }
        
        .test-result {
            margin: 10px 0;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #007bff;
            background: #333;
        }
        
        .test-result.success {
            border-left-color: #28a745;
            background: #1e3a26;
        }
        
        .test-result.error {
            border-left-color: #dc3545;
            background: #3a1e1e;
        }
        
        .test-result.warning {
            border-left-color: #ffc107;
            background: #3a321e;
        }
        
        .room-preview {
            border: 2px solid #444;
            border-radius: 12px;
            margin: 10px 0;
            background: #333;
            overflow: hidden;
        }
        
        .room-preview.permanent {
            border-color: #ffd700;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.3);
            position: relative;
        }
        
        .room-preview.permanent::before {
            content: "";
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #ffd700, #ffa500);
            border-radius: 14px;
            z-index: -1;
            opacity: 0.4;
        }
        
        .room-header {
            padding: 15px;
            background: #404040;
        }
        
        .room-header.permanent {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.15), rgba(255, 165, 0, 0.15));
            border-bottom: 1px solid rgba(255, 215, 0, 0.3);
        }
        
        .permanent-indicator {
            background: linear-gradient(135deg, #ffd700, #ffa500);
            color: #000;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            display: inline-block;
            margin-left: 10px;
        }
        
        .permanent-star {
            color: #ffd700;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
            animation: permanentGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes permanentGlow {
            from { text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
            to { text-shadow: 0 0 20px rgba(255, 215, 0, 0.8); }
        }
        
        pre {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #444;
            overflow-x: auto;
            font-size: 0.9em;
        }
        
        .btn-test {
            margin: 5px;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1><i class="fas fa-bug"></i> Permanent Rooms Debug Tool</h1>
        <p class="text-muted">This tool helps debug and test the permanent room functionality.</p>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Quick Tests</h3>
                <button class="btn btn-primary btn-test" onclick="testAPI()">Test API Response</button>
                <button class="btn btn-secondary btn-test" onclick="testDatabase()">Check Database Schema</button>
                <button class="btn btn-warning btn-test" onclick="createTestRoom()">Create Test Permanent Room</button>
                <button class="btn btn-info btn-test" onclick="testStyling()">Test CSS Styling</button>
            </div>
            <div class="col-md-6">
                <h3>Manual Tests</h3>
                <p class="small text-muted">Check these manually:</p>
                <ul class="small text-muted">
                    <li>Permanent rooms should appear at the top of the list</li>
                    <li>Should have a golden star icon in the title</li>
                    <li>Should have "PERMANENT" badge in the features</li>
                    <li>Should have orange glow around the card</li>
                    <li>Should show "Permanent Room" in the meta info</li>
                </ul>
            </div>
        </div>
        
        <h3>Test Results</h3>
        <div id="testResults">
            <p class="text-muted">Click a test button above to see results here...</p>
        </div>
        
        <h3>Room Previews</h3>
        <div id="roomPreviews">
            <p class="text-muted">API test results will show room previews here...</p>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function addResult(type, title, message, data = null) {
            const html = `
                <div class="test-result ${type}">
                    <h5><i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'exclamation-triangle'}"></i> ${title}</h5>
                    <p>${message}</p>
                    ${data ? `<pre>${JSON.stringify(data, null, 2)}</pre>` : ''}
                </div>
            `;
            $('#testResults').append(html);
        }
        
        function clearResults() {
            $('#testResults').empty();
            $('#roomPreviews').empty();
        }
        
        function testAPI() {
            clearResults();
            addResult('info', 'Testing API', 'Calling api/get_rooms.php...');
            
            $.ajax({
                url: 'api/get_rooms.php',
                method: 'GET',
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    if (Array.isArray(response)) {
                        addResult('success', 'API Response Received', `Got ${response.length} rooms from API`);
                        
                        const permanentRooms = response.filter(r => r.permanent === true || r.permanent === 1);
                        if (permanentRooms.length > 0) {
                            addResult('success', 'Permanent Rooms Found', `Found ${permanentRooms.length} permanent rooms`, permanentRooms);
                        } else {
                            addResult('warning', 'No Permanent Rooms', 'No permanent rooms found in API response. This could mean no rooms are marked as permanent in the database.');
                        }
                        
                        // Show room previews
                        showRoomPreviews(response);
                        
                        // Show full API response for debugging
                        addResult('info', 'Full API Response', 'Complete raw response from the API', response);
                        
                    } else if (response.status === 'error') {
                        addResult('error', 'API Error', response.message, response);
                    } else {
                        addResult('error', 'Unexpected Response', 'API returned unexpected format', response);
                    }
                },
                error: function(xhr, status, error) {
                    addResult('error', 'AJAX Error', `Failed to call API: ${error}`, {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                }
            });
        }
        
        function testDatabase() {
            clearResults();
            addResult('info', 'Testing Database Schema', 'Calling debug API...');
            
            $.ajax({
                url: 'api/debug_permanent_column.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const info = response.debug_info;
                        
                        if (info.chatrooms_table_exists) {
                            addResult('success', 'Table Exists', 'Chatrooms table found in database');
                            
                            if (info.permanent_column_exists) {
                                addResult('success', 'Permanent Column Exists', 'Permanent column found in chatrooms table', info.permanent_column);
                                
                                if (info.permanent_rooms_count !== undefined) {
                                    if (info.permanent_rooms_count > 0) {
                                        addResult('success', 'Permanent Rooms in DB', `Found ${info.permanent_rooms_count} permanent rooms in database`);
                                    } else {
                                        addResult('warning', 'No Permanent Rooms in DB', 'Permanent column exists but no rooms are marked as permanent');
                                    }
                                }
                            } else {
                                if (info.permanent_column_added) {
                                    addResult('success', 'Column Added', 'Permanent column was missing but has been added automatically');
                                } else {
                                    addResult('error', 'Missing Column', 'Permanent column does not exist and could not be added', info.permanent_column_add_error);
                                }
                            }
                        } else {
                            addResult('error', 'Table Missing', 'Chatrooms table does not exist');
                        }
                        
                        addResult('info', 'Full Database Info', 'Complete database schema information', info);
                    } else {
                        addResult('error', 'Database Check Failed', response.message, response);
                    }
                },
                error: function(xhr, status, error) {
                    addResult('error', 'Database Check Error', `Could not check database schema: ${error}. Make sure api/debug_permanent_column.php exists.`);
                }
            });
        }
        
        function createTestRoom() {
            clearResults();
            addResult('info', 'Creating Test Room', 'This would create a permanent room for testing...');
            addResult('warning', 'Manual Step Required', 'You need to either:<br>1. Create a room via the UI and mark it as permanent (if you have admin access)<br>2. Run the provided SQL script to create a test permanent room<br>3. Manually update an existing room in the database: UPDATE chatrooms SET permanent = 1 WHERE id = [room_id]');
        }
        
        function testStyling() {
            clearResults();
            addResult('info', 'Testing CSS Styling', 'Creating preview of permanent room styling...');
            
            const testRoomHtml = `
                <div class="room-preview permanent">
                    <div class="room-header permanent">
                        <h5><i class="fas fa-star permanent-star"></i> Test Permanent Room <span class="permanent-indicator"><i class="fas fa-star"></i> PERMANENT</span></h5>
                        <p class="mb-0">This is how a permanent room should look with proper styling applied.</p>
                    </div>
                    <div style="padding: 15px;">
                        <p><strong>Expected features:</strong></p>
                        <ul>
                            <li>Golden star icon with glow animation</li>
                            <li>Orange/gold border glow around the card</li>
                            <li>Golden "PERMANENT" badge</li>
                            <li>Slightly different header background</li>
                        </ul>
                    </div>
                </div>
            `;
            
            $('#roomPreviews').html('<h4>Styling Test Preview</h4>' + testRoomHtml);
            addResult('success', 'Styling Test', 'CSS styling preview created above. If you can see the golden effects, the CSS is working.');
        }
        
        function showRoomPreviews(rooms) {
            let html = '<h4>Room Previews from API</h4>';
            
            if (rooms.length === 0) {
                html += '<p class="text-muted">No rooms to preview.</p>';
            } else {
                rooms.slice(0, 5).forEach(room => {
                    const isPermanent = room.permanent === true || room.permanent === 1;
                    html += `
                        <div class="room-preview ${isPermanent ? 'permanent' : ''}">
                            <div class="room-header ${isPermanent ? 'permanent' : ''}">
                                <h6>
                                    ${isPermanent ? '<i class="fas fa-star permanent-star"></i>' : ''}
                                    ${room.name}
                                    ${isPermanent ? '<span class="permanent-indicator"><i class="fas fa-star"></i> PERMANENT</span>' : ''}
                                </h6>
                                <small class="text-muted">
                                    ID: ${room.id} | 
                                    Permanent: ${isPermanent ? 'YES' : 'NO'} | 
                                    Raw value: ${JSON.stringify(room.permanent)}
                                </small>
                            </div>
                        </div>
                    `;
                });
            }
            
            $('#roomPreviews').html(html);
        }
        
        // Auto-run API test on page load
        $(document).ready(function() {
            setTimeout(testAPI, 1000);
        });
    </script>
</body>
</html>
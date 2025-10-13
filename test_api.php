<?php
// test_api_responses.php - Test that API endpoints return proper JSON
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Response Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .test { margin: 10px 0; padding: 10px; border: 1px solid #333; }
        .pass { border-color: #0f0; }
        .fail { border-color: #f00; color: #f00; }
        .pending { border-color: #ff0; color: #ff0; }
    </style>
</head>
<body>
    <h1>🔧 API Response Test</h1>
    <p>Testing API endpoints for proper JSON responses...</p>
    
    <div id="results"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const tests = [
            { name: 'join_lounge.php', endpoint: '/api/join_lounge.php', method: 'POST', data: { guest_name: 'Test' } },
            { name: 'join_room.php', endpoint: '/api/join_room.php', method: 'POST', data: { room_id: 1 } },
            { name: 'check_room_status.php', endpoint: '/api/check_room_status.php', method: 'GET' }
        ];
        
        function runTest(test) {
            const startTime = Date.now();
            const testDiv = $('<div class="test pending"></div>').appendTo('#results');
            testDiv.html(`Testing ${test.name}... <span class="timer">0s</span>`);
            
            const timer = setInterval(() => {
                const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
                testDiv.find('.timer').text(elapsed + 's');
            }, 100);
            
            $.ajax({
                url: test.endpoint,
                method: test.method,
                data: test.data,
                timeout: 5000,
                dataType: 'json'
            })
            .done(function(response) {
                clearInterval(timer);
                const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
                testDiv.removeClass('pending').addClass('pass');
                testDiv.html(`✅ ${test.name}: PASS (${elapsed}s)<br>Response: ${JSON.stringify(response).substring(0, 100)}`);
            })
            .fail(function(xhr, status, error) {
                clearInterval(timer);
                const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
                const responseText = xhr.responseText ? xhr.responseText.substring(0, 200) : 'No response';
                
                if (status === 'timeout') {
                    testDiv.removeClass('pending').addClass('fail');
                    testDiv.html(`❌ ${test.name}: TIMEOUT (${elapsed}s) - This was the hanging issue!`);
                } else {
                    // May be a normal error (like unauthorized), check if it's JSON
                    try {
                        JSON.parse(xhr.responseText);
                        testDiv.removeClass('pending').addClass('pass');
                        testDiv.html(`✅ ${test.name}: Returns JSON error (${elapsed}s)<br>${responseText}`);
                    } catch(e) {
                        testDiv.removeClass('pending').addClass('fail');
                        testDiv.html(`❌ ${test.name}: Returns non-JSON (${elapsed}s)<br>${responseText}`);
                    }
                }
            });
        }
        
        $(document).ready(function() {
            tests.forEach((test, index) => {
                setTimeout(() => runTest(test), index * 1000);
            });
        });
    </script>
</body>
</html>
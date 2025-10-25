<!DOCTYPE html>
<html>
<head>
    <title>API Test</title>
    <style>
        body { background: #000; color: #0f0; font-family: monospace; padding: 20px; }
        button { background: #0f0; color: #000; padding: 10px 20px; font-weight: bold; border: none; cursor: pointer; }
        pre { background: #111; border: 1px solid #0f0; padding: 15px; white-space: pre-wrap; word-wrap: break-word; }
        .error { color: #f00; }
    </style>
</head>
<body>
    <h1>🔍 Direct API Test</h1>
    
    <p>Room ID: <input type="number" id="rid" value="1" style="padding: 5px;"> 
    <button onclick="test()">TEST NOW</button></p>
    
    <h2>Raw Response:</h2>
    <pre id="raw"></pre>
    
    <h2>Is it an array?</h2>
    <pre id="check"></pre>

    <script>
    function test() {
        const roomId = document.getElementById('rid').value;
        const url = 'api/get_room_users.php?room_id=' + roomId;
        
        document.getElementById('raw').textContent = 'Loading...';
        document.getElementById('check').textContent = 'Checking...';
        
        fetch(url)
            .then(r => r.text())
            .then(text => {
                // Show raw
                document.getElementById('raw').textContent = text;
                
                // Check if array
                try {
                    const json = JSON.parse(text);
                    const isArray = Array.isArray(json);
                    const length = isArray ? json.length : 'N/A';
                    
                    document.getElementById('check').innerHTML = 
                        'Array.isArray: <strong>' + isArray + '</strong><br>' +
                        'Length: <strong>' + length + '</strong><br>' +
                        'Type: <strong>' + typeof json + '</strong><br><br>' +
                        (isArray ? 
                            '<span style="color:#0f0;">✓ This will work in room.js</span>' : 
                            '<span class="error">✗ This will NOT work - room.js expects an array!</span>');
                } catch(e) {
                    document.getElementById('check').innerHTML = 
                        '<span class="error">ERROR: Not valid JSON!<br>' + e.message + '</span>';
                }
            })
            .catch(err => {
                document.getElementById('raw').innerHTML = '<span class="error">' + err + '</span>';
            });
    }
    
    // Auto-test on load
    window.onload = () => test();
    </script>
</body>
</html>
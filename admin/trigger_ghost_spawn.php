<?php
// admin/trigger_ghost_spawn.php - Manual ghost spawn trigger
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die("Admin only");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ghost Hunt Control Panel</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { max-width: 800px; margin: 0 auto; }
        .btn { padding: 10px 20px; margin: 10px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn-primary { background: #ff6b00; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #28a745; }
        .error { background: #dc3545; }
        .info { background: #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #444; }
        th { background: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👻 Ghost Hunt Control Panel</h1>
        
        <div style="margin: 20px 0;">
            <button class="btn btn-primary" onclick="spawnGhost()">Spawn Ghost Now</button>
            <button class="btn btn-danger" onclick="clearAllGhosts()">Clear All Active Ghosts</button>
        </div>
        
        <div id="status"></div>
        
        <h2>Active Ghost Hunts</h2>
        <div id="activeGhosts">Loading...</div>
        
        <h2>Recent Claims</h2>
        <div id="recentClaims">Loading...</div>
    </div>
    
    <script>
    function showStatus(message, type) {
        const statusDiv = document.getElementById('status');
        statusDiv.innerHTML = `<div class="status ${type}">${message}</div>`;
        setTimeout(() => statusDiv.innerHTML = '', 5000);
    }
    
    function spawnGhost() {
        fetch('../api/spawn_ghost.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    showStatus(`✅ Spawned ${data.spawned} ghost hunt(s)!`, 'success');
                    loadActiveGhosts();
                } else {
                    showStatus(`❌ Error: ${data.message}`, 'error');
                }
            });
    }
    
    function clearAllGhosts() {
        if (!confirm('Clear all active ghost hunts?')) return;
        
        fetch('../api/clear_ghosts.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    showStatus(`✅ Cleared ${data.cleared} ghost hunt(s)`, 'success');
                    loadActiveGhosts();
                } else {
                    showStatus(`❌ Error: ${data.message}`, 'error');
                }
            });
    }
    
    function loadActiveGhosts() {
        fetch('../api/get_ghost_status.php')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    let html = '<table><tr><th>Room</th><th>Phrase</th><th>Reward</th><th>Spawned</th></tr>';
                    
                    if (data.active.length === 0) {
                        html += '<tr><td colspan="4">No active ghost hunts</td></tr>';
                    } else {
                        data.active.forEach(g => {
                            html += `<tr>
                                <td>${g.room_name}</td>
                                <td><strong>${g.ghost_phrase}</strong></td>
                                <td>${g.reward_amount} 🎃</td>
                                <td>${g.spawned_at}</td>
                            </tr>`;
                        });
                    }
                    
                    html += '</table>';
                    document.getElementById('activeGhosts').innerHTML = html;
                }
            });
    }
    
    function loadRecentClaims() {
        fetch('../api/get_ghost_status.php')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    let html = '<table><tr><th>Room</th><th>Winner</th><th>Phrase</th><th>Reward</th><th>Time</th></tr>';
                    
                    if (data.recent.length === 0) {
                        html += '<tr><td colspan="5">No recent claims</td></tr>';
                    } else {
                        data.recent.forEach(g => {
                            html += `<tr>
                                <td>${g.room_name}</td>
                                <td>${g.winner}</td>
                                <td>${g.ghost_phrase}</td>
                                <td>${g.reward_amount} 🎃</td>
                                <td>${g.claimed_at}</td>
                            </tr>`;
                        });
                    }
                    
                    html += '</table>';
                    document.getElementById('recentClaims').innerHTML = html;
                }
            });
    }
    
    // Load data on page load
    loadActiveGhosts();
    loadRecentClaims();
    
    // Auto-refresh every 10 seconds
    setInterval(() => {
        loadActiveGhosts();
        loadRecentClaims();
    }, 10000);
    </script>
</body>
</html>
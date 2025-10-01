<?php
/**
 * Quick test for auto_sanitize.php
 * Visit: http://yoursite.com/test_auto_sanitize.php
 */

require_once 'auto_sanitize.php';

// Simulate malicious input
$_POST['test_name'] = '<script>alert("XSS")</script>';
$_POST['test_message'] = '<img src=x onerror=alert(1)>';
$_POST['test_html'] = 'Hello<br>World<b>Bold</b>';
$_POST['test_utf8'] = '日本語 中文 العربية 🎉';
$_POST['test_special'] = 'User!@#$%^&*()_+-=[]{}|;:\'",.<>?/~`';
$_POST['password'] = 'mypassword123!@#'; // Should NOT be sanitized

// Apply sanitization
foreach ($_POST as $key => $value) {
    if (stripos($key, 'password') === false) {
        $_POST[$key] = sanitize_value($value);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Auto-Sanitize Test</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 800px; margin: 0 auto; }
        .result { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .pass { background: #d4edda; border: 2px solid #28a745; }
        .fail { background: #f8d7da; border: 2px solid #dc3545; }
        code { background: #f4f4f4; padding: 3px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔒 Auto-Sanitize Test Results</h1>
    
    <div class="result <?php echo (strpos($_POST['test_name'], '<script>') === false) ? 'pass' : 'fail'; ?>">
        <h3>Test 1: Script Tag Removal</h3>
        <p><strong>Original:</strong> <code>&lt;script&gt;alert("XSS")&lt;/script&gt;</code></p>
        <p><strong>Result:</strong> <code><?php echo htmlspecialchars($_POST['test_name']); ?></code></p>
        <p><?php echo (strpos($_POST['test_name'], '<script>') === false) ? '✅ PASS - Script removed!' : '❌ FAIL - Script still present!'; ?></p>
    </div>
    
    <div class="result <?php echo (strpos($_POST['test_message'], '<img') === false) ? 'pass' : 'fail'; ?>">
        <h3>Test 2: IMG onerror Removal</h3>
        <p><strong>Original:</strong> <code>&lt;img src=x onerror=alert(1)&gt;</code></p>
        <p><strong>Result:</strong> <code><?php echo htmlspecialchars($_POST['test_message']); ?></code></p>
        <p><?php echo (strpos($_POST['test_message'], '<img') === false) ? '✅ PASS - IMG removed!' : '❌ FAIL - IMG still present!'; ?></p>
    </div>
    
    <div class="result <?php echo ($_POST['test_html'] === 'HelloWorldBold') ? 'pass' : 'fail'; ?>">
        <h3>Test 3: HTML Tags Stripped (br, b, etc.)</h3>
        <p><strong>Original:</strong> <code>Hello&lt;br&gt;World&lt;b&gt;Bold&lt;/b&gt;</code></p>
        <p><strong>Result:</strong> <code><?php echo htmlspecialchars($_POST['test_html']); ?></code></p>
        <p><?php echo ($_POST['test_html'] === 'HelloWorldBold') ? '✅ PASS - All HTML tags removed!' : '❌ FAIL - HTML tags still present!'; ?></p>
    </div>
    
    <div class="result <?php echo ($_POST['test_utf8'] === '日本語 中文 العربية 🎉') ? 'pass' : 'fail'; ?>">
        <h3>Test 4: UTF-8 & Emoji Preservation</h3>
        <p><strong>Original:</strong> <code>日本語 中文 العربية 🎉</code></p>
        <p><strong>Result:</strong> <code><?php echo htmlspecialchars($_POST['test_utf8']); ?></code></p>
        <p><?php echo ($_POST['test_utf8'] === '日本語 中文 العربية 🎉') ? '✅ PASS - UTF-8 preserved!' : '❌ FAIL - UTF-8 modified!'; ?></p>
    </div>
    
    <div class="result <?php echo (strpos($_POST['test_special'], '!@#$%^&*()') !== false) ? 'pass' : 'fail'; ?>">
        <h3>Test 5: Special Characters Preserved</h3>
        <p><strong>Original:</strong> <code>User!@#$%^&amp;*()_+-=[]{}|;:'",.<>?/~`</code></p>
        <p><strong>Result:</strong> <code><?php echo htmlspecialchars($_POST['test_special']); ?></code></p>
        <p><?php echo (strpos($_POST['test_special'], '!@#$%^&*()') !== false) ? '✅ PASS - Special chars preserved!' : '❌ FAIL - Special chars removed!'; ?></p>
    </div>
    
    <div class="result <?php echo ($_POST['password'] === 'mypassword123!@#') ? 'pass' : 'fail'; ?>">
        <h3>Test 6: Password NOT Sanitized</h3>
        <p><strong>Original:</strong> <code>mypassword123!@#</code></p>
        <p><strong>Result:</strong> <code><?php echo htmlspecialchars($_POST['password']); ?></code></p>
        <p><?php echo ($_POST['password'] === 'mypassword123!@#') ? '✅ PASS - Password untouched!' : '❌ FAIL - Password was sanitized!'; ?></p>
    </div>
    
    <?php
    $all_pass = (
        strpos($_POST['test_name'], '<script>') === false &&
        strpos($_POST['test_message'], '<img') === false &&
        $_POST['test_html'] === 'HelloWorldBold' &&
        $_POST['test_utf8'] === '日本語 中文 العربية 🎉' &&
        strpos($_POST['test_special'], '!@#$%^&*()') !== false &&
        $_POST['password'] === 'mypassword123!@#'
    );
    ?>
    
    <div class="result <?php echo $all_pass ? 'pass' : 'fail'; ?>">
        <h2><?php echo $all_pass ? '✅ ALL TESTS PASSED!' : '❌ SOME TESTS FAILED'; ?></h2>
        <?php if ($all_pass): ?>
            <p>The auto-sanitizer is working correctly!</p>
            <p><strong>Next step:</strong> Add it to db_connect.php</p>
        <?php else: ?>
            <p>Check that auto_sanitize.php is properly implemented.</p>
        <?php endif; ?>
    </div>
    
    <hr>
    <p><small>Delete this test file after verification.</small></p>
</body>
</html>
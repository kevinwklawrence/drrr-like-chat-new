<?php
/**
 * SANITIZER TEST PAGE
 * Tests if your sanitizer is working correctly
 * Place in project root and visit: http://yoursite.com/test_sanitizer.php
 */

$tests = [];
$sanitizer_exists = file_exists('input_sanitizer.php');

if ($sanitizer_exists) {
    require_once 'input_sanitizer.php';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sanitizer Test</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test { padding: 15px; margin: 10px 0; border-radius: 5px; border: 2px solid #ddd; }
        .pass { background: #d4edda; border-color: #28a745; }
        .fail { background: #f8d7da; border-color: #dc3545; }
        .warning { background: #fff3cd; border-color: #ffc107; }
        .badge { padding: 5px 10px; border-radius: 3px; font-weight: bold; margin-right: 10px; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
        h3 { margin: 0 0 10px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔒 Input Sanitizer Test</h1>
    
    <?php if (!$sanitizer_exists): ?>
        <div class="test fail">
            <h3><span class="badge badge-danger">FAIL</span> Sanitizer Not Found</h3>
            <p>The file <code>input_sanitizer.php</code> does not exist in your project root.</p>
            <p><strong>Action:</strong> Create the file first before testing.</p>
        </div>
    <?php else: ?>
        <div class="test pass">
            <h3><span class="badge badge-success">PASS</span> Sanitizer Found</h3>
            <p>File <code>input_sanitizer.php</code> exists and is loaded.</p>
        </div>
        
        <?php
        // Test 1: XSS Script Tags
        $input1 = '<script>alert("XSS")</script>';
        $output1 = InputSanitizer::sanitizeText($input1, 100);
        $pass1 = (strpos($output1, '<script>') === false && strpos($output1, '</script>') === false);
        ?>
        <div class="test <?php echo $pass1 ? 'pass' : 'fail'; ?>">
            <h3>
                <span class="badge badge-<?php echo $pass1 ? 'success' : 'danger'; ?>">
                    <?php echo $pass1 ? 'PASS' : 'FAIL'; ?>
                </span>
                Test 1: Script Tag Removal
            </h3>
            <p><strong>Input:</strong> <code><?php echo htmlspecialchars($input1); ?></code></p>
            <p><strong>Output:</strong> <code><?php echo htmlspecialchars($output1); ?></code></p>
            <?php if ($pass1): ?>
                <p>✅ Script tags successfully removed/escaped!</p>
            <?php else: ?>
                <p>❌ Script tags still present - XSS possible!</p>
            <?php endif; ?>
        </div>
        
        <?php
        // Test 2: IMG onerror XSS
        $input2 = '<img src=x onerror=alert(1)>';
        $output2 = InputSanitizer::sanitizeText($input2, 100);
        $pass2 = (strpos($output2, '<img') === false && strpos($output2, 'onerror') === false);
        ?>
        <div class="test <?php echo $pass2 ? 'pass' : 'fail'; ?>">
            <h3>
                <span class="badge badge-<?php echo $pass2 ? 'success' : 'danger'; ?>">
                    <?php echo $pass2 ? 'PASS' : 'FAIL'; ?>
                </span>
                Test 2: IMG onerror XSS
            </h3>
            <p><strong>Input:</strong> <code><?php echo htmlspecialchars($input2); ?></code></p>
            <p><strong>Output:</strong> <code><?php echo htmlspecialchars($output2); ?></code></p>
            <?php if ($pass2): ?>
                <p>✅ IMG tag successfully removed/escaped!</p>
            <?php else: ?>
                <p>❌ IMG tag still present - XSS possible!</p>
            <?php endif; ?>
        </div>
        
        <?php
        // Test 3: UTF-8 Characters (should preserve)
        $input3 = '日本語のテスト 中文测试 العربية 🎉';
        $output3 = InputSanitizer::sanitizeText($input3, 100);
        $pass3 = ($output3 === $input3); // Should be identical
        ?>
        <div class="test <?php echo $pass3 ? 'pass' : 'fail'; ?>">
            <h3>
                <span class="badge badge-<?php echo $pass3 ? 'success' : 'danger'; ?>">
                    <?php echo $pass3 ? 'PASS' : 'FAIL'; ?>
                </span>
                Test 3: UTF-8 Character Preservation
            </h3>
            <p><strong>Input:</strong> <code><?php echo htmlspecialchars($input3); ?></code></p>
            <p><strong>Output:</strong> <code><?php echo htmlspecialchars($output3); ?></code></p>
            <?php if ($pass3): ?>
                <p>✅ Foreign characters preserved correctly!</p>
            <?php else: ?>
                <p>❌ Foreign characters were modified!</p>
            <?php endif; ?>
        </div>
        
        <?php
        // Test 4: Username Sanitization
        $input4 = 'user<script>hack</script>123';
        $output4 = InputSanitizer::sanitizeUsername($input4, 20);
        $pass4 = (preg_match('/^[a-zA-Z0-9_-]+$/', $output4) === 1);
        ?>
        <div class="test <?php echo $pass4 ? 'pass' : 'fail'; ?>">
            <h3>
                <span class="badge badge-<?php echo $pass4 ? 'success' : 'danger'; ?>">
                    <?php echo $pass4 ? 'PASS' : 'FAIL'; ?>
                </span>
                Test 4: Username Sanitization
            </h3>
            <p><strong>Input:</strong> <code><?php echo htmlspecialchars($input4); ?></code></p>
            <p><strong>Output:</strong> <code><?php echo htmlspecialchars($output4); ?></code></p>
            <?php if ($pass4): ?>
                <p>✅ Username contains only safe characters!</p>
            <?php else: ?>
                <p>❌ Username contains dangerous characters!</p>
            <?php endif; ?>
        </div>
        
        <?php
        // Test 5: Length Limiting
        $input5 = str_repeat('A', 5000);
        $output5 = InputSanitizer::sanitizeText($input5, 100);
        $pass5 = (mb_strlen($output5) <= 100);
        ?>
        <div class="test <?php echo $pass5 ? 'pass' : 'fail'; ?>">
            <h3>
                <span class="badge badge-<?php echo $pass5 ? 'success' : 'danger'; ?>">
                    <?php echo $pass5 ? 'PASS' : 'FAIL'; ?>
                </span>
                Test 5: Length Limiting
            </h3>
            <p><strong>Input Length:</strong> <?php echo strlen($input5); ?> characters</p>
            <p><strong>Output Length:</strong> <?php echo mb_strlen($output5); ?> characters</p>
            <?php if ($pass5): ?>
                <p>✅ Length properly limited to max!</p>
            <?php else: ?>
                <p>❌ Length not properly limited!</p>
            <?php endif; ?>
        </div>
        
        <?php
        // Overall Result
        $total_tests = 5;
        $passed_tests = ($pass1 ? 1 : 0) + ($pass2 ? 1 : 0) + ($pass3 ? 1 : 0) + ($pass4 ? 1 : 0) + ($pass5 ? 1 : 0);
        $all_passed = ($passed_tests === $total_tests);
        ?>
        
        <div class="test <?php echo $all_passed ? 'pass' : 'fail'; ?>">
            <h2>📊 Overall Result: <?php echo $passed_tests; ?>/<?php echo $total_tests; ?> Tests Passed</h2>
            <?php if ($all_passed): ?>
                <p><strong>✅ Sanitizer is working correctly!</strong></p>
                <p>Next steps:</p>
                <ol>
                    <li>Update your API files to use the sanitizer</li>
                    <li>Run the database cleanup script</li>
                    <li>Test on your actual website</li>
                    <li>Delete this test file</li>
                </ol>
            <?php else: ?>
                <p><strong>❌ Sanitizer has issues!</strong></p>
                <p>Check that input_sanitizer.php is correctly implemented.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <hr>
    <p><small>After verifying the sanitizer works, <strong>delete this test file</strong> for security.</small></p>
</div>
</body>
</html>
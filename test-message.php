<?php
// Test if send_message.php has syntax errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing send_message.php syntax...\n";

$file = 'api/send_message.php';
if (!file_exists($file)) {
    die("Error: $file not found\n");
}

// Check for syntax errors
$output = shell_exec("php -l $file 2>&1");
echo $output . "\n";

// Check for BOM or whitespace before <?php
$content = file_get_contents($file);
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    echo "⚠️ WARNING: File has UTF-8 BOM at start\n";
}

if ($content[0] !== '<') {
    echo "⚠️ WARNING: File has whitespace before <?php\n";
}

// Check what's being included
if (preg_match_all('/(?:include|require)(?:_once)?\s+[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
    echo "\nIncludes found:\n";
    foreach ($matches[1] as $include) {
        echo "  - $include\n";
    }
}

echo "\n✅ Test complete\n";
?>
<?php
// Image proxy to bypass hotlinking protection
session_start();
include '../db_connect.php';

if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    exit('Missing URL parameter');
}

$image_url = $_GET['url'];

// Validate URL - allow query parameters after image extension
if (!filter_var($image_url, FILTER_VALIDATE_URL) || 
    !preg_match('/^https?:\/\//', $image_url) ||
    !preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $image_url)) {
    http_response_code(400);
    exit('Invalid image URL');
}

// Security: Block local/internal URLs
if (preg_match('/localhost|127\.0\.0\.1|192\.168\.|10\.|172\./', $image_url)) {
    http_response_code(403);
    exit('Access denied');
}

try {
    // Create curl request with proper headers to bypass hotlinking protection
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $image_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_REFERER => parse_url($image_url, PHP_URL_SCHEME) . '://' . parse_url($image_url, PHP_URL_HOST),
        CURLOPT_HTTPHEADER => [
            'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$image_data) {
        http_response_code(404);
        exit('Image not found');
    }
    
    // Validate content type
    if (!str_starts_with($content_type, 'image/')) {
        http_response_code(400);
        exit('Not an image');
    }
    
    // Set headers and output image
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . strlen($image_data));
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
    header('Access-Control-Allow-Origin: *');
    
    echo $image_data;
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Proxy error');
}
?>
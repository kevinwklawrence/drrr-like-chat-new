<?php
header('Content-Type: application/json');

try {
    $avatars = [];
    $image_dir = '../images/';
    
    // Scan for avatar files
    if (is_dir($image_dir)) {
        $files = scandir($image_dir);
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($image_dir . $file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                // Only include image files that look like avatars
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && 
                    (strpos($file, 'avatar') !== false || 
                     strpos($file, 'user') !== false || 
                     preg_match('/^[mfun]\d+\.(jpg|jpeg|png|gif)$/i', $file) ||
                     $file === 'default_avatar.jpg')) {
                    $avatars[] = $file;
                }
            }
        }
    }
    
    // Add some default avatars if directory scan fails
    if (empty($avatars)) {
        $avatars = [
            'default_avatar.jpg',
            'm1.png', 'm2.png', 'm3.png', 'm4.png',
            'f1.png', 'f2.png', 'f3.png', 'f4.png',
            'u0.png', 'u1.png', 'u2.png', 'u3.png'
        ];
    }
    
    sort($avatars);
    echo json_encode($avatars);
    
} catch (Exception $e) {
    error_log("Get avatars error: " . $e->getMessage());
    echo json_encode(['default_avatar.jpg']);
}
?>
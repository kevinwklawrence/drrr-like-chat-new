<?php
// api/get_organized_avatars.php - Serve avatars organized by folders with user permissions
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_type = $_GET['user_type'] ?? 'guest';
$is_registered = ($user_type === 'registered');

try {
    $organized_avatars = [];
    $image_base_dir = __DIR__ . '/../images';
    $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    
    // Define which folders each user type can access
    $excluded_folders = ['staff', 'bg', 'icon', 'covers']; // Never show these
    
    if ($is_registered) {
        // Registered users can access all folders except excluded ones
        $accessible_folders = [];
        foreach (glob($image_base_dir . '/*', GLOB_ONLYDIR) as $folder_path) {
            $folder_name = basename($folder_path);
            if (!in_array(strtolower($folder_name), $excluded_folders)) {
                $accessible_folders[] = $folder_name;
            }
        }
    } else {
        // Guest users can only access default and time-limited
        $accessible_folders = ['time-limited', 'default', 'drrrjp', 'drrrx2'];
    }
    
    // Scan each accessible folder for avatars
    foreach ($accessible_folders as $folder) {
        $folder_path = $image_base_dir . '/' . $folder;
        
        if (is_dir($folder_path)) {
            $avatars_in_folder = [];
            
            // Get all image files in this folder
            foreach (glob($folder_path . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) as $img_path) {
                $img_file = basename($img_path);
                $ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed_ext)) {
                    // Store relative path from images directory
                    $avatars_in_folder[] = $folder . '/' . $img_file;
                }
            }
            
            // Sort avatars within folder
            sort($avatars_in_folder);
            
            // Only include folder if it has avatars
            if (!empty($avatars_in_folder)) {
                $organized_avatars[$folder] = $avatars_in_folder;
            }
        }
    }
    
    // Log the result for debugging
    error_log("Organized avatars for $user_type: " . json_encode(array_map(function($folder) {
        return count($organized_avatars[$folder] ?? []);
    }, array_keys($organized_avatars))));
    
    echo json_encode($organized_avatars);
    
} catch (Exception $e) {
    error_log("Get organized avatars error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load avatars']);
}
?>